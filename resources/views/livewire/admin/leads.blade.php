<div wire:poll.15s>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-heading text-3xl font-bold tracking-tight">Leads</h1>
        <button type="button" class="admin-button admin-button-quiet" wire:click="export">
            Export CSV
        </button>
    </div>

    @error('highlevel') <p class="admin-error mb-4">{{ $message }}</p> @enderror

    <div class="admin-card mb-6 flex flex-wrap items-end gap-4">
        <div class="min-w-56 flex-1">
            <label class="admin-label" for="lead-search">Search</label>
            <input id="lead-search" type="search" class="admin-input" placeholder="Name, email, phone, or message" wire:model.live.debounce.300ms="search">
        </div>

        <div>
            <label class="admin-label" for="lead-status">Status</label>
            <select id="lead-status" class="admin-input" wire:model.live="status">
                <option value="">All</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-card">
        @forelse ($leads as $lead)
            <div class="border-t border-border py-4 first:border-t-0 first:pt-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $lead->name }}</p>
                            <span @class([
                                'admin-pill',
                                'bg-accent/20 text-accent-on-page' => $lead->status === 'new',
                                'bg-primary/15 text-primary' => $lead->status === 'handled',
                                'bg-text/10 text-text-muted' => $lead->status === 'archived',
                            ])>{{ $lead->status }}</span>
                        </div>

                        <p class="mt-1 text-sm text-text-muted">
                            @if ($lead->email)
                                <a class="underline underline-offset-2" href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>
                            @endif
                            @if ($lead->email && $lead->phone) · @endif
                            @if ($lead->phone)
                                <a class="underline underline-offset-2" href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a>
                            @endif
                        </p>
                        @if ($lead->highLevelDelivery)
                            <p class="mt-2 text-sm">HighLevel: <span class="admin-pill">{{ str_replace('_', ' ', $lead->highLevelDelivery->status) }}</span></p>
                            @if ($lead->highLevelDelivery->error)
                                <p class="mt-1 max-w-prose text-sm text-danger">{{ $lead->highLevelDelivery->error }}</p>
                            @endif
                        @endif

                        <p class="mt-1 text-sm text-text-muted">
                            {{ $lead->service ?: 'No service selected' }} · {{ $lead->created_at?->format('M j, Y g:ia') }}
                        </p>

                        <p class="mt-1 text-sm">
                            <span class="text-text-muted">Came from</span>
                            <span class="font-semibold">{{ $lead->source() }}</span>
                            @if ($lead->utm_campaign)
                                <span class="text-text-muted">·</span>
                                <span class="font-mono text-xs">{{ $lead->utm_campaign }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($highLevelEnabled && $lead->highLevelDelivery?->status !== 'synced')
                            @if ($lead->highLevelDelivery?->status === 'needs_review')
                                <button type="button" class="admin-button admin-button-quiet" wire:click="syncHighLevel({{ $lead->id }}, 'check')" wire:loading.attr="disabled">Check HighLevel note</button>
                                <button type="button" class="admin-button admin-button-quiet" wire:click="syncHighLevel({{ $lead->id }}, 'missing-note')" wire:confirm="First inspect this contact in HighLevel. Only continue if its website submission note is missing. This can create a duplicate note if the original already exists." wire:loading.attr="disabled">Retry missing note</button>
                            @else
                                <button type="button" class="admin-button admin-button-quiet" wire:click="syncHighLevel({{ $lead->id }})" wire:confirm="Send this saved enquiry to the connected HighLevel location with the Website Lead tag?" wire:loading.attr="disabled">{{ $lead->highLevelDelivery ? 'Retry HighLevel' : 'Send to HighLevel' }}</button>
                            @endif
                        @endif
                        <button type="button" class="admin-button admin-button-quiet" wire:click="toggle({{ $lead->id }})">
                            {{ $expanded === $lead->id ? 'Hide' : 'Show' }} details
                        </button>

                        @if ($lead->status !== 'handled')
                            <button type="button" class="admin-button admin-button-quiet" wire:click="markAs({{ $lead->id }}, 'handled')">
                                Mark handled
                            </button>
                        @endif

                        @if ($lead->status !== 'archived')
                            <button type="button" class="admin-button admin-button-quiet" wire:click="markAs({{ $lead->id }}, 'archived')">
                                Archive
                            </button>
                        @endif

                        <button
                            type="button"
                            class="admin-button admin-button-quiet"
                            wire:click="delete({{ $lead->id }})"
                            wire:confirm="Permanently delete this lead?"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                @if ($expanded === $lead->id)
                    @if ($lead->message)
                        <p class="mt-3 whitespace-pre-line rounded-lg bg-surface p-4 text-sm">{{ $lead->message }}</p>
                    @endif

                    @if (filled($lead->consent_text))
                        <div class="mt-3 rounded-lg bg-surface p-4 text-sm">
                            <p class="text-xs font-semibold text-text-muted">
                                Contact permission — {{ $lead->consent_given ? 'agreed' : 'not agreed' }}
                                @if ($lead->consent_at)
                                    on {{ $lead->consent_at->format('M j, Y \a\t g:ia') }}
                                @endif
                                @if ($lead->consent_ip)
                                    from {{ $lead->consent_ip }}
                                @endif
                            </p>
                            <p class="mt-1 italic">“{{ $lead->consent_text }}”</p>
                        </div>
                    @endif

                    <dl class="mt-3 grid gap-x-6 gap-y-2 rounded-lg bg-surface p-4 text-sm sm:grid-cols-2">
                        @foreach ([
                            'Campaign' => $lead->utm_campaign,
                            'Source' => $lead->utm_source,
                            'Medium' => $lead->utm_medium,
                            'Term' => $lead->utm_term,
                            'Content' => $lead->utm_content,
                            'Google click ID' => $lead->gclid,
                            'Meta click ID' => $lead->fbclid,
                            'Microsoft click ID' => $lead->msclkid,
                            'Referrer' => $lead->referrer,
                            'Landed on' => $lead->landing_page,
                        ] as $label => $value)
                            @if (filled($value))
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold text-text-muted">{{ $label }}</dt>
                                    <dd class="truncate font-mono text-xs" title="{{ $value }}">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach

                        @unless (collect($lead->attribution())->filter()->except('landing_page')->isNotEmpty())
                            <p class="text-text-muted sm:col-span-2">
                                No campaign details — this visitor arrived without any tracking parameters.
                            </p>
                        @endunless
                    </dl>
                @endif
            </div>
        @empty
            <p class="py-8 text-center text-sm text-text-muted">No leads match this view.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $leads->links() }}
    </div>
</div>
