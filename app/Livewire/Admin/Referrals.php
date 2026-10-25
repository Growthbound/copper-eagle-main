<?php

namespace App\Livewire\Admin;

use App\Models\Referral;
use App\Services\Referrals\HighLevelClient;
use App\Services\Referrals\ReferralCards;
use App\Services\Referrals\ReferralSync;
use App\Services\Referrals\TeamDirectory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.admin')]
#[Title('Referrals')]
class Referrals extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $batch = '';

    public $quantity = 5;

    public array $contactIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedBatch(): void
    {
        $this->resetPage();
    }

    public function generate(ReferralCards $cards): void
    {
        $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $this->batch = $cards->generate((int) $this->quantity);
        $this->search = '';
        $this->status = '';
        $this->resetPage();
        session()->flash('status', $this->quantity.' cards created. Download this batch below.');
    }

    public function refreshTeam(TeamDirectory $directory, HighLevelClient $client): void
    {
        try {
            $count = $directory->refresh($client);
            session()->flash('status', 'Team refreshed. '.$count.' contacts are available for registration.');
        } catch (Throwable) {
            $this->addError('integration', 'Team refresh failed. Check the HighLevel connection, location, and user permissions. The previously saved team was kept.');
        }
    }

    public function sync(int $id, ReferralSync $sync): void
    {
        $referral = Referral::whereNotNull('registered_at')->findOrFail($id);
        $ok = $sync->sync($referral);
        session()->flash('status', $ok ? 'Affiliate synced to HighLevel.' : ($referral->fresh()->sync_message ?: 'This sync needs reconciliation before it can continue.'));
    }

    public function reconcile(int $id, ReferralSync $sync): void
    {
        $this->validate(['contactIds.'.$id => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,100}$/D']]);
        $ok = $sync->reconcile(Referral::findOrFail($id), $this->contactIds[$id]);
        if ($ok) {
            unset($this->contactIds[$id]);
            session()->flash('status', 'Existing HighLevel contact verified and linked. No duplicate was created.');
        } else {
            $this->addError('contactIds.'.$id, 'Could not verify this contact. Its location and referral-code field must match this registration.');
        }
    }

    public function render(): View
    {
        $query = Referral::query();
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('business_name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('main_contact_name', 'like', $term));
        }
        match ($this->status) {
            'unused' => $query->whereNull('registered_at'),
            'registered' => $query->whereNotNull('registered_at'),
            'attention' => $query->whereNotNull('registered_at')->where('sync_status', '!=', 'synced'),
            default => null,
        };
        if ($this->batch !== '') {
            $query->where('batch_id', $this->batch);
        }

        return view('livewire.admin.referrals', [
            'referrals' => $query->latest('id')->paginate(12),
            'batches' => Referral::whereNotNull('batch_id')->selectRaw('batch_id, MIN(created_at) as created_at, COUNT(*) as total')->groupBy('batch_id')->orderByDesc('created_at')->limit(30)->get(),
            'registeredCount' => Referral::whereNotNull('registered_at')->count(),
            'unusedCount' => Referral::whereNull('registered_at')->count(),
            'visitCount' => Referral::sum('visits'),
            'team' => app(TeamDirectory::class)->available(),
            'configurationProblem' => app(ReferralSync::class)->configurationProblem(),
        ]);
    }
}
