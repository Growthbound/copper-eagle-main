<?php

namespace App\Content;

use App\Content\Enums\PageTemplate;
use Throwable;

/**
 * The people shown in the footer team spotlight.
 *
 * They are read from the team section of the published team page, so the
 * team page is the one place a name, photo or bio is edited and the footer
 * follows. Nothing renders when that page is missing or unpublished, on the
 * team page itself (its grid already shows everyone), or on a fictional
 * template preview, where real staff photos must never appear.
 */
final readonly class TeamSpotlight
{
    /** The slug of the page whose `team` section supplies the spotlight. */
    public const PAGE = 'team';

    public function __construct(
        private ManagedContentRepository $content,
        private BlockData $data,
    ) {}

    /**
     * @return array{heading: string, members: list<array{name: string, bio: ?string, image: array<string, mixed>}>}|null
     */
    public function current(): ?array
    {
        $request = request();

        if ($request->routeIs('template-preview.*')
            || trim($request->path(), '/') === self::PAGE) {
            return null;
        }

        try {
            $page = $this->content->findPage(self::PAGE);
        } catch (Throwable) {
            // Site chrome must never take a page down with it.
            return null;
        }

        if (! $page instanceof ManagedPage
            || $page->template !== PageTemplate::Team
            || ! isset($page->sections['team'])) {
            return null;
        }

        $props = $page->sections['team']->props;

        return $this->spotlight(
            is_string($props['heading'] ?? null) ? $props['heading'] : '',
            $props['members'] ?? [],
        );
    }

    /**
     * Normalize a heading and member list, dropping any member without a
     * name or a complete photo, so a portrait never appears without the
     * person's name beside it.
     *
     * @return array{heading: string, members: list<array{name: string, bio: ?string, image: array<string, mixed>}>}|null
     */
    public function spotlight(string $heading, mixed $members): ?array
    {
        $people = collect(is_array($members) ? $members : [])
            ->map(function (mixed $member): ?array {
                if (! is_array($member)
                    || ! is_string($member['name'] ?? null)
                    || trim($member['name']) === '') {
                    return null;
                }

                $image = $this->data->media($member['image'] ?? null);

                if ($image === null) {
                    return null;
                }

                $bio = is_string($member['bio'] ?? null) && trim($member['bio']) !== ''
                    ? trim($member['bio'])
                    : null;

                return ['name' => trim($member['name']), 'bio' => $bio, 'image' => $image];
            })
            ->filter()
            ->values()
            ->all();

        if ($people === [] || trim($heading) === '') {
            return null;
        }

        return ['heading' => trim($heading), 'members' => $people];
    }
}
