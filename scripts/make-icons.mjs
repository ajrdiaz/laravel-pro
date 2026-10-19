// Draws the app icons (PWA and home screen) from the sidebar logo.
//
//   npm run pwa:icons
//
// It renders a small HTML page in Chrome and saves screenshots, so it needs the same
// browser the e2e tests use: Playwright's Chromium, or the Chrome on the machine.
import { chromium } from '@playwright/test';
import { existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, 'public/icons');

// The path of resources/js/components/AppLogoIcon.vue, in a 40 x 42 box.
const logo =
    'M17.2 5.633 8.6.855 0 5.633v26.51l16.2 9 16.2-9v-8.442l7.6-4.223V9.856l-8.6-4.777-8.6 4.777V18.3l-5.6 3.111V5.633ZM38 18.301l-5.6 3.11v-6.157l5.6-3.11V18.3Zm-1.06-7.856-5.54 3.078-5.54-3.079 5.54-3.078 5.54 3.079ZM24.8 18.3v-6.157l5.6 3.111v6.158L24.8 18.3Zm-1 1.732 5.54 3.078-13.14 7.302-5.54-3.078 13.14-7.3v-.002Zm-16.2 7.89 7.6 4.222V38.3L2 30.966V7.92l5.6 3.111v16.892ZM8.6 9.3 3.06 6.222 8.6 3.143l5.54 3.08L8.6 9.3Zm21.8 15.51-13.2 7.334V38.3l13.2-7.334v-6.156ZM9.6 11.034l5.6-3.11v14.6l5.6-3.11v-14.6Z';

// Same dark as the sidebar logo tile.
const background = '#1a1a1a';

/**
 * `rounded`: the icon draws its own rounded corners (transparent outside them).
 * `logoShare`: how much of the width the logo takes. Maskable icons keep it small so
 * the operating system can crop them to a circle without cutting the logo.
 */
const icons = [
    { file: 'icon-192.png', size: 192, rounded: true, logoShare: 0.56 },
    { file: 'icon-512.png', size: 512, rounded: true, logoShare: 0.56 },
    {
        file: 'icon-maskable-512.png',
        size: 512,
        rounded: false,
        logoShare: 0.46,
    },
    // iOS rounds the corners itself, so it gets a full square.
    {
        file: '../apple-touch-icon.png',
        size: 180,
        rounded: false,
        logoShare: 0.56,
    },
];

const html = ({ size, rounded, logoShare }) => `<!doctype html>
<html>
<body style="margin:0;background:transparent">
<div style="width:${size}px;height:${size}px;background:${background};border-radius:${rounded ? size * 0.22 : 0}px;display:flex;align-items:center;justify-content:center">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" width="${size * logoShare}" height="${size * logoShare * 1.05}" fill="#fff"><path fill-rule="evenodd" clip-rule="evenodd" d="${logo}"/></svg>
</div>
</body>
</html>`;

const bundled = existsSync(chromium.executablePath());
const browser = await chromium.launch(
    bundled ? {} : { channel: process.env.PW_CHANNEL ?? 'chrome' },
);

mkdirSync(out, { recursive: true });

for (const icon of icons) {
    const page = await browser.newPage({
        viewport: { width: icon.size, height: icon.size },
    });

    await page.setContent(html(icon));
    await page.screenshot({
        path: resolve(out, icon.file),
        omitBackground: true,
    });
    await page.close();

    console.log(`public/icons/${icon.file} (${icon.size}x${icon.size})`);
}

await browser.close();
