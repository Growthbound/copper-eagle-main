<?php

namespace App\Livewire\Admin;

use App\Jobs\SendWebhook;
use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\LeadNotifications;
use App\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.admin')]
#[Title('Lead delivery')]
class Webhooks extends Component
{
    public string $name = '';

    public string $url = '';

    public ?int $editing = null;

    public ?int $inspecting = null;

    public bool $notifyByEmail = true;

    public string $notificationRecipients = '';

    public function mount(): void
    {
        $this->notifyByEmail = LeadNotifications::enabled();
        $this->notificationRecipients = implode("\n", LeadNotifications::recipients());
    }

    public function saveNotifications(): void
    {
        $this->validate([
            'notificationRecipients' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, callable $fail): void {
                    $addresses = array_filter(array_map('trim', preg_split('/[\r\n,]+/', trim((string) $value)) ?: []));

                    foreach ($addresses as $address) {
                        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                            $fail('"'.$address.'" is not a valid email address.');
                        }
                    }

                    if (count($addresses) > LeadNotifications::MAX_RECIPIENTS) {
                        $fail('Notify at most '.LeadNotifications::MAX_RECIPIENTS.' addresses. '
                            .'Use a distribution list on your own mail system if more people need these.');
                    }
                },
            ],
        ]);

        LeadNotifications::save($this->notifyByEmail, $this->notificationRecipients);

        $this->notificationRecipients = implode("\n", LeadNotifications::recipients());

        session()->flash('status', 'Notification settings saved.');
    }

    public function sendTestEmail(): void
    {
        if (LeadNotifications::recipients() === []) {
            $this->addError('notificationRecipients', 'Add an address to send a test to.');

            return;
        }

        // The only place a signed-in owner can send mail on demand. Sending
        // uses credentials shared across every site built from this template,
        // so an unbounded button here would let one site burn the sending
        // reputation of all of them.
        $key = 'lead-notification-test:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 3)) {
            $this->addError('notificationRecipients', 'Several tests sent already. Try again in '
                .ceil(RateLimiter::availableIn($key) / 60).' minute(s).');

            return;
        }

        RateLimiter::hit($key, decaySeconds: 600);

        $lead = new Lead([
            'name' => 'Test Submission',
            'email' => 'test@example.com',
            'phone' => '555 000 0000',
            'service' => 'Example Service',
            'message' => 'This is a test notification from the admin.',
        ]);
        $lead->id = 0;
        $lead->created_at = now();

        try {
            foreach (LeadNotifications::recipients() as $recipient) {
                Mail::to($recipient)->send(new LeadReceived($lead));
            }
        } catch (Throwable $e) {
            // The whole point of this button is to fail here rather than on a
            // real enquiry, so the mail server's own words are worth more than
            // a tidy message: they name the wrong port, the refused
            // credentials, or the unverified sender.
            $this->addError('notificationRecipients', 'The mail server rejected it: '.$e->getMessage());

            return;
        }

        session()->flash('status', LeadNotifications::isPretending()
            ? 'Test written to the application log. No mail service is configured, so nothing was actually delivered.'
            : 'Test sent to '.implode(', ', LeadNotifications::recipients()).'.');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:80',
            'url' => 'required|url|max:500|starts_with:https://,http://',
        ], [
            'url.starts_with' => 'The address must begin with https:// or http://.',
            'url.url' => 'Enter a full address, such as https://example.com/hooks/leads.',
        ]);

        if ($this->editing !== null) {
            Webhook::query()->findOrFail($this->editing)->update([
                'name' => $this->name,
                'url' => $this->url,
            ]);

            session()->flash('status', 'Destination updated.');
        } else {
            Webhook::query()->create([
                'name' => $this->name,
                'url' => $this->url,
                'is_active' => true,
            ]);

            session()->flash('status', 'Destination added. Copy its signing secret into the receiving system.');
        }

        $this->reset(['name', 'url', 'editing']);
    }

    public function edit(int $webhookId): void
    {
        $webhook = Webhook::query()->findOrFail($webhookId);

        $this->editing = $webhook->id;
        $this->name = $webhook->name;
        $this->url = $webhook->url;
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'url', 'editing']);
    }

    public function toggle(int $webhookId): void
    {
        $webhook = Webhook::query()->findOrFail($webhookId);

        $webhook->update(['is_active' => ! $webhook->is_active]);
    }

    public function delete(int $webhookId): void
    {
        Webhook::query()->findOrFail($webhookId)->delete();

        session()->flash('status', 'Destination removed.');
    }

    public function rotateSecret(int $webhookId): void
    {
        Webhook::query()->findOrFail($webhookId)->update(['secret' => Webhook::newSecret()]);

        session()->flash('status', 'Signing secret replaced. Update the receiving system or deliveries will be rejected.');
    }

    /**
     * Sends a representative payload so the receiving end can be wired up
     * before a real enquiry arrives.
     */
    public function sendTest(int $webhookId): void
    {
        $webhook = Webhook::query()->findOrFail($webhookId);

        SendWebhook::dispatchSync($webhook->id, 'lead.test', [
            'id' => 0,
            'name' => 'Test Submission',
            'email' => 'test@example.com',
            'phone' => '555 000 0000',
            'service' => 'Example Service',
            'message' => 'This is a test delivery from the admin.',
            'status' => 'new',
            'received_at' => now()->toIso8601String(),
        ]);

        session()->flash('status', 'Test sent. The result is in the delivery history below.');
    }

    public function redeliver(int $deliveryId): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($deliveryId);

        SendWebhook::dispatchSync($delivery->webhook_id, $delivery->event, $delivery->payload);

        session()->flash('status', 'Sent again.');
    }

    public function inspect(int $webhookId): void
    {
        $this->inspecting = $this->inspecting === $webhookId ? null : $webhookId;
    }

    public function render(): View
    {
        return view('livewire.admin.webhooks', [
            'webhooks' => Webhook::query()->latest()->get(),
            'deliveries' => WebhookDelivery::query()
                ->with('webhook')
                ->latest()
                ->limit(20)
                ->get(),
            'events' => [WebhookDispatcher::LEAD_CREATED],
            'mailProblem' => LeadNotifications::problem(),
        ]);
    }
}
