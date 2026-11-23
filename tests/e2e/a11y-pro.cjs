// RC-137: Pro-Widgets (Karussell, Plattformleiste, Testimonial-Wall, Trustmarks, Provider-Embed-Platzhalter,
// schwebendes Badge) – axe-core, Tastatur (Fokus, Reihenfolge, Badge schließen), reduced motion, iPhone 13/Pixel 7
// (überdeckt das Badge Formulare?). Seiten aus tests/e2e/php/suite/widgets.php (+ /e2e-feedback/ aus form.php).
// Aufruf: E2E_DIR=… NODE_PATH=/opt/node-tools/node_modules node tests/e2e/a11y-pro.cjs
// Ergebnis: $E2E_DIR/results-a11y-pro.json, Screenshots → E2E_SHOTS (Standard docs/qa/screenshots/2026-11-23).
const { chromium, devices } = require('playwright');
const fs = require('fs');
const path = require('path');
const E2E_DIR = process.env.E2E_DIR || '/tmp/claude-0/wp-e2e';
const BASE = process.env.E2E_URL || 'http://127.0.0.1:8899';
const SHOTS = process.env.E2E_SHOTS || path.join(__dirname, '../../docs/qa/screenshots/2026-11-23');
const AXE = fs.readFileSync(process.env.E2E_AXE || path.join(E2E_DIR, 'node/node_modules/axe-core/axe.min.js'), 'utf8');
fs.mkdirSync(SHOTS, { recursive: true });
const WIDGETS = { carousel: '.rc-pro-carousel', platform_bar: '.rc-pro-bar', testimonial_wall: '.rc-pro-testimonial, .rc-pro-wall, [class*="testimonial"]', trustmarks: '.rc-pro-trust', provider_embed: '.rc-pro-provider-embed', floating_badge: '.rc-pro-floating' };
const out = { axe: [], keyboard: {}, motion: {}, mobile: [], shots: [], external: [] };
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function axe(page, include) {
  await page.addScriptTag({ content: AXE });
  return page.evaluate(async ({ include, TAGS }) => {
    const ctx = include ? { include: [[include]] } : document;
    const r = await window.axe.run(ctx, { runOnly: { type: 'tag', values: TAGS } });
    return r.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.map((n) => ({ target: n.target.join(' '), html: n.html.slice(0, 200), summary: (n.failureSummary || '').split('\n').slice(0, 3).join(' ') })) }));
  }, { include, TAGS });
}

function owner(target) {
  return /rc-pro|rc-widget|rc-card|rc-source|rc-visually|rc-date|rc-badge|rc-review/.test(target) ? 'Review Captain' : 'Theme/Seite';
}

async function focusInfo(page) {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return null;
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const widget = el.closest('.rc-pro-carousel, .rc-pro-bar, .rc-pro-trust, .rc-pro-provider-embed, .rc-pro-floating, .rc-pro, .e2e-contact, .rc-form');
    return {
      tag: el.tagName.toLowerCase(),
      text: (el.getAttribute('aria-label') || el.innerText || el.value || el.name || '').trim().replace(/\s+/g, ' ').slice(0, 50),
      widget: widget ? widget.className.split(' ').filter((c) => c.startsWith('rc-pro-') || c === 'e2e-contact' || c === 'rc-form')[0] || widget.className.split(' ')[0] : '',
      visible: (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || cs.boxShadow !== 'none',
      inViewport: r.bottom > 0 && r.top < window.innerHeight && r.width > 0,
      y: Math.round(r.top + window.scrollY),
    };
  });
}

async function keyboard(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  page.on('request', (r) => { if (!r.url().startsWith(BASE)) out.external.push({ phase: 'keyboard', url: r.url() }); });
  await page.goto(`${BASE}/rc-pro-widgets/`);
  const order = [];
  for (let i = 0; i < 80; i++) {
    await page.keyboard.press('Tab');
    const f = await focusInfo(page);
    if (!f) break;
    if (order.length && order[0].tag === f.tag && order[0].text === f.text && order[0].y === f.y) break;
    order.push(f);
  }
  const rc = order.filter((o) => o.widget && o.widget !== 'e2e-contact');
  const ys = order.filter((o) => o.widget && o.widget !== 'rc-pro-floating').map((o) => o.y);
  out.keyboard.tabOrder = order.map((o) => `${o.widget || '-'}:${o.tag}:${o.text}`);
  out.keyboard.allVisibleFocus = rc.every((o) => o.visible);
  out.keyboard.noVisibleFocus = rc.filter((o) => !o.visible).map((o) => `${o.widget}:${o.tag}:${o.text}`);
  out.keyboard.domOrderFollowsLayout = ys.every((y, i) => i === 0 || y >= ys[i - 1] - 5);
  out.keyboard.badgeLastInOrder = order.length > 0 && order.filter((o) => o.widget === 'rc-pro-floating').length > 0 && order.findIndex((o) => o.widget === 'rc-pro-floating') > order.findIndex((o) => o.widget === 'e2e-contact');

  // Carousel: buttons and arrow keys.
  const track = page.locator('.rc-pro-carousel__track').first();
  const next = page.locator('.rc-pro-carousel__next').first();
  await next.focus();
  const s0 = await track.evaluate((t) => t.scrollLeft);
  await page.keyboard.press('Enter');
  await page.waitForTimeout(700);
  const s1 = await track.evaluate((t) => t.scrollLeft);
  const status1 = await page.locator('.rc-pro-carousel__status').first().innerText().catch(() => '');
  await track.focus().catch(() => {});
  const trackFocusable = await track.evaluate((t) => document.activeElement === t || t.contains(document.activeElement));
  await page.keyboard.press('End'); await page.waitForTimeout(700);
  const sEnd = await track.evaluate((t) => t.scrollLeft);
  await page.keyboard.press('Home'); await page.waitForTimeout(700);
  const sHome = await track.evaluate((t) => t.scrollLeft);
  const live = await page.locator('.rc-pro-carousel__status').first().evaluate((e) => ({ live: e.getAttribute('aria-live'), role: e.getAttribute('role') })).catch(() => null);
  const prevDisabledAtStart = await page.locator('.rc-pro-carousel__prev').first().evaluate((b) => b.disabled || b.getAttribute('aria-disabled')).catch(() => null);
  out.keyboard.carousel = { enterOnNext: [s0, s1], status: status1, trackFocusable, end: sEnd, home: sHome, live, prevDisabledAtStart };

  // Provider embed: placeholder button, no provider request before the click.
  const load = page.locator('.rc-pro-provider-embed__load').first();
  out.keyboard.providerEmbed = { button: await load.evaluate((b) => ({ tag: b.tagName.toLowerCase(), type: b.type, text: b.innerText.trim().slice(0, 80) })).catch(() => null), externalBeforeClick: out.external.filter((e) => /trustpilot/.test(e.url)).length };
  await load.focus();
  await page.keyboard.press('Enter');
  await page.waitForTimeout(800);
  out.keyboard.providerEmbed.externalAfterClick = out.external.filter((e) => /trustpilot/.test(e.url)).map((e) => e.url.slice(0, 80));
  out.keyboard.providerEmbed.focusAfterClick = await focusInfo(page);

  // Floating badge: open, close with Enter, close with Escape, focus stays.
  const summary = page.locator('.rc-pro-floating__summary').first();
  await summary.focus();
  const st = async () => page.evaluate(() => { const d = document.querySelector('.rc-pro-floating__details'); return { open: d.open, focusOnSummary: document.activeElement === d.querySelector('summary') }; });
  const before = await st();
  await page.keyboard.press('Enter'); const afterEnter = await st();
  await page.screenshot({ path: path.join(SHOTS, 'badge-open-desktop.png'), clip: await page.locator('.rc-pro-floating').first().boundingBox().then((b) => ({ x: Math.max(0, b.x - 10), y: Math.max(0, b.y - 10), width: Math.min(1280, b.width + 20), height: Math.min(900, b.height + 20) })) });
  out.shots.push('badge-open-desktop.png');
  await page.keyboard.press('Escape'); const afterEscape = await st();
  if (afterEscape.open) await page.keyboard.press('Enter');
  const afterClose = await st();
  await page.keyboard.press('Space'); const afterSpace = await st();
  await page.keyboard.press('Space');
  const summaryName = await summary.evaluate((s) => s.innerText.replace(/\s+/g, ' ').trim());
  const ariaExpanded = await summary.getAttribute('aria-expanded');
  out.keyboard.floating = { before, afterEnter, afterEscape, afterClose, afterSpace, summaryName, ariaExpanded };
  await ctx.close();
}

async function motion(browser) {
  for (const reducedMotion of ['no-preference', 'reduce']) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/rc-pro-widgets/`);
    out.motion[reducedMotion] = await page.evaluate(() => {
      const all = [...document.querySelectorAll('.rc-pro, .rc-pro *')];
      const anim = all.filter((e) => { const cs = getComputedStyle(e); return (cs.animationName !== 'none' && parseFloat(cs.animationDuration) > 0) || parseFloat(cs.transitionDuration) > 0; }).map((e) => `${e.className.toString().split(' ')[0]} ${getComputedStyle(e).transitionProperty}/${getComputedStyle(e).transitionDuration}/${getComputedStyle(e).animationName}`);
      const t = document.querySelector('.rc-pro-carousel__track');
      return { carouselScrollBehavior: t ? getComputedStyle(t).scrollBehavior : null, animated: [...new Set(anim)].slice(0, 10), autoplay: !!document.querySelector('[data-autoplay="1"], [data-autoplay="true"]') };
    });
    await ctx.close();
  }
}

async function mobile(browser) {
  for (const [name, dev] of [['iphone13', devices['iPhone 13']], ['pixel7', devices['Pixel 7']]]) {
    const ctx = await browser.newContext({ ...dev, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    for (const slug of ['rc-pro-widgets', 'e2e-feedback', 'rc-pro-form']) {
      const resp = await page.goto(`${BASE}/${slug}/`);
      if (!resp || resp.status() !== 200) { out.mobile.push({ vp: name, page: slug, error: resp && resp.status() }); continue; }
      await page.waitForTimeout(300);
      const res = await page.evaluate(async () => {
        const badge = document.querySelector('.rc-pro-floating');
        const det = document.querySelector('.rc-pro-floating__details');
        const r = { badge: !!badge, startsOpen: det ? det.open : null, overflowX: document.documentElement.scrollWidth > window.innerWidth + 1, overlaps: [], overlapsWhileFocused: [], smallTargets: [] };
        if (badge) { const b = badge.getBoundingClientRect(); r.badgeBox = [Math.round(b.width), Math.round(b.height)]; }
        const fields = [...document.querySelectorAll('form input:not([type=hidden]), form textarea, form select, form button, form [type=submit]')].filter((e) => !e.closest('.rc-pro-floating') && !e.closest('.rc-form__hp') && e.offsetParent !== null);
        const visible = (el) => { const cs = getComputedStyle(el); return cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05 && cs.display !== 'none'; };
        const inter = (a, b) => Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
        for (const el of fields) {
          el.scrollIntoView({ block: 'end' });
          await new Promise((res) => setTimeout(res, 30));
          const a = el.getBoundingClientRect();
          if (badge && visible(badge)) { const area = inter(a, badge.getBoundingClientRect()); if (area > 0) r.overlaps.push({ field: el.name || el.id || el.tagName, area: Math.round(area) }); }
          if (['input', 'textarea', 'select'].includes(el.tagName.toLowerCase())) {
            el.focus();
            await new Promise((res) => setTimeout(res, 350));
            if (badge && visible(badge)) { const area = inter(el.getBoundingClientRect(), badge.getBoundingClientRect()); if (area > 0) r.overlapsWhileFocused.push({ field: el.name || el.id || el.tagName, area: Math.round(area), opacity: getComputedStyle(badge).opacity }); }
            el.blur();
          }
          if ((a.width < 24 || a.height < 24) && !['radio', 'checkbox'].includes(el.type)) r.smallTargets.push({ field: el.name || el.id || el.tagName, w: Math.round(a.width), h: Math.round(a.height) });
        }
        for (const el of document.querySelectorAll('.rc-pro a, .rc-pro button, .rc-pro summary')) { const a = el.getBoundingClientRect(); if (a.width && (a.width < 24 || a.height < 24) && getComputedStyle(el).display !== 'inline') r.smallTargets.push({ field: el.className.toString().split(' ')[0] || el.tagName, w: Math.round(a.width), h: Math.round(a.height) }); }
        window.scrollTo(0, 0);
        return r;
      });
      res.vp = name; res.page = slug;
      if (slug === 'rc-pro-widgets') res.axe = await axe(page, '.entry-content, main');
      out.mobile.push(res);
      if (slug === 'rc-pro-widgets' && name === 'iphone13') {
        await page.locator('.e2e-contact textarea').scrollIntoViewIfNeeded();
        await page.evaluate(() => document.querySelector('.e2e-contact button').scrollIntoView({ block: 'end' }));
        await page.screenshot({ path: path.join(SHOTS, 'badge-iphone13-kontaktformular.png') });
        out.shots.push('badge-iphone13-kontaktformular.png');
      }
      if (slug === 'e2e-feedback' && name === 'pixel7') {
        await page.evaluate(() => { const f = document.querySelectorAll('form textarea, form input:not([type=hidden])'); if (f.length) f[f.length - 1].scrollIntoView({ block: 'end' }); });
        await page.screenshot({ path: path.join(SHOTS, 'badge-pixel7-formcaptain.png') });
        out.shots.push('badge-pixel7-formcaptain.png');
      }
    }
    await ctx.close();
  }
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  page.on('request', (r) => { if (!r.url().startsWith(BASE)) out.external.push({ phase: 'load', url: r.url() }); });
  await page.goto(`${BASE}/rc-pro-widgets/`);
  out.present = await page.evaluate((W) => Object.fromEntries(Object.entries(W).map(([k, s]) => [k, document.querySelectorAll(s).length])), WIDGETS);
  out.axe.push({ scope: 'page', violations: await axe(page) });
  out.axe.push({ scope: '.rc-pro', violations: await axe(page, '.rc-pro') });
  await page.screenshot({ path: path.join(SHOTS, 'pro-widgets-desktop.png'), fullPage: true, animations: 'disabled' });
  out.shots.push('pro-widgets-desktop.png');
  await page.evaluate(() => { const d = document.querySelector('.rc-pro-floating__details'); if (d) d.open = true; });
  out.axe.push({ scope: 'badge-open', violations: await axe(page, '.rc-pro-floating') });
  await ctx.close();
  await keyboard(browser);
  await motion(browser);
  await mobile(browser);
  fs.writeFileSync(path.join(E2E_DIR, 'results-a11y-pro.json'), JSON.stringify(out, null, 2));
  console.log('PRESENT', JSON.stringify(out.present));
  for (const a of out.axe) for (const v of a.violations) console.log('AXE', a.scope, v.impact, v.id, [...new Set(v.nodes.map((n) => owner(n.target)))].join('/'), v.nodes.map((n) => n.target).slice(0, 3).join(' | '));
  for (const m of out.mobile) { console.log('MOBILE', m.vp, m.page, JSON.stringify({ badge: m.badge, startsOpen: m.startsOpen, box: m.badgeBox, overflowX: m.overflowX, overlaps: m.overlaps, overlapsWhileFocused: m.overlapsWhileFocused, small: m.smallTargets })); for (const v of m.axe || []) console.log('AXE', m.vp, v.impact, v.id, v.nodes.map((n) => n.target).slice(0, 3).join(' | ')); }
  console.log('KEYBOARD', JSON.stringify(out.keyboard, null, 1));
  console.log('MOTION', JSON.stringify(out.motion));
  console.log('EXTERNAL', JSON.stringify(out.external.slice(0, 10)));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
