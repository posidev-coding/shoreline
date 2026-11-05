<?php

namespace App\Http\Controllers;

use App\Enums\SyncIssueKind;
use App\Jobs\ProcessQuickBooksWebhook;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksWebhookEvent;
use App\Services\QuickBooks\SyncIssues;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Takes Intuit's webhook deliveries. Intuit wants a 200 within 3 seconds, so this only checks
 * the signature, stores the connected company's events, and queues one job to pull what
 * changed. An event stored before (Intuit retries until it's acknowledged) is skipped.
 */
class QuickBooksWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();

        if (! self::isSignedByIntuit($body, $request->header('intuit-signature'))) {
            return response('', 401);
        }

        // Read from the raw body: Intuit may send application/cloudevents+json, which Laravel doesn't parse.
        $events = json_decode($body, true);

        if (! is_array($events) || ! array_is_list($events)) {
            SyncIssues::raise(SyncIssueKind::WebhookUnreadable, 'QuickBooks sent a webhook in a shape the app doesn’t read. If it’s the old format, turn on CloudEvents for the app’s webhooks in Intuit’s developer portal.');

            // Signed by Intuit, so sending it again won't help.
            return response('', 200);
        }

        $realmId = QuickBooksConnection::current()?->realm_id;
        $stored = [];

        foreach ($events as $event) {
            // A delivery can carry events for other companies the Intuit app is connected to.
            if (! is_array($event) || blank($event['id'] ?? null) || (string) ($event['intuitaccountid'] ?? '') !== $realmId) {
                continue;
            }

            $row = QuickBooksWebhookEvent::query()->createOrFirst(['event_id' => (string) $event['id']], [
                'realm_id' => $realmId,
                'type' => (string) ($event['type'] ?? ''),
                'qb_id' => isset($event['intuitentityid']) ? (string) $event['intuitentityid'] : null,
                'payload' => $event,
                'created_at' => now(),
            ]);

            if ($row->wasRecentlyCreated) {
                $stored[] = $row->getKey();
            }
        }

        if ($stored !== []) {
            ProcessQuickBooksWebhook::dispatch($stored);
        }

        return response('', 200);
    }

    /**
     * Intuit signs the raw body with the app's verifier token: base64 of its HMAC-SHA256.
     */
    private static function isSignedByIntuit(string $body, ?string $signature): bool
    {
        $token = config('services.quickbooks.webhook_verifier_token');

        if (! is_string($token) || $token === '' || blank($signature)) {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $token, true)), $signature);
    }
}
