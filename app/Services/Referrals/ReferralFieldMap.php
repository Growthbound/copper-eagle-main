<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ReferralFieldMap
{
    private const KEYS = [
        'code' => 'contact.referral_code',
        'source' => 'contact.referral_source',
        'business' => 'contact.referral_business',
        'main_contact' => 'contact.internal_sales_person',
        'url' => 'contact.referral_url',
        'service_areas' => 'contact.service_areas',
    ];

    public function __construct(private HighLevelClient $client) {}

    public function resolve(bool $includeServiceAreas): array
    {
        $keys = $includeServiceAreas ? self::KEYS : array_diff_key(self::KEYS, ['service_areas' => true]);
        $configured = config('referrals.highlevel.fields', []);
        $ids = [];
        foreach ($keys as $name => $fieldKey) {
            if (filled($configured[$name] ?? null)) {
                $ids[$name] = $configured[$name];
            }
        }
        if (count($ids) === count($keys) && count(array_unique($ids)) === count($keys)) {
            return $ids;
        }

        $location = config('referrals.highlevel.location_id');
        try {
            return Cache::remember('referral-fields:'.hash('sha256', $location).':'.($includeServiceAreas ? 'areas' : 'base'),
                now()->addMinutes(15), fn () => $this->discover($keys, $location));
        } catch (ReferralFieldMappingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ReferralFieldMappingException('HighLevel contact fields are unavailable. Registration remains saved locally.');
        }
    }

    private function discover(array $keys, string $location): array
    {
        try {
            $response = $this->client->contactFields();
            $fields = $response->json('customFields');
        } catch (Throwable) {
            throw new ReferralFieldMappingException('HighLevel contact fields are unavailable. Registration remains saved locally.');
        }
        if (! $response->successful() || ! is_array($fields) || ! array_is_list($fields)) {
            throw new ReferralFieldMappingException('HighLevel contact fields are unavailable. Registration remains saved locally.');
        }

        $resolved = [];
        foreach ($keys as $name => $fieldKey) {
            $matches = array_values(array_filter($fields, fn ($field) => is_array($field)
                && ($field['fieldKey'] ?? null) === $fieldKey
                && ($field['locationId'] ?? null) === $location));
            $field = $matches[0] ?? null;
            $type = $name === 'service_areas' ? 'MULTIPLE_OPTIONS' : 'TEXT';
            if (count($matches) !== 1 || ($field['dataType'] ?? null) !== $type || ! is_string($field['id'] ?? null) || $field['id'] === '') {
                throw new ReferralFieldMappingException('HighLevel Contact field '.($name === 'service_areas' ? 'Service Areas' : $fieldKey).' is missing or has the wrong type. Registration remains saved locally.');
            }
            if ($name === 'service_areas') {
                $options = $field['picklistOptions'] ?? null;
                $expected = config('referrals.service_areas');
                if (! is_array($options) || ! array_is_list($options)) {
                    throw new ReferralFieldMappingException('HighLevel Service Areas choices do not match the registration form. Registration remains saved locally.');
                }
                sort($options);
                sort($expected);
                if ($options !== $expected) {
                    throw new ReferralFieldMappingException('HighLevel Service Areas choices do not match the registration form. Registration remains saved locally.');
                }
            }
            $resolved[$name] = $field['id'];
        }
        if (count(array_unique($resolved)) !== count($resolved)) {
            throw new ReferralFieldMappingException('HighLevel referral fields are not distinct. Registration remains saved locally.');
        }

        return $resolved;
    }
}
