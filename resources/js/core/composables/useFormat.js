import { usePage } from '@inertiajs/vue3'

const OPTIONS = {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
}

export function useFormat() {
    const page = usePage()

    function parts(iso) {
        const date = new Date(iso)
        if (!iso || Number.isNaN(date.getTime())) return null
        const zone = page.props.app?.timezone || 'UTC'
        let fmt
        try { fmt = new Intl.DateTimeFormat('en-GB', { ...OPTIONS, timeZone: zone }) }
        catch { fmt = new Intl.DateTimeFormat('en-GB', { ...OPTIONS, timeZone: 'UTC' }) }
        return Object.fromEntries(fmt.formatToParts(date).map(p => [p.type, p.value]))
    }

    // Waqt: UTC se workspace ke timezone mein
    const dateTime = (iso, seconds = false) => {
        const p = parts(iso)
        return p ? `${p.day}-${p.month}-${p.year} ${p.hour}:${p.minute}${seconds ? ':' + p.second : ''}` : ''
    }

    // Sirf tareekh (YYYY-MM-DD): timezone ka koi asar nahi
    const date = s => (/^\d{4}-\d{2}-\d{2}/.test(s ?? '') ? `${s.slice(8, 10)}-${s.slice(5, 7)}-${s.slice(0, 4)}` : '')

    const money = n => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2 })

    // "5 min ago" / "2 hours ago" / "3 days ago"; 7 din se purana ho to tareekh
    const ago = iso => {
        const t = new Date(iso ?? '').getTime()
        if (!iso || Number.isNaN(t)) return ''
        const secs = Math.max(0, Math.round((Date.now() - t) / 1000))
        if (secs < 60) return 'just now'
        const mins = Math.floor(secs / 60)
        if (mins < 60) return `${mins} min ago`
        const hours = Math.floor(mins / 60)
        if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`
        const days = Math.floor(hours / 24)
        if (days <= 7) return `${days} ${days === 1 ? 'day' : 'days'} ago`
        return dateTime(iso)
    }

    return { dateTime, date, money, ago }
}