import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import inertia from '@inertiajs/vite';

// `@khattakbits/ui` ab npm dependency hai (git tag v1.0.0) — yani clean runner par
// sibling folder ki zaroorat nahi. Local dev mein live edits ke liye override:
//
//   KB_UI_PATH=../khattakbits-ui/src npm run dev
//
// (CSS ke liye `resources/css/app.css` mein sibling `@source` pehle se maujood hai,
// is liye sirf JS alias override karna kaafi hai. CI mein woh path nahi hota aur
// Tailwind usay chupchaap ignore karta hai.)
const uiSrc = process.env.KB_UI_PATH
    ? fileURLToPath(new URL(process.env.KB_UI_PATH, import.meta.url))
    : fileURLToPath(new URL('./node_modules/@khattakbits/ui/src', import.meta.url));

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
        // Package apne peer deps (vue, inertia) apne node_modules se resolve nahi kar
        // sakta — dedupe unhein host ke node_modules se uthata hai, is liye package ke
        // andar node_modules rakhne ki zaroorat nahi.
        dedupe: ['vue', '@inertiajs/vue3', '@tanstack/vue-query', '@tanstack/vue-virtual', 'three'],
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // Alias ka naam wohi (`@core`) rakha gaya hai taake 40 host files ke
            // imports na badlein.
            '@core': uiSrc,
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        // KB_UI_PATH ke saath package project root se bahar rehta hai — dev server
        // ko wahan se files serve karne ki ijazat chahiye, warna 403 aata hai.
        fs: {
            allow: ['..'],
        },
    },
});
