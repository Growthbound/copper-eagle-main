<?php

namespace App\Services\Referrals;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HighLevelClient
{
    public function configured(): bool
    {
        return filled(config('referrals.highlevel.token')) && filled(config('referrals.highlevel.location_id'));
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Configure the HighLevel token and location ID first.');
        }

        return Http::baseUrl('https://services.leadconnectorhq.com')->withToken(config('referrals.highlevel.token'))
            ->withHeaders(['Version' => '2021-07-28'])->acceptJson()->asJson()->connectTimeout(3)->timeout(10)
            ->withoutRedirecting();
    }

    public function users(): array
    {
        $all = [];
        $company = config('referrals.highlevel.company_id');
        $skip = 0;
        for ($page = 0; $page < 20; $page++) {
            $query = ['locationId' => config('referrals.highlevel.location_id')];
            if ($company) {
                $query += ['companyId' => $company, 'skip' => $skip, 'limit' => 100];
            }
            try {
                $response = $this->request()->get($company ? '/users/search' : '/users/', $query);
            } catch (Throwable) {
                throw new RuntimeException('Unable to reach HighLevel. The saved team was kept.');
            }
            if (! $response->successful() || ! is_array($response->json('users')) || ! array_is_list($response->json('users'))) {
                throw new RuntimeException('Unable to refresh HighLevel team (HTTP '.$response->status().'). The saved team was kept.');
            }
            $users = $response->json('users');
            $all = [...$all, ...$users];
            if (! $company) {
                return $all;
            }
            $skip += count($users);
            $total = $response->json('count');
            if (is_numeric($total) ? $skip >= (int) $total : count($users) < 100) {
                return $all;
            }
            if ($users === []) {
                throw new RuntimeException('HighLevel returned an incomplete team list. The saved team was kept.');
            }
        }
        throw new RuntimeException('HighLevel returned too many team pages. The saved team was kept.');
    }

    public function createContact(array $payload): Response
    {
        // No automatic retries: creation can succeed even when its response is lost.
        return $this->request()->post('/contacts/', $payload);
    }

    public function contactFields(): Response
    {
        return $this->request()->get('/locations/'.rawurlencode(config('referrals.highlevel.location_id')).'/customFields', ['model' => 'contact']);
    }

    public function contact(string $id): Response
    {
        return $this->request()->get('/contacts/'.rawurlencode($id));
    }
}
