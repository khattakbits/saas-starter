const { chromium } = require('playwright-core')
const OUT = 'E:/LaraProjects/saas-starter/.tmp-shots'
;(async () => {
  const b = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true })
  const p = await b.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 })
  const errs = []
  p.on('pageerror', (e) => errs.push(e.message))
  await p.goto('http://127.0.0.1:8123/login', { waitUntil: 'networkidle' })
  await p.waitForTimeout(900)
  await p.fill('#kb-email', 'a@b.com')
  await p.fill('#kb-pass', 'secret')
  await p.evaluate(() => {
    window.fetch = async () => new Response(JSON.stringify({ component: 'Home/Index', url: 'http://127.0.0.1:8123/', props: {} }), { status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true' } })
    window.__t0 = performance.now()
    window.__frames = []
    const loop = () => { window.__frames.push(Math.round(performance.now() - window.__t0)); requestAnimationFrame(loop) }
    requestAnimationFrame(loop)
  })
  await p.click('.submit')
  for (let i = 0; i < 9; i += 1) {
    await p.waitForTimeout(220)
    const t = await p.evaluate(() => Math.round(performance.now() - window.__t0))
    await p.screenshot({ path: `${OUT}/f${i}-${t}.png` })
  }
  console.log('errors:', errs)
  await b.close()
})()
