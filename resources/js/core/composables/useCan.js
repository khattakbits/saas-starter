import { usePage } from '@inertiajs/vue3'

export function useCan() {
    const page = usePage()
    return permission => {
        const auth = page.props.auth
        return !!auth && (auth.super || auth.permissions.includes(permission))
    }
}