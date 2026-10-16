<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />


    <!-- new style -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/styleTree.css') }}" class="css">
    {{-- ?v= con la fecha del archivo: al cambiar el CSS, el navegador no usa la copia en caché --}}
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar.css') }}?v={{ filemtime(public_path('assets/css/sidebar.css')) }}">


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Styles -->
    @livewireStyles
    <script src="{{ asset('assets/js/vendor.min.js') }}"></script>
    <!-- js -->
    <script src="{{ asset('assets/js/app.min.js') }}"></script>


</head>

<body class="font-sans antialiased">


    @include('../navigation')

    <!-- Page Content -->
    <main class="main">
        {{ $slot }}
    </main>

    @stack('modals')

    @livewireScripts
</body>

</html>