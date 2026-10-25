<!DOCTYPE html>
<html lang="en" class="bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} | {{ config('site.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-surface text-text antialiased">
    @auth
        <header class="border-b border-border bg-page">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="font-heading text-lg font-bold tracking-tight">
                    {{ config('site.name') }}
                </a>

                <nav class="flex flex-wrap items-center gap-1 text-sm font-semibold">
                    @foreach ([
                        ['admin.dashboard', 'Dashboard'],
                        ['admin.leads', 'Leads'],
                        ['admin.referrals', 'Referrals'],
                        ['admin.content.index', 'Content'],
                        ['admin.media', 'Media'],
                        ['admin.site-structure', 'Navigation'],
                        ['admin.redirects', 'Redirects'],
                        ['admin.keys', 'API keys'],
                        ['admin.theme', 'Theme'],
                        ['admin.details', 'Site details'],
                        ['admin.tracking', 'Tracking'],
                        ['admin.announcement', 'Announcement'],
                        ['admin.webhooks', 'Lead delivery'],
                    ] as [$route, $label])
                        <a
                            href="{{ route($route) }}"
                            wire:navigate
                            @class([
                                'rounded-lg px-3 py-2 transition hover:bg-surface',
                                'bg-primary text-on-primary hover:bg-primary' => request()->routeIs($route),
                            ])
                        >{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-4 text-sm">
                    <a href="{{ route('home') }}" class="font-semibold text-text-muted underline-offset-4 hover:text-text hover:underline">
                        View site
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 font-semibold text-text-muted transition hover:bg-surface hover:text-text">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto w-full max-w-6xl px-5 py-10">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
