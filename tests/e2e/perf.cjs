// RC-136 / TEC-014: Antwortzeiten nach `wp review-captain dev seed`. SQLite + php -S (ein Prozess) ≠ MySQL/PHP-FPM!
// Aufruf: NODE_PATH=/opt/node-tools/node_modules node tests/e2e/perf.cjs
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const E2E_DIR = process.env.E2E_DIR || '/tmp/claude-0/wp-e2e';
const BASE = process.env.E2E_URL || 'http://127.0.0.1:8899';
const REPEAT = 3;
const res = [];
async function timeGet(req, label, url) {
  const t = [];
  let size = 0, status = 0, queries = '';
  for (let i = 0; i < REPEAT; i++) {
    const s = Date.now(); const r = await req.get(url); const body = await r.text(); t.push(Date.now() - s); size = body.length; status = r.status();
  }
  t.sort((a, b) => a - b);
  res.push({ label, url: url.replace(BASE, ''), status, bytes: size, ms_min: t[0], ms_median: t[Math.floor(t.length / 2)], ms_max: t[t.length - 1] });
  console.log(`${label}: ${status} median ${t[Math.floor(t.length / 2)]} ms (min ${t[0]}, max ${t[t.length - 1]}), ${size} B`);
}
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ acceptDownloads: true });
  const p = await ctx.newPage();
  await p.goto(`${BASE}/wp-login.php`); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  const anon = (await browser.newContext()).request;
  await timeGet(anon, 'Frontend Startseite (Referenz)', `${BASE}/`);
  await timeGet(anon, 'Shortcode-Seite (12 Reviews, Grid+CTA+Formular)', `${BASE}/rc-shortcodes/`);
  await timeGet(anon, 'Shortcode-Seite paginiert (24/Seite)', `${BASE}/rc-perf/`);
  await timeGet(anon, 'Produktseite (Kontextfilter)', `${BASE}/rc-produkt/`);
  await timeGet(ctx.request, 'Admin-Liste Reviews (Seite 1)', `${BASE}/wp-admin/admin.php?page=review-captain-reviews`);
  await timeGet(ctx.request, 'Admin-Liste Seite 500', `${BASE}/wp-admin/admin.php?page=review-captain-reviews&paged=500`);
  await timeGet(ctx.request, 'Admin-Liste Suche "Kaffee"', `${BASE}/wp-admin/admin.php?page=review-captain-reviews&s=Kaffee`);
  await timeGet(ctx.request, 'Admin-Liste Status ausstehend', `${BASE}/wp-admin/admin.php?page=review-captain-reviews&status=pending`);
  await timeGet(ctx.request, 'Dashboard', `${BASE}/wp-admin/admin.php?page=review-captain`);
  await timeGet(ctx.request, 'Diagnose', `${BASE}/wp-admin/admin.php?page=review-captain-diagnostics`);
  // Export (alle Reviews) über das Admin-Formular
  await p.goto(`${BASE}/wp-admin/admin.php?page=review-captain-import-export`);
  const s = Date.now();
  const [dl] = await Promise.all([p.waitForEvent('download', { timeout: 300000 }), p.locator('form:has(input[name="action"][value="review_captain_export"]) [type="submit"]').first().click()]);
  const f = path.join(E2E_DIR, 'artifacts/export-perf.csv'); await dl.saveAs(f);
  const ms = Date.now() - s; const lines = fs.readFileSync(f, 'utf8').trim().split(/\r?\n/).length - 1;
  res.push({ label: 'CSV-Export alle Reviews', ms_median: ms, rows: lines, bytes: fs.statSync(f).size });
  console.log(`CSV-Export: ${ms} ms, ${lines} Zeilen, ${fs.statSync(f).size} B`);
  fs.writeFileSync(path.join(E2E_DIR, 'results-perf.json'), JSON.stringify(res, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
