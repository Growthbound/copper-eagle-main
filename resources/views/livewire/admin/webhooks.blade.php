<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">Lead delivery</h1>
    <p class="mb-6 text-sm text-text-muted">
        Where enquiries go the moment they arrive: an email to you, and any other system you want them sent to.
    </p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <livewire:admin.high-level-connection />

    <form wire:submit="saveNotifications" class="admin-card mb-8">
        <h2 class="mb-1 font-heading text-xl font-bold">Email notifications</h2>
        <p class="mb-5 text-sm text-text-muted">
            The safety net. Even if another system is misconfigured, someone still hears about the enquiry.
        </p>

        @if ($mailProblem)
            <p class="mb-5 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $mailProblem }}</p>
        @endif

        <label class="mb-5 flex items-center gap-3 text-sm font-semibold">
            <input type="checkbox" class="size-4 rounded border-border" wire:model="notifyByEmail">
            Email me when an enquiry arrives
        </label>

        <div>
            <label class="admin-label" for="notification-recipients">Send to</label>
            <p class="mb-2 text-sm text-text-muted">
                One address per line. Leave blank to use the site's contact address from Site details.
            </p>
            <textarea
                id="notification-recipients"
                rows="3"
                class="admin-input"
                placeholder="owner@example.com"
                wire:model="notificationRecipients"
            ></textarea>
            @error('notificationRecipients')
                <p class="admin-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="admin-button">Save notifications</button>
            <button type="button" class="admin-button admin-button-quiet" wire:click="sendTestEmail">Send test email</button>
        </div>
    </form>

    <form wire:submit="save" class="admin-card mb-8">
        <h2 class="mb-1 font-heading text-xl font-bold">{{ $editing ? 'Edit destination' : 'Send to another system' }}</h2>
        <p class="mb-4 text-sm text-text-muted">
            A CRM, an automation tool, or your own endpoint. Each submission is posted there as it arrives.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="admin-label" for="webhook-name">Name</label>
                <p class="mb-2 text-sm text-text-muted">What this destination is, so you recognise it later.</p>
                <input id="webhook-name" type="text" class="admin-input" placeholder="CRM" wire:model="name">
                @error('name') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="admin-label" for="webhook-url">Address</label>
                <p class="mb-2 text-sm text-text-muted">Where the submission is sent.</p>
                <input id="webhook-url" type="url" class="admin-input font-mono text-xs" placeholder="https://example.com/hooks/leads" wire:model="url">
                @error('url') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="admin-label" for="webhook-authentication">Authentication</label>
                <select id="webhook-authentication" class="admin-input" wire:model.live="authenticationMode">
                    <option value="hmac">Signed payload (HMAC-SHA256)</option>
                    <option value="header">Plain secret in a header (Local Business Pro)</option>
                </select>
                @error('authenticationMode') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="webhook-header">Header name</label>
                <input id="webhook-header" type="text" class="admin-input font-mono text-xs" placeholder="X-Signature" wire:model="headerName" maxlength="80" autocapitalize="off" spellcheck="false">
                @error('headerName') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="webhook-secret">{{ $authenticationMode === 'header' ? 'Receiver secret' : 'Signing secret' }}</label>
                <p id="webhook-secret-help" class="mb-2 text-sm text-text-muted">
                    @if ($authenticationMode === 'header')
                        For Local Business Pro, use X-Signature as the header name and paste its generated secret here.
                        The secret is sent exactly as entered over HTTPS. Leave blank when editing to keep the saved receiver secret.
                    @else
                        Leave blank to {{ $editing ? 'keep the current signing secret' : 'generate a signing secret' }}, or enter a replacement.
                        The header carries a signature calculated from this secret and the payload.
                    @endif
                </p>
                <input id="webhook-secret" type="password" class="admin-input font-mono text-xs" wire:model="secret" autocomplete="new-password" maxlength="255" aria-describedby="webhook-secret-help">
                @error('secret') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="admin-button">{{ $editing ? 'Save changes' : 'Add destination' }}</button>

            @if ($editing)
                <button type="button" class="admin-button admin-button-quiet" wire:click="cancelEdit">Cancel</button>
            @endif
        </div>
    </form>

    <div class="admin-card mb-8">
        <h2 class="mb-4 font-heading text-xl font-bold">Destinations</h2>

        @forelse ($webhooks as $webhook)
            <div class="border-t border-border py-4 first:border-t-0 first:pt-0" wire:key="webhook-{{ $webhook->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $webhook->name }}</p>
                            <span @class([
                                'admin-pill',
                                'bg-primary/15 text-primary' => $webhook->is_active,
                                'bg-text/10 text-text-muted' => ! $webhook->is_active,
                            ])>{{ $webhook->is_active ? 'active' : 'paused' }}</span>
                        </div>

                        <p class="mt-1 truncate font-mono text-xs text-text-muted">{{ $webhook->url }}</p>
                        <p class="mt-1 break-words text-xs text-text-muted">
                            {{ $webhook->authentication_mode === 'header' ? 'Plain secret' : 'HMAC signature' }}
                            in <code>{{ $webhook->header_name }}</code>
                        </p>

                        <p class="mt-1 text-sm text-text-muted">
                            @if ($webhook->last_delivered_at)
                                Last delivered {{ $webhook->last_delivered_at->diffForHumans() }}
                            @else
                                Nothing delivered yet
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="admin-button admin-button-quiet" wire:click="sendTest({{ $webhook->id }})">
                            Send test
                        </button>
                        @if ($webhook->authentication_mode === 'hmac')
                            <button type="button" class="admin-button admin-button-quiet" wire:click="inspect({{ $webhook->id }})">
                                {{ $inspecting === $webhook->id ? 'Hide' : 'Show' }} secret
                            </button>
                        @endif
                        <button type="button" class="admin-button admin-button-quiet" wire:click="edit({{ $webhook->id }})">
                            Edit
                        </button>
                        <button type="button" class="admin-button admin-button-quiet" wire:click="toggle({{ $webhook->id }})">
                            {{ $webhook->is_active ? 'Pause' : 'Resume' }}
                        </button>
                        <button
                            type="button"
                            class="admin-button admin-button-quiet"
                            wire:click="delete({{ $webhook->id }})"
                            wire:confirm="Remove this destination? Submissions will stop being sent there."
                        >
                            Delete
                        </button>
                    </div>
                </div>

                @if ($inspecting === $webhook->id && $webhook->authentication_mode === 'hmac')
                    <div class="mt-4 rounded-lg bg-surface p-4">
                        <label class="admin-label" for="secret-{{ $webhook->id }}">Signing secret</label>
                        <p class="mb-2 text-sm text-text-muted">
                            The receiving system uses this to confirm a request genuinely came from your site.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                id="secret-{{ $webhook->id }}"
                                type="text"
                                class="admin-input flex-1 font-mono text-xs"
                                readonly
                                value="{{ $webhook->secret }}"
                                onfocus="this.select()"
                            >
                            <button
                                type="button"
                                class="admin-button admin-button-quiet shrink-0"
                                wire:click="rotateSecret({{ $webhook->id }})"
                                wire:confirm="Replace this secret? Deliveries will fail until the receiving system is updated."
                            >
                                Replace
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-sm text-text-muted">
                No destinations yet. Add one above and every new submission will be sent there.
            </p>
        @endforelse
    </div>

    <div class="admin-card">
        <h2 class="mb-1 font-heading text-xl font-bold">Recent deliveries</h2>
        <p class="mb-4 text-sm text-text-muted">The last 20 attempts, newest first.</p>

        @forelse ($deliveries as $delivery)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border py-3 first:border-t-0 first:pt-0" wire:key="delivery-{{ $delivery->id }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'admin-pill',
                            'bg-primary/15 text-primary' => $delivery->succeeded,
                            'bg-danger/15 text-danger' => ! $delivery->succeeded,
                        ])>{{ $delivery->succeeded ? 'sent' : 'failed' }}</span>

                        <p class="text-sm font-semibold">{{ $delivery->webhook?->name ?? 'Removed destination' }}</p>
                        <p class="font-mono text-xs text-text-muted">{{ $delivery->event }}</p>
                    </div>

                    <p class="mt-1 text-sm text-text-muted">
                        {{ $delivery->summary() }}
                        @if ($delivery->attempts > 1) after {{ $delivery->attempts }} attempts @endif
                        · {{ $delivery->created_at?->diffForHumans() }}
                    </p>

                    @if ($delivery->error)
                        <p class="mt-1 font-mono text-xs text-danger">{{ $delivery->error }}</p>
                    @endif
                </div>

                @if ($delivery->webhook)
                    <button type="button" class="admin-button admin-button-quiet" wire:click="redeliver({{ $delivery->id }})">
                        Send again
                    </button>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-sm text-text-muted">Nothing sent yet.</p>
        @endforelse
    </div>

    <div class="admin-card mt-8">
        <h2 class="mb-3 font-heading text-xl font-bold">What gets sent</h2>
        <p class="mb-3 text-sm text-text-muted">
            A POST with a JSON body and the authentication header chosen for each destination.
            Signed payloads use an HMAC-SHA256 of the timestamp and body, with the timestamp in
            <code class="font-mono text-xs">{{ App\Models\Webhook::TIMESTAMP_HEADER }}</code>.
            Plain-secret destinations send the receiver's saved secret as the header value.
            Redirects are rejected; use the receiving system's final address.
        </p>

        <pre class="overflow-x-auto rounded-lg bg-surface p-4 font-mono text-xs">{{ json_encode([
    'event' => $events[0],
    'site' => config('site.name'),
    'sent_at' => now()->toIso8601String(),
    'data' => [
        'id' => 12,
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
        'phone' => '555 555 0142',
        'service' => 'General service',
        'message' => 'Requesting a quote for next week.',
        'status' => 'new',
        'received_at' => now()->toIso8601String(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>
