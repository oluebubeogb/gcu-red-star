<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#A52A2A">
        @php
            $meta = array_merge([
                'title' => 'The Red Star — '.config('redstar.tagline'),
                'description' => 'The Red Star — the voice of Government College Umuahia. Digital editions, archive and community.',
                'url' => url()->current(), 'type' => 'website',
            ], array_filter($meta ?? []));
            $meta['image'] = $meta['image'] ?? asset('gcu-badge.png');
        @endphp
        <meta name="description" content="{{ $meta['description'] }}">
        <link rel="canonical" href="{{ $meta['url'] }}">
        <meta property="og:site_name" content="The Red Star">
        <meta property="og:type" content="{{ $meta['type'] }}">
        <meta property="og:title" content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        <meta property="og:url" content="{{ $meta['url'] }}">
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" type="image/png" href="/gcu-badge.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <title inertia>{{ config('app.name', 'The Red Star') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        {{-- Non-blocking fonts: text renders immediately in system fonts on slow connections. --}}
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700,800,700i|source-serif-4:400,600,400i&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700,800,700i|source-serif-4:400,600,400i&display=swap" rel="stylesheet" /></noscript>
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="bg-milk font-sans text-ink antialiased">
        @inertia
    </body>
</html>
