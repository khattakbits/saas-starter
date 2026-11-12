import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import inertia from '@inertiajs/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        inertia(),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        // Package project root se bahar hai, is liye uski files apne upar wale
        // folders mein node_modules dhoondti hain aur peer deps miss ho jate hain.
        // dedupe unhein host ke node_modules se resolve karta hai — package ke
        // andar node_modules rakhne ki zaroorat nahi.
        dedupe: ['vue', '@inertiajs/vue3', '@tanstack/vue-query', '@tanstack/vue-virtual', 'three'],
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // @core ab package se aata hai (khattakbits-ui). Alias ka naam wohi
            // rakha gaya hai taake 40 host files ke imports na badlein.
            '@core': fileURLToPath(new URL('../khattakbits-ui/src', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        // Package project root se bahar rehta hai — dev server ko wahan se
        // files serve karne ki ijazat chahiye, warna 403 aata hai.
        fs: {
            allow: ['..'],
        },
    },
});