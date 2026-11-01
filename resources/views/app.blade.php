<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#A52A2A">
        <meta name="description" content="The Red Star — the voice of Government College Umuahia. Digital editions, archive and community.">
        <link rel="icon" type="image/png" href="/gcu-badge.png">
        <title inertia>{{ config('app.name', 'The Red Star') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700,800,700i|source-serif-4:400,600,400i&display=swap" rel="stylesheet" />
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="bg-milk font-sans text-ink antialiased">
        @inertia
    </body>
</html>
