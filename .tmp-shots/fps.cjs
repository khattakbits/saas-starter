const { chromium } = require('playwright-core')

const EXE = 'C:/Program Files/Google/Chrome/Application/chrome.exe'

;(async () => {
    for (const args of [[], ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader']]) {
        const browser = await chromium.launch({ executablePath: EXE, headless: true, args })
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
        await page.goto('http://127.0.0.1:8123/login', { waitUntil: 'networkidle' })
        await page.waitForTimeout(1200)
        const fps = await page.evaluate(
            () =>
                new Promise((resolve) => {
                    let n = 0
                    const t0 = performance.now()
                    const tick = () => {
                        n += 1
                        if (performance.now() - t0 < 1000) requestAnimationFrame(tick)
                        else resolve(n)
                    }
                    requestAnimationFrame(tick)
                }),
        )
        const gl = await page.evaluate(() => {
            const c = document.createElement('canvas')
            const g = c.getContext('webgl2') || c.getContext('webgl')
            if (!g) return 'none'
            const d = g.getExtension('WEBGL_debug_renderer_info')
            return d ? g.getParameter(d.UNMASKED_RENDERER_WEBGL) : 'unknown'
        })
        console.log(JSON.stringify(args), 'fps~', fps, 'gl:', gl)
        await browser.close()
    }
})()
