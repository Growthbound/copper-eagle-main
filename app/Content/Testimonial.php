<?php

namespace App\Content;

/**
 * A customer quote the business has permission to publish.
 *
 * Distinct from Google reviews on purpose: choosing among testimonials you
 * collected is ordinary marketing, whereas filtering someone else's public
 * review feed to hide the bad ones is what the FTC's rule on review
 * suppression prohibits. These are the former.
 */
class Testimonial extends Record
{
    public function url(): string
    {
        return route('home').'#testimonials';
    }

    public function quote(): string
    {
        return trim((string) $this->get('quote', ''));
    }

    /** The customer's name, as they agreed it could be published. */
    public function author(): string
    {
        return $this->name();
    }

    public function location(): ?string
    {
        $location = trim((string) $this->get('location', ''));

        return $location === '' ? null : $location;
    }

    public function rating(): ?int
    {
        $rating = $this->get('rating');

        return is_numeric($rating) ? max(1, min(5, (int) $rating)) : null;
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

    public function appliesToService(?Service $service): bool
    {
        $scoped = $this->serviceSlugs();

        return $scoped === null || ($service !== null && in_array($service->slug, $scoped, true));
    }

    public function appliesToArea(?Area $area): bool
    {
        $scoped = $this->areaSlugs();

        return $scoped === null || ($area !== null && in_array($area->slug, $scoped, true));
    }
}
