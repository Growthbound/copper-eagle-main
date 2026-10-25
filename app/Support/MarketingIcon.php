<?php

namespace App\Support;

use InvalidArgumentException;

final class MarketingIcon
{
    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return config('marketing-icons.icons', []);
    }

    public static function component(string $name): string
    {
        if (! array_key_exists($name, self::catalog())) {
            throw new InvalidArgumentException(sprintf(
                'Unknown marketing icon [%s]. Approved icons: %s',
                $name,
                implode(', ', array_keys(self::catalog())),
            ));
        }

        return 'heroicon-o-'.$name;
    }
}
