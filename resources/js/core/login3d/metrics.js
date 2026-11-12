// Shared cover geometry.
//
// The DOM form and the WebGL cover both derive their size from these numbers,
// so the form can never drift off the cover: the Vue page positions the form
// plane with `coverMetrics()`, and the scene scales the book with the same
// result. The camera is front-on, so a CSS `perspective` of `metrics.perspective`
// reproduces the WebGL projection exactly and the form can be flipped with a
// plain `rotateY()` that matches the cover's hinge.

export const FOV = 32
export const CAMERA_DIST = 6

/** cover width : cover height (wider horizontal proportion) */
export const RATIO = 8 / 9
/** cover height when the cover width is 1 (book space) */
export const BOOK_H = 9 / 8
/** the brief's "u" — one fortieth of the cover width — in book space */
export const U = 1 / 40

const HALF_FOV = (FOV * Math.PI) / 360

/**
 * @param {number} width  stage width in CSS px
 * @param {number} height stage height in CSS px
 */
export function coverMetrics(width, height) {
    const w0 = Math.max(1, width)
    const h0 = Math.max(1, height)

    // Height first (88% of the stage for comfortable framing on white background),
    // then shrink so the cover plus margin fits the stage width on narrow viewports.
    const h = Math.min(h0 * 0.88, w0 / 1.15 / RATIO)
    const w = h * RATIO

    return {
        w,
        h,
        left: (w0 - w) / 2,
        top: (h0 - h) / 2,
        // Perspective distance in CSS px that matches the WebGL camera.
        perspective: h0 / (2 * Math.tan(HALF_FOV)),
        // World units per CSS pixel on the z = 0 plane.
        worldPerPx: (2 * CAMERA_DIST * Math.tan(HALF_FOV)) / h0,
    }
}

/** x for a fraction of the cover width, in book space (origin at the centre). */
export const bx = (f) => (f - 0.5) * 1

/** y for a fraction of the cover height measured from the top, in book space. */
export const by = (f) => (0.5 - f) * BOOK_H
