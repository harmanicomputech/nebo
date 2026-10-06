<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('nebo.brand.name') }}</title>
<meta name="theme-color" content="#1A1A1A">
<meta name="application-name" content="{{ config('nebo.brand.name') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Nebo Stage">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
@vite(['resources/css/app.css', 'resources/js/app.js'])
