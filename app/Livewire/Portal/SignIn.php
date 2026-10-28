<?php

namespace App\Livewire\Portal;

use App\Support\PortalSignIn;
use App\Support\VisitorIp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Component;

/**
 * The portal's sign-in: an email address, then the code emailed to it. What it says after the
 * first step is the same whether or not the address has a portal.
 */
class SignIn extends Component
{
    public string $email = '';

    public string $code = '';

    public bool $codeSent = false;

    public function mount(?string $email = null): void
    {
        $this->email = (string) $email;
    }

    public function sendCode(Request $request): void
    {
        // Phones' autofill often leaves a trailing space.
        $this->email = trim($this->email);
        $this->validate(['email' => ['required', 'email', 'max:255']], ['email.email' => 'Enter an email address, like you@example.com.']);

        $ip = VisitorIp::from($request);
        $wait = PortalSignIn::waitBeforeSending($this->email, $ip);

        if ($wait > 0) {
            $this->addError('email', 'That’s a lot of codes. Try again in '.max(1, (int) ceil($wait / 60)).' min.');

            return;
        }

        PortalSignIn::sendCode($this->email, $ip);

        $this->code = '';
        $this->codeSent = true;
    }

    public function signIn(Request $request): void
    {
        $this->code = preg_replace('/\D/', '', $this->code) ?? '';
        $this->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'The code is 6 digits.']);

        $result = PortalSignIn::attempt($this->email, $this->code, VisitorIp::from($request));

        if (! $result->isOk()) {
            $this->addError('code', PortalSignIn::failureMessage($result));

            return;
        }

        $this->redirectIntended(route('portal.dashboard'));
    }

    public function useAnotherEmail(): void
    {
        $this->reset('code', 'codeSent');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.portal.sign-in');
    }
}
