<div class="mx-auto w-full max-w-sm">
    <h1 class="mb-1 text-center font-heading text-3xl font-bold tracking-tight">{{ config('site.name') }}</h1>
    <p class="mb-8 text-center text-sm text-text-muted">Sign in to manage your site.</p>

    <form wire:submit="authenticate" class="admin-card">
        <div class="mb-4">
            <label class="admin-label" for="email">Email</label>
            <input
                id="email"
                type="email"
                class="admin-input"
                autocomplete="username"
                required
                autofocus
                wire:model="email"
            >
            @error('email')
                <p class="admin-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label class="admin-label" for="password">Password</label>
            <input
                id="password"
                type="password"
                class="admin-input"
                autocomplete="current-password"
                required
                wire:model="password"
            >
            @error('password')
                <p class="admin-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="mb-6 flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" wire:model="remember" class="size-4 rounded border-border">
            Keep me signed in
        </label>

        <button type="submit" class="admin-button w-full">
            <span wire:loading.remove wire:target="authenticate">Sign in</span>
            <span wire:loading wire:target="authenticate">Signing in…</span>
        </button>
    </form>
</div>
