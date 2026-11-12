const { chromium } = require('playwright-core')
;(async () => {
  const b = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true })
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } })
  await p.goto('http://127.0.0.1:8123/login', { waitUntil: 'networkidle' })
  await p.waitForTimeout(800)
  console.log('reduced:', await p.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches))
  await b.close()
})()
