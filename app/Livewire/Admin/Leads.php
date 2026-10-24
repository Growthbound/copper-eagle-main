<?php

namespace App\Livewire\Admin;

use App\HighLevel\LeadSync;
use App\Models\HighLevelConnection;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.admin')]
#[Title('Leads')]
class Leads extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public ?int $expanded = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggle(int $leadId): void
    {
        $this->expanded = $this->expanded === $leadId ? null : $leadId;
    }

    public function markAs(int $leadId, string $status): void
    {
        Lead::query()->findOrFail($leadId)->markAs($status);
    }

    public function delete(int $leadId): void
    {
        Lead::query()->findOrFail($leadId)->delete();

        if ($this->expanded === $leadId) {
            $this->expanded = null;
        }
    }

    public function syncHighLevel(int $leadId, string $action = 'retry'): void
    {
        if (! HighLevelConnection::current()?->enabled || ! in_array($action, ['retry', 'check', 'missing-note'], true)) {
            $this->addError('highlevel', 'Connect HighLevel in Lead delivery before sending.');

            return;
        }
        $key = 'highlevel-manual:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('highlevel', 'Several leads were sent recently. Wait a minute before retrying.');

            return;
        }
        RateLimiter::hit($key, 60);
        $sync = app(LeadSync::class);
        $delivery = $sync->enqueue(Lead::query()->findOrFail($leadId));
        match ($action) {
            'check' => $sync->reconcile($delivery->id),
            'missing-note' => $sync->retryMissingNote($delivery->id),
            default => $sync->send($delivery->id, manual: true),
        };
    }

    public function export(): StreamedResponse
    {
        $leads = $this->query()->get();

        return response()->streamDownload(function () use ($leads): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Received', 'Name', 'Email', 'Phone', 'Service', 'Status', 'Message',
                'Consent given', 'Consent at', 'Consent IP', 'Consent wording',
                'Source', 'UTM source', 'UTM medium', 'UTM campaign', 'UTM term', 'UTM content',
                'Google click ID', 'Meta click ID', 'Microsoft click ID', 'Referrer', 'Landing page',
            ]);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->created_at?->toDateTimeString(),
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->service,
                    $lead->status,
                    $lead->message,
                    $lead->consent_given ? 'yes' : 'no',
                    $lead->consent_at?->toDateTimeString(),
                    $lead->consent_ip,
                    $lead->consent_text,
                    $lead->source(),
                    $lead->utm_source,
                    $lead->utm_medium,
                    $lead->utm_campaign,
                    $lead->utm_term,
                    $lead->utm_content,
                    $lead->gclid,
                    $lead->fbclid,
                    $lead->msclkid,
                    $lead->referrer,
                    $lead->landing_page,
                ]);
            }

            fclose($handle);
        }, 'leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        return view('livewire.admin.leads', [
            'leads' => $this->query()->paginate(15),
            'statuses' => Lead::STATUSES,
            'highLevelEnabled' => HighLevelConnection::current()?->enabled ?? false,
        ]);
    }

    private function query()
    {
        return Lead::query()
            ->with('highLevelDelivery')
            ->search($this->search)
            ->status($this->status ?: null)
            ->latest();
    }
}
