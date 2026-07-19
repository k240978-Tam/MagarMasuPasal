<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'POS' }} · {{ config('app.name') }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/pos.js'])
</head>
<body class="h-screen overflow-hidden bg-bg text-ink antialiased" x-data="{ theme: localStorage.getItem('theme') || 'light' }" x-init="$watch('theme', v => { localStorage.setItem('theme', v); document.documentElement.setAttribute('data-theme', v); })">
    {{ $slot }}
</body>
</html>
