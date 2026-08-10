import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                orbit: {
                    50: '#effbff',
                    100: '#d8f4ff',
                    200: '#b9ecff',
                    300: '#89e0ff',
                    400: '#52cbff',
                    500: '#2eb0fb',
                    600: '#178fdf',
                    700: '#1672b4',
                    800: '#195f91',
                    900: '#1a5077',
                    950: '#0d3048',
                },
            },
        },
    },
    plugins: [forms],
};
