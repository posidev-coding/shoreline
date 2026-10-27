<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\VisitFeed;
use Illuminate\Http\Response;

/**
 * Serves an admin's visits feed to their calendar app. No session or cookies: the secret in
 * the URL is the only credential, so an unknown one is a plain 404.
 */
class VisitFeedController extends Controller
{
    public function __invoke(string $token): Response
    {
        $user = VisitFeed::owner($token) ?? abort(404);

        // Without touching updated_at: this says when the calendar app last checked, nothing more.
        User::query()->whereKey($user->getKey())->toBase()->update(['calendar_feed_read_at' => now()]);

        return response(VisitFeed::render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
