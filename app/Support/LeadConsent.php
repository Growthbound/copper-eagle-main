<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The permission a visitor gives when they submit the form.
 *
 * Three modes, because sites differ in what they need:
 *
 *  - off        no consent language at all
 *  - disclosure a line of text; submitting the form is the agreement
 *  - checkbox   an explicit tick, recorded as an affirmative act
 *
 * Checkbox mode defaults to optional and starts unticked. Consent to
 * automated marketing calls or texts generally cannot be made a condition of
 * getting a quote, and a pre-ticked box is not consent in any regime that
 * recognises the term. Requiring it is available, but it is a deliberate
 * choice the owner makes rather than the default they inherit.
 */
class LeadConsent
{
    public const MODE = 'consent.mode';

    public const TEXT = 'consent.text';

    public const REQUIRED = 'consent.required';

    public const MODES = ['off', 'disclosure', 'checkbox'];

    public const DEFAULT_TEXT = 'I agree that %s may contact me by phone, text, or email about my request. Message and data rates may apply.';

    public static function mode(): string
    {
        $default = self::defaultMode();
        $mode = Setting::read(self::MODE, $default);

        return in_array($mode, self::MODES, true) ? $mode : $default;
    }

    public static function enabled(): bool
    {
        return self::mode() !== 'off';
    }

    public static function isCheckbox(): bool
    {
        return self::mode() === 'checkbox';
    }

    /**
     * Ticking is only ever enforced in checkbox mode: there is nothing to
     * enforce when the agreement is implied by submitting.
     */
    public static function isRequired(): bool
    {
        $default = config('website-forms.consent.required', false) ? '1' : '0';

        return self::isCheckbox() && Setting::read(self::REQUIRED, $default) === '1';
    }

    public static function text(): string
    {
        $saved = trim((string) Setting::read(self::TEXT));
        $configured = trim((string) config('website-forms.consent.text'));

        return $saved !== ''
            ? $saved
            : ($configured !== '' ? $configured : sprintf(self::DEFAULT_TEXT, config('site.name')));
    }

    /**
     * A plain-text transcript of the checkbox disclosure and link targets.
     *
     * The rendered links are part of what the visitor sees and agrees to. The
     * evidence stored with the lead therefore cannot stop at the sentence that
     * precedes them.
     */
    public static function evidenceText(): string
    {
        if (! self::isCheckbox()) {
            return self::text();
        }

        return self::text()
            .' See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).';
    }

    public static function save(string $mode, string $text, bool $required): void
    {
        Setting::write([
            self::MODE => in_array($mode, self::MODES, true) ? $mode : self::defaultMode(),
            self::TEXT => trim($text) === '' ? null : trim($text),
            self::REQUIRED => $required ? '1' : '0',
        ]);
    }

    /**
     * What to record against a lead.
     *
     * The wording is captured whenever consent language was shown, including
     * disclosure mode: the visitor still saw terms, and that is the thing that
     * has to be evidenced later.
     *
     * @return array<string, mixed>
     */
    public static function record(bool $ticked, ?string $ip): array
    {
        if (! self::enabled()) {
            return [];
        }

        $given = self::isCheckbox() ? $ticked : true;

        return [
            'consent_given' => $given,
            'consent_text' => self::evidenceText(),
            'consent_at' => $given ? now() : null,
            'consent_ip' => $given ? $ip : null,
        ];
    }

    private static function defaultMode(): string
    {
        $mode = config('website-forms.consent.mode', 'disclosure');

        return is_string($mode) && in_array($mode, self::MODES, true)
            ? $mode
            : 'disclosure';
    }
}
