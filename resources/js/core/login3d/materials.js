// One material per look, created once and shared by every mesh so the scene
// stays cheap and disposal is a single pass. No shadow maps, no env maps —
// gold is faked with a mid metalness plus a little emissive so it still reads
// as metal under plain directional lights.

import * as THREE from 'three'

export const PALETTE = {
    leather: 0x163665,
    leatherDark: 0x0c203f,
    liner: 0xf4ece1,
    pages: 0xf0e3ca,
    gold: 0xffd866,
    goldDark: 0xb88820,
    keyhole: 0x081020,
    skin: 0xe8be99,
    skinShade: 0xcf9e7b,
    nail: 0xf6d4c2,
    sclera: 0xfafcff,
    iris: 0x6e3e18,
    pupil: 0x070c14,
    lid: 0x0f2444,
    lidLower: 0x163665,
    brow: 0x0b1a32,
}

export function createMaterials(coverTexture) {
    const standard = (color, extra = {}) =>
        new THREE.MeshStandardMaterial({ color, roughness: 0.75, metalness: 0, ...extra })

    return {
        art: standard(0xffffff, {
            map: coverTexture,
            roughness: 0.52,
            metalness: 0.06,
        }),
        leather: standard(PALETTE.leather, { roughness: 0.75 }),
        leatherDark: standard(PALETTE.leatherDark, { roughness: 0.8 }),
        liner: standard(PALETTE.liner, { roughness: 0.92 }),
        page: standard(0xfffcf7, { roughness: 0.95 }),
        pages: standard(PALETTE.pages, { roughness: 0.85, metalness: 0.2 }),

        gold: standard(PALETTE.gold, {
            roughness: 0.22,
            metalness: 0.65,
            emissive: 0x3d2b07,
        }),
        goldDark: standard(PALETTE.goldDark, { roughness: 0.32, metalness: 0.55 }),
        // Own instance so the failure glow never leaks into the key.
        lockBody: standard(PALETTE.gold, {
            roughness: 0.22,
            metalness: 0.65,
            emissive: 0x3d2b07,
        }),
        keyhole: standard(PALETTE.keyhole, { roughness: 0.9 }),

        skin: standard(PALETTE.skin, { roughness: 0.6 }),
        skinShade: standard(PALETTE.skinShade, { roughness: 0.64 }),
        nail: standard(PALETTE.nail, { roughness: 0.35 }),

        sclera: standard(PALETTE.sclera, { roughness: 0.25 }),
        iris: standard(PALETTE.iris, { roughness: 0.2 }),
        pupil: standard(PALETTE.pupil, { roughness: 0.15 }),
        lid: standard(PALETTE.lid, { roughness: 0.72, side: THREE.DoubleSide }),
        lidLower: standard(PALETTE.lidLower, { roughness: 0.74, side: THREE.DoubleSide }),
        brow: standard(PALETTE.brow, { roughness: 0.8 }),
        highlight: standard(0xffffff, {
            roughness: 0.08,
            emissive: 0xffffff,
            emissiveIntensity: 0.5,
        }),
    }
}

/** Frees every geometry and material under a subtree (textures included). */
export function disposeObject(root) {
    root.traverse((node) => {
        if (node.geometry) node.geometry.dispose()
        if (!node.material) return
        const list = Array.isArray(node.material) ? node.material : [node.material]
        list.forEach((m) => {
            Object.keys(m).forEach((key) => {
                const value = m[key]
                if (value && value.isTexture) value.dispose()
            })
            m.dispose()
        })
    })
}
