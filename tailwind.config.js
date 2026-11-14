/** @type {import('tailwindcss').Config} */
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                paper: '#f7f9fc',
                sand: '#edf2f8',
                stone: '#e1e9f2',
                ink: '#142338',
                muted: '#5a6779',
                line: '#d9e2ec',
                brand: { DEFAULT: '#004b89', dark: '#003a6b', soft: '#e0ecf6', tint: '#cfe3f3' },
                accent: '#0095da',
                sun: '#ffe500',
                ok: { DEFAULT: '#367405', soft: '#e8f4da' },
                danger: { DEFAULT: '#b42318', soft: '#fdeceb' },
                lilac: { DEFAULT: '#68527b', soft: '#e0dbe7' },
            },
            fontFamily: {
                sans: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
