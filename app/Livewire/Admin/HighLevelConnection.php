<?php

namespace App\Livewire\Admin;

use App\HighLevel\Client;
use App\HighLevel\Failure;
use App\Models\HighLevelConnection as Connection;
use App\Models\HighLevelDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class HighLevelConnection extends Component
{
    public string $locationId = '';

    // Write-only input: never initialized from persistence, cleared after every save attempt.
    public string $apiKey = '';

    #[Locked]
    public int $revision = 0;

    public function mount(): void
    {
        $connection = Connection::current();
        $this->locationId = $connection?->location_id ?? '';
        $this->revision = $connection?->revision ?? 0;
    }

    public function connect(): void
    {
        try {
            $this->validate([
                'locationId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,80}$/D'],
                'apiKey' => ['nullable', 'string', 'max:1000', 'regex:/^[^\s]+$/D'],
            ]);
            $this->rateLimit();
            $connection = Connection::current();
            $this->assertTarget($connection);
            $token = trim($this->apiKey) ?: $connection?->token;
            if (! $token) {
                throw ValidationException::withMessages(['apiKey' => 'Enter a HighLevel Private Integration token.']);
            }
            $result = (new Client($token))->request('GET', '/locations/'.$this->locationId);
            $location = $result['location'] ?? null;
            if (! is_array($location) || ($location['id'] ?? null) !== $this->locationId || ! is_string($location['name'] ?? null) || blank($location['name'])) {
                throw new Failure('HighLevel did not confirm this location. Check the location ID and key.');
            }

            DB::transaction(function () use ($location, $token): void {
                $connection = Connection::query()->lockForUpdate()->find(1);
                $this->assertTarget($connection);
                $connection ??= new Connection(['id' => 1]);
                $connection->fill([
                    'location_id' => $this->locationId, 'location_name' => mb_substr($location['name'], 0, 255),
                    'token' => $token, 'enabled' => true, 'verified_at' => now(), 'revision' => $this->revision + 1,
                ])->save();
                $this->revision = $connection->revision;
            });
            session()->flash('highlevel-status', 'HighLevel connected. New website submissions will be sent to '.$location['name'].'.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->addError('apiKey', $e instanceof Failure ? $e->getMessage() : 'Connection could not be saved. Reload and try again.');
        } finally {
            $this->apiKey = '';
        }
    }

    public function pause(): void
    {
        $this->changeConnection(false);
        session()->flash('highlevel-status', 'HighLevel delivery paused. New leads remain pending in the website inbox.');
    }

    public function disconnect(): void
    {
        $this->changeConnection(true);
        session()->flash('highlevel-status', 'HighLevel key removed. Add a key to resume delivery to this location.');
    }

    public function render(): View
    {
        return view('livewire.admin.high-level-connection', ['connection' => Connection::current()]);
    }

    private function assertTarget(?Connection $connection): void
    {
        if (($connection?->revision ?? 0) !== $this->revision) {
            throw ValidationException::withMessages(['locationId' => 'Connection changed in another tab. Reload before saving.']);
        }
        if ($connection && $connection->location_id !== $this->locationId && HighLevelDelivery::query()->exists()) {
            throw ValidationException::withMessages(['locationId' => 'Saved deliveries belong to this location. Changing accounts requires a separate migration.']);
        }
    }

    private function changeConnection(bool $forgetKey): void
    {
        DB::transaction(function () use ($forgetKey): void {
            $connection = Connection::query()->lockForUpdate()->find(1);
            if (! $connection) {
                return;
            }
            if ($connection->revision !== $this->revision) {
                throw ValidationException::withMessages(['locationId' => 'Connection changed in another tab. Reload before saving.']);
            }
            $connection->enabled = false;
            if ($forgetKey) {
                $connection->token = null;
            }
            $connection->revision++;
            $connection->save();
            $this->revision = $connection->revision;
        });
        $this->apiKey = '';
    }

    private function rateLimit(): void
    {
        $key = 'highlevel-connect:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['apiKey' => 'Several checks were made recently. Try again in a few minutes.']);
        }
        RateLimiter::hit($key, 300);
    }
}
