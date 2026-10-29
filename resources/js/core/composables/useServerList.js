import { computed, reactive, ref, watch } from 'vue'
import { keepPreviousData, useInfiniteQuery, useQueryClient } from '@tanstack/vue-query'
import { usePage } from '@inertiajs/vue3'

/**
 * Server-driven list ka poora state: search, filters, sort, page size, cursor pagination.
 * Use: const list = useServerList({ endpoint, filters: { status: '' } })
 */
export function useServerList({ endpoint, filters = {}, sort = { key: 'id', dir: 'desc' }, pageSize = null }) {
    const page = usePage()
    const qc = useQueryClient()

    const search = ref('')
    const term = ref('')
    let timer
    watch(search, v => {
        clearTimeout(timer)
        timer = setTimeout(() => (term.value = v.trim()), 300)   // debounce
    })

    const sortState = ref({ ...sort })
    const filterState = reactive({ ...filters })
    const size = ref(pageSize ?? page.props.app?.pageSize ?? 20)

    const query = useInfiniteQuery({
        queryKey: ['list', endpoint, term, sortState, filterState, size],
        initialPageParam: null,
        queryFn: async ({ queryKey, pageParam, signal }) => {
            const [, url, q, s, f, n] = queryKey
            const p = new URLSearchParams({ sort: s.key, dir: s.dir, per_page: String(n) })
            if (q) p.set('q', q)
            for (const [k, v] of Object.entries(f)) if (v) p.set(`f[${k}]`, v)
            if (pageParam) p.set('cursor', pageParam)

            const res = await fetch(`${url}?${p}`, { headers: { Accept: 'application/json' }, signal })
            if (res.status === 401 || res.status === 419) {
                window.location.href = '/login'
                return new Promise(() => { })
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`)
            return res.json()
        },
        getNextPageParam: last => last.meta?.next_cursor ?? undefined,
        staleTime: 0,                       // cache foran dikhao, peeche refresh karo
        placeholderData: keepPreviousData,
    })

    const rows = computed(() => query.data.value?.pages.flatMap(p => p.data) ?? [])
    const total = computed(() => query.data.value?.pages[0]?.total)
    const isFiltered = computed(() => !!term.value || Object.values(filterState).some(Boolean))
    const resetKey = computed(() => JSON.stringify([term.value, sortState.value, filterState, size.value]))

    const loadMore = () => {
        if (query.hasNextPage.value && !query.isFetchingNextPage.value) query.fetchNextPage()
    }
    const refresh = () => qc.invalidateQueries({ queryKey: ['list', endpoint] })
    const clearFilters = () => {
        search.value = ''
        term.value = ''
        Object.keys(filterState).forEach(k => (filterState[k] = ''))
    }

    return reactive({
        rows, total, isFiltered, resetKey,
        loading: query.isPending, error: query.isError,
        loadingMore: query.isFetchingNextPage, hasMore: query.hasNextPage,
        search, filters: filterState, sort: sortState, pageSize: size,
        loadMore, refresh, clearFilters, retry: () => query.refetch(),
    })
}