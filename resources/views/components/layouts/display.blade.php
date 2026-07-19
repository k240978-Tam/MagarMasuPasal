<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Customer Display' }}</title>
    @vite(['resources/css/app.css', 'resources/js/display.js'])
</head>
<body class="h-screen overflow-hidden font-sans antialiased">
    {{ $slot }}
</body>
</html>
