import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                // GCU palette — school red leads, pink/brown are accents.
                star: { DEFAULT: '#A52A2A', 50: '#FBF1F1', 100: '#F5DADA', 200: '#E8B0B0', 600: '#A52A2A', 700: '#8A2222', 800: '#6E1B1B', 900: '#4F1414' },
                pink: { DEFAULT: '#E8A0B0', soft: '#F6DCE2' },
                brown: { DEFAULT: '#6B4423', soft: '#C9AE94', dark: '#4A2F18' },
                milk: '#F5F0E8',
                ink: '#1A1210',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
                serif: ['"Source Serif 4"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
        },
    },
    plugins: [forms],
};
