import '@fontsource-variable/inter'
import { createInertiaApp } from '@inertiajs/vue3'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'

createInertiaApp({
    withApp(app) {
        app.use(VueQueryPlugin, {
            queryClient: new QueryClient({
                defaultOptions: { queries: { refetchOnWindowFocus: false } },
            }),
        })
    },
})