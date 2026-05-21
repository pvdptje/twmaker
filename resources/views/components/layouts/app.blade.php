<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Tailwind Template Builder' }}</title>
        @unless (app()->environment('testing'))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endunless
        <style>[x-cloak] { display: none !important; }</style>
        @livewireStyles
    </head>
    <body class="min-h-screen bg-neutral-950 font-sans text-neutral-100 antialiased">
        {{ $slot }}
        <script src="/builder-canvas.js?v={{ filemtime(public_path('builder-canvas.js')) }}" defer></script>
        @livewireScripts
    </body>
</html>
