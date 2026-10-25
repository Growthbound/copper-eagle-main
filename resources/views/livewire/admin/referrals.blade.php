<div>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Referrals</h1>
            <p class="mt-2 text-sm text-text-muted">Create cards, see who's registered, and keep every referral connected.</p>
        </div>
        <button class="admin-button admin-button-quiet" wire:click="refreshTeam" wire:loading.attr="disabled">Refresh HighLevel team</button>
    </div>
    @if (session('status'))<p class="mb-6 rounded-lg bg-primary/10 p-4 text-sm" role="status">{{ session('status') }}</p>@endif
    @error('integration')<p class="admin-error mb-6" role="alert">{{ $message }}</p>@enderror
    @if ($configurationProblem || $team->isEmpty())
        <div class="admin-card mb-6">
            <h2 class="text-lg font-bold">HighLevel setup</h2>
            @if ($configurationProblem)<p class="mt-2 text-sm text-text-muted">{{ $configurationProblem }}</p>@endif
            @if ($team->isEmpty())<p class="mt-2 text-sm text-text-muted">No main contacts are available. Check the HighLevel connection, then refresh the team to enable registration.</p>@endif
        </div>
    @endif
    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        @foreach (['Registered affiliates' => $registeredCount, 'Unused cards' => $unusedCount, 'Link visits' => $visitCount] as $label => $value)
            <div class="admin-card"><p class="text-sm text-text-muted">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p></div>
        @endforeach
    </div>
    <form wire:submit="generate" class="admin-card mb-8">
        <h2 class="mb-2 text-xl font-bold">Create new QR cards</h2>
        <p class="mb-4 text-sm text-text-muted">Each card has a permanent link and a readable code beneath the QR. The first person to register it becomes its affiliate.</p>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="quantity" class="admin-label">Number of cards</label>
                <input id="quantity" type="number" min="1" max="100" wire:model="quantity" class="admin-input" required>
                @error('quantity')<p class="admin-error">{{ $message }}</p>@enderror
            </div>
            <button class="admin-button" type="submit" wire:loading.attr="disabled">Generate QR codes</button>
        </div>
    </form>
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div><label for="search" class="admin-label">Search affiliates or codes</label><input id="search" class="admin-input" wire:model.live.debounce.300ms="search" placeholder="Name, business, contact, or code"></div>
        <div><label for="status" class="admin-label">Show</label><select id="status" class="admin-input" wire:model.live="status"><option value="">All cards</option><option value="registered">Registered affiliates</option><option value="unused">Unused cards</option><option value="attention">Sync needs attention</option></select></div>
        <div><label for="batch" class="admin-label">Card batch</label><select id="batch" class="admin-input" wire:model.live="batch"><option value="">All batches</option>@foreach ($batches as $item)<option value="{{ $item->batch_id }}">{{ $item->created_at->format('M j, g:i A') }} · {{ $item->total }} cards · {{ substr($item->batch_id, 0, 8) }}</option>@endforeach</select></div>
    </div>
    @if ($batch && $referrals->total())
        <a href="{{ route('admin.referrals.batch', ['batch' => $batch]) }}" class="admin-button mb-5">Download batch ZIP</a>
    @endif
    <p class="mb-5 text-xs text-text-muted">Visits include repeat opens and may include link previews. They are not unique people or homeowner form submissions.</p>
    <div class="grid gap-5 lg:grid-cols-2">
        @forelse ($referrals as $referral)
            <article class="admin-card" wire:key="referral-{{ $referral->id }}">
                <div class="flex items-start gap-4">
                    <img class="w-24 shrink-0" loading="lazy" src="{{ route('admin.referrals.card', ['referral' => $referral, 'inline' => 1]) }}" alt="QR card {{ $referral->code }}">
                    <div class="min-w-0">
                        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-text-muted">{{ $referral->registered_at ? 'Registered affiliate' : 'Unused card' }}</p>
                        <h2 class="break-words text-xl font-bold">{{ $referral->name ?: $referral->code }}</h2>
                        @if ($referral->registered_at)
                            <p class="text-sm">{{ $referral->business_name }}</p>
                            <p class="mt-1 text-sm text-text-muted">Main contact: {{ $referral->main_contact_name ?: 'Not recorded' }}</p>
                            @if ($referral->phone)<p class="text-sm text-text-muted">Phone: {{ $referral->phone }}</p>@endif
                            @if ($referral->email)<p class="break-all text-sm text-text-muted">Email: {{ $referral->email }}</p>@endif
                            @if ($referral->service_areas)<p class="text-sm text-text-muted">Service areas: {{ implode(', ', $referral->service_areas) }}</p>@endif
                        @endif
                        <p class="mt-2 break-all font-mono text-xs">{{ $referral->code }}</p>
                    </div>
                </div>
                <dl class="my-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-text-muted">
                    <div><dt class="inline font-semibold">Visits:</dt> <dd class="inline">{{ $referral->visits }}</dd></div>
                    <div><dt class="inline font-semibold">Last visit:</dt> <dd class="inline">{{ $referral->last_visited_at?->format('M j, Y g:i A') ?: 'Never' }}</dd></div>
                    @if ($referral->registered_at)<div><dt class="inline font-semibold">Registered:</dt> <dd class="inline">{{ $referral->registered_at->format('M j, Y g:i A') }}</dd></div>@endif
                </dl>
                <div x-data="{ copied: false }">
                    <label class="sr-only" for="link-{{ $referral->id }}">Referral link {{ $referral->code }}</label>
                    <input id="link-{{ $referral->id }}" x-ref="link" class="admin-input font-mono text-xs" readonly value="{{ $referral->publicUrl() }}" onfocus="this.select()">
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="admin-button admin-button-quiet" x-on:click="navigator.clipboard.writeText($refs.link.value).then(() => copied = true).catch(() => $refs.link.select())" x-text="copied ? 'Copied' : 'Copy link'">Copy link</button>
                        <a class="admin-button admin-button-quiet" href="{{ route('admin.referrals.card', $referral) }}">Download PNG</a>
                    </div>
                </div>
                @if ($referral->registered_at)
                    <div class="mt-5 border-t border-border pt-4">
                        <p class="text-xs font-semibold">HighLevel: {{ ucfirst($referral->sync_status) }}</p>
                        @if ($referral->sync_message)<p class="mt-1 text-xs text-text-muted">{{ $referral->sync_message }}</p>@endif
                        @if (in_array($referral->sync_status, ['pending', 'failed']))
                            <button class="admin-button admin-button-quiet mt-3" wire:click="sync({{ $referral->id }})" wire:loading.attr="disabled">Sync to HighLevel</button>
                        @elseif (in_array($referral->sync_status, ['uncertain', 'syncing']))
                            <p class="my-2 text-xs text-text-muted">If a sync was interrupted, wait two minutes. Find this code in HighLevel, then enter the existing contact ID to verify it without creating a duplicate.</p>
                            <label class="admin-label" for="contact-{{ $referral->id }}">Existing HighLevel contact ID</label>
                            <input id="contact-{{ $referral->id }}" class="admin-input" wire:model="contactIds.{{ $referral->id }}" maxlength="100">
                            @error('contactIds.'.$referral->id)<p class="admin-error">{{ $message }}</p>@enderror
                            <button class="admin-button admin-button-quiet mt-3" wire:click="reconcile({{ $referral->id }})" wire:loading.attr="disabled">Verify existing contact</button>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <p class="admin-card text-sm text-text-muted lg:col-span-2">No cards match these filters. Generate a batch to get started.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $referrals->links() }}</div>
    @if ($team->isNotEmpty())<p class="mt-8 text-xs text-text-muted">Available main contacts: {{ $team->pluck('name')->join(', ') }}. Refresh the team after staff changes in HighLevel.</p>@endif
</div>
