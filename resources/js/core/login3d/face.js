// The pair of eyes set into the cover, and the pair of hands that hide them.
//
// Eyes: a cream sphere in a dark recess, a red-brown iris and black pupil as
// spherical caps on that same sphere (so they never float off the eyeball), a
// heavy skin-brown upper lid that slants 23° down toward the nose and a brow
// at 29°. Both pupils are driven by one shared offset.
//
// Hands: the palm faces the character's own eyes, so the VIEWER always sees the
// back of the hand — knuckles, fingernails, the last finger segment curled 43°
// toward the face and the thumb swung 50° out to the outer side.

import * as THREE from 'three'
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js'
import { by } from './metrics'

export const EYE_R = 0.092
export const EYE_X = 0.203
export const EYE_Y = by(0.28)
/** hand width in cover widths — eye width (0.184) + 12% */
export const HAND_W = 0.235
const EYE_Z = -0.05
const EYE_H = EYE_R * 2

const rbox = (w, h, d, r) => new RoundedBoxGeometry(w, h, d, 2, r)

/* --------------------------------------------------------------------- eye */

function buildEye(materials, side) {
    const eye = new THREE.Group()

    // eyeball, mostly buried in the cover so only the front cap shows
    const white = new THREE.Mesh(new THREE.SphereGeometry(EYE_R, 26, 18), materials.sclera)
    white.position.z = EYE_Z
    eye.add(white)

    // iris + pupil + glint, all caps on the eyeball's own sphere
    const gaze = new THREE.Group()
    gaze.position.z = EYE_Z
    eye.add(gaze)

    const irisR = EYE_R * 0.42
    const iris = new THREE.Mesh(
        new THREE.SphereGeometry(EYE_R * 1.004, 22, 10, 0, Math.PI * 2, 0, Math.asin(irisR / EYE_R)),
        materials.iris,
    )
    iris.rotation.x = Math.PI / 2
    gaze.add(iris)

    const pupilR = irisR * 0.46
    const pupil = new THREE.Mesh(
        new THREE.SphereGeometry(EYE_R * 1.012, 18, 8, 0, Math.PI * 2, 0, Math.asin(pupilR / EYE_R)),
        materials.pupil,
    )
    pupil.rotation.x = Math.PI / 2
    gaze.add(pupil)

    const glint = new THREE.Mesh(new THREE.SphereGeometry(0.008, 8, 6), materials.highlight)
    glint.position.set(0.024, 0.028, EYE_R * 0.9)
    gaze.add(glint)

    // upper lid: a gentle, calm eyelid resting naturally over the upper eye
    const upper = new THREE.Group()
    upper.position.z = EYE_Z
    upper.rotation.z = side * 0.06 // Gentle, dignified natural curve (not angry)
    eye.add(upper)

    const lid = new THREE.Mesh(
        new THREE.SphereGeometry(EYE_R * 1.055, 26, 14, 0, Math.PI * 2, 0, 1.28),
        materials.lid,
    )
    upper.add(lid)

    // lower lid: subtle lower rim keeping the eye framed
    const lower = new THREE.Mesh(
        new THREE.SphereGeometry(EYE_R * 1.04, 24, 10, 0, Math.PI * 2, 2.12, 1.02),
        materials.lidLower,
    )
    lower.position.z = EYE_Z
    eye.add(lower)

    // brow: elegant, dignified arch sitting safely INSIDE the brass eye plate recess
    const brow = new THREE.Mesh(rbox(0.115, 0.022, 0.024, 0.01), materials.brow)
    brow.position.set(side * 0.005, 0.062, 0.002)
    brow.rotation.z = side * 0.08
    eye.add(brow)

    eye.userData.gaze = gaze
    return eye
}

/* -------------------------------------------------------------------- hand */

function buildHand(materials, side) {
    const group = new THREE.Group()

    // `body` carries the rise / scale / tilt animation so the group itself can
    // stay put on the eye.
    const body = new THREE.Group()
    group.add(body)

    // Palm: the flat side faces the character's own eyes (-z); the back of the
    // hand, with knuckles and nails, faces the viewer (+z).
    const palm = new THREE.Mesh(rbox(0.92, 0.52, 0.13, 0.07), materials.skin)
    palm.position.set(0, -0.18, -0.045)
    body.add(palm)

    const fingerXs = [-0.32, -0.105, 0.105, 0.32]
    const proxGeo = rbox(0.158, 0.34, 0.135, 0.065)
    const distGeo = rbox(0.145, 0.24, 0.125, 0.06)
    const nailGeo = rbox(0.08, 0.1, 0.025, 0.018)
    const knuckleGeo = new THREE.SphereGeometry(0.052, 12, 10)

    // Knuckle ridge on the back of the hand.
    fingerXs.forEach((fx) => {
        const bump = new THREE.Mesh(knuckleGeo, materials.skinShade)
        bump.position.set(fx, -0.015, 0.028)
        bump.scale.set(1, 0.8, 0.48)
        body.add(bump)
    })

    fingerXs.forEach((fx, i) => {
        const spread = (i - 1.5) * 0.055

        const knuckle = new THREE.Group()
        // knuckles sit slightly behind the dorsal plane so fingers arc forward
        knuckle.position.set(fx, 0.055, -0.015)
        knuckle.rotation.z = spread
        body.add(knuckle)

        const prox = new THREE.Mesh(proxGeo, materials.skin)
        prox.position.y = 0.17
        knuckle.add(prox)

        // last segment curls ~43° toward the face (-z), away from the viewer
        const joint = new THREE.Group()
        joint.position.y = 0.34
        joint.rotation.x = 0.75
        knuckle.add(joint)

        const dist = new THREE.Mesh(distGeo, materials.skin)
        dist.position.y = 0.12
        joint.add(dist)

        // fingernail on the back of the fingertip, facing the viewer
        const nail = new THREE.Mesh(nailGeo, materials.nail)
        nail.position.set(0, 0.12, 0.064)
        joint.add(nail)
    })

    // thumb: outer side, swung ~50° outward, also showing its nail to the viewer
    const thumb = new THREE.Group()
    thumb.position.set(0.45 * side, -0.11, -0.015)
    thumb.rotation.z = -0.87 * side
    body.add(thumb)

    const thumbProx = new THREE.Mesh(new THREE.CapsuleGeometry(0.06, 0.19, 4, 10), materials.skin)
    thumbProx.position.y = 0.095
    thumb.add(thumbProx)

    const thumbTip = new THREE.Group()
    thumbTip.position.y = 0.19
    thumbTip.rotation.x = 0.62
    thumb.add(thumbTip)

    const thumbDist = new THREE.Mesh(new THREE.CapsuleGeometry(0.054, 0.14, 4, 10), materials.skin)
    thumbDist.position.y = 0.07
    thumbTip.add(thumbDist)

    const thumbNail = new THREE.Mesh(rbox(0.064, 0.074, 0.02, 0.014), materials.nail)
    thumbNail.position.set(0, 0.07, 0.052)
    thumbTip.add(thumbNail)

    // short bare forearm going down behind the hand
    const forearm = new THREE.Mesh(new THREE.CapsuleGeometry(0.17, 0.58, 4, 12), materials.skin)
    forearm.position.set(0.02 * side, -0.72, -0.05)
    body.add(forearm)

    return { group, body }
}

/* -------------------------------------------------------------------- face */

export function buildFace(materials) {
    const root = new THREE.Group()
    const eyes = []
    const hands = []

    for (const side of [-1, 1]) {
        const eye = buildEye(materials, side)
        eye.position.set(side * EYE_X, EYE_Y, 0)
        root.add(eye)
        eyes.push({ side, gaze: eye.userData.gaze })

        const hand = buildHand(materials, side)
        // hand geometry is authored at 1 = hand width
        hand.group.scale.setScalar(HAND_W)
        hand.group.position.set(side * EYE_X, EYE_Y + 0.008, 0.14)
        hand.group.visible = false
        root.add(hand.group)
        hands.push({ side, group: hand.group, body: hand.body })
    }

    return {
        root,
        eyes,
        hands,
        eyeHeight: EYE_H,
        // 1.8 eye-heights, expressed in hand-local units
        handRise: (1.8 * EYE_H) / HAND_W,
    }
}
