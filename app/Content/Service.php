<?php

namespace App\Content;

class Service extends Record
{
    public function url(): string
    {
        return $this->consolidated(route('services.show', $this->slug));
    }

    public function urlInArea(Area $area): string
    {
        return route('services.area', [$this->slug, $area->slug]);
    }

    public function description(): ?string
    {
        $description = trim((string) $this->get('description', ''));

        return $description === '' ? $this->summary() : $description;
    }

    /**
     * @return array<int, string>
     */
    public function highlights(): array
    {
        $highlights = $this->get('highlights', []);

        return is_array($highlights) ? array_values(array_filter($highlights, 'is_string')) : [];
    }

    /**
     * Which areas this service gets a combined page for.
     *
     * Omitted means every area, which is the usual case. Listing them is for a
     * service the business only offers in part of its territory.
     *
     * @return array<int, string>|null
     */
    public function areaSlugs(): ?array
    {
        $areas = $this->get('areas');

        return is_array($areas) ? array_values(array_filter($areas, 'is_string')) : null;
    }

    public function servesArea(Area $area): bool
    {
        $allowed = $this->areaSlugs();

        return $allowed === null || in_array($area->slug, $allowed, true);
    }
}
