import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // URL gambar di CSS dibuat relatif agar aset tetap benar bila aplikasi dipasang di subfolder
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            return hostType === 'css' ? { relative: true } : undefined;
        },
    },
});
