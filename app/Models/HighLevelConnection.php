<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'location_id', 'location_name', 'token', 'enabled', 'revision', 'verified_at'])]
class HighLevelConnection extends Model
{
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'enabled' => 'boolean', 'verified_at' => 'datetime'];
    }

    public static function current(): ?self
    {
        return static::query()->find(1);
    }
}
