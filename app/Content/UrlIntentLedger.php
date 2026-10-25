<?php

namespace App\Content;

use RuntimeException;

/**
 * The authority for "which URL owns which search intent, and what happens to it".
 *
 * The migration ledger answers where a legacy WordPress URL went. This one
 * answers a different question: of the URLs the site publishes today, which is
 * the canonical owner of each intent, which are duplicates competing for the
 * same query, and what has been decided about each of them.
 *
 * Two rules do most of the work. A URL gets exactly one disposition, so no page
 * is quietly both preserved and retired. An intent gets exactly one primary
 * owner, so two pages cannot be pointed at the same query — that is what
 * cannibalization looks like before anyone notices it in Search Console.
 */
final class UrlIntentLedger
{
    /**
     * What has been decided about a URL.
     *
     * `noindex_hold` is the state for a page that stays reachable but is kept
     * out of the index until its evidence gate clears; `unresolved` is the
     * honest state for a decision nobody has made yet. Neither is a synonym for
     * `retire`, and pretending otherwise is how equity gets thrown away.
     */
    private const DISPOSITIONS = [
        'preserve',
        'consolidate',
        'redirect',
        'noindex_hold',
        'retire',
        'unresolved',
    ];

    /**
     * Dispositions that send a visitor somewhere else, and so need a target.
     */
    private const FORWARDING_DISPOSITIONS = ['consolidate', 'redirect'];

    private const INTENT_ROLES = ['primary_owner', 'supporting', 'contested', 'none'];

    private const INTENT_STATUSES = ['resolved', 'unresolved'];

    private const INDEXATION_STATES = ['indexable', 'noindex'];

    /**
     * @param  array<string, mixed>  $ledger
     * @param  list<string>  $claimKeys  keys from the claims register that an entry may gate on
     * @return list<string>
     */
    public function validate(array $ledger, array $claimKeys = []): array
    {
        $errors = [];

        if (($ledger['schema_version'] ?? null) !== 1) {
            $errors[] = 'The ledger schema_version must be 1.';
        }

        $intents = $ledger['intents'] ?? null;
        $entries = $ledger['entries'] ?? null;

        if (! is_array($intents) || ! array_is_list($intents)) {
            return [...$errors, 'The ledger intents must be a list.'];
        }

        if (! is_array($entries) || ! array_is_list($entries)) {
            return [...$errors, 'The ledger entries must be a list.'];
        }

        $intentStatuses = [];

        foreach ($intents as $index => $intent) {
            $pointer = "intents.{$index}";

            if (! is_array($intent)) {
                $errors[] = "{$pointer} must be an object.";

                continue;
            }

            $id = $intent['id'] ?? null;

            if (! is_string($id) || trim($id) === '') {
                $errors[] = "{$pointer}.id is required.";

                continue;
            }

            if (isset($intentStatuses[$id])) {
                $errors[] = "{$pointer}.id {$id} appears more than once.";

                continue;
            }

            if (! is_string($intent['label'] ?? null) || trim((string) $intent['label']) === '') {
                $errors[] = "{$pointer}.label is required.";
            }

            $status = $intent['status'] ?? null;

            if (! is_string($status) || ! in_array($status, self::INTENT_STATUSES, true)) {
                $errors[] = "{$pointer}.status must be one of ".implode(', ', self::INTENT_STATUSES).'.';
                $status = 'unresolved';
            }

            $intentStatuses[$id] = $status;
        }

        $urls = [];
        $owners = [];
        $forwarding = [];

        foreach ($entries as $index => $entry) {
            $pointer = "entries.{$index}";

            if (! is_array($entry)) {
                $errors[] = "{$pointer} must be an object.";

                continue;
            }

            $url = $entry['url'] ?? null;

            if (! is_string($url) || ! str_starts_with($url, '/')) {
                $errors[] = "{$pointer}.url must be an absolute site path.";

                continue;
            }

            if (isset($urls[$url])) {
                $errors[] = "{$pointer}.url {$url} appears more than once, first at {$urls[$url]}.";

                continue;
            }

            $urls[$url] = $pointer;

            foreach (['source', 'content_kind', 'notes'] as $field) {
                if (! is_string($entry[$field] ?? null) || trim((string) $entry[$field]) === '') {
                    $errors[] = "{$pointer}.{$field} is required.";
                }
            }

            $indexation = $entry['indexation'] ?? null;

            if (! is_string($indexation) || ! in_array($indexation, self::INDEXATION_STATES, true)) {
                $errors[] = "{$pointer}.indexation must be one of ".implode(', ', self::INDEXATION_STATES).'.';
            }

            $intent = $entry['intent'] ?? null;
            $role = $entry['intent_role'] ?? null;

            if (! is_string($role) || ! in_array($role, self::INTENT_ROLES, true)) {
                $errors[] = "{$pointer}.intent_role must be one of ".implode(', ', self::INTENT_ROLES).'.';
                $role = 'none';
            }

            if (! is_string($intent) || trim($intent) === '') {
                $errors[] = "{$pointer}.intent is required.";
            } elseif (! array_key_exists($intent, $intentStatuses)) {
                $errors[] = "{$pointer} references unknown intent {$intent}.";
            } else {
                if ($role === 'primary_owner') {
                    $owners[$intent][] = $url;
                }

                if ($role === 'contested' && $intentStatuses[$intent] === 'resolved') {
                    $errors[] = "{$pointer} is contested but its intent {$intent} is resolved.";
                }
            }

            $disposition = $entry['disposition'] ?? null;
            $target = $entry['disposition_target'] ?? null;

            if (! is_string($disposition) || ! in_array($disposition, self::DISPOSITIONS, true)) {
                $errors[] = "{$pointer} has unknown disposition ".json_encode($disposition).'.';
            } elseif (in_array($disposition, self::FORWARDING_DISPOSITIONS, true)) {
                if (! is_string($target) || ! str_starts_with($target, '/')) {
                    $errors[] = "{$pointer} is a {$disposition} and requires a disposition_target.";
                } elseif ($target === $url) {
                    $errors[] = "{$pointer} redirects to itself.";
                } else {
                    $forwarding[$url] = ['target' => $target, 'pointer' => $pointer];
                }

                /*
                 * A URL cannot both forward and be indexable. Recording it as
                 * indexable is how a fold gets decided and then quietly not
                 * done: the entry reads as retired while the duplicate keeps
                 * serving a 200 and keeps competing for its pillar's query.
                 */
                if ($indexation === 'indexable') {
                    $errors[] = "{$pointer} is a {$disposition} and cannot also be indexable.";
                }
            } elseif ($target !== null) {
                $errors[] = "{$pointer} is a {$disposition} and must not set a disposition_target.";
            }

            $gates = $entry['evidence_gates'] ?? null;

            if (! is_array($gates) || ! array_is_list($gates)) {
                $errors[] = "{$pointer}.evidence_gates must be a list of claim keys.";
            } else {
                foreach ($gates as $gate) {
                    if (! is_string($gate) || trim($gate) === '') {
                        $errors[] = "{$pointer}.evidence_gates must contain only claim keys.";
                    } elseif ($claimKeys !== [] && ! in_array($gate, $claimKeys, true)) {
                        $errors[] = "{$pointer} references unknown claim key {$gate}.";
                    }
                }
            }
        }

        foreach ($forwarding as $url => $hop) {
            if (! isset($urls[$hop['target']])) {
                $errors[] = "{$hop['pointer']} targets {$hop['target']}, which is not in the ledger.";

                continue;
            }

            if (isset($forwarding[$hop['target']])) {
                $errors[] = "{$hop['pointer']} creates a redirect chain through {$forwarding[$hop['target']]['pointer']}.";
            }
        }

        foreach ($intentStatuses as $id => $status) {
            $count = count($owners[$id] ?? []);

            if ($count > 1) {
                $errors[] = "Intent {$id} has {$count} primary owners: ".implode(', ', $owners[$id]).'.';
            }

            if ($status === 'resolved' && $count === 0) {
                $errors[] = "Intent {$id} is resolved but has no primary owner.";
            }
        }

        $defects = $ledger['known_defects'] ?? [];

        if (! is_array($defects) || ! array_is_list($defects)) {
            $errors[] = 'The ledger known_defects must be a list.';

            return $errors;
        }

        foreach ($defects as $index => $defect) {
            $pointer = "known_defects.{$index}";

            if (! is_array($defect)) {
                $errors[] = "{$pointer} must be an object.";

                continue;
            }

            foreach (['id', 'url', 'detail'] as $field) {
                if (! is_string($defect[$field] ?? null) || trim((string) $defect[$field]) === '') {
                    $errors[] = "{$pointer}.{$field} is required.";
                }
            }

            $url = $defect['url'] ?? null;

            if (is_string($url) && ! isset($urls[$url])) {
                $errors[] = "{$pointer} declares a defect for {$url}, which is not in the ledger.";
            }
        }

        return $errors;
    }

    /**
     * The defects the ledger's own contents prove, independent of what it
     * declares in `known_defects`.
     *
     * Keeping detection separate from declaration is the point: the feature
     * test compares the two, so a newly added city that is indexable without
     * evidence shows up as an undeclared defect rather than passing quietly.
     *
     * @param  array<string, mixed>  $ledger
     * @param  array<string, string>  $claimStatuses  claim key => status
     * @return list<array{id: string, url: string, detail: string}>
     */
    public function detectDefects(array $ledger, array $claimStatuses): array
    {
        $defects = [];
        $entries = $ledger['entries'] ?? [];

        if (! is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['url'] ?? null)) {
                continue;
            }

            $url = $entry['url'];
            $gates = $entry['evidence_gates'] ?? [];

            if (($entry['indexation'] ?? null) === 'indexable' && is_array($gates)) {
                foreach ($gates as $gate) {
                    if (! is_string($gate)) {
                        continue;
                    }

                    $status = $claimStatuses[$gate] ?? 'unknown';

                    if ($status !== 'approved') {
                        $defects[] = [
                            'id' => 'indexable_without_evidence',
                            'url' => $url,
                            'detail' => "Indexable while claim {$gate} is {$status}.",
                        ];
                    }
                }
            }

            if (($entry['intent_role'] ?? null) === 'contested') {
                $defects[] = [
                    'id' => 'contested_intent',
                    'url' => $url,
                    'detail' => 'Competes for intent '.((string) ($entry['intent'] ?? '?')).' with no resolved owner.',
                ];
            }
        }

        usort($defects, fn (array $a, array $b): int => [$a['id'], $a['url']] <=> [$b['id'], $b['url']]);

        return $defects;
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @param  list<string>  $claimKeys
     */
    public function assertValid(array $ledger, array $claimKeys = []): void
    {
        $errors = $this->validate($ledger, $claimKeys);

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
    }
}
