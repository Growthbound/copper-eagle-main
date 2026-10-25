<?php

namespace App\Content;

final class ContentSnapshotDiff
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{path: string, before: mixed, after: mixed}>
     */
    public function between(array $before, array $after): array
    {
        $left = $this->flatten($before);
        $right = $this->flatten($after);
        $paths = array_unique([...array_keys($left), ...array_keys($right)]);
        sort($paths);
        $changes = [];

        foreach ($paths as $path) {
            $beforeValue = $left[$path] ?? null;
            $afterValue = $right[$path] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(mixed $value, string $pointer = ''): array
    {
        if (! is_array($value) || $value === []) {
            return [$pointer === '' ? '/' : $pointer => $value];
        }

        $flat = [];

        foreach ($value as $key => $child) {
            $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
            $flat += $this->flatten($child, $pointer.'/'.$segment);
        }

        return $flat;
    }
}
