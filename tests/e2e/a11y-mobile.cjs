// RC-137 / RC-120: axe-core, Tastatur, Mobile-Viewports, Überlagerungen, Screenshots.
// Aufruf: NODE_PATH=/opt/node-tools/node_modules node tests/e2e/a11y-mobile.cjs
// Erwartet axe-core unter $E2E_DIR/node/node_modules/axe-core. Screenshots → docs/qa/screenshots/.
const { chromium, devices } = require('playwright');
const fs = require('fs');
const path = require('path');
const E2E_DIR = process.env.E2E_DIR || '/tmp/claude-0/wp-e2e';
const BASE = process.env.E2E_URL || 'http://127.0.0.1:8899';
const SHOTS = process.env.E2E_SHOTS || path.join(__dirname, '../../docs/qa/screenshots');
const AXE = fs.readFileSync(path.join(E2E_DIR, 'node/node_modules/axe-core/axe.min.js'), 'utf8');
fs.mkdirSync(SHOTS, { recursive: true });
const pages = (process.env.E2E_PAGES || 'rc-shortcodes,rc-blocks,rc-produkt,rc-pro').split(',');
const viewports = {
  desktop: { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 },
  iphone13: { ...devices['iPhone 13'], deviceScaleFactor: 1 },
  pixel7: { ...devices['Pixel 7'], deviceScaleFactor: 1 },
};
const out = { axe: [], keyboard: {}, layout: [], shots: [] };

async function runAxe(page) {
  await page.addScriptTag({ content: AXE });
  return page.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] } });
    return r.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.map((n) => ({ target: n.target.join(' '), summary: (n.failureSummary || '').split('\n').slice(0, 3).join(' '), html: n.html.slice(0, 160) })) }));
  });
}

async function layoutChecks(page, vpName, slug) {
  return page.evaluate(({ vpName, slug }) => {
    const res = { vp: vpName, page: slug, overflowX: document.documentElement.scrollWidth > window.innerWidth + 1, scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth, overlaps: [], smallTargets: [] };
    const fixed = [...document.querySelectorAll('.rc-pro-floating, [class*="rc-"]')].filter((el) => ['fixed', 'sticky'].includes(getComputedStyle(el).position));
    const interactive = [...document.querySelectorAll('.rc-form__submit, .rc-cta__button, .rc-pro-carousel__prev, .rc-pro-carousel__next, .rc-form input, .rc-form textarea, a.rc-review__source, .rc-widget a, .rc-widget button')];
    // Überlagerung bei jeder Scrollposition prüfen: Element in die Mitte/unten scrollen und Fixed-Box vergleichen.
    for (const el of interactive) {
      el.scrollIntoView({ block: 'end' });
      const a = el.getBoundingClientRect();
      if (!a.width || !a.height) continue;
      for (const f of fixed) {
        if (f.contains(el)) continue;
        const b = f.getBoundingClientRect();
        const ix = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
        const iy = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
        if (ix * iy > 0) res.overlaps.push({ fixed: f.className.split(' ').slice(0, 3).join('.'), target: (el.className || el.name || el.tagName).toString().slice(0, 60), area: Math.round(ix * iy) });
      }
      if ((a.width < 24 || a.height < 24) && !['radio', 'checkbox', 'hidden'].includes(el.type) && !el.closest('.rc-form__hp')) res.smallTargets.push({ target: (el.className || el.name || el.tagName).toString().slice(0, 60), w: Math.round(a.width), h: Math.round(a.height) });
    }
    window.scrollTo(0, 0);
    return res;
  }, { vpName, slug });
}

async function keyboard(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/rc-shortcodes/`);
  await page.locator('#rc-form-1').scrollIntoViewIfNeeded();
  // Von der ersten Formular-Nachbarschaft aus tabben: Fokus vor das Formular setzen.
  await page.evaluate(() => { const f = document.querySelector('#rc-form-1'); const s = document.createElement('a'); s.href = '#'; s.id = 'e2e-start'; s.textContent = 'start'; f.parentNode.insertBefore(s, f); s.focus(); });
  const order = [];
  for (let i = 0; i < 25; i++) {
    await page.keyboard.press('Tab');
    const info = await page.evaluate(() => { const el = document.activeElement; if (!el || !el.closest('#rc-form-1')) return null; const cs = getComputedStyle(el); return { tag: el.tagName.toLowerCase(), name: el.name || '', type: el.type || '', id: el.id, outline: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0, boxShadow: cs.boxShadow !== 'none' }; });
    if (!info) break;
    order.push(info);
    if (info.type === 'submit') break;
  }
  const r = { tabOrder: order.map((o) => `${o.tag}${o.type ? ':' + o.type : ''}${o.name ? '[' + o.name + ']' : ''}`), focusVisible: order.map((o) => o.outline || o.boxShadow), honeypotFocusable: order.some((o) => o.name === 'rc_website') };
  // Formular komplett per Tastatur ausfüllen und absenden
  await page.goto(`${BASE}/rc-shortcodes/`);
  await page.evaluate(() => { const f = document.querySelector('#rc-form-1'); const s = document.createElement('a'); s.href = '#'; s.textContent = 'start'; f.parentNode.insertBefore(s, f); s.focus(); });
  await page.keyboard.press('Tab'); // erstes Radio (5 Sterne)
  await page.keyboard.press('ArrowDown'); // → 4 Sterne
  const radio = await page.evaluate(() => document.activeElement.value + '/' + document.activeElement.checked);
  await page.keyboard.press('Tab'); await page.keyboard.type('Per Tastatur');
  await page.keyboard.press('Tab'); await page.keyboard.type('Komplett mit der Tastatur ausgefüllt und abgeschickt.');
  await page.keyboard.press('Tab'); await page.keyboard.type('Tara Tastatur');
  await page.keyboard.press('Tab'); await page.keyboard.type(`tara.${Date.now()}@example.test`);
  await page.keyboard.press('Tab'); await page.keyboard.press('Space');
  await page.waitForTimeout(3500);
  await page.keyboard.press('Tab');
  const onSubmit = await page.evaluate(() => document.activeElement.className);
  await Promise.all([page.waitForNavigation(), page.keyboard.press('Enter')]);
  r.keyboardSubmit = { radioAfterArrow: radio, focusBeforeEnter: onSubmit, url: page.url().replace(BASE, '') };
  // Fehlerfall: Fokusführung nach Server-Validierung
  await page.goto(`${BASE}/rc-shortcodes/`);
  await page.waitForTimeout(3500);
  await Promise.all([page.waitForNavigation(), page.locator('#rc-form-1 .rc-form__submit').click()]);
  r.afterError = await page.evaluate(() => { const el = document.activeElement; const alert = document.querySelector('#rc-form-1 [role="alert"], #rc-form-1 .rc-form__errors, #rc-form-1 [aria-live]'); return { active: el ? (el.id || el.tagName) : null, hasAlertRegion: !!alert, alertTag: alert ? alert.outerHTML.slice(0, 160) : null, invalidMarked: document.querySelectorAll('#rc-form-1 [aria-invalid="true"]').length }; });
  await page.locator('#rc-form-1').screenshot({ path: path.join(SHOTS, 'form-errors-desktop.png') });
  out.shots.push('form-errors-desktop.png');
  // Reduzierte Bewegung (Karussell)
  const rm = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
  const p2 = await rm.newPage();
  await p2.goto(`${BASE}/rc-pro/`);
  r.reducedMotion = await p2.evaluate(() => { const t = document.querySelector('.rc-pro-carousel__track'); return t ? { scrollBehavior: getComputedStyle(t).scrollBehavior, transition: getComputedStyle(t).transitionDuration, autoplayAttr: t.closest('[data-autoplay]') ? t.closest('[data-autoplay]').dataset.autoplay : null } : null; });
  // Karussell per Tastatur
  const next = p2.locator('.rc-pro-carousel__next').first();
  if (await next.count()) { await next.focus(); const before = await p2.evaluate(() => document.querySelector('.rc-pro-carousel__track').scrollLeft); await p2.keyboard.press('Enter'); await p2.waitForTimeout(600); r.carouselKeyboard = { before, after: await p2.evaluate(() => document.querySelector('.rc-pro-carousel__track').scrollLeft), status: await p2.locator('.rc-pro-carousel__status').first().innerText().catch(() => '') }; }
  await rm.close();
  out.keyboard = r;
  await ctx.close();
}

(async () => {
  const browser = await chromium.launch();
  for (const [vpName, opts] of Object.entries(viewports)) {
    const ctx = await browser.newContext(opts);
    const page = await ctx.newPage();
    for (const slug of pages) {
      const resp = await page.goto(`${BASE}/${slug}/`);
      if (!resp || resp.status() !== 200) { out.layout.push({ vp: vpName, page: slug, error: resp && resp.status() }); continue; }
      if (vpName === 'desktop' || slug === 'rc-shortcodes' || slug === 'rc-pro') {
        const v = await runAxe(page);
        out.axe.push({ vp: vpName, page: slug, violations: v });
      }
      out.layout.push(await layoutChecks(page, vpName, slug));
      const name = `${slug}-${vpName}.png`;
      await page.screenshot({ path: path.join(SHOTS, name), fullPage: vpName !== 'desktop', animations: 'disabled' });
      out.shots.push(name);
    }
    await ctx.close();
  }
  await keyboard(browser);
  fs.writeFileSync(path.join(E2E_DIR, 'results-a11y.json'), JSON.stringify(out, null, 2));
  // Kurzfassung
  const sev = {};
  for (const a of out.axe) for (const v of a.violations) { const k = `${v.impact} ${v.id}`; sev[k] = sev[k] || new Set(); sev[k].add(`${a.page}@${a.vp}`); }
  for (const [k, s] of Object.entries(sev).sort()) console.log('AXE', k, [...s].join(', '));
  for (const l of out.layout) if (l.overflowX || (l.overlaps && l.overlaps.length) || (l.smallTargets && l.smallTargets.length)) console.log('LAYOUT', l.vp, l.page, 'overflowX=' + l.overflowX, JSON.stringify(l.overlaps.slice(0, 4)), JSON.stringify(l.smallTargets.slice(0, 4)));
  console.log('KEYBOARD', JSON.stringify(out.keyboard));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
