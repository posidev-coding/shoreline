<?php

namespace App\Livewire;

use App\Enums\ContactMethod;
use App\Enums\Urgency;
use App\Jobs\AttachLeadPhotos;
use App\Mail\QuoteRequestReceived;
use App\Models\Lead;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\NewLead;
use App\Support\HurricaneReadiness;
use App\Support\QuoteQuestions;
use App\Support\VisitorIp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Propaganistas\LaravelPhone\PhoneNumber;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/**
 * The quote request: service, details, photos, contact, then a review before it's sent.
 *
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, ServiceArea> $towns
 * @property-read ?Service $selectedService
 *
 * Spam guards: a honeypot, Cloudflare Turnstile (when its keys are set), and a limit on
 * requests per visitor per hour. Photos go up as Livewire temporary uploads (straight to
 * the bucket on Cloud), and a queued job attaches them once the request is in.
 */
class QuoteWizard extends Component
{
    use UsesSpamProtection, WithFileUploads;

    public const int SERVICE = 1;

    public const int DETAILS = 2;

    public const int PHOTOS = 3;

    public const int CONTACT = 4;

    public const int REVIEW = 5;

    /** @var array<int, string> */
    public const array STEPS = [
        self::SERVICE => 'Service',
        self::DETAILS => 'Details',
        self::PHOTOS => 'Photos',
        self::CONTACT => 'Contact',
        self::REVIEW => 'Review',
    ];

    /** Requests one visitor can send in an hour. */
    public const int HOURLY_LIMIT = 5;

    /** @var list<string> */
    private const array ATTRIBUTION_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'landing_page', 'referrer'];

    public int $step = self::SERVICE;

    /** The furthest step reached, so the step list can jump back to any of them. */
    #[Locked]
    public int $furthestStep = self::SERVICE;

    /** A service slug, or "other". */
    public string $service = '';

    public string $doorSize = '';

    public string $style = '';

    /** @var array<array-key, string> Checkbox values; the browser decides the keys. */
    public array $symptoms = [];

    public string $urgency = '';

    public string $message = '';

    /** @var array<string, string> Answers from the hurricane-ready check, when they came from it. */
    #[Locked]
    public array $hurricane = [];

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $preferredContact = 'call';

    public string $street = '';

    /** A service area slug, or "other". */
    public string $town = '';

    public string $otherTown = '';

    public string $zip = '';

    public bool $smsConsent = false;

    public bool $marketingConsent = false;

    /** @var array<string, mixed> Campaign tags, landing page, and referrer, from the browser (resources/js/app.js). */
    public array $attribution = [];

    public string $turnstileToken = '';

    public HoneypotData $extraFields;

    /**
     * Turnstile runs only when its keys are set. Without them the honeypot and the hourly
     * limit still apply, and the dashboard's launch checklist says the check is off.
     */
    public static function usesTurnstile(): bool
    {
        return filled(config('services.turnstile.key')) && filled(config('services.turnstile.secret'));
    }

    /**
     * Named apart from the properties they fill: Livewire assigns a mount parameter straight
     * to a public property of the same name, which would skip the checks here.
     *
     * @param  array<array-key, mixed>  $quizAnswers  The hurricane-ready check's query string.
     */
    public function mount(?string $serviceSlug = null, array $quizAnswers = []): void
    {
        $this->extraFields = new HoneypotData;
        $this->hurricane = HurricaneReadiness::fromInput($quizAnswers)->answers;

        if ($serviceSlug !== null && $this->services->contains('slug', $serviceSlug)) {
            $this->service = $serviceSlug;
            $this->step = $this->furthestStep = self::DETAILS;
        }
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->published()->ordered()->get();
    }

    /**
     * @return Collection<int, ServiceArea>
     */
    #[Computed]
    public function towns(): Collection
    {
        return ServiceArea::query()->published()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        return $this->services->firstWhere('slug', $this->service);
    }

    /**
     * The details step's answers as the review step lists them: only what the chosen service asks.
     *
     * @return list<string>
     */
    public function detailLines(): array
    {
        return array_values(array_filter([
            ...QuoteQuestions::summaryLines($this->answers()),
            Urgency::tryFrom($this->urgency)?->getLabel(),
            trim($this->message),
        ]));
    }

    public function next(): void
    {
        $this->validate($this->rulesFor($this->step));

        $this->step = min($this->step + 1, self::REVIEW);
        $this->furthestStep = max($this->furthestStep, $this->step);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, self::SERVICE);
    }

    public function goTo(int $step): void
    {
        if ($step >= self::SERVICE && $step <= $this->furthestStep) {
            $this->step = $step;
        }
    }

    /**
     * Keeps the photos that pass and drops the rest, with a message, so one bad file doesn't block the others.
     */
    public function updatedPhotos(): void
    {
        $this->resetErrorBag('photos');
        $kept = [];
        $rejected = [];

        foreach ($this->photos as $photo) {
            if (count($kept) < Lead::MAX_PHOTOS && Validator::make(['photo' => $photo], ['photo' => $this->photoRule()])->passes()) {
                $kept[] = $photo;
            } else {
                $rejected[] = $photo->getClientOriginalName();
                $photo->delete();
            }
        }

        $this->photos = $kept;

        if ($rejected !== []) {
            $this->addError('photos', 'We couldn’t add '.implode(', ', $rejected).'. Send up to '.Lead::MAX_PHOTOS.' photos: JPEG, PNG, WebP, or iPhone HEIC, each up to '.(Lead::maxPhotoKilobytes() / 1024).' MB.');
        }
    }

    public function removePhoto(int $index): void
    {
        foreach (array_splice($this->photos, $index, 1) as $removed) {
            $removed->delete();
        }
    }

    public function submit(Request $request): void
    {
        $this->protectAgainstSpam();

        $visitor = 'quote-requests:'.self::clientIp($request);

        if (RateLimiter::tooManyAttempts($visitor, self::HOURLY_LIMIT)) {
            throw ValidationException::withMessages([
                'submit' => 'You’ve sent several requests in the last hour. Please call us instead.',
            ]);
        }

        $this->validateEveryStep();

        if (self::usesTurnstile()) {
            try {
                $this->validate(['turnstileToken' => ['required', new Turnstile]], [
                    'turnstileToken.required' => 'Please wait for the security check below to finish, then send again.',
                ]);
            } catch (ValidationException $exception) {
                $this->turnstileToken = '';
                $this->dispatch('turnstile-reset');

                throw $exception;
            }
        }

        $area = $this->towns->firstWhere('slug', $this->town);

        $lead = Lead::create([
            'service_id' => $this->selectedService?->getKey(),
            'urgency' => $this->urgency,
            'answers' => $this->answers(),
            'message' => Str::limit(trim($this->message), 2000, '') ?: null,
            'name' => trim($this->name),
            'email' => $this->email,
            'phone' => (new PhoneNumber($this->phone, 'US'))->formatE164(),
            'preferred_contact' => $this->preferredContact,
            'street' => trim($this->street),
            'town' => $area !== null ? $area->name : trim($this->otherTown),
            'zip' => $this->zip,
            'service_area_id' => $area?->getKey(),
            'attribution' => $this->cleanAttribution() ?: null,
            'sms_consent' => $this->smsConsent,
            'marketing_consent' => $this->marketingConsent,
            'ip_address' => self::clientIp($request),
        ]);

        RateLimiter::hit($visitor, 3600);

        if ($this->photos !== []) {
            AttachLeadPhotos::dispatch($lead, FileUploadConfiguration::disk(), array_map(fn (TemporaryUploadedFile $photo): array => [
                'path' => FileUploadConfiguration::directory().'/'.$photo->getFilename(),
                'name' => $photo->getClientOriginalName(),
            ], $this->photos));
        }

        Notification::send(User::leadRecipients(), new NewLead($lead));
        Mail::to($lead->email, $lead->name)->send(new QuoteRequestReceived($lead));

        $this->redirectRoute('quote.thanks');
    }

    public function render(): View
    {
        return view('livewire.quote-wizard');
    }

    public static function clientIp(Request $request): string
    {
        return VisitorIp::from($request);
    }

    /**
     * Validates every step before sending, and shows the first step with a problem.
     */
    private function validateEveryStep(): void
    {
        foreach (array_keys(self::STEPS) as $step) {
            $rules = $this->rulesFor($step);

            if ($rules === []) {
                continue;
            }

            try {
                $this->validate($rules);
            } catch (ValidationException $exception) {
                $this->step = $step;

                throw $exception;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(int $step): array
    {
        $service = $this->selectedService;

        return match ($step) {
            self::SERVICE => [
                'service' => ['required', Rule::in([...$this->services->pluck('slug')->all(), 'other'])],
            ],
            self::DETAILS => [
                'doorSize' => [Rule::requiredIf(QuoteQuestions::asksDoorSize($service)), 'nullable', Rule::in(array_keys(QuoteQuestions::DOOR_SIZES))],
                'style' => ['nullable', Rule::in(array_keys(QuoteQuestions::STYLES))],
                'symptoms' => ['array'],
                'symptoms.*' => [Rule::in(array_keys(QuoteQuestions::SYMPTOMS))],
                'urgency' => ['required', new Enum(Urgency::class)],
                'message' => [Rule::requiredIf(QuoteQuestions::requiresMessage($service)), 'nullable', 'string', 'max:2000'],
            ],
            self::PHOTOS => [
                'photos' => ['array', 'max:'.Lead::MAX_PHOTOS],
                'photos.*' => $this->photoRule(),
            ],
            self::CONTACT => [
                'name' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'phone:US'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'preferredContact' => ['required', new Enum(ContactMethod::class)],
                'smsConsent' => ['boolean', 'accepted_if:preferredContact,text'],
                'marketingConsent' => ['boolean'],
                'street' => ['required', 'string', 'max:200'],
                'town' => ['required', Rule::in([...$this->towns->pluck('slug')->all(), 'other'])],
                'otherTown' => ['required_if:town,other', 'nullable', 'string', 'max:100'],
                'zip' => ['required', 'regex:/^\d{5}$/'],
            ],
            default => [],
        };
    }

    private function photoRule(): File
    {
        return File::types(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'])->max(Lead::maxPhotoKilobytes());
    }

    /**
     * Only the answers the chosen service asks for.
     *
     * @return array<string, mixed>
     */
    private function answers(): array
    {
        $service = $this->selectedService;

        return array_filter([
            'door_size' => QuoteQuestions::asksDoorSize($service) ? $this->doorSize : null,
            'style' => QuoteQuestions::asksStyle($service) && $this->style !== '' ? $this->style : null,
            'symptoms' => QuoteQuestions::asksSymptoms($service) ? array_values($this->symptoms) : null,
            'hurricane' => $this->hurricane,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @return array<string, string>
     */
    private function cleanAttribution(): array
    {
        return collect(Arr::only($this->attribution, self::ATTRIBUTION_KEYS))
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => Str::limit(trim($value), 500, ''))
            ->all();
    }
}
