<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0F5E6E">
<title>{{ isset($title) ? $title.' — ' : '' }}IRIS BBWS NT II</title>
<meta name="base-url" content="{{ rtrim(url('/'), '/') }}">
<meta name="asset-url" content="{{ rtrim(asset(''), '/') }}">
<meta name="geojson-versi" content="{{ \App\Support\GeojsonVersi::get() }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="IRIS NT II">
<meta name="application-name" content="IRIS NT II">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
