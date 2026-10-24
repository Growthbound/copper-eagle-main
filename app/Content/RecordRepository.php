<?php

namespace App\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use JsonException;

/**
 * Loads records from a directory of JSON files, one file per record, named by
 * slug. Same convention as pages: the filesystem is the index.
 */
abstract class RecordRepository
{
    /** @var Collection<string, Record>|null */
    private ?Collection $cache = null;

    abstract public function directory(): string;

    abstract protected function make(string $slug, array $data): Record;

    /**
     * The field every record of this kind must have. Services and areas call it
     * a name; a question reads better as a question.
     */
    protected function requiredField(): string
    {
        return 'name';
    }

    /**
     * @return Collection<int, Record>
     */
    public function all(): Collection
    {
        // One composite key rather than a multi-sort array: explicit order
        // first, then name, so records without an order fall to the end
        // alphabetically instead of in filesystem order.
        return $this->load()
            ->sortBy(fn (Record $record): string => sprintf('%06d-%s', $record->order(), $record->name()))
            ->values();
    }

    public function find(string $slug): ?Record
    {
        return $this->load()->get($slug);
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    public function isEmpty(): bool
    {
        return $this->load()->isEmpty();
    }

    /**
     * @return Collection<string, Record>
     */
    private function load(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! File::isDirectory($this->directory())) {
            return $this->cache = collect();
        }

        return $this->cache = collect(File::files($this->directory()))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->mapWithKeys(function ($file): array {
                $slug = $file->getFilenameWithoutExtension();

                try {
                    $data = json_decode((string) File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new InvalidRecord($this->directory(), $slug, 'not valid JSON: '.$e->getMessage());
                }

                if (! is_array($data)) {
                    throw new InvalidRecord($this->directory(), $slug, 'must contain a JSON object.');
                }

                $required = $this->requiredField();

                if (! isset($data[$required]) || ! is_string($data[$required]) || trim($data[$required]) === '') {
                    throw new InvalidRecord($this->directory(), $slug, 'needs a "'.$required.'".');
                }

                return [$slug => $this->make($slug, $data)];
            });
    }
}
