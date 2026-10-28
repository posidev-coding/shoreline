<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The visitor's address, for per-visitor limits. On Laravel Cloud, every request comes through
 * Cloudflare, which sets CF-Connecting-IP; the proxy chain behind it decides what
 * request()->ip() returns.
 */
final class VisitorIp
{
    public static function from(Request $request): string
    {
        $cloudflare = laravel_cloud() ? $request->header('CF-Connecting-IP') : null;

        return is_string($cloudflare) && filter_var($cloudflare, FILTER_VALIDATE_IP) !== false
            ? $cloudflare
            : (string) $request->ip();
    }
}
