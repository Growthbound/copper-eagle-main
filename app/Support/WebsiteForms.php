<?php

namespace App\Support;

final class WebsiteForms
{
    public static function provider(): string
    {
        $provider = config('website-forms.provider');

        return is_string($provider) ? $provider : 'disabled';
    }

    public static function nativeEnabled(): bool
    {
        return self::provider() === 'native';
    }

    public static function purpose(string $purpose): string
    {
        // Existing published feedback content must not become a sales funnel
        // during the interval before an owner publishes its purpose revision.
        return config('website-forms.page_purposes.'.trim(request()->path(), '/'), $purpose);
    }

    /** @return array{src: string, formId: string, iframeId: string, title: string}|null */
    public static function embed(string $purpose, string $instanceId): ?array
    {
        if (self::provider() !== 'highlevel'
            || ! in_array($purpose, ['inquiry', 'feedback', 'comments'], true)) {
            return null;
        }

        $origin = config('website-forms.origin');
        $formId = config('website-forms.forms.'.$purpose);
        if (! in_array($origin, ['https://api.leadconnectorhq.com'], true)
            || ! is_string($formId) || preg_match('/\A[a-zA-Z0-9]{20}\z/', $formId) !== 1
            || preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/', $instanceId) !== 1) {
            return null;
        }

        if ($purpose !== 'inquiry' && $formId === config('website-forms.forms.inquiry')) {
            return null;
        }

        $query = self::campaignParameters();
        request()->attributes->set('website-form-rendered', true);

        return [
            'src' => $origin.'/widget/form/'.$formId.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''),
            'formId' => $formId,
            'iframeId' => $instanceId.'-highlevel',
            'title' => match ($purpose) {
                'feedback' => 'Service feedback',
                'comments' => 'Comments and reviews',
                default => 'Request an estimate',
            },
        ];
    }

    /** @return array<string, string> */
    private static function campaignParameters(): array
    {
        $stored = request()->hasSession() ? session('website_form_campaign', Attribution::stored()) : [];
        $query = request()->query();

        return self::filterCampaign([...$stored, ...$query]);
    }

    /** @param array<string, mixed> $query */
    public static function rememberCampaign(array $query): void
    {
        // Capture before native attribution truncates long values. A truncated
        // click identifier is not a valid identifier on a later page view.
        $stored = session('website_form_campaign', self::filterCampaign(Attribution::stored()));
        $incoming = self::filterCampaign($query);
        if ($stored === [] && $incoming !== []) {
            $stored = $incoming;
        }
        session(['website_form_campaign' => $stored]);
    }

    /** @return array<string, string> */
    private static function filterCampaign(array $query): array
    {
        $parameters = [];
        foreach ([...Attribution::UTM_FIELDS, ...Attribution::CLICK_FIELDS] as $key) {
            $value = $query[$key] ?? null;
            if (is_string($value) && trim($value) !== '' && mb_strlen($value) <= 255
                && ! preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $parameters[$key] = trim($value);
            }
        }

        return $parameters;
    }
}
