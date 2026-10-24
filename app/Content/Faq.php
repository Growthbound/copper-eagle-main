<?php

namespace App\Content;

/**
 * A question the business is actually asked.
 *
 * A record can be scoped to particular services or areas. Left unscoped it is
 * general and appears wherever questions are shown.
 */
class Faq extends Record
{
    public function url(): string
    {
        return route('home').'#faq';
    }

    public function question(): string
    {
        return $this->name();
    }

    public function answer(): string
    {
        return trim((string) $this->get('answer', ''));
    }

    /**
     * @return array<int, string>|null
     */
    public function serviceSlugs(): ?array
    {
        $services = $this->get('services');

        return is_array($services) ? array_values(array_filter($services, 'is_string')) : null;
    }

    /**
     * @return array<int, string>|null
     */
    public function areaSlugs(): ?array
    {
        $areas = $this->get('areas');

        return is_array($areas) ? array_values(array_filter($areas, 'is_string')) : null;
    }

    public function isGeneral(): bool
    {
        return $this->serviceSlugs() === null && $this->areaSlugs() === null;
    }

    public function appliesToService(?Service $service): bool
    {
        $scoped = $this->serviceSlugs();

        if ($scoped === null) {
            return true;
        }

        return $service !== null && in_array($service->slug, $scoped, true);
    }

    public function appliesToArea(?Area $area): bool
    {
        $scoped = $this->areaSlugs();

        if ($scoped === null) {
            return true;
        }

        return $area !== null && in_array($area->slug, $scoped, true);
    }
}
