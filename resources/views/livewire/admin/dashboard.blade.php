<div>
    <h1 class="mb-8 font-heading text-3xl font-bold tracking-tight">Dashboard</h1>

    <div class="mb-10 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['New leads', $newLeads],
            ['Total leads', $totalLeads],
            ['Images', $mediaCount],
        ] as [$label, $value])
            <div class="admin-card">
                <p class="text-sm font-semibold text-text-muted">{{ $label }}</p>
                <p class="mt-2 font-heading text-4xl font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="admin-card">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-heading text-xl font-bold">Latest requests</h2>
            <a href="{{ route('admin.leads') }}" wire:navigate class="text-sm font-semibold underline underline-offset-4">
                View all
            </a>
        </div>

        @forelse ($recentLeads as $lead)
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border py-3 first:border-t-0">
                <div>
                    <p class="font-semibold">{{ $lead->name }}</p>
                    <p class="text-sm text-text-muted">{{ $lead->email ?: $lead->phone }}</p>
                </div>
                <p class="text-sm text-text-muted">{{ $lead->created_at?->diffForHumans() }}</p>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-text-muted">No requests yet. They'll appear here as soon as someone submits the form.</p>
        @endforelse
    </div>
</div>
