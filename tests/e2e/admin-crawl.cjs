// Besucht alle Admin-Seiten unter dem Menü „Review Captain“ (Core + Pro) als Admin und meldet
// HTTP-Status, kritische Fehler, JS-Konsolenfehler. debug.log separat auswerten.
// Aufruf: NODE_PATH=/opt/node-tools/node_modules node tests/e2e/admin-crawl.cjs [label]
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const E2E_DIR = process.env.E2E_DIR || '/tmp/claude-0/wp-e2e';
const BASE = process.env.E2E_URL || 'http://127.0.0.1:8899';
const label = process.argv[2] || 'run';
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  const consoleErrors = [];
  p.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(`${p.url().replace(BASE, '')}: ${m.text().slice(0, 200)}`); });
  p.on('pageerror', (e) => consoleErrors.push(`${p.url().replace(BASE, '')}: pageerror ${String(e).slice(0, 200)}`));
  await p.goto(`${BASE}/wp-login.php`); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  await p.goto(`${BASE}/wp-admin/admin.php?page=review-captain`);
  const links = await p.$$eval('#adminmenu a[href*="page="]', (as) => [...new Set(as.map((a) => a.href).filter((h) => /page=review-captain/.test(h)))]);
  const out = [];
  for (const url of links) {
    const r = await p.goto(url);
    const html = await p.content();
    const tabs = await p.$$eval('.nav-tab-wrapper a, .rc-tabs a', (as) => as.map((a) => a.href)).catch(() => []);
    const entry = { url: url.replace(BASE, ''), status: r.status(), title: (await p.title()).slice(0, 60), critical: /critical error|Fatal error|Warning:|Notice:|Deprecated:/.test(html), notices: (await p.$$eval('.notice-error, .notice-warning', (n) => n.map((x) => x.innerText.trim().slice(0, 140)))).slice(0, 3) };
    out.push(entry);
    for (const t of tabs.filter((t) => t.startsWith(BASE) && !links.includes(t)).slice(0, 8)) {
      const r2 = await p.goto(t); const h2 = await p.content();
      out.push({ url: t.replace(BASE, ''), status: r2.status(), tab: true, critical: /critical error|Fatal error|Warning:|Notice:|Deprecated:/.test(h2) });
    }
  }
  for (const e of out) console.log(`${e.status} ${e.critical ? 'CRIT ' : ''}${e.url} ${e.title || ''} ${e.notices && e.notices.length ? '| ' + e.notices.join(' / ') : ''}`);
  for (const c of consoleErrors) console.log('CONSOLE', c);
  fs.writeFileSync(path.join(E2E_DIR, `results-admin-${label}.json`), JSON.stringify({ out, consoleErrors }, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
