// The closed ledger: back cover, dark page block, spine on the LEFT, and a
// front cover hinged on that spine. The padlock, strap studs and keyhole ride
// with the cover because they are physically part of it.
//
// Book space: cover width = 1, cover height = BOOK_H, origin at the centre of
// the cover's FRONT face (z = 0). Everything behind is negative z, so the DOM
// form can sit exactly on z = 0 and flip with the hinge.

import * as THREE from 'three'
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js'
import { BOOK_H, bx, by } from './metrics'

const W = 1
const H = BOOK_H
const T = 0.025 * W // cover thickness ≈ 2.5% of the cover width
const PAGE_T = T * 1.6

const rbox = (w, h, d, r) => new RoundedBoxGeometry(w, h, d, 2, r)

export function buildBook(materials) {
    const group = new THREE.Group()

    // --- back cover + page block -------------------------------------------
    const back = new THREE.Mesh(rbox(W, H, T, 0.012), materials.leatherDark)
    back.position.z = -T - PAGE_T - T / 2
    group.add(back)

    const pages = new THREE.Mesh(new THREE.BoxGeometry(W - 0.014, H - 0.016, PAGE_T), materials.pages)
    pages.position.z = -T - PAGE_T / 2
    group.add(pages)

    // first page, revealed when the cover swings open
    const pageTop = new THREE.Mesh(new THREE.PlaneGeometry(W - 0.06, H - 0.055), materials.page)
    pageTop.position.z = -T + 0.002
    group.add(pageTop)

    // --- spine --------------------------------------------------------------
    const spine = new THREE.Mesh(rbox(0.05, H, T * 2 + PAGE_T, 0.02), materials.leatherDark)
    spine.position.set(-W / 2 + 0.018, 0, -(T * 2 + PAGE_T) / 2 + T / 2)
    group.add(spine)

    // --- hinged front cover -------------------------------------------------
    const coverPivot = new THREE.Group()
    coverPivot.position.set(-W / 2, 0, 0)
    group.add(coverPivot)

    const coverFace = new THREE.Group()
    coverFace.position.x = W / 2
    coverPivot.add(coverFace)

    const cover = new THREE.Mesh(new THREE.BoxGeometry(W, H, T), [
        materials.leather, // +x fore-edge
        materials.leather, // -x
        materials.leather, // +y
        materials.leather, // -y
        materials.art, // +z printed cover
        materials.liner, // -z inside of the cover
    ])
    cover.position.z = -T / 2
    coverFace.add(cover)

    // --- strap studs (the strap itself is painted into the cover art) -------
    const studGeo = new THREE.SphereGeometry(0.021, 12, 8)
    for (const sy of [-0.19, 0.19]) {
        const stud = new THREE.Mesh(studGeo, materials.gold)
        stud.position.set(bx(0.926), by(0.55) + sy, 0.012)
        stud.scale.z = 0.6
        coverFace.add(stud)
    }

    // --- padlock: body about as wide as the 11% strap ------------------------
    const lock = new THREE.Group()
    lock.position.set(bx(0.926), by(0.55), 0.006)
    coverFace.add(lock)

    const body = new THREE.Mesh(rbox(0.115, 0.1, 0.055, 0.016), materials.lockBody)
    body.position.z = 0.028
    lock.add(body)

    // recessed face plate so the keyhole reads as a hole, not a dot
    const plate = new THREE.Mesh(rbox(0.074, 0.062, 0.008, 0.02), materials.goldDark)
    plate.position.z = 0.057
    lock.add(plate)

    const hole = new THREE.Mesh(new THREE.CircleGeometry(0.0085, 12), materials.keyhole)
    hole.position.set(0, 0.005, 0.0615)
    lock.add(hole)

    const slot = new THREE.Mesh(new THREE.PlaneGeometry(0.0065, 0.021), materials.keyhole)
    slot.position.set(0, -0.011, 0.0615)
    lock.add(slot)

    // shackle: hinged on its LEFT leg so it lifts and swings from that side
    const shackle = new THREE.Group()
    shackle.position.set(-0.03, 0.048, 0.028)
    lock.add(shackle)

    const arc = new THREE.Mesh(new THREE.TorusGeometry(0.03, 0.0075, 8, 16, Math.PI), materials.gold)
    arc.position.x = 0.03
    shackle.add(arc)

    const legGeo = new THREE.CylinderGeometry(0.0075, 0.0075, 0.034, 8)
    for (const lx of [0, 0.06]) {
        const leg = new THREE.Mesh(legGeo, materials.gold)
        leg.position.set(lx, -0.017, 0)
        shackle.add(leg)
    }

    // marker the key snaps to: +z points straight out of the keyhole
    const keyholeAnchor = new THREE.Object3D()
    keyholeAnchor.position.set(0, 0.005, 0.063)
    lock.add(keyholeAnchor)

    return { group, coverPivot, coverFace, lock, shackle, keyholeAnchor, body }
}

/* ---------------------------------------------------------------------- key */

export function buildKey(materials) {
    const gold = materials.gold.clone()
    gold.transparent = true
    gold.opacity = 1

    const rig = new THREE.Group() // sits on the keyhole, +z outward
    const slide = new THREE.Group() // insertion depth along z
    const spin = new THREE.Group() // turn about the shaft axis
    rig.add(slide)
    slide.add(spin)

    // tip points at the keyhole (-z), ring at the back (+z)
    const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.011, 0.011, 0.18, 10), gold)
    shaft.rotation.x = Math.PI / 2
    shaft.position.z = 0.02
    spin.add(shaft)

    const tip = new THREE.Mesh(new THREE.ConeGeometry(0.011, 0.028, 10), gold)
    tip.rotation.x = -Math.PI / 2
    tip.position.z = -0.084
    spin.add(tip)

    const bow = new THREE.Mesh(new THREE.TorusGeometry(0.046, 0.012, 8, 18), gold)
    bow.position.z = 0.155
    spin.add(bow)

    const toothGeo = new THREE.BoxGeometry(0.03, 0.022, 0.016)
    for (const [z, w] of [
        [-0.05, 1],
        [-0.022, 0.8],
    ]) {
        const tooth = new THREE.Mesh(toothGeo, gold)
        tooth.position.set(0.018 * w, -0.019, z)
        tooth.scale.x = w
        spin.add(tooth)
    }

    return { rig, slide, spin, material: gold }
}
