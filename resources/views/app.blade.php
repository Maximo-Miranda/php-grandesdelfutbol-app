<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: hsl(0 0% 100%);
            }

            html.dark {
                background-color: hsl(222 47% 11%);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="Grandes del Futbol te ayuda a organizar partidos de cancha sintetica con tus amigos. Controla asistencia, arma equipos y lleva estadisticas de cada jugador.">
        <link rel="canonical" href="{{ url()->current() }}">

        <meta property="og:site_name" content="Grandes del Fútbol">
        <meta property="og:title" content="Grandes del Fútbol">
        <meta property="og:description" content="App gratis para organizar partidos de fútbol con amigos. Confirma asistencia, arma equipos, lleva estadísticas y comparte reels.">
        <meta property="og:url" content="{{ config('app.url') }}">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/pwa-512x512.png">
        <meta property="og:image:width" content="512">
        <meta property="og:image:height" content="512">
        <meta property="og:locale" content="es_CO">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Grandes del Fútbol">
        <meta name="twitter:description" content="App gratis para organizar partidos de fútbol con amigos.">
        <meta name="twitter:image" content="{{ rtrim(config('app.url'), '/') }}/pwa-512x512.png">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="manifest" href="/build/manifest.webmanifest">
        <meta name="theme-color" content="#16a34a">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Grandes del Futbol">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=public-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
