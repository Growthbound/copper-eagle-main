<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HighLevelDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return array_fill_keys(['tagged_at', 'note_started_at', 'next_attempt_at', 'locked_until', 'synced_at'], 'datetime');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
