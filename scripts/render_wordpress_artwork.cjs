/* Render deterministic artwork; PLAYWRIGHT_MODULE_DIR can point at a bundled runtime. */
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const dependencies = process.env.PLAYWRIGHT_MODULE_DIR;
const { chromium } = require(dependencies ? path.join(dependencies, 'playwright') : 'playwright');
const sharp = require(dependencies ? path.join(dependencies, 'sharp') : 'sharp');
const root = path.resolve(__dirname, '..');
const source = path.join(root, 'design/wordpress-org/source.html');
const out = path.join(root, 'design/wordpress-org/exports');

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const scale of [1, 2]) {
      const page = await browser.newPage({ viewport: { width: 900, height: 760 }, deviceScaleFactor: scale });
      await page.goto(pathToFileURL(source).href);
      await page.evaluate(() => document.fonts.ready);
      await page.waitForFunction(() => [...document.images].every(img => img.complete && img.naturalWidth > 0));
      const banner = await page.locator('#banner').screenshot();
      const name = scale === 1 ? 'banner-772x250.png' : 'banner-1544x500.png';
      await sharp(banner).png({ compressionLevel: 9 }).toFile(path.join(out, name));
      if (scale === 1) {
        const icon = await page.locator('#icon').screenshot();
        for (const size of [128, 256]) {
          await sharp(icon).resize(size, size).png({ compressionLevel: 9 }).toFile(path.join(out, `icon-${size}x${size}.png`));
        }
      }
      await page.close();
    }
    console.log('Rendered four WordPress.org PNG assets.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
