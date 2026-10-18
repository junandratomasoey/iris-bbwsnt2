<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0F5E6E">
<title>{{ isset($title) ? $title.' — ' : '' }}IRIS BBWS NT II</title>
<meta name="base-url" content="{{ rtrim(url('/'), '/') }}">
<meta name="asset-url" content="{{ rtrim(asset(''), '/') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" href="{{ asset('ikon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
