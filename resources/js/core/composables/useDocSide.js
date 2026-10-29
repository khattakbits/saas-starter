import { ref } from 'vue'

const KEY = 'doc-side-collapsed'

// Sab FormPage instances aik hi state share karte hain (module-level ref)
const collapsed = ref(false)        // default: panel khula hua

export function useDocSide() {
    // localStorage sirf mount ke baad padha jata hai (SSR par window nahi hoti)
    function restore() {
        try {
            collapsed.value = localStorage.getItem(KEY) === '1'
        } catch {
            // private mode / storage band: default hi rahega
        }
    }

    function toggle() {
        collapsed.value = !collapsed.value
        try {
            localStorage.setItem(KEY, collapsed.value ? '1' : '0')
        } catch {
            // save na ho sake to sirf isi session mein yaad rahega
        }
    }

    return { collapsed, toggle, restore }
}
