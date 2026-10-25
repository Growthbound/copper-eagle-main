<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The permission a visitor gives when they submit the form.
 *
 * Four modes, because sites differ in what they need:
 *
 *  - off        no consent language at all
 *  - disclosure a line of text; submitting the form is the agreement
 *  - checkbox   an explicit tick, recorded as an affirmative act
 *  - choices    separate boxes, one per kind of message (CHOICE_KINDS), each
 *               recorded on its own. The wording comes from the site's
 *               configuration because it must match a registered text
 *               campaign word for word; the admin cannot edit it.
 *
 * Checkbox mode defaults to optional and starts unticked. Consent to
 * automated marketing calls or texts generally cannot be made a condition of
 * getting a quote, and a pre-ticked box is not consent in any regime that
 * recognises the term. Requiring it is available, but it is a deliberate
 * choice the owner makes rather than the default they inherit. Choices are
 * never required: each box is a separate permission a visitor may decline.
 */
class LeadConsent
{
    public const MODE = 'consent.mode';

    public const TEXT = 'consent.text';

    public const REQUIRED = 'consent.required';

    public const MODES = ['off', 'disclosure', 'checkbox', 'choices'];

    /**
     * The kinds of permission a lead can record separately, each with its own
     * column (consent_service, consent_marketing).
     */
    public const CHOICE_KINDS = ['service', 'marketing'];

    public const DEFAULT_TEXT = 'I agree that %s may contact me by phone, text, or email about my request. Message and data rates may apply.';

    public static function mode(): string
    {
        $default = self::defaultMode();
        $mode = Setting::read(self::MODE, $default);

        return in_array($mode, self::modes(), true) ? $mode : $default;
    }

    /**
     * The modes this site can show. Choices need configured wording.
     *
     * @return list<string>
     */
    public static function modes(): array
    {
        return self::choices() === []
            ? array_values(array_diff(self::MODES, ['choices']))
            : self::MODES;
    }

    public static function hasChoices(): bool
    {
        return self::mode() === 'choices';
    }

    /**
     * The configured wording for each separate box, in display order.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $configured = config('website-forms.consent.choices');

        if (! is_array($configured) || trim((string) config('website-forms.consent.choices_title')) === '') {
            return [];
        }

        $choices = [];

        foreach ($configured as $kind => $text) {
            if (in_array($kind, self::CHOICE_KINDS, true) && is_string($text) && trim($text) !== '') {
                $choices[$kind] = trim($text);
            }
        }

        return $choices;
    }

    public static function choicesTitle(): string
    {
        return trim((string) config('website-forms.consent.choices_title'));
    }

    public static function choicesNote(): string
    {
        return trim((string) config('website-forms.consent.choices_note'));
    }

    /**
     * Link text for the two legal pages shown beside the choices.
     *
     * @return array{privacy: string, terms: string}
     */
    public static function choicesLinks(): array
    {
        $privacy = trim((string) config('website-forms.consent.choices_privacy_link'));
        $terms = trim((string) config('website-forms.consent.choices_terms_link'));

        return [
            'privacy' => $privacy !== '' ? $privacy : 'Privacy Policy',
            'terms' => $terms !== '' ? $terms : 'Terms and Conditions',
        ];
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
        if (self::hasChoices()) {
            $links = self::choicesLinks();

            return implode("\n", [
                self::choicesTitle(),
                ...array_map(fn (string $text): string => '- '.$text, array_values(self::choices())),
                ltrim(self::choicesNote().' See our '.$links['privacy'].' (/privacy-policy/) and '
                    .$links['terms'].' (/terms-and-conditions/).'),
            ]);
        }

        if (! self::isCheckbox()) {
            return self::text();
        }

        return self::text()
            .' See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).';
    }

    public static function save(string $mode, string $text, bool $required): void
    {
        Setting::write([
            self::MODE => in_array($mode, self::modes(), true) ? $mode : self::defaultMode(),
            self::TEXT => trim($text) === '' ? null : trim($text),
            self::REQUIRED => $required ? '1' : '0',
        ]);
    }

    /**
     * What to record against a lead.
     *
     * The wording is captured whenever consent language was shown, including
     * disclosure mode: the visitor still saw terms, and that is the thing that
     * has to be evidenced later. In choices mode each shown box is recorded as
     * ticked or not; consent_given means at least one was ticked.
     *
     * @param  array<string, bool>  $choices  ticked state by CHOICE_KINDS key
     * @return array<string, mixed>
     */
    public static function record(bool $ticked, ?string $ip, array $choices = []): array
    {
        if (! self::enabled()) {
            return [];
        }

        if (self::hasChoices()) {
            $shown = self::choices();
            $chosen = [];

            foreach (self::CHOICE_KINDS as $kind) {
                $chosen['consent_'.$kind] = array_key_exists($kind, $shown)
                    ? ($choices[$kind] ?? false) === true
                    : null;
            }

            $given = in_array(true, $chosen, true);

            return [
                'consent_given' => $given,
                'consent_text' => self::evidenceText(),
                'consent_at' => $given ? now() : null,
                'consent_ip' => $given ? $ip : null,
                ...$chosen,
            ];
        }

        $given = self::isCheckbox() ? $ticked : true;

        return [
            'consent_given' => $given,
            'consent_text' => self::evidenceText(),
            'consent_at' => $given ? now() : null,
            'consent_ip' => $given ? $ip : null,
        ];
    }

    /**
     * The configured mode, or the single box when choices are configured as
     * the mode but their wording is missing.
     */
    private static function defaultMode(): string
    {
        $mode = config('website-forms.consent.mode', 'disclosure');

        if ($mode === 'choices' && self::choices() === []) {
            return 'checkbox';
        }

        return is_string($mode) && in_array($mode, self::MODES, true)
            ? $mode
            : 'disclosure';
    }
}
