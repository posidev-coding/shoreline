<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalSignIn;
use App\Support\VisitorIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Signing in and out. The emailed link opens show(), which only asks; confirm() signs in, so a
 * mail scanner that opens links doesn't use the code up.
 */
class SignInController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->canUsePortal()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.sign-in', ['email' => $request->string('email')->toString()]);
    }

    public function show(Request $request): View
    {
        return view('portal.confirm-sign-in', [
            'email' => $request->string('email')->toString(),
            'code' => $request->string('code')->toString(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $email = $request->string('email')->trim()->toString();
        $code = $request->string('code')->toString();

        // A mangled link gets the same answer as a used one.
        $isWellFormed = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && preg_match('/^\d{6}$/', $code) === 1;

        if (! $isWellFormed || ! PortalSignIn::attempt($email, $code, VisitorIp::from($request))->isOk()) {
            return redirect()->route('portal.login', ['email' => $email])
                ->with('status', 'That sign-in link has expired or was already used. Ask for a new code below.');
        }

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You’re signed out.');
    }
}
