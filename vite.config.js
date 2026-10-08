import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/shop.css', 'resources/js/shop.js', 'resources/js/card-form.js'],
            refresh: true,
        }),
    ],
});
