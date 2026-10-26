<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\HeicConverter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves a quote request's photos from Livewire's temporary uploads onto the lead, converting
 * HEIC to JPEG on the way. It runs after the request, so the visitor doesn't wait on it, and
 * apart from the owner's alert, so a photo that won't convert never holds up a lead.
 *
 * Each upload is deleted once it's attached, so a retry skips what's already done.
 */
class AttachLeadPhotos implements ShouldQueue
{
    use Queueable;

    /**
     * What the file's contents must turn out to be. Browsers upload straight to the bucket,
     * so the type they declared proves nothing. A HEIC stays one only when it won't convert.
     */
    private const array IMAGE_EXTENSIONS = ['jpg', 'png', 'webp', 'heic', 'heif'];

    public int $tries = 3;

    public int $timeout = 85;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  string  $disk  The disk Livewire keeps temporary uploads on.
     * @param  list<array{path: string, name: string}>  $uploads
     */
    public function __construct(
        public Lead $lead,
        public string $disk,
        public array $uploads,
    ) {}

    public function handle(HeicConverter $converter): void
    {
        $storage = Storage::disk($this->disk);

        foreach ($this->uploads as $upload) {
            if (! $storage->exists($upload['path'])) {
                if (! $this->alreadyAttached($upload['path'])) {
                    Log::error('A quote photo’s upload was gone before it could be attached.', ['lead' => $this->lead->getKey(), 'disk' => $this->disk, 'path' => $upload['path']]);
                }

                continue;
            }

            $local = $this->download($storage, $upload['path']);
            $jpeg = $converter->isHeic($local) ? $converter->toJpeg($local) : null;
            $file = $jpeg ?? $local;
            $extension = (new File($file))->guessExtension();

            if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $name = Str::limit(pathinfo($upload['name'], PATHINFO_FILENAME), 100, '') ?: 'Photo';

                try {
                    $this->lead->addMedia($file)
                        ->usingName($name)
                        ->usingFileName((Str::slug($name) ?: 'photo').'.'.$extension)
                        ->withCustomProperties(['upload' => $upload['path']])
                        ->toMediaCollection('photos');
                } catch (FileIsTooBig|FileUnacceptableForCollection $exception) {
                    // Retrying won't change the answer, and failing the job would lose the other photos too.
                    Log::error('A quote photo was refused.', ['lead' => $this->lead->getKey(), 'name' => $upload['name'], 'error' => $exception->getMessage()]);
                }
            } else {
                Log::warning('Skipped a quote photo that isn’t an image.', ['lead' => $this->lead->getKey(), 'type' => $extension]);
            }

            foreach ([$local, $jpeg] as $leftover) {
                if ($leftover !== null && is_file($leftover)) {
                    unlink($leftover);
                }
            }

            $storage->delete($upload['path']);
        }
    }

    /**
     * A retry finds the uploads an earlier attempt attached already gone; that's expected.
     */
    private function alreadyAttached(string $path): bool
    {
        return $this->lead->getMedia('photos')->contains(fn (Media $media): bool => $media->getCustomProperty('upload') === $path);
    }

    private function download(Filesystem $storage, string $path): string
    {
        $local = (string) tempnam(sys_get_temp_dir(), 'lead-photo-');
        $stream = $storage->readStream($path);
        file_put_contents($local, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $local;
    }
}
