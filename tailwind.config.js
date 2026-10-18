import forms from '@tailwindcss/forms';
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    // kelas status dibentuk dinamis di Blade (chip-{status}), jadi wajib di-safelist
    safelist: ['chip-aman', 'chip-waspada', 'chip-siaga', 'chip-kritis', 'chip-kosong'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
                gauge: ['"Barlow Semi Condensed"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                basalt: { DEFAULT: '#1E2A2F', soft: '#4A585E', mute: '#7C898E' },
                savana: { DEFAULT: '#F3F5F2', line: '#DDE3DE', deep: '#E7ECE8' },
                saluran: { 50: '#E8F3F4', 100: '#CDE6E9', 500: '#16798C', 600: '#0F5E6E', 700: '#0B4955', 800: '#083640' },
                status: { aman: '#4E8A2E', waspada: '#D4A017', siaga: '#D9622B', kritis: '#B3261E', kosong: '#9AA4A8' },
            },
        },
    },
    plugins: [forms],
};
