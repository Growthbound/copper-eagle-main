<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Who gets told when an enquiry arrives.
 *
 * Kept in settings rather than environment variables so the owner can change
 * the recipients without a deploy — the person who should be called about a
 * new job changes more often than the code does.
 */
class LeadNotifications
{
    public const ENABLED = 'notifications.leads.enabled';

    public const RECIPIENTS = 'notifications.leads.recipients';

    /**
     * A cap on who gets notified.
     *
     * Sending goes through credentials shared by every site this template
     * runs, so the reputation of that one sending domain is a shared asset. A
     * business needs two or three addresses; a list of fifty is either a
     * mistake or someone using the notification screen as a mailing list, and
     * either way the bounces land on every other client.
     */
    public const MAX_RECIPIENTS = 5;

    public static function enabled(): bool
    {
        $saved = Setting::read(self::ENABLED);

        // On by default: a site that captures leads and tells nobody is the
        // worse failure, so this has to be switched off deliberately.
        return $saved === null ? true : $saved === '1';
    }

    /**
     * Addresses to notify, falling back to the site's own contact address.
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        $saved = trim((string) Setting::read(self::RECIPIENTS));

        // Nothing saved in the admin: the deployment's environment list, then
        // the site's own contact address.
        if ($saved === '') {
            $saved = trim((string) config('website-forms.lead_notification_recipients'));
        }

        if ($saved === '') {
            $fallback = trim((string) config('site.email'));

            return $fallback === '' ? [] : [$fallback];
        }

        // A closure, not trim(...): map() passes each item's index as the
        // second argument, which trim reads as the characters to strip, so
        // "a@x.test, b@y.test" kept the space and dropped every address after
        // the first.
        return collect(preg_split('/[\r\n,]+/', $saved) ?: [])
            ->map(fn (string $address): string => trim($address))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->take(self::MAX_RECIPIENTS)
            ->values()
            ->all();
    }

    public static function shouldSend(): bool
    {
        return self::enabled() && self::recipients() !== [];
    }

    /**
     * @param  array<int, string>  $recipients
     */
    public static function save(bool $enabled, string $recipients): void
    {
        Setting::write([
            self::ENABLED => $enabled ? '1' : '0',
            self::RECIPIENTS => trim($recipients) === '' ? null : trim($recipients),
        ]);
    }

    /**
     * Whether mail is only being written to the log rather than actually sent.
     *
     * Surfaced in the admin because "notifications are on" and "notifications
     * arrive" are different things, and the gap is invisible otherwise.
     */
    public static function isPretending(): bool
    {
        return in_array(config('mail.default'), ['log', 'array', 'null'], true);
    }

    /**
     * Why mail would not arrive, if there is a reason worth naming.
     *
     * A configured driver is not the same as a working one. The two ways this
     * goes wrong silently are a transport that only pretends to send, and a
     * sender address that was never set — the second is accepted locally and
     * then rejected by the provider, which is harder to notice.
     */
    public static function problem(): ?string
    {
        if (self::isPretending()) {
            return 'No mail service is set up for this site, so notifications are written to the application log '
                .'instead of being delivered. Until that is configured, treat email as switched off.';
        }

        if (blank(config('mail.from.address'))) {
            return 'Mail is configured but has no sender address, so the provider will reject it. '
                .'Set MAIL_FROM_ADDRESS to an address on a domain you have verified.';
        }

        return null;
    }
}
