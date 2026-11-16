// Funktions-E2E Core im echten Browser (Playwright/Chromium).
// Aufruf: node tests/e2e/functional.cjs   (Server läuft, setup.sh + php/content.php ausgeführt)
// Ergebnis: $E2E_DIR/results-functional.json, Artefakte (CSV, ZIP) unter $E2E_DIR/artifacts/.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const E2E_DIR = process.env.E2E_DIR || '/tmp/claude-0/wp-e2e';
const BASE = process.env.E2E_URL || 'http://127.0.0.1:8899';
const ART = path.join(E2E_DIR, 'artifacts');
fs.mkdirSync(ART, { recursive: true });
const results = [];
const rec = (id, ok, detail) => { results.push({ id, ok, detail }); console.log(`${ok === true ? 'PASS' : ok === false ? 'FAIL' : 'INFO'} ${id}: ${detail}`); };
const wpcli = (...args) => execFileSync('php', ['-d', 'memory_limit=1G', path.join(E2E_DIR, 'composer/vendor/wp-cli/wp-cli/php/boot-fs.php'), `--path=${path.join(E2E_DIR, 'wp')}`, '--allow-root', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
const SQL = (q) => execFileSync('php', [path.join(__dirname, 'php/sql.php'), path.join(E2E_DIR, 'wp/wp-content/database/.ht.sqlite'), q], { encoding: 'utf8' }).trim();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const RUN = Date.now().toString(36);

async function submitForm(page, url, data) {
  await page.goto(url);
  const form = page.locator('form.rc-form__form').first();
  await form.locator(`input[name="rc_rating"][value="${data.rating}"]`).check();
  if (data.title) await form.locator('input[name="rc_title"]').fill(data.title);
  await form.locator('textarea[name="rc_body"]').fill(data.body);
  await form.locator('input[name="rc_name"]').fill(data.name);
  await form.locator('input[name="rc_email"]').fill(data.email);
  await form.locator('input[name="rc_consent"]').check();
  await sleep(3500); // MIN_SECONDS = 3 (Zeitstempel-Prüfung)
  await Promise.all([page.waitForNavigation(), form.locator('button[type="submit"]').click()]);
  return page.url();
}

(async () => {
  const browser = await chromium.launch();
  const anon = await browser.newContext({ deviceScaleFactor: 1 });
  const p = await anon.newPage();

  // 1. Einreichen per Formular (anonym)
  const t1 = `E2E Shortcode ${RUN}`;
  const u1 = await submitForm(p, `${BASE}/rc-shortcodes/`, { rating: 5, title: t1, body: 'Sehr guter Service, schnelle Lieferung und freundliche Beratung.', name: 'Erika Mustermann', email: `erika.${RUN}@example.test` });
  const msg1 = await p.locator('#rc-form-1').innerText().catch(() => '');
  rec('form.submit', /rc_submitted=1/.test(u1), `Redirect ${u1.replace(BASE, '')}; Meldung: ${msg1.split('\n')[0]}`);
  const t2 = `E2E Produkt ${RUN}`;
  const u2 = await submitForm(p, `${BASE}/rc-produkt/`, { rating: 4, title: t2, body: 'Die Kaffeemühle mahlt gleichmäßig und ist angenehm leise.', name: 'Max Muster', email: `max.${RUN}@example.test` });
  rec('form.submit.product', /rc_submitted=1/.test(u2), `Redirect ${u2.replace(BASE, '')}`);
  // Negativfälle: zu schnell, fehlende Felder
  await p.goto(`${BASE}/rc-shortcodes/`);
  await p.locator('input[name="rc_rating"][value="3"]').first().check();
  await p.locator('textarea[name="rc_body"]').first().fill('kurz');
  await Promise.all([p.waitForNavigation(), p.locator('button.rc-form__submit').first().click()]);
  rec('form.too_fast', /rc_error=too_fast/.test(p.url()), `Sofortiges Absenden → ${p.url().replace(BASE, '')}`);
  await p.goto(`${BASE}/rc-shortcodes/`);
  await p.locator('textarea[name="rc_body"]').first().fill('kurz');
  await sleep(3500);
  await Promise.all([p.waitForNavigation(), p.locator('button.rc-form__submit').first().click()]);
  const errUrl = p.url();
  const errText = await p.locator('#rc-form-1').innerText().catch(() => '');
  const kept = await p.locator('textarea[name="rc_body"]').first().inputValue().catch(() => '');
  rec('form.validation', /rc_error=/.test(errUrl) && /rating/.test(errUrl), `Fehlercodes ${decodeURIComponent((errUrl.match(/rc_error=([^&#]+)/) || [])[1] || '')}; Entwurf erhalten: ${kept === 'kurz'}; Fehlertext sichtbar: ${/Bitte wählen/.test(errText)}`);
  const focusInfo = await p.evaluate(() => document.activeElement && (document.activeElement.id || document.activeElement.className || document.activeElement.tagName));
  rec('form.validation.focus', null, `Fokus nach Fehler-Reload: ${focusInfo}`);
  // Nonce-Manipulation per Request
  const r = await anon.request.post(`${BASE}/wp-admin/admin-post.php`, { form: { action: 'review_captain_submit', _rc_nonce: 'deadbeef00', rc_form: '1', rc_rating: '5', rc_body: 'x'.repeat(30), rc_name: 'A', rc_email: 'a@example.test', rc_consent: '1', rc_return: `${BASE}/rc-shortcodes/` }, maxRedirects: 0 });
  rec('form.nonce', /rc_error=nonce/.test(r.headers()['location'] || ''), `Ungültige Nonce → ${r.status()} ${(r.headers()['location'] || '').replace(BASE, '')}`);
  const before = await p.goto(`${BASE}/rc-shortcodes/`).then(() => p.content());
  rec('grid.pending_hidden', !before.includes(t1), 'Ausstehende Review erscheint nicht im Grid');

  // 2. Moderation im Admin (echter Login)
  const admin = await browser.newContext({ deviceScaleFactor: 1, acceptDownloads: true });
  const a = await admin.newPage();
  await a.goto(`${BASE}/wp-login.php`);
  await a.fill('#user_login', 'admin'); await a.fill('#user_pass', 'admin');
  await Promise.all([a.waitForNavigation(), a.click('#wp-submit')]);
  rec('admin.login', a.url().includes('/wp-admin'), a.url().replace(BASE, ''));
  await a.goto(`${BASE}/wp-admin/admin.php?page=review-captain-reviews&status=pending`);
  const row = a.locator('tr', { hasText: t1 }).first();
  const approveHref = await row.locator('.row-actions .approve a, .row-actions a:has-text("Freigeben")').first().getAttribute('href').catch(() => null);
  if (approveHref) {
    await a.goto(new URL(approveHref, BASE).href);
    rec('admin.approve', true, `Row-Action "Freigeben" → ${a.url().replace(BASE, '')}`);
  } else rec('admin.approve', false, 'Kein Freigeben-Link in der Zeile gefunden');
  // Bulk-Freigabe für die Produkt-Review
  await a.goto(`${BASE}/wp-admin/admin.php?page=review-captain-reviews&status=pending`);
  const row2 = a.locator('tr', { hasText: t2 }).first();
  if (await row2.count()) {
    await row2.locator('input[type="checkbox"]').first().check();
    await a.selectOption('#bulk-action-selector-top', 'approve');
    await Promise.all([a.waitForNavigation(), a.click('#doaction')]);
    rec('admin.bulk_approve', true, `Bulk "approve" → ${a.url().replace(BASE, '')}`);
  } else rec('admin.bulk_approve', false, 'Produkt-Review nicht in "Ausstehend" gefunden');
  const st = SQL(`SELECT title,status,context_type,context_id FROM wp_review_captain_reviews WHERE title IN ('${t1}','${t2}')`);
  rec('admin.status_db', /approved[\s\S]*approved/.test(st), st.replace(/\n/g, ' | '));
  const adminErrors = await a.locator('.notice-error').allInnerTexts();
  if (adminErrors.length) rec('admin.notices', null, adminErrors.join(' / ').slice(0, 300));

  // 3. Shortcode, Block, CTA rendern
  const sc = await p.goto(`${BASE}/rc-shortcodes/`).then(() => p.content());
  rec('render.shortcode', sc.includes(t1) && /rc-widget--grid/.test(sc), `Grid enthält freigegebene Review: ${sc.includes(t1)}`);
  rec('render.cta', /rc-cta__button/.test(sc), `CTA-Button: ${/rc-cta__button/.test(sc)}`);
  const bl = await p.goto(`${BASE}/rc-blocks/`).then(() => p.content());
  rec('render.block', bl.includes(t1) && /rc-form__form/.test(bl), `Block-Seite: Review ${bl.includes(t1)}, Formular ${/rc-form__form/.test(bl)}`);
  const pr = await p.goto(`${BASE}/rc-produkt/`).then(() => p.content());
  rec('render.product_context', pr.includes(t2) && !pr.includes(t1), `Produktseite zeigt nur Produkt-Review: ${pr.includes(t2) && !pr.includes(t1)}`);

  // 4. CSV-Export über das Admin-Formular
  await a.goto(`${BASE}/wp-admin/admin.php?page=review-captain-import-export`);
  const exportForm = a.locator('form:has(input[name="action"][value="review_captain_export"])');
  if (await exportForm.count()) {
    const [dl] = await Promise.all([a.waitForEvent('download', { timeout: 30000 }), exportForm.locator('[type="submit"]').first().click()]);
    const csvPath = path.join(ART, 'export.csv'); await dl.saveAs(csvPath);
    const csv = fs.readFileSync(csvPath, 'utf8');
    const lines = csv.trim().split(/\r?\n/);
    rec('csv.export', csv.includes(t1) && lines.length >= 3, `${lines.length - 1} Datenzeilen; Kopf: ${lines[0].replace(/^﻿/, '').slice(0, 120)}; E-Mail enthalten: ${csv.includes('@example.test')}`);
  } else rec('csv.export', false, 'Export-Formular nicht gefunden');

  // 5. CSV-Import als Dry-Run (Upload → Vorschau → Abbrechen)
  const imp = path.join(ART, 'import.csv');
  fs.writeFileSync(imp, '﻿kind,platform,external_id,rating_value,rating_scale_max,title,body,language,author_name,published_at,source_url,verification,status,context_type,context_id\n' +
    `review,local,,5,5,Import ${RUN} A,"Importierter Text mit Komma, und Umlauten äöü",de,Ina Import,2026-10-01 10:00:00,,unverified,pending,,\n` +
    `testimonial,local,,,5,Import ${RUN} B,=HYPERLINK("http://evil"),de,Formel Test,2026-10-02 10:00:00,,unverified,pending,,\n` +
    `review,local,,9,5,Import ${RUN} C,Ungültige Sterne,de,Fehler Fall,2026-10-03 10:00:00,,unverified,pending,,\n`);
  const cntBefore = SQL('SELECT COUNT(*) FROM wp_review_captain_reviews');
  await a.goto(`${BASE}/wp-admin/admin.php?page=review-captain-import-export`);
  await a.setInputFiles('#rc-import-file', imp);
  await Promise.all([a.waitForNavigation(), a.locator('form:has(#rc-import-file) [type="submit"]').first().click()]);
  const prev = await a.locator('#wpbody-content').innerText();
  fs.writeFileSync(path.join(ART, 'import-preview.txt'), prev);
  const hasConfirm = await a.locator('input[name="action"][value="review_captain_import_confirm"]').count();
  const cntPrev = SQL('SELECT COUNT(*) FROM wp_review_captain_reviews');
  rec('csv.import.preview', hasConfirm > 0 && cntPrev === cntBefore, `Vorschau mit Bestätigen-Knopf: ${hasConfirm > 0}; Reviews vor/nach Vorschau: ${cntBefore}/${cntPrev}; Auszug: ${prev.replace(/\s+/g, ' ').slice(0, 260)}`);
  const cancel = a.locator('form:has(input[name="action"][value="review_captain_import_cancel"]) [type="submit"]');
  if (await cancel.count()) { await Promise.all([a.waitForNavigation(), cancel.first().click()]); }
  const cntAfter = SQL('SELECT COUNT(*) FROM wp_review_captain_reviews');
  rec('csv.import.cancel', cntAfter === cntBefore, `Nach Abbrechen: ${cntAfter} (vorher ${cntBefore})`);

  // 6. Datenschutz: Export und Löschung über Werkzeuge → Personenbezogene Daten
  const email = `erika.${RUN}@example.test`;
  for (const [tool, action] of [['export-personal-data.php', 'export'], ['erase-personal-data.php', 'erase']]) {
    await a.goto(`${BASE}/wp-admin/${tool}`);
    await a.fill('#username_or_email_for_privacy_request', email);
    const sendConf = a.locator('#send_confirmation_email');
    if (await sendConf.count()) await sendConf.uncheck();
    await Promise.all([a.waitForNavigation(), a.click('#submit')]);
    const reqRow = a.locator('tr', { hasText: email }).first();
    if (action === 'export') {
      const btn = reqRow.locator('.export-personal-data-handle, button:has-text("Download")').first();
      await reqRow.hover();
      try {
        const [dl] = await Promise.all([a.waitForEvent('download', { timeout: 60000 }), btn.click()]);
        const zip = path.join(ART, 'privacy-export.zip'); await dl.saveAs(zip);
        const listing = execFileSync('unzip', ['-p', zip, 'index.html'], { encoding: 'utf8' });
        fs.writeFileSync(path.join(ART, 'privacy-export.html'), listing);
        rec('privacy.export', listing.includes(t1), `ZIP ${fs.statSync(zip).size} B; Review-Titel enthalten: ${listing.includes(t1)}; Gruppe Review Captain: ${/Review Captain|Bewertung/.test(listing)}`);
      } catch (e) { rec('privacy.export', false, `Download fehlgeschlagen: ${e.message.split('\n')[0]}`); }
    } else {
      await reqRow.hover();
      const btn = reqRow.locator('.remove-personal-data-handle, button:has-text("Erase")').first();
      await btn.click();
      await a.waitForSelector('.request-results, .notice', { timeout: 60000 }).catch(() => null);
      await sleep(1500);
      const res = await a.locator('#wpbody-content').innerText();
      const dbRow = SQL(`SELECT status,author_name,IFNULL(author_email,'NULL') FROM wp_review_captain_reviews WHERE title='${t1}'`);
      rec('privacy.erase', !dbRow.includes(email), `DB nach Löschung: ${dbRow.replace(/\t/g, ' / ') || '(Zeile gelöscht)'}; UI: ${(res.match(/(No personal data|Personal data was|Keine personenbezogenen|Personenbezogene Daten wurden)[^\n]*/) || ['?'])[0].slice(0, 160)}`);
    }
  }

  fs.writeFileSync(path.join(E2E_DIR, 'results-functional.json'), JSON.stringify({ run: RUN, results }, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); fs.writeFileSync(path.join(E2E_DIR, 'results-functional.json'), JSON.stringify({ error: String(e), results }, null, 2)); process.exit(1); });
