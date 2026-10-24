<?php

namespace App\Services\Referrals;

use App\Models\ReferralTeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeamDirectory
{
    public function available(): Collection
    {
        $excluded = config('referrals.highlevel.excluded_user_ids', []);
        if (! config('referrals.highlevel.location_id')) {
            return new Collection;
        }

        return ReferralTeamMember::where('location_id', config('referrals.highlevel.location_id'))
            ->where('active', true)->whereNotIn('highlevel_id', $excluded)->orderBy('name')->get();
    }

    public function refresh(HighLevelClient $client): int
    {
        $users = $client->users();
        $location = config('referrals.highlevel.location_id');
        $rows = [];
        foreach ($users as $user) {
            if (! is_array($user) || empty($user['id']) || ! is_string($user['id'])) {
                throw new RuntimeException('HighLevel returned an invalid team list. The saved team was kept.');
            }
            $locations = $user['roles']['locationIds'] ?? [$location];
            if (! is_array($locations) || ! in_array($location, $locations, true)) {
                continue;
            }
            $name = trim($user['name'] ?? (($user['firstName'] ?? '').' '.($user['lastName'] ?? '')));
            if ($name === '' || mb_strlen($name) > 255) {
                throw new RuntimeException('HighLevel returned an invalid team name. The saved team was kept.');
            }
            $rows[] = ['highlevel_id' => $user['id'], 'location_id' => $location, 'name' => $name,
                'active' => ! ($user['deleted'] ?? false) && ! ($user['disabled'] ?? false) && ($user['active'] ?? true)
                    && ! in_array($user['id'], config('referrals.highlevel.excluded_user_ids'), true)];
        }
        DB::transaction(function () use ($rows, $location): void {
            ReferralTeamMember::where('location_id', $location)->update(['active' => false]);
            foreach ($rows as $row) {
                ReferralTeamMember::updateOrCreate(['location_id' => $location, 'highlevel_id' => $row['highlevel_id']], $row);
            }
        });

        return $this->available()->count();
    }
}
