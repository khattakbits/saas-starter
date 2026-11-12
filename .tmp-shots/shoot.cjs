const { chromium } = require('playwright-core')

const EXE = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const OUT = 'E:/LaraProjects/saas-starter/.tmp-shots'
const URL = 'http://127.0.0.1:8123/login'

const shot = (page, name) => page.screenshot({ path: `${OUT}/${name}.png` })

;(async () => {
    const browser = await chromium.launch({ executablePath: EXE, headless: true })
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 })
    const logs = []
    page.on('console', (m) => logs.push(`${m.type()}: ${m.text()}`))
    page.on('pageerror', (e) => logs.push(`PAGEERROR: ${e.message}`))

    await page.goto(URL, { waitUntil: 'networkidle' })
    await page.waitForTimeout(1000)
    console.log('canvas:', await page.evaluate(() => !!document.querySelector('canvas')))
    await shot(page, '01-idle')

    await page.mouse.move(120, 120)
    await page.waitForTimeout(600)
    await shot(page, '02-pointer-topleft')

    await page.click('#kb-email')
    await page.type('#kb-email', 'rafique@khattakbook.com', { delay: 10 })
    await page.waitForTimeout(500)
    await shot(page, '03-email-typing')

    await page.click('#kb-pass')
    await page.type('#kb-pass', 'secret', { delay: 15 })
    await page.waitForTimeout(600)
    await shot(page, '04-hands')

    await page.click('.reveal')
    await page.waitForTimeout(500)
    await shot(page, '05-hands-off')

    await page.fill('#kb-email', '')
    await page.fill('#kb-pass', '')
    await page.click('.submit')
    await page.waitForTimeout(260)
    await shot(page, '06-empty')

    // ---- stubbed 422 -------------------------------------------------------
    await page.fill('#kb-email', 'wrong@example.com')
    await page.fill('#kb-pass', 'nope')
    await page.evaluate(() => {
        window.fetch = async () =>
            new Response(
                JSON.stringify({
                    message: 'These credentials do not match.',
                    errors: { email: ['These credentials do not match.'] },
                }),
                { status: 422, headers: { 'Content-Type': 'application/json' } },
            )
    })
    await page.click('.submit')
    await page.waitForTimeout(520)
    await shot(page, '07-key-flight')
    await page.waitForTimeout(800)
    await shot(page, '08-key-inserted')
    await page.waitForTimeout(900)
    await shot(page, '09-failure')

    // ---- stubbed success ---------------------------------------------------
    await page.evaluate(() => {
        window.fetch = async () =>
            new Response(JSON.stringify({ component: 'Home/Index', url: 'http://127.0.0.1:8123/', props: {} }), {
                status: 200,
                headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true' },
            })
    })
    await page.fill('#kb-pass', 'secret')
    await page.click('.submit')
    await page.waitForTimeout(1150)
    await shot(page, '10-unlocked')
    await page.waitForTimeout(800)
    await shot(page, '11-shackle')
    await page.waitForTimeout(450)
    await shot(page, '12-flip-a')
    await page.waitForTimeout(400)
    await shot(page, '13-flip-b')
    await page.waitForTimeout(500)
    await shot(page, '14-flip-c')
    await page.waitForTimeout(700)
    await shot(page, '15-flipped')

    // portrait phone
    const phone = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 })
    await phone.goto(URL, { waitUntil: 'networkidle' })
    await phone.waitForTimeout(1500)
    await phone.screenshot({ path: `${OUT}/16-phone.png` })
    await phone.click('#kb-pass')
    await phone.waitForTimeout(700)
    await phone.screenshot({ path: `${OUT}/17-phone-hands.png` })

    console.log(logs.join('\n'))
    await browser.close()
})()
