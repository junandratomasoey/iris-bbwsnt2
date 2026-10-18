<!DOCTYPE html>
<html lang="id">
<head>@include('layouts.head')</head>
<body class="min-h-screen">
    {{ $slot }}
    @stack('scripts')
</body>
</html>
