<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

#[Fillable(['code', 'name', 'business_name', 'first_name', 'last_name', 'phone', 'email', 'service_areas', 'main_contact_id', 'main_contact_name', 'registered_at', 'batch_id', 'referral_url'])]
class Referral extends Model
{
    protected function casts(): array
    {
        return ['registered_at' => 'datetime', 'last_visited_at' => 'datetime', 'synced_at' => 'datetime', 'sync_started_at' => 'datetime', 'visits' => 'integer', 'service_areas' => 'array'];
    }

    public function source(): string
    {
        return preg_replace('/\s+/u', '_', trim($this->name ?? ''));
    }

    public function publicUrl(): string
    {
        return $this->referral_url ?: self::urlForCode($this->code);
    }

    public static function urlForCode(string $code): string
    {
        // Never use a visitor's Host header in a printed or synchronized link.
        return rtrim(config('app.url'), '/').'/referrals/'.rawurlencode($code);
    }

    public function destinationUrl(): string
    {
        $url = config('referrals.form_url');
        $parts = is_string($url) ? parse_url($url) : false;
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('REFERRAL_FORM_URL must be an absolute HTTPS URL.');
        }
        parse_str($parts['query'] ?? '', $query);
        $query['refferral_source'] = $this->source();
        $query['refferral_business'] = $this->business_name;
        $base = explode('?', explode('#', $url, 2)[0], 2)[0];

        return $base.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
