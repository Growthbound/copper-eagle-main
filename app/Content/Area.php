<?php

namespace App\Content;

class Area extends Record
{
    public function url(): string
    {
        return $this->consolidated(route('areas.show', $this->slug));
    }

    public function region(): ?string
    {
        $region = trim((string) $this->get('region', ''));

        return $region === '' ? null : $region;
    }

    /**
     * "Springfield, Illinois" where a region is set, otherwise just the name.
     */
    public function label(): string
    {
        return $this->region() === null
            ? $this->name()
            : $this->name().', '.$this->region();
    }

    /**
     * Neighbourhoods, landmarks, or anything else that makes a page about this
     * area genuinely about this area.
     *
     * @return array<int, string>
     */
    public function landmarks(): array
    {
        $landmarks = $this->get('landmarks', []);

        return is_array($landmarks) ? array_values(array_filter($landmarks, 'is_string')) : [];
    }

    /**
     * Whether there is enough here to justify its own page.
     *
     * A page that is another page with the place name swapped is a doorway
     * page, which search engines penalise. content:check warns when an area
     * has nothing local to say.
     */
    public function hasLocalContent(): bool
    {
        return $this->summary() !== null || $this->landmarks() !== [];
    }
}
