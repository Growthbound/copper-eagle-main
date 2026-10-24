<?php

namespace App\Models;

use App\Enums\MediaKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'path',
    'original_name',
    'mime_type',
    'size',
    'width',
    'height',
    'alt',
    'kind',
    'title',
    'handle',
    'source_url',
    'source_hash',
    'checksum',
])]
class Media extends Model
{
    protected $table = 'media';

    protected $attributes = [
        'kind' => 'image',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * Matches the fields the owner actually knows an item by: what the file was
     * called, and whatever they typed about it. The stored path is a random
     * hash and is deliberately not searchable.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            foreach (['original_name', 'title', 'alt', 'handle'] as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    public static function forHandle(string $handle): ?self
    {
        return self::query()->where('handle', $handle)->first();
    }

    public function url(): string
    {
        return route('media.public', ['media' => $this]);
    }

    public function readableSize(): string
    {
        $units = ['B', 'KB', 'MB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
