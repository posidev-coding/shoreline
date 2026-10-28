<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `client` middleware: the portal is for signed-in portal users who still have a client to
 * see. Guests go to the sign-in page, and someone whose access was removed is signed out.
 */
class EnsurePortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('portal.login'));
        }

        if ($user->role->canAccessAdmin()) {
            abort(403, 'Admins see the portal from a client’s Portal tab, with “View their portal”.');
        }

        if (! $user->canUsePortal()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->with('status', 'Your portal access has ended. Questions? Give us a call.');
        }

        return $next($request);
    }
}
