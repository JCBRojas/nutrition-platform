import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Los componentes de Jetstream usan indigo: se reemplaza por los violetas de la paleta NUT-CMP-001
            colors: {
                indigo: {
                    50: '#F4F2FD',
                    100: '#E8E5FB',
                    200: '#D3CDF6',
                    300: '#C9C2FA',
                    400: '#A79DF0',
                    500: '#6C5DD3',
                    600: '#5B4CC4',
                    700: '#4A3BB0',
                    800: '#3A2E8C',
                    900: '#2B2268',
                    950: '#1C1B33',
                },
            },
        },
    },

    plugins: [forms, typography],
};
