<?php

namespace App\Content;

use Illuminate\Support\Collection;

class TestimonialRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/testimonials');
    }

    protected function make(string $slug, array $data): Record
    {
        return new Testimonial($slug, $data);
    }

    /**
     * Quotes worth showing on a page, narrowed to its service or area when it
     * has one. Unscoped testimonials show everywhere.
     *
     * @return Collection<int, Testimonial>
     */
    public function for(?Service $service = null, ?Area $area = null): Collection
    {
        return $this->all()
            ->filter(fn (Testimonial $testimonial): bool => $testimonial->appliesToService($service)
                && $testimonial->appliesToArea($area))
            ->values();
    }
}
