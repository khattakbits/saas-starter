// Paints the luxury Royal Sapphire & 24K Gold cover into ONE 1200x1350 canvas:
// vibrant Lapis/Sapphire leather grain, polished 24K gold leaf frames,
// corner Baroque filigree, embossed "KhattakBits Login" title, gilded brass eye-plate,
// three minted relief medallions, and gold-stitched strap.

const CW = 1200
const CH = 1350

// Brilliant 24K Gold Metallic Palette
const GOLD_SPECULAR = '#ffffff'
const GOLD_BRIGHT = '#fff6d4'
const GOLD_LIGHT = '#ffdf70'
const GOLD = '#f4bc34'
const GOLD_MID = '#c88c16'
const GOLD_DEEP = '#8e5e09'
const GOLD_DARK = '#462d03'

const px = (f) => f * CW
const py = (f) => f * CH

function roundRect(ctx, x, y, w, h, r) {
    const rr = Math.max(0, Math.min(r, w / 2, h / 2))
    ctx.beginPath()
    ctx.moveTo(x + rr, y)
    ctx.lineTo(x + w - rr, y)
    ctx.quadraticCurveTo(x + w, y, x + w, y + rr)
    ctx.lineTo(x + w, y + h - rr)
    ctx.quadraticCurveTo(x + w, y + h, x + w - rr, y + h)
    ctx.lineTo(x + rr, y + h)
    ctx.quadraticCurveTo(x, y + h, x, y + h - rr)
    ctx.lineTo(x, y + rr)
    ctx.quadraticCurveTo(x, y, x + rr, y)
    ctx.closePath()
}

/** Multi-stop gleaming 24K metallic gold linear gradient */
function goldGrad(ctx, x0, y0, x1, y1) {
    const g = ctx.createLinearGradient(x0, y0, x1, y1)
    g.addColorStop(0, GOLD_SPECULAR)
    g.addColorStop(0.16, GOLD_BRIGHT)
    g.addColorStop(0.38, GOLD_LIGHT)
    g.addColorStop(0.65, GOLD)
    g.addColorStop(0.86, GOLD_MID)
    g.addColorStop(1, GOLD_DEEP)
    return g
}

/** Radial metallic gold gradient for studs and medallions */
function goldRadial(ctx, cx, cy, r) {
    const g = ctx.createRadialGradient(cx - r * 0.35, cy - r * 0.4, r * 0.08, cx, cy, r)
    g.addColorStop(0, GOLD_SPECULAR)
    g.addColorStop(0.24, GOLD_BRIGHT)
    g.addColorStop(0.55, GOLD)
    g.addColorStop(0.82, GOLD_MID)
    g.addColorStop(1, GOLD_DARK)
    return g
}

/** Strokes a path with realistic debossed/embossed metallic depth */
function embossStroke(ctx, buildPath, lineWidth, gradient, shadowOffset = 2.4) {
    ctx.save()
    ctx.lineJoin = 'round'
    ctx.lineCap = 'round'

    // Cast shadow on leather
    ctx.lineWidth = lineWidth
    ctx.translate(shadowOffset, shadowOffset * 1.1)
    ctx.strokeStyle = 'rgba(4, 10, 24, 0.75)'
    buildPath()
    ctx.stroke()

    // Pure white specular rim
    ctx.translate(-shadowOffset * 2, -shadowOffset * 2.2)
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.45)'
    buildPath()
    ctx.stroke()

    // Main 24K gold metallic stroke
    ctx.translate(shadowOffset, shadowOffset * 1.1)
    ctx.strokeStyle = gradient
    buildPath()
    ctx.stroke()
    ctx.restore()
}

function noiseTile(size = 128) {
    const c = document.createElement('canvas')
    c.width = c.height = size
    const g = c.getContext('2d')
    const img = g.createImageData(size, size)
    for (let i = 0; i < img.data.length; i += 4) {
        const v = 128 + (Math.random() - 0.5) * 75
        img.data[i] = img.data[i + 1] = img.data[i + 2] = v
        img.data[i + 3] = 255
    }
    g.putImageData(img, 0, 0)
    return c
}

/* ------------------------------------------------------------------ leather */

function paintLeather(ctx) {
    // Vibrant Royal Sapphire & Imperial Lapis base
    const base = ctx.createRadialGradient(CW * 0.42, CH * 0.28, CW * 0.1, CW * 0.5, CH * 0.5, CH * 0.85)
    base.addColorStop(0, '#214e8c')
    base.addColorStop(0.35, '#173a6e')
    base.addColorStop(0.72, '#0f274d')
    base.addColorStop(1, '#081730')
    ctx.fillStyle = base
    ctx.fillRect(0, 0, CW, CH)

    // Hand-buffed sapphire patina (natural leather highlights)
    for (let i = 0; i < 90; i += 1) {
        const x = Math.random() * CW
        const y = Math.random() * CH
        const r = 40 + Math.random() * 240
        const isBright = Math.random() > 0.45
        const g = ctx.createRadialGradient(x, y, 0, x, y, r)
        g.addColorStop(0, isBright ? 'rgba(56, 128, 224, 0.12)' : 'rgba(3, 8, 20, 0.22)')
        g.addColorStop(1, 'rgba(0, 0, 0, 0)')
        ctx.fillStyle = g
        ctx.fillRect(x - r, y - r, r * 2, r * 2)
    }

    // Fine organic leather grain overlay
    ctx.save()
    ctx.globalCompositeOperation = 'overlay'
    ctx.globalAlpha = 0.2
    ctx.fillStyle = ctx.createPattern(noiseTile(), 'repeat')
    ctx.fillRect(0, 0, CW, CH)
    ctx.restore()

    // Radiant key light glow from top-left
    const keyGlow = ctx.createRadialGradient(CW * 0.25, CH * 0.1, 15, CW * 0.3, CH * 0.15, CH * 0.75)
    keyGlow.addColorStop(0, 'rgba(150, 200, 255, 0.22)')
    keyGlow.addColorStop(0.5, 'rgba(90, 160, 245, 0.08)')
    keyGlow.addColorStop(1, 'rgba(0, 0, 0, 0)')
    ctx.fillStyle = keyGlow
    ctx.fillRect(0, 0, CW, CH)

    // Edge burnishing & rich corner vignette
    const edgeBurnish = ctx.createRadialGradient(CW * 0.5, CH * 0.5, CH * 0.35, CW * 0.5, CH * 0.5, CH * 0.78)
    edgeBurnish.addColorStop(0, 'rgba(0, 0, 0, 0)')
    edgeBurnish.addColorStop(0.75, 'rgba(4, 12, 28, 0.35)')
    edgeBurnish.addColorStop(1, 'rgba(3, 8, 20, 0.75)')
    ctx.fillStyle = edgeBurnish
    ctx.fillRect(0, 0, CW, CH)
}

/* ------------------------------------------------------------------- frames */

const FRAME_INSETS = [0.024, 0.04, 0.062].map((f) => f * CW)
const FRAME_WIDTHS = [3.5, 2.0, 6.0]
const FRAME_RADII = [24, 20, 15]

function paintFrames(ctx) {
    const grad = goldGrad(ctx, 0, 0, CW * 0.85, CH)
    FRAME_INSETS.forEach((inset, i) => {
        const w = CW - inset * 2
        const h = CH - inset * 2
        embossStroke(
            ctx,
            () => roundRect(ctx, inset, inset, w, h, FRAME_RADII[i]),
            FRAME_WIDTHS[i],
            grad,
            i === 2 ? 3.0 : 2.2,
        )
    })

    // Guilloche / diamond lace band inside the inner frame
    const inner = FRAME_INSETS[2]
    const step = 34
    const inset = inner + 20
    const top = inset
    const bottom = CH - inset
    const left = inset
    const right = CW - inset

    ctx.save()
    ctx.fillStyle = goldGrad(ctx, left, top, right, bottom)
    const diamond = (x, y) => {
        ctx.beginPath()
        ctx.moveTo(x, y - 6)
        ctx.lineTo(x + 4.5, y)
        ctx.lineTo(x, y + 6)
        ctx.lineTo(x - 4.5, y)
        ctx.closePath()
        ctx.fill()

        // Micro gold center pip
        ctx.beginPath()
        ctx.arc(x, y, 1.4, 0, Math.PI * 2)
        ctx.fillStyle = GOLD_SPECULAR
        ctx.fill()
        ctx.fillStyle = goldGrad(ctx, left, top, right, bottom)
    }

    for (let x = left + step; x < right - step / 2; x += step) {
        diamond(x, top)
        diamond(x, bottom)
    }
    for (let y = top + step; y < bottom - step / 2; y += step) {
        diamond(left, y)
        diamond(right, y)
    }
    ctx.restore()
}

/* ------------------------------------------------------ corner filigree & rosettes */

function baroqueCorner(ctx, cx, cy, scaleX, scaleY) {
    ctx.save()
    ctx.translate(cx, cy)
    ctx.scale(scaleX, scaleY)

    const grad = goldGrad(ctx, 0, 0, 85, 85)
    ctx.strokeStyle = grad
    ctx.fillStyle = grad
    ctx.lineWidth = 2.6
    ctx.lineCap = 'round'

    // Outer spiral flourish
    ctx.beginPath()
    ctx.moveTo(14, 70)
    ctx.bezierCurveTo(16, 42, 42, 16, 70, 14)
    ctx.stroke()

    // Inner scroll with leaf tip
    ctx.beginPath()
    ctx.moveTo(24, 60)
    ctx.bezierCurveTo(26, 36, 36, 26, 60, 24)
    ctx.stroke()

    // Corner leaf droplet
    ctx.beginPath()
    ctx.ellipse(38, 38, 13, 7, Math.PI / 4, 0, Math.PI * 2)
    ctx.fill()

    ctx.restore()
}

function rosette(ctx, cx, cy, r) {
    ctx.save()
    ctx.translate(cx, cy)
    const grad = goldGrad(ctx, -r, -r, r, r)

    // Stamped sapphire deboss pocket
    ctx.beginPath()
    ctx.arc(0, 0, r * 1.15, 0, Math.PI * 2)
    ctx.fillStyle = 'rgba(5, 12, 28, 0.65)'
    ctx.fill()

    ctx.fillStyle = grad
    for (let i = 0; i < 16; i += 1) {
        ctx.save()
        ctx.rotate((i / 16) * Math.PI * 2)
        ctx.beginPath()
        ctx.ellipse(0, -r * 0.62, r * 0.13, r * 0.32, 0, 0, Math.PI * 2)
        ctx.fill()

        // Tiny petal spine
        ctx.beginPath()
        ctx.moveTo(0, -r * 0.85)
        ctx.lineTo(0, -r * 0.35)
        ctx.strokeStyle = GOLD_SPECULAR
        ctx.lineWidth = 1
        ctx.stroke()
        ctx.restore()
    }

    // Outer beaded ring
    ctx.beginPath()
    ctx.arc(0, 0, r * 0.48, 0, Math.PI * 2)
    ctx.strokeStyle = grad
    ctx.lineWidth = 2.4
    ctx.stroke()

    // Central 24K gold gemstone boss
    const boss = goldRadial(ctx, 0, 0, r * 0.3)
    ctx.beginPath()
    ctx.arc(0, 0, r * 0.28, 0, Math.PI * 2)
    ctx.fillStyle = boss
    ctx.fill()
    ctx.strokeStyle = 'rgba(60, 40, 10, 0.6)'
    ctx.lineWidth = 1.8
    ctx.stroke()

    // Specular center glint
    ctx.beginPath()
    ctx.arc(-r * 0.08, -r * 0.08, r * 0.08, 0, Math.PI * 2)
    ctx.fillStyle = 'rgba(255, 255, 255, 0.85)'
    ctx.fill()

    ctx.restore()
}

function paintRosettes(ctx) {
    const inset = FRAME_INSETS[2]
    const r = 0.046 * CW

    rosette(ctx, inset, inset, r)
    rosette(ctx, CW - inset, inset, r)
    rosette(ctx, inset, CH - inset, r)
    rosette(ctx, CW - inset, CH - inset, r)

    baroqueCorner(ctx, inset + 4, inset + 4, 1, 1)
    baroqueCorner(ctx, CW - inset - 4, inset + 4, -1, 1)
    baroqueCorner(ctx, inset + 4, CH - inset - 4, 1, -1)
    baroqueCorner(ctx, CW - inset - 4, CH - inset - 4, -1, -1)
}

/* --------------------------------------------------------------------- type */

function spacedText(ctx, text, cx, y, spacing) {
    const chars = [...text]
    const widths = chars.map((c) => ctx.measureText(c).width)
    const total = widths.reduce((a, b) => a + b, 0) + spacing * (chars.length - 1)
    let x = cx - total / 2
    ctx.textAlign = 'left'
    for (let i = 0; i < chars.length; i += 1) {
        ctx.fillText(chars[i], x, y)
        x += widths[i] + spacing
    }
    ctx.textAlign = 'center'
}

function paintTitle(ctx) {
    // "KhattakBits Login" as requested
    const text = 'KhattakBits Login'
    let size = 118
    const max = CW * 0.76
    ctx.font = `700 ${size}px "Cinzel", "Playfair Display", Georgia, serif`
    const measured = ctx.measureText(text).width
    if (measured > max) {
        size = Math.floor((size * max) / measured)
        ctx.font = `700 ${size}px "Cinzel", "Playfair Display", Georgia, serif`
    }

    const y = py(0.138)
    const x = CW / 2
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'alphabetic'

    // Deep stamped leather recess shadow
    ctx.fillStyle = 'rgba(3, 8, 22, 0.88)'
    ctx.fillText(text, x + 4, y + 5)

    // Specular top highlight
    ctx.fillStyle = 'rgba(255, 255, 255, 0.35)'
    ctx.fillText(text, x - 2, y - 2)

    // Gleaming 24K gold foil face
    ctx.fillStyle = goldGrad(ctx, x, y - size * 0.75, x, y + size * 0.1)
    ctx.fillText(text, x, y)

    // Crisp gold bevel outline
    ctx.lineWidth = 1.3
    ctx.strokeStyle = 'rgba(90, 60, 15, 0.6)'
    ctx.strokeText(text, x, y)
    ctx.restore()
}

function paintSubtitle(ctx) {
    const y = py(0.174)
    ctx.save()
    ctx.textBaseline = 'alphabetic'
    ctx.font = '600 23px "Cinzel", Georgia, serif'

    // Shadow
    ctx.fillStyle = 'rgba(3, 8, 22, 0.75)'
    spacedText(ctx, 'ENTERPRISE ACCESS • UNIFIED PORTAL', CW / 2 + 1.5, y + 2, 7)

    // Gold face
    ctx.fillStyle = goldGrad(ctx, CW * 0.18, y - 26, CW * 0.82, y)
    spacedText(ctx, 'ENTERPRISE ACCESS • UNIFIED PORTAL', CW / 2, y, 7)
    ctx.restore()
}

function paintOrnament(ctx) {
    const y = py(0.194)
    const mid = CW / 2
    ctx.save()
    const grad = goldGrad(ctx, CW * 0.32, y - 4, CW * 0.68, y + 4)
    ctx.strokeStyle = grad
    ctx.lineWidth = 1.8

    // Flourish wings
    for (const [x0, x1] of [
        [CW * 0.32, mid - 26],
        [mid + 26, CW * 0.68],
    ]) {
        ctx.beginPath()
        ctx.moveTo(x0, y)
        ctx.lineTo(x1, y)
        ctx.stroke()

        // Wing end pip
        ctx.beginPath()
        ctx.arc(x0, y, 2.6, 0, Math.PI * 2)
        ctx.fillStyle = grad
        ctx.fill()
    }

    // Center crest lozenge
    ctx.beginPath()
    ctx.moveTo(mid, y - 9)
    ctx.lineTo(mid + 8, y)
    ctx.lineTo(mid, y + 9)
    ctx.lineTo(mid - 8, y)
    ctx.closePath()
    ctx.fillStyle = grad
    ctx.fill()
    ctx.strokeStyle = 'rgba(50, 35, 10, 0.65)'
    ctx.lineWidth = 1
    ctx.stroke()

    // Specular center pip
    ctx.beginPath()
    ctx.arc(mid, y, 2.2, 0, Math.PI * 2)
    ctx.fillStyle = GOLD_SPECULAR
    ctx.fill()

    ctx.restore()
}

/* ---------------------------------------------------------------- eye plate */

function paintEyePlate(ctx) {
    const x = px(0.205)
    const y = py(0.216)
    const w = px(0.59)
    const h = py(0.134)
    const r = 18

    // Cast shadow on leather
    ctx.save()
    ctx.shadowColor = 'rgba(2, 6, 18, 0.85)'
    ctx.shadowBlur = 28
    ctx.shadowOffsetY = 6
    ctx.fillStyle = '#030814'
    roundRect(ctx, x, y, w, h, r)
    ctx.fill()
    ctx.restore()

    // Recessed deep royal midnight velvet interior
    const fill = ctx.createLinearGradient(0, y, 0, y + h)
    fill.addColorStop(0, '#0c1b36')
    fill.addColorStop(0.48, '#061022')
    fill.addColorStop(1, '#020610')
    ctx.fillStyle = fill
    roundRect(ctx, x, y, w, h, r)
    ctx.fill()

    // Gilded beveled brass border
    ctx.save()
    roundRect(ctx, x, y, w, h, r)
    ctx.clip()

    // Inner beveled rim
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.88)'
    ctx.lineWidth = 16
    roundRect(ctx, x + 8, y + 8, w - 16, h - 16, r - 5)
    ctx.stroke()

    ctx.strokeStyle = 'rgba(255, 235, 180, 0.12)'
    ctx.lineWidth = 3
    roundRect(ctx, x + 3, y + 3, w - 6, h - 6, r - 2)
    ctx.stroke()

    // Eye sockets: two deep velvet wells where 3D eyeballs sit
    for (const cx of [px(0.297), px(0.703)]) {
        const cy = py(0.28)
        const g = ctx.createRadialGradient(cx, cy, 5, cx, cy, 0.11 * CW)
        g.addColorStop(0, 'rgba(1, 4, 12, 0.98)')
        g.addColorStop(0.68, 'rgba(4, 12, 30, 0.82)')
        g.addColorStop(1, 'rgba(0, 0, 0, 0)')
        ctx.fillStyle = g
        ctx.beginPath()
        ctx.ellipse(cx, cy, 0.12 * CW, 0.088 * CW, 0, 0, Math.PI * 2)
        ctx.fill()
    }
    ctx.restore()

    // Heavy 24K gold embossed frame around the eye plate
    embossStroke(
        ctx,
        () => roundRect(ctx, x, y, w, h, r),
        2.8,
        goldGrad(ctx, x, y, x + w, y + h),
        2.4,
    )

    // Polished corner rivets on the eye plate
    const studR = 4.4
    for (const [sx, sy] of [
        [x + 14, y + 14],
        [x + w - 14, y + 14],
        [x + 14, y + h - 14],
        [x + w - 14, y + h - 14],
    ]) {
        ctx.beginPath()
        ctx.arc(sx, sy, studR, 0, Math.PI * 2)
        ctx.fillStyle = goldRadial(ctx, sx, sy, studR)
        ctx.fill()
        ctx.strokeStyle = 'rgba(50, 35, 10, 0.65)'
        ctx.lineWidth = 1
        ctx.stroke()
    }
}

function paintCaption(ctx) {
    const y = py(0.388)
    ctx.save()
    ctx.textBaseline = 'alphabetic'
    ctx.font = '700 23px "Cinzel", Georgia, serif'

    ctx.fillStyle = 'rgba(2, 6, 18, 0.8)'
    spacedText(ctx, 'SECURE ADMIN & USER ACCESS', CW / 2 + 1.5, y + 2, 7)

    ctx.fillStyle = goldGrad(ctx, CW * 0.28, y - 26, CW * 0.72, y)
    spacedText(ctx, 'SECURE ADMIN & USER ACCESS', CW / 2, y, 7)
    ctx.restore()
}

/* ------------------------------------------------------------ ledger columns */

function coin(ctx, cx, cy, r) {
    const g = goldRadial(ctx, cx, cy, r)
    ctx.beginPath()
    ctx.arc(cx, cy, r, 0, Math.PI * 2)
    ctx.fillStyle = g
    ctx.fill()

    ctx.strokeStyle = 'rgba(40, 25, 5, 0.65)'
    ctx.lineWidth = 1.3
    ctx.stroke()

    // Inner embossed coin rim
    ctx.beginPath()
    ctx.arc(cx, cy, r * 0.52, 0, Math.PI * 2)
    ctx.strokeStyle = 'rgba(80, 50, 10, 0.6)'
    ctx.lineWidth = 1
    ctx.stroke()
}

function paintCoinColumns(ctx) {
    const top = 0.41
    const bottom = 0.76
    const count = 11
    const r = 0.012 * CW

    // Only on the far left side so the right side has plenty of room for the lock!
    for (const fx of [0.075]) {
        for (let i = 0; i < count; i += 1) {
            const t = i / (count - 1)
            coin(ctx, px(fx), py(top + (bottom - top) * t), r)
        }
    }
}

/* --------------------------------------------------------------- medallions */

function abacus(ctx, cx, cy, s) {
    const grad = goldGrad(ctx, cx - s, cy - s, cx + s, cy + s)
    ctx.strokeStyle = grad
    ctx.fillStyle = grad
    ctx.lineWidth = s * 0.09
    ctx.lineCap = 'round'

    ctx.beginPath()
    ctx.moveTo(cx - s * 0.62, cy - s * 0.66)
    ctx.lineTo(cx + s * 0.62, cy - s * 0.66)
    ctx.moveTo(cx - s * 0.62, cy + s * 0.66)
    ctx.lineTo(cx + s * 0.62, cy + s * 0.66)
    ctx.moveTo(cx - s * 0.62, cy - s * 0.66)
    ctx.lineTo(cx - s * 0.62, cy + s * 0.66)
    ctx.moveTo(cx + s * 0.62, cy - s * 0.66)
    ctx.lineTo(cx + s * 0.62, cy + s * 0.66)
    ctx.stroke()

    for (let row = 0; row < 3; row += 1) {
        const y = cy + (row - 1) * s * 0.38
        ctx.beginPath()
        ctx.moveTo(cx - s * 0.62, y)
        ctx.lineTo(cx + s * 0.62, y)
        ctx.lineWidth = s * 0.05
        ctx.stroke()

        for (let b = 0; b < 3; b += 1) {
            const bx = cx + (b - 1) * s * 0.34 + (row === 1 ? s * 0.08 : 0)
            ctx.beginPath()
            ctx.arc(bx, y, s * 0.08, 0, Math.PI * 2)
            ctx.fill()
        }
    }
}

function scales(ctx, cx, cy, s) {
    const grad = goldGrad(ctx, cx - s, cy - s, cx + s, cy + s)
    ctx.strokeStyle = grad
    ctx.fillStyle = grad
    ctx.lineWidth = s * 0.08
    ctx.lineCap = 'round'

    ctx.beginPath()
    ctx.moveTo(cx, cy - s * 0.6)
    ctx.lineTo(cx, cy + s * 0.68)
    ctx.moveTo(cx - s * 0.3, cy + s * 0.7)
    ctx.lineTo(cx + s * 0.3, cy + s * 0.7)
    ctx.moveTo(cx - s * 0.56, cy - s * 0.42)
    ctx.lineTo(cx + s * 0.56, cy - s * 0.42)
    ctx.stroke()

    ctx.beginPath()
    ctx.arc(cx, cy - s * 0.66, s * 0.075, 0, Math.PI * 2)
    ctx.fill()

    for (const side of [-1, 1]) {
        const ex = cx + side * s * 0.54
        ctx.beginPath()
        ctx.moveTo(ex, cy - s * 0.42)
        ctx.lineTo(ex, cy - s * 0.2)
        ctx.stroke()
        ctx.beginPath()
        ctx.arc(ex, cy - s * 0.08, s * 0.22, 0, Math.PI)
        ctx.stroke()
    }
}

function chart(ctx, cx, cy, s) {
    const grad = goldGrad(ctx, cx - s, cy - s, cx + s, cy + s)
    ctx.strokeStyle = grad
    ctx.fillStyle = grad
    ctx.lineWidth = s * 0.08
    ctx.lineCap = 'round'

    ctx.beginPath()
    ctx.moveTo(cx - s * 0.6, cy + s * 0.62)
    ctx.lineTo(cx + s * 0.6, cy + s * 0.62)
    ctx.moveTo(cx - s * 0.6, cy + s * 0.62)
    ctx.lineTo(cx - s * 0.6, cy - s * 0.62)
    ctx.stroke()

    const bars = [
        [-0.34, 0.24],
        [-0.04, 0.46],
        [0.26, 0.72],
    ]
    ctx.lineWidth = s * 0.14
    for (const [x, h] of bars) {
        ctx.beginPath()
        ctx.moveTo(cx + s * x, cy + s * 0.58)
        ctx.lineTo(cx + s * x, cy + s * (0.58 - h))
        ctx.stroke()
    }

    // Trend arrow
    ctx.lineWidth = s * 0.07
    ctx.beginPath()
    ctx.moveTo(cx - s * 0.44, cy - s * 0.02)
    ctx.lineTo(cx + s * 0.34, cy - s * 0.56)
    ctx.stroke()
    ctx.beginPath()
    ctx.moveTo(cx + s * 0.34, cy - s * 0.56)
    ctx.lineTo(cx + s * 0.16, cy - s * 0.56)
    ctx.moveTo(cx + s * 0.34, cy - s * 0.56)
    ctx.lineTo(cx + s * 0.32, cy - s * 0.36)
    ctx.stroke()
}

function paintMedallions(ctx) {
    const cy = py(0.875)
    const r = 0.046 * CW
    const icons = [abacus, scales, chart]

    ;[0.25, 0.5, 0.75].forEach((fx, i) => {
        const cx = px(fx)

        // Drop shadow
        ctx.save()
        ctx.shadowColor = 'rgba(2, 6, 18, 0.8)'
        ctx.shadowBlur = 18
        ctx.shadowOffsetY = 4
        ctx.beginPath()
        ctx.arc(cx, cy, r, 0, Math.PI * 2)
        ctx.fillStyle = '#061124'
        ctx.fill()
        ctx.restore()

        // Inner royal disc
        const disc = ctx.createRadialGradient(cx - r * 0.3, cy - r * 0.4, r * 0.08, cx, cy, r)
        disc.addColorStop(0, '#163868')
        disc.addColorStop(0.7, '#0c2242')
        disc.addColorStop(1, '#051224')
        ctx.beginPath()
        ctx.arc(cx, cy, r, 0, Math.PI * 2)
        ctx.fillStyle = disc
        ctx.fill()

        // Embossed gold rim
        embossStroke(
            ctx,
            () => {
                ctx.beginPath()
                ctx.arc(cx, cy, r, 0, Math.PI * 2)
            },
            2.6,
            goldGrad(ctx, cx - r, cy - r, cx + r, cy + r),
            2.2,
        )

        // Inner beaded ring
        ctx.beginPath()
        ctx.arc(cx, cy, r * 0.84, 0, Math.PI * 2)
        ctx.strokeStyle = 'rgba(244, 188, 52, 0.7)'
        ctx.lineWidth = 1.3
        ctx.stroke()

        icons[i](ctx, cx, cy, r * 0.72)
    })
}

/* -------------------------------------------------------------- lock strap */

function paintStrap(ctx) {
    const x = px(0.926)
    const w = 0.11 * CW
    const h = 0.375 * CH
    const y = py(0.55) - h / 2

    ctx.save()
    ctx.shadowColor = 'rgba(2, 6, 18, 0.85)'
    ctx.shadowBlur = 24
    ctx.shadowOffsetX = 4
    ctx.shadowOffsetY = 7

    const g = ctx.createLinearGradient(x - w / 2, 0, x + w / 2, 0)
    g.addColorStop(0, '#0c2242')
    g.addColorStop(0.28, '#1e4882')
    g.addColorStop(0.65, '#153664')
    g.addColorStop(1, '#081830')
    ctx.fillStyle = g
    roundRect(ctx, x - w / 2, y, w, h, 12)
    ctx.fill()
    ctx.restore()

    // Fine gold double stitching
    ctx.save()
    ctx.setLineDash([8, 7])
    ctx.lineWidth = 1.8
    ctx.strokeStyle = 'rgba(255, 223, 112, 0.75)'
    for (const off of [-w / 2 + 12, w / 2 - 12]) {
        ctx.beginPath()
        ctx.moveTo(x + off, y + 14)
        ctx.lineTo(x + off, y + h - 14)
        ctx.stroke()
    }
    ctx.restore()

    // Edge bevel shading
    const shade = ctx.createLinearGradient(0, y, 0, y + h)
    shade.addColorStop(0, 'rgba(150, 200, 255, 0.22)')
    shade.addColorStop(0.06, 'rgba(0, 0, 0, 0)')
    shade.addColorStop(0.94, 'rgba(0, 0, 0, 0)')
    shade.addColorStop(1, 'rgba(0, 0, 0, 0.55)')
    ctx.save()
    roundRect(ctx, x - w / 2, y, w, h, 12)
    ctx.clip()
    ctx.fillStyle = shade
    ctx.fillRect(x - w / 2, y, w, h)
    ctx.restore()
}

/* ------------------------------------------------------------------- export */

export function paintCoverArt() {
    const canvas = document.createElement('canvas')
    canvas.width = CW
    canvas.height = CH
    const ctx = canvas.getContext('2d')

    paintLeather(ctx)
    paintFrames(ctx)
    paintRosettes(ctx)
    paintTitle(ctx)
    paintSubtitle(ctx)
    paintOrnament(ctx)
    paintEyePlate(ctx)
    paintCaption(ctx)
    paintCoinColumns(ctx)
    paintMedallions(ctx)
    paintStrap(ctx)

    return canvas
}

export const ART_SIZE = { width: CW, height: CH }
