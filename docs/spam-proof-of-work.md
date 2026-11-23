# Spam-Schutz: eingebaute Rechenprüfung (Proof of Work)

Stand: 23.11.2026 · Aufgabe SET-RC-11 · Entscheidung S7 (derselbe kostenlose, datenschutzfreundliche Anbieter wie in Form Captain)

Review Captain übernimmt das in Form Captain gebaute Verfahren (Anbieter `pow`) mit eigenem Präfix. Maßgebliche Beschreibung ist `wp-formmaster/docs/spam-proof-of-work.md` (Form Captain, Commit `ca81401`); diese Datei beschreibt die Umsetzung in Review Captain und die Abweichungen.

## Kurz

- Einstellung `submissions.captcha_provider`: `none` (Standard) oder `pow`. Reiter „Einreichungen & Moderation“, Abschnitt „Schutz vor Missbrauch“.
- `pow`: Der Browser sucht im Hintergrund eine Zahl, deren SHA-256-Hash zur Aufgabe des Servers passt. Kein Rätsel, keine Cookies, keine Anfrage an Dritte, keine personenbezogenen Daten. Rechtlich unkritisch, weil keine Daten die Website verlassen; deshalb ohne Rechtsprüfung freigegeben.
- Gilt für das Bewertungsformular, das Formular „Melden“ und das Frageformular (Q&A).
- Externe Anbieter (z. B. Turnstile, hCaptcha, Friendly Captcha) nur über den Filter `review_captain_spam_checks_v1` und **nur nach Rechtsprüfung**.

## Datenformat

Aufgabe (Antwort von `admin-ajax.php?action=review_captain_pow_challenge`, `{"success":true,"data":{…}}`):

| Feld | Inhalt |
|---|---|
| `algorithm` | immer `SHA-256` |
| `challenge` | `hex(SHA-256(salt . n))`, 64 Zeichen; `n` ist die geheime Zahl in `[0, maxnumber]` |
| `maxnumber` | Obergrenze der Suche (Schwierigkeit) |
| `salt` | 24 zufällige Hex-Zeichen + `?expires=<Unix-Zeit>` |
| `signature` | `hex(HMAC-SHA-256(challenge, Geheimnis))`; Geheimnis aus `wp_salt('nonce')`, verlässt nie den Server |
| `expires` | Ablaufzeit (Unix-Zeit), nur als Hilfe für das Skript; maßgeblich ist der Wert im `salt` |

Lösung (Formularfeld `rc_pow`): `base64(JSON{algorithm, challenge, number, salt, signature})`; `number` ist eine ganze Zahl.

Die geheime Zahl wird nie ausgeliefert. Beim Ausgeben einer Aufgabe wird nichts gespeichert (sie trägt Ablaufzeit und Signatur selbst).

## Prüfregeln (Server)

In dieser Reihenfolge; jede Verletzung lehnt ab, gespeichert wird nichts:

1. Feld fehlt oder leer → `pow_missing` (Hinweis „bitte JavaScript aktivieren“).
2. Länger als 2048 Byte, kein gültiges Base64/JSON, `algorithm` ≠ `SHA-256`, `challenge`/`signature` nicht 64 Hex-Zeichen, `number` keine ganze Zahl in `[0, 10 000 000]` → `pow_invalid`.
3. Signatur passt nicht zur Aufgabe (zeitkonstanter Vergleich) → `pow_invalid`.
4. `salt` ohne `?expires=` oder abgelaufen → `pow_invalid`.
5. `SHA-256(salt . number)` ≠ `challenge` → `pow_invalid`.
6. Aufgabe schon einmal benutzt → `pow_replayed`. Einmalnutzung: `SHA-256('pow|' . challenge)` wird bis zum Ablauf (+ 60 s) als Transient `review_captain_pow_…` gemerkt – dieselbe Speicherart wie die Sendebegrenzung (`Security\RateLimiter`).

Danach läuft der Filter `review_captain_spam_checks_v1`. Reihenfolge in den Formularen: Nonce → Spam-Falle (stiller Erfolg wie bisher) → Mindest-Ausfüllzeit → Rechenprüfung → Filter → Kontext, Sendebegrenzung, Feldprüfung.

Besucher sehen bei `pow_invalid`, `pow_replayed` und Gründen aus dem Filter (`spam_check`) denselben neutralen Hinweis; nur `pow_missing` nennt JavaScript. Die Gründe stehen wie bei `too_fast` im Fehlercode der Weiterleitung (`rc_error`, `rc_question_error`; beim Melden `rc_reported=error`).

## Parameter

| Parameter | Wert | Änderbar |
|---|---|---|
| Schwierigkeit `maxnumber` | 100 000 (Durchschnitt 50 000 Hashes, mit dem mitgelieferten SHA-256 rund 0,1–0,5 s auf aktuellen Geräten) | Filter `review_captain_pow_max_number_v1`, begrenzt auf 1 000–10 000 000 (wie `form_captain_pow_max_number_v1`) |
| Lebensdauer einer Aufgabe | 3600 s | nein (`ProofOfWork::DEFAULT_TTL`) |
| Höchstlänge der Lösung | 2048 Byte | nein |

## Browser (`assets/js/proof-of-work.js`, Handle `review-captain-pow`)

- Eigenes SHA-256 (kein `crypto.subtle`), läuft daher auch ohne HTTPS.
- Holt die Aufgabe beim ersten Fokus oder Antippen im Formular per `POST` an `admin-ajax.php` (`cache: no-store`) – damit funktioniert der Schutz auch auf vollständig gecachten Seiten. Die Seite selbst enthält keine Aufgabe.
- Sucht in Abschnitten von 4000 Zahlen (`setTimeout`), die Seite bleibt bedienbar.
- Wird vor dem Ende abgeschickt, wartet das Absenden und läuft dann normal weiter (normaler POST, keine Ajax-Übermittlung). Eine Lösung, die in weniger als 60 s abläuft, wird vor dem Absenden ersetzt.
- Barrierefreiheit: nichts zu lösen oder anzuklicken; Stand („Prüfung läuft …“, „bereit zum Absenden“, Fehler) in einer Zeile mit `role="status"` und `aria-live="polite"`. Ohne JavaScript erklärt ein `<noscript>`-Hinweis vorab, warum das Absenden nicht klappt.

## Filter `review_captain_spam_checks_v1`

```php
add_filter( 'review_captain_spam_checks_v1', function ( array $reasons, array $context ): array {
	// $context['form']     – review | report | question
	// $context['provider'] – none | pow (pow hat hier bereits bestanden)
	// $context['post']     – ungefilterte, entslashte POST-Daten
	if ( ! my_provider_verify( $context['post']['my-token'] ?? '' ) ) {
		$reasons[] = 'my_provider_failed';
	}
	return $reasons;
}, 10, 2 );
```

Jeder nicht leere Grund (über `sanitize_key`) lehnt neutral ab; der Grund selbst wird Besuchern nicht gezeigt. **Nur nach Rechtsprüfung einsetzen** – externe Dienste übertragen in der Regel IP-Adresse und Browserdaten an Dritte.

## Abweichungen von Form Captain

- Präfix/Namensraum: `ReviewCaptain\Spam\ProofOfWork\ProofOfWork`, `ReviewCaptain\Security\PowCaptcha`, Feld `rc_pow`, Aktion `review_captain_pow_challenge`, Filter `review_captain_*`, Einstellung unter `submissions.` statt `spam.` (Review Captain hat keinen Reiter „Spam“; „Schutz vor Missbrauch“ liegt unter Einreichungen).
- Skript liegt unter `assets/js/` (Review Captain hat keinen Build-Schritt für `assets/src/`).
- Aufgaben kommen über `admin-ajax.php`, weil Review Captain im Frontend keine REST-Routen hat; öffentlich ohne Nonce, weil eine Nonce auf gecachten Seiten veralten würde und die Aufgabe ohnehin signiert ist.
- Bei Ablehnung wird kein Entwurf des Bewertungstextes gespeichert (Review Captain speichert Entwürfe nur bei Feldfehlern).

Hinweis zur Abstimmung: Die Umsetzung folgt der mit der Form-Sitzung abgestimmten Beschreibung (Altcha-artiges SHA-256-Verfahren mit `maxnumber`, signierter Aufgabe, Ablauf im Salt, Einmalnutzung). Der Quelltext von Form Captain `ca81401` konnte beim Portieren nicht direkt gelesen werden; ein Abgleich der Feldnamen und Grenzwerte mit `wp-formmaster/docs/spam-proof-of-work.md` steht aus (siehe TODO SET-RC-11).
