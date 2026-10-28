<?php

namespace App\Support;

use App\Settings\SiteSettings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * What every email to a client shares: from the business, replies to its inbox, and signed off
 * with its phone number once a real one is set.
 */
final class ClientMail
{
    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public static function message(string $subject, string $view, array $data): MailMessage
    {
        $settings = app(SiteSettings::class);

        return (new MailMessage)
            ->subject($subject)
            ->replyTo($settings->email, $settings->business_name)
            ->markdown($view, [
                ...$data,
                'businessName' => $settings->business_name,
                'phone' => $settings->hasPlaceholderPhone() ? null : $settings->phoneForDisplay(),
                'phoneHref' => $settings->phoneHref(),
            ]);
    }

    public static function firstName(string $name): string
    {
        return str($name)->before(' ')->toString();
    }
}
