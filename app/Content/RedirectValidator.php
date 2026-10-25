<?php

namespace App\Content;

use App\Content\Validation\ContentValidationResult;
use App\Models\RedirectRule;

final class RedirectValidator
{
    private const ALLOWED_STATUS_CODES = [301, 302];

    public function __construct(
        private readonly UrlDispositionRegistry $dispositions,
        private readonly LegacyUrlRegistry $legacy,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function validate(
        array $attributes,
        ?RedirectRule $ignore = null,
    ): ContentValidationResult {
        $errors = [];
        $source = $this->normalizePath($attributes['source_path'] ?? null);
        $target = $attributes['target'] ?? null;
        $relativeTarget = $this->normalizePath($target);
        $localTarget = $relativeTarget ?? $this->sameOriginPath($target);
        $enabled = $attributes['enabled'] ?? true;

        if ($source === null
            || $source !== ($attributes['source_path'] ?? null)
            || $this->isProtectedSource($source)) {
            $errors['/source_path'][] = 'The source must be an exact normalized public path.';
        }

        if (! is_bool($enabled)) {
            $errors['/enabled'][] = 'The enabled value must be boolean.';
        }

        if (! in_array($attributes['status_code'] ?? null, self::ALLOWED_STATUS_CODES, true)) {
            $errors['/status_code'][] = 'The redirect status must be 301 or 302.';
        }

        $isRelative = $relativeTarget !== null && $relativeTarget === $target;
        $isAbsoluteHttps = is_string($target) && $this->isSafeAbsoluteHttps($target);

        if (! $isRelative && ! $isAbsoluteHttps) {
            $errors['/target'][] = 'The target must be a normalized public path or an absolute HTTPS URL.';
        }

        if ($source !== null && $localTarget !== null && $source === $localTarget) {
            $errors['/target'][] = 'A redirect cannot target its own source path.';
        }

        /*
         * The chain checks below only see other rows in this table. A URL the
         * intent ledger has folded is a redirect too, so pointing a managed
         * rule at one is the same two-hop chain, just spread across the two
         * authorities that serve redirects.
         */
        if ($localTarget !== null && $this->dispositions->isForwarded($localTarget)) {
            $target = $this->dispositions->targetFor($localTarget);

            $errors['/target'][] = "This target has been consolidated into {$target}; link to that instead.";
        }

        /*
         * Three systems serve redirects on this site — managed rows, ledger
         * dispositions, and the legacy URL registry — and each one's chain
         * check only sees its own. A rule aimed at a legacy source builds the
         * same two-hop chain, just spread across two of them.
         */
        /*
         * Gated on enabled, so a rule that only became a conflict when the
         * legacy registry changed can still be switched off. A disabled rule
         * cannot chain — the middleware ignores it — and refusing the toggle
         * would leave the owner unable to fix the very problem this reports.
         */
        if ($enabled === true
            && $localTarget !== null
            && ($legacy = $this->legacy->targetFor($localTarget)) !== null) {
            $errors['/target'][] = "This target is a legacy URL that already forwards to {$legacy}; use that instead.";
        }

        /*
         * The source matters as much as the target, in two ways.
         *
         * A rule whose source the legacy layer already claims can never run,
         * because that middleware answers first — the rule saves cleanly and
         * then does nothing, which is worse than being refused.
         *
         * And a rule whose source is a legacy *destination* extends every
         * legacy hop by one. `/feed` forwards to `/blog/`, so a managed rule on
         * `/blog` turns one redirect into two for every feed request.
         */
        if ($enabled === true && $source !== null) {
            if (($legacy = $this->legacy->targetFor($source)) !== null) {
                $errors['/source_path'][] = "This source is already forwarded to {$legacy} before managed redirects run, so the rule could never take effect.";
            }

            foreach ($this->legacy->declaredTargets() as $destination) {
                if ($this->normalizePath($destination) === $source) {
                    $errors['/source_path'][] = 'This source is where legacy URLs already land; a rule here would add a second hop to every one of them.';

                    break;
                }
            }
        }

        if ($source !== null) {
            $duplicate = RedirectRule::query()
                ->where('source_path', $source)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($duplicate) {
                $errors['/source_path'][] = 'A redirect already exists for this source path.';
            }
        }

        if ($enabled === true && $localTarget !== null) {
            $targetIsManagedSource = RedirectRule::query()
                ->where('enabled', true)
                ->where('source_path', $localTarget)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($targetIsManagedSource) {
                $errors['/target'][] = 'Redirect chains are not allowed; target the final destination.';
            }
        }

        if ($enabled === true && $source !== null) {
            $sourceIsManagedTarget = RedirectRule::query()
                ->where('enabled', true)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->get(['target'])
                ->contains(fn (RedirectRule $rule): bool => (
                    $this->normalizePath($rule->target)
                    ?? $this->sameOriginPath($rule->target)
                ) === $source);

            if ($sourceIsManagedTarget) {
                $errors['/source_path'][] = 'This source is already another redirect target and would create a chain.';
            }
        }

        return new ContentValidationResult($errors);
    }

    public function normalizePath(mixed $path): ?string
    {
        if (! is_string($path)
            || $path === ''
            || str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x20\x7F]/', $path) === 1
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')) {
            return null;
        }

        $normalized = '/'.trim($path, '/');

        if ($normalized !== '/'
            && preg_match('#^/[A-Za-z0-9][A-Za-z0-9/_-]*$#', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function isProtectedSource(string $source): bool
    {
        return ReservedPublicPath::isReserved($source);
    }

    private function isSafeAbsoluteHttps(string $target): bool
    {
        $parts = parse_url($target);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function sameOriginPath(mixed $target): ?string
    {
        if (! is_string($target) || ! $this->isSafeAbsoluteHttps($target)) {
            return null;
        }

        $targetParts = parse_url($target);
        $siteParts = parse_url((string) config('app.url'));

        if (! is_array($targetParts)
            || ! is_array($siteParts)
            || strtolower((string) ($targetParts['host'] ?? '')) !== strtolower((string) ($siteParts['host'] ?? ''))
            || $this->effectivePort($targetParts) !== $this->effectivePort($siteParts)) {
            return null;
        }

        return $this->normalizePath($targetParts['path'] ?? '/');
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function effectivePort(array $parts): int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80;
    }
}
