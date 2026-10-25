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
     * The service's line icon for the icon strip: a path under public/, such
     * as "images/brand/icons/drain-cleaning.png".
     *
     * Null when the record has none, and also when the value is anything but
     * a plain relative path to an existing raster image inside public/, so a
     * record can never point the strip at another host, a script, or a file
     * outside the web root.
     */
    public function icon(): ?string
    {
        return $this->iconImage()['src'] ?? null;
    }

    /**
     * The icon with its intrinsic size, which the strip needs for `width` and
     * `height`.
     *
     * @return array{src: string, width: int, height: int}|null
     */
    public function iconImage(): ?array
    {
        $icon = $this->get('icon');

        if (! is_string($icon)) {
            return null;
        }

        $icon = ltrim(trim($icon), '/');

        if (str_contains($icon, '..')
            || preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)*\.(?:png|webp|jpe?g)$#i', $icon) !== 1) {
            return null;
        }

        $root = realpath(public_path());
        $file = realpath(public_path($icon));

        if ($root === false
            || $file === false
            || ! is_file($file)
            || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $size = getimagesize($file);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return ['src' => $icon, 'width' => $size[0], 'height' => $size[1]];
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
