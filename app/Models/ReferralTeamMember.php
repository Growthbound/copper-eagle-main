<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['highlevel_id', 'location_id', 'name', 'active'])]
class ReferralTeamMember extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
