<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriHealth | Sistema de Gestión</title>

    <!-- Fuente estilo Apple -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        pastelGreen: '#A8E6CF',
                        accentGreen: '#6BCB9A',
                        softGray: '#F5F7F6',
                        darkBg: '#0F1115',
                        darkCard: '#1C1F26'
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background-color: #F5F7F6;
        }

        .dark body {
            background-color: #0F1115;
        }

        .card-minimal {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .dark .card-minimal {
            background: #1C1F26;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .input-minimal input {
            border-radius: 14px !important;
            border: 1px solid #E5E7EB !important;
            background-color: #FFFFFF !important;
        }

        .dark .input-minimal input {
            background-color: #2A2D35 !important;
            border: 1px solid #3A3D45 !important;
            color: white !important;
        }

        .btn-apple {
            background-color: #6BCB9A;
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .btn-apple:hover {
            background-color: #57B989;
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="h-full flex items-center justify-center px-6">

    <!-- Toggle Dark Mode -->
    <div class="absolute top-6 right-6">
        <button onclick="toggleDark()"
                class="text-sm px-4 py-2 rounded-full bg-white shadow-sm hover:shadow-md transition dark:bg-darkCard dark:text-white">
            🌙
        </button>
    </div>

    <div class="w-full max-w-md fade-in">

        <!-- Branding minimal -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-pastelGreen flex items-center justify-center text-2xl">
                🥗
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                NutriHealth
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Gestión inteligente de dietas
            </p>
        </div>

        <!-- Card -->
        <div class="card-minimal p-8">

            <x-validation-errors class="mb-4 text-sm text-red-500" />

            @if (session('status'))
                <div class="mb-4 text-sm text-accentGreen">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div class="input-minimal">
                    <x-label for="email" value="Correo electrónico" class="text-sm text-gray-600 dark:text-gray-300" />
                    <x-input id="email"
                             class="block mt-2 w-full px-4 py-3"
                             type="email"
                             name="email"
                             :value="old('email')"
                             required autofocus />
                </div>

                <div class="input-minimal">
                    <x-label for="password" value="Contraseña" class="text-sm text-gray-600 dark:text-gray-300" />
                    <x-input id="password"
                             class="block mt-2 w-full px-4 py-3"
                             type="password"
                             name="password"
                             required />
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center text-gray-500 dark:text-gray-400">
                        <x-checkbox name="remember" class="mr-2" />
                        Recordarme
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-accentGreen hover:underline">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>

                <button type="submit"
                        class="w-full py-3 text-white font-medium btn-apple">
                    Iniciar sesión
                </button>
            </form>
        </div>

        <div class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500">
            © {{ date('Y') }} NutriHealth
        </div>
    </div>

    <script>
        function toggleDark() {
            document.documentElement.classList.toggle('dark');
        }

        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>

</body>
</html>