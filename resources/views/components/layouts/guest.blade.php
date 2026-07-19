<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg text-ink antialiased" x-data>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 flex flex-col items-center gap-2">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-lg font-bold text-accent-ink">M</span>
            <span class="text-sm font-semibold text-ink-soft">{{ config('app.name') }}</span>
        </div>

        <div class="w-full max-w-sm rounded-2xl border border-line bg-surface p-8 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
