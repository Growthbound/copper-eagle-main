@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('site.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeOverrides = App\Support\Theme::cssOverrides())
        <style>{{ $themeOverrides }}</style>
    @endif
</head>
<body class="bg-page text-text antialiased">
    <main class="mx-auto w-full max-w-xl px-5 py-10 sm:py-16">
        <a href="{{ route('home') }}" class="mb-10 inline-block font-heading text-xl font-bold">{{ config('site.name') }}</a>
        {{ $slot }}
    </main>
</body>
</html>
