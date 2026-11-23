# Spam-Schutz: eingebaute Rechenprüfung (Proof of Work)

Stand: 23.11.2026 · Aufgabe SET-RC-11 · Entscheidung S7 (derselbe kostenlose, datenschutzfreundliche Anbieter wie in Form Captain)

Review Captain übernimmt das in Form Captain gebaute Verfahren (Anbieter `pow`). Maßgebliche Beschreibung des Verfahrens ist `wp-formmaster/docs/spam-proof-of-work.md` (Form Captain, Commit `ca81401`); diese Datei beschreibt die Anbindung in Review Captain und die Abweichungen.

## Kurz

- Einstellung `submissions.captcha_provider`: `none` (Standard) oder `pow`. Reiter „Einreichungen & Moderation“, Abschnitt „Schutz vor Missbrauch“.
- `pow`: Der Browser sucht im Hintergrund eine Zahl, deren SHA-256-Hash zur Aufgabe des Servers passt. Kein Rätsel, keine Cookies, keine Anfrage an Dritte, keine personenbezogenen Daten. Rechtlich unkritisch, weil keine Daten die Website verlassen; deshalb ohne Rechtsprüfung freigegeben.
- Gilt für das Bewertungsformular, das Formular „Melden“ und das Frageformular (Q&A).
- Externe Anbieter (z. B. Turnstile, hCaptcha, Friendly Captcha) nur über den Filter `review_captain_spam_checks_v1` und **nur nach Rechtsprüfung**.

## Dateien

| Datei | Herkunft | Inhalt |
|---|---|---|
| `src/Spam/ProofOfWork/ProofOfWork.php` | Kopie aus Form Captain `ca81401`, nur Namespace (`ReviewCaptain\Spam\ProofOfWork`) und `@package` geändert; von phpcs ausgenommen, damit sie gleich bleibt | Aufgabe erzeugen, prüfen, Einmalverwendung, Schwierigkeit; ohne WordPress |
| `assets/js/proof-of-work.js` | Kopie aus Form Captain `ca81401`, nur globale Variable `window.reviewCaptainPow` (statt `window.formCaptainPow`) | Löser im Browser mit eigener SHA-256 (läuft auch ohne HTTPS) |
| `src/Security/PowCaptcha.php` | eigene Anbindung (entspricht Form Captains `PowCaptcha.php`) | Schlüssel, Bereich, Einmal-Speicher, Aufgabe per admin-ajax, Prüfung, Filter, Markup |
| `assets/js/pow-form.js` | eigen (Handle `review-captain-pow-form`, hängt von `review-captain-pow` ab) | Formular-Anbindung: Aufgabe holen, Löser starten, Feld füllen, Absenden abwarten, Status |

Bei Änderungen am Verfahren in Form Captain werden die beiden Kopien erneut übernommen; der Test `test_core_file_has_no_host_dependencies` stellt sicher, dass der Kern frei von WordPress bleibt.

## Datenformat (wie Form Captain)

Aufgabe (Antwort von `POST admin-ajax.php`, `action=review_captain_pow_challenge`, `scope=<Bereich>`; `{"success":true,"data":{…}}`):

```json
{
  "algorithm": "SHA-256",
  "challenge": "<64 hex>",
  "maxnumber": 50000,
  "salt": "<24 hex Zufall>.<Ablauf Unix-Zeit>.<16 hex Bereichskennung>",
  "signature": "<64 hex>",
  "expires": 1763900000
}
```

- `challenge` = SHA-256( `salt` . n ) für eine geheime Zufallszahl 0 ≤ n ≤ `maxnumber`; n wird nie ausgeliefert.
- `signature` = HMAC-SHA-256( `challenge`, Schlüssel ); Schlüssel = HMAC-SHA-256( `review-captain/pow/v1`, `wp_salt('nonce')` ), nie ausgegeben.
- Bereichskennung = erste 16 hex von HMAC-SHA-256( `"scope|" . Bereich`, Schlüssel ).

Lösung (Formularfeld `_rc_pow`): Base64 von JSON `{ "algorithm", "challenge", "number", "salt", "signature" }`.

Beim Ausgeben einer Aufgabe wird nichts gespeichert (sie trägt Ablaufzeit, Bereich und Signatur selbst).

### Bereiche

Eine Lösung gilt nur für das Formular und den Kontext, für den die Aufgabe ausgegeben wurde (`PowCaptcha::scope()`, beim Ausgeben aus den versteckten Feldern, beim Prüfen aus den POST-Daten berechnet):

| Formular | Bereich |
|---|---|
| Bewertung | `review\|<rc_context_type>\|<rc_context_id>` (ohne Kontext `review\|\|`) |
| Frage | `question\|<rc_context_type>\|<rc_context_id>` |
| Melden | `report\|<Review-UUID>` |

Der Bereich steht im Attribut `data-rc-pow-scope` des Formulars (keine geheime Angabe, cache-fest). Die admin-ajax-Aktion gibt nur für Bereiche mit `review|`, `question|` oder `report|` (höchstens 200 Zeichen, ohne Steuerzeichen) eine Aufgabe aus. Kontextfelder sind zusätzlich durch `rc_context_sig` geschützt.

## Prüfregeln (Kern, Reihenfolge wie Form Captain)

1. Lösung fehlt → `missing`.
2. Nutzlast > 1024 Byte, kein gültiges Base64-JSON, falscher Algorithmus, Felder nicht im Format (hex, `salt`-Muster, `number` ganzzahlig 0…1 000 000) → `malformed`.
3. `signature` ≠ HMAC(`challenge`) (Vergleich in konstanter Zeit) → `bad_signature`.
4. Ablaufzeit aus `salt` < jetzt → `expired`.
5. Bereichskennung passt nicht → `wrong_scope`.
6. SHA-256(`salt` . `number`) ≠ `challenge` → `wrong_solution`.
7. Einmalverwendung: schon benutzt → `replayed`.
8. Sonst `ok`.

Der Einmal-Speicher wird erst in Schritt 7 berührt, also nur für korrekt signierte, richtige Lösungen.

Zuordnung zu Spam-Gründen (wie Form Captain): `missing` → `pow_missing`; `replayed` → `pow_replayed`; alle übrigen → `pow_invalid`.

Danach läuft der Filter `review_captain_spam_checks_v1`. Reihenfolge in den Formularen: Nonce → Spam-Falle (stiller Erfolg wie bisher) → Mindest-Ausfüllzeit → Rechenprüfung → Filter → Kontext, Sendebegrenzung, Feldprüfung.

Besucher sehen bei `pow_invalid`, `pow_replayed` und Gründen aus dem Filter (`spam_check`) denselben neutralen Hinweis; nur `pow_missing` nennt JavaScript. Gespeichert wird nichts, auch kein Entwurf. Die Gründe stehen wie bei `too_fast` im Fehlercode der Weiterleitung (`rc_error`, `rc_question_error`; beim Melden `rc_reported=error`).

## Einmal-Speicher

- Je benutzter Aufgabe eine Option `review_captain_pow_used_<40 hex>` (SHA-256 von `review-captain/pow-used|` . `challenge`), Wert = Ablauf + 60 s, nicht automatisch geladen.
- Atomar über `add_option()`: WordPress schreibt mit einem einzigen `INSERT … ON DUPLICATE KEY UPDATE` auf den eindeutigen `option_name`. Weil der Wert für dieselbe Aufgabe immer gleich ist, ändert eine parallele zweite Einsendung keine Zeile, `add_option()` liefert `false` → `replayed`. Review Captain hat keine eigene Tabelle für Sendegrenzen (die Sendebegrenzung nutzt Transients); Form Captain nutzt `INSERT IGNORE` in `fc_rate_limits`.
- Die tägliche Wartung (`Maintenance::run()`, Schlüssel `pow_used`) löscht abgelaufene Einträge (höchstens 1000 je Lauf). Deinstallation entfernt Reste mit dem Präfix `review_captain_`.

## Parameter

| Parameter | Wert | Änderbar |
|---|---|---|
| Schwierigkeit `maxnumber` | 50 000 (Ø 25 000 Hashes, unter einer Sekunde auf einem Mittelklasse-Telefon) | Filter `review_captain_pow_max_number_v1`, begrenzt auf 1 000–1 000 000 (wie `form_captain_pow_max_number_v1`) |
| Gültigkeit einer Aufgabe | 1 800 s | nein (`ProofOfWork::DEFAULT_TTL`) |
| Höchstlänge der Lösung | 1 024 Byte | nein |

## Browser

- `pow-form.js` holt die Aufgabe beim ersten Fokus oder Antippen im Formular per `POST` an `admin-ajax.php` (`cache: no-store`) – damit funktioniert der Schutz auch auf vollständig gecachten Seiten. Die Seite selbst enthält keine Aufgabe.
- Der Löser (`window.reviewCaptainPow.solve()`) sucht in Abschnitten von 2000 Zahlen, die Seite bleibt bedienbar.
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

- **Ohne JavaScript keine Ausnahme.** Form Captain verlangt die Lösung ohne JavaScript nur bei Spamschutz „strenger“; sonst entscheiden dort die übrigen Prüfungen. Grund: Form Captain hat zwei Wege – die REST-Route des Formular-Skripts (verlangt immer eine Lösung) und den No-JS-Rückfall über `admin-post.php`. Review Captains Formulare sind immer ein normaler POST an `admin-post.php`; ein Rückfall „fehlende Lösung = erlaubt“ würde Bots erlauben, die Prüfung einfach wegzulassen. Ist `pow` aktiv, ist das Formular deshalb ohne JavaScript nicht nutzbar (Hinweis vorab und nach dem Absenden). Es gibt keine Einstellung je Formular (`spam.mode`).
- **Ablehnung statt Spam-Ordner:** Form Captain speichert die Einsendung mit Spam-Grund; Review Captain lehnt wie bei den übrigen Prüfungen ab und speichert nichts.
- **Einstellung** unter `submissions.` statt `spam.` (Review Captain hat keinen Reiter „Spam“).
- **Aufgabe über admin-ajax** statt über die Token-Route, weil Review Captain im Frontend keine REST-Routen hat; öffentlich ohne Nonce (würde auf gecachten Seiten veralten; die Aufgabe ist ohnehin signiert). Der Bereich kommt aus `data-rc-pow-scope` statt aus der Formular-ID.
- **Einmal-Speicher** über `add_option()` statt `INSERT IGNORE` in eine Sendegrenzen-Tabelle (siehe oben).
- **Skripte** unter `assets/js/` (kein Build-Schritt für `assets/src/`); Feld `_rc_pow` statt `_fc_pow`.
