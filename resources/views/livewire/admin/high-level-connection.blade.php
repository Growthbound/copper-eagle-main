<div class="admin-card mb-8">
    <h2 class="mb-1 font-heading text-xl font-bold">GoHighLevel</h2>
    <p class="mb-4 text-sm text-text-muted">
        Send website enquiries directly to your HighLevel contacts with the Website Lead tag.
        Each contact receives the captured source and a note with the request, referral URL, and campaign details.
    </p>

    @if (session('highlevel-status'))
        <p class="mb-4 rounded-lg bg-primary/10 p-3 text-sm" role="status">{{ session('highlevel-status') }}</p>
    @endif

    @if ($connection)
        <p class="mb-2 break-words text-sm font-semibold">
            {{ $connection->location_name }}
            <span class="admin-pill">{{ $connection->enabled ? 'connected' : 'paused' }}</span>
        </p>
        <p class="mb-4 break-all font-mono text-xs text-text-muted">{{ $connection->location_id }}</p>
    @endif

    <form wire:submit="connect">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="admin-label" for="highlevel-location">HighLevel location ID</label>
                <input id="highlevel-location" class="admin-input" type="text" wire:model="locationId" autocomplete="off">
                @error('locationId') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="highlevel-key">Private Integration token</label>
                <input id="highlevel-key" class="admin-input" type="password" wire:model="apiKey" autocomplete="new-password">
                <p class="mt-2 text-xs text-text-muted">{{ $connection ? 'Leave blank to check the saved key, or paste a replacement.' : 'Create a Private Integration in this HighLevel sub-account.' }} The saved key is encrypted and never displayed.</p>
                @error('apiKey') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="mt-4 text-sm text-text-muted">Required permissions: read locations and read and write contacts (including notes and tags). Connecting checks the location without creating a test contact.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button class="admin-button" type="submit" wire:loading.attr="disabled" wire:target="connect">{{ $connection ? 'Check and reconnect' : 'Connect HighLevel' }}</button>
            @if ($connection?->enabled)
                <button class="admin-button admin-button-quiet" type="button" wire:click="pause" wire:loading.attr="disabled">Pause delivery</button>
            @endif
            @if ($connection)
                <button class="admin-button admin-button-quiet" type="button" wire:click="disconnect" wire:confirm="Remove the saved API key and pause HighLevel delivery?" wire:loading.attr="disabled">Remove key</button>
            @endif
        </div>
    </form>
    <p class="mt-4 text-sm text-text-muted">Delivery results and retry controls appear beside each lead in the <a class="underline" href="{{ route('admin.leads') }}" wire:navigate>website inbox</a>. Existing leads are sent individually from there.</p>
</div>
