# Länderprofile – Entwurf (Vorschlag, nicht umgesetzt)

| | |
|---|---|
| Status | **Entwurf aus der Prüfphase SET-RC-P3 (23.11.2026).** Nichts davon ist gebaut (Umsetzung: SET-RC-15). |
| Rechtsstand | **Kein Wert mit rechtlichem Gewicht ist geprüft.** Jeder solche Wert trägt „nicht geprüft“ und *wartet auf* Rechtsprüfung je Land (S8). Dieses Dokument ist keine Rechtsberatung. |
| Profile | `DE`, `AT`, `CH`, `EU` (EU allgemein), `OTHER` (alle übrigen Länder) – suiteweit gleiche Schlüssel (Entscheidung S8) |
| Steuer | Review Captain und das Pro-Add-on haben **keine steuerlich relevanten Einstellungen** (keine Preise, keine Rechnungen). Die Steuerprüfung je Land betrifft andere Plugins der Suite bzw. den Verkauf der Lizenzen, nicht diese Profile. |

## Regeln für die Umsetzung (aus S8/S9 und SET-RC-15)

1. **Nur Vorschlag.** „Profil anwenden“ zeigt eine Vorschau „bisher / neu“ über die vorhandene Import-Vorschau (`src/Admin/ConfigTransfer.php`) und ändert erst nach Bestätigung. Nie still, nie automatisch (auch nicht bei Wechsel der WordPress-Sprache oder beim Update).
2. **Bestehende Werte bleiben auswählbar.** Die Vorschau listet jeden Wert einzeln; der Betreiber kann einzelne Zeilen abwählen. Eigene Texte (nicht leere Textfelder) werden standardmäßig *nicht* überschrieben.
3. **Leer heißt Standard.** Ein Profil setzt Textfelder auf `''` (= übersetzter Standardtext der Sprachdatei), nie auf einen festen Wortlaut, solange der Text nicht rechtlich freigegeben ist.
4. **Profile setzen nie:** `submissions.require_consent = false`, `privacy.delete_data_on_uninstall`, `privacy.delete_data_confirmed_at`, Rollen (`access.*`), Zugangsdaten, `legal_basis_confirmed` einer Kampagne (Pro), Absender (`requests.sender_*`). Diese Entscheidungen trifft immer der Betreiber selbst.
5. **Filter gewinnt** (S2): `review_captain_country_presets_v1` (Liste der Profile mit Werten) kann Profile ändern oder ergänzen.
6. **Protokoll:** Anwenden schreibt einen Eintrag im Änderungsprotokoll (`settings.imported` bzw. eigener Typ `settings.country_profile`) mit alten und neuen Werten (SET-RC-17).
7. **Kennzeichnung:** Jede Profilzeile mit Rechtsgewicht zeigt in der Vorschau den Hinweis „nicht geprüft“, bis die Inhaberin die Freigabe je Land einträgt (Feld `reviewed` im Profil, siehe unten).

## Neue Werte, die die Umsetzung braucht (Vorschlag)

| Schlüssel | Typ | Standard | Zweck | Status |
|---|---|---|---|---|
| `general.country_profile` | enum `''`\|`DE`\|`AT`\|`CH`\|`EU`\|`OTHER` | `''` (keins) | zuletzt angewendetes Profil; nur Anzeige und Website-Zustand, wirkt selbst auf nichts | *wartet auf* Inhaberin (Name suiteweit) |
| `general.country_profile_applied_at` | string (UTC), intern | `''` | Zeitpunkt des Anwendens | *wartet auf* Inhaberin |
| Profil-Merkmal `reviewed` (je Profil im Code/Filter, keine Einstellung) | map Schlüssel → Datum der Freigabe | leer | steuert die Kennzeichnung „nicht geprüft“ in der Vorschau | *wartet auf* Rechtsprüfung |

## Werte, die ein Profil setzen muss – Kern

Legende Rechtsgewicht: **R** = rechtlich gewichtig (nicht geprüft), **–** = rein technisch/sprachlich.

| Schlüssel | DE | AT | CH | EU | OTHER | Rechtsgewicht | Prüffrage |
|---|---|---|---|---|---|---|---|
| `general.date_format` | `d.m.Y` | `d.m.Y` | `d.m.Y` | `''` (WordPress) | `''` (WordPress) | – | – |
| `general.new_review_status` | `pending` | `pending` | `pending` | `pending` | `pending` | R – nicht geprüft | Ist eine Vorabprüfung nötig oder genügt Prüfung nach Hinweis (Haftung für veröffentlichte Inhalte)? |
| `submissions.require_consent` | `true` | `true` | `true` | `true` | `true` | R – nicht geprüft | Ist die Einwilligung die richtige Grundlage für die Veröffentlichung mit Namen? Profile schalten sie nie aus. |
| `submissions.consent_text` | `''` | `''` | `''` | `''` | `''` | R – nicht geprüft | Wortlaut je Land (Widerruf, Zweck, Name öffentlich). CH: Schreibweise ohne „ß“ (Sprachdatei `de_CH`, SET-RC-14). |
| `submissions.email_hint` | `''` | `''` | `''` | `''` | `''` | R – nicht geprüft | Zweckangabe zur E-Mail-Adresse ausreichend? |
| `submissions.name_label` | `''` | `''` | `''` | `''` | `''` | – | – |
| `display.verification_notice_show` | `true` | `true` | `true` | `true` | `true` | R – nicht geprüft | DE/AT/EU: Pflicht zur Information, ob und wie Bewertungen geprüft werden (Omnibus-Richtlinie (EU) 2019/2161; DE-Umsetzung im UWG – Fundstelle bestätigen). CH: gilt eine vergleichbare Pflicht? |
| `display.verification_notice_title` | `''` | `''` | `''` | `''` | `''` | R – nicht geprüft | Ist „So prüfen wir Bewertungen“ als Linktext ausreichend auffindbar? |
| `display.verification_notice` | `''` (neutraler Entwurf) | `''` | `''` | `''` | `''` | R – nicht geprüft | Genügt der Entwurf (nur vorkommende Stufen)? Muss er Kontrollen/Löschpraxis nennen? |
| `display.verification_notice_position` | `below` | `below` | `below` | `below` | `below` | R – nicht geprüft | Muss der Hinweis vor der Liste stehen? |
| `display.show_unverified_label` | `false` | `false` | `false` | `false` | `false` | R – nicht geprüft | Muss „ungeprüft“ an jeder Karte stehen, oder genügt der Hinweis? |
| `display.label_purchase_verified` … `label_unverified` | `''` | `''` | `''` | `''` | `''` | R – nicht geprüft | Sind die Standardbezeichnungen zutreffend/irreführungsfrei? |
| `display.show_avatar` | `false` | `false` | `false` | `false` | `false` | R – nicht geprüft | Gravatar = Übermittlung an Dritte (Drittland); Profile schalten es nie ein. |
| `display.date_style` | `absolute` | `absolute` | `absolute` | `absolute` | `absolute` | – | – |
| `privacy.retention_contact_days` | `365` | `365` | `365` | `365` | `365` | R – nicht geprüft | S9 nennt 12 Monate als Beispiel. Welche Frist ist angemessen (Rückfragen, Auskunft, Korrektur-Links)? |
| `privacy.retention_approved_days` | `0` (nie) | `0` | `0` | `0` | `0` | R – nicht geprüft | Dürfen veröffentlichte Bewertungen unbegrenzt bleiben (Einwilligung bis Widerruf)? |
| `privacy.retention_rejected_days` | `90` | `90` | `90` | `90` | `90` | R – nicht geprüft | Frist für abgelehnte Einreichungen. |
| `privacy.retention_corrections_days` | `90` | `90` | `90` | `90` | `90` | R – nicht geprüft | Frist für frühere Fassungen. |
| `privacy.retention_logs_days` | `90` | `90` | `90` | `90` | `90` | R – nicht geprüft | Protokolle ohne Inhalte – Frist angemessen? |
| `privacy.retention_audit_days` | `365` | `365` | `365` | `365` | `365` | R – nicht geprüft | Nachweis von Rechte-/Einstellungsänderungen. |
| `privacy.retention_jobs_days` | `30` | `30` | `30` | `30` | `30` | – | – |
| `privacy.retention_external_days` | `30` | `30` | `30` | `30` | `30` | R – nicht geprüft | Wie lange darf ein nicht mehr bestätigtes Plattform-Review gespeichert bleiben (Plattform-AGB, Datenschutz)? |
| `privacy.erase_mode` | `anonymize` | `anonymize` | `anonymize` | `anonymize` | `anonymize` | R – nicht geprüft | Genügt Anonymisierung bei Löschanfrage, oder ist vollständiges Löschen nötig? |

Das Archiv der Einwilligungstexte (`review_captain_consent_texts`, Prüfphase P3) hat heute keine eigene Frist; ob und wie lange es aufbewahrt wird, *wartet auf* Rechtsprüfung.

## Werte, die ein Profil setzen muss – Pro (Einladungen = Werbung)

Nur wenn das Add-on aktiv ist und das Feld anmeldet (SET-RC-07); sonst erscheinen die Zeilen nicht in der Vorschau.

| Schlüssel | DE | AT | CH | EU | OTHER | Rechtsgewicht | Prüffrage |
|---|---|---|---|---|---|---|---|
| Kampagne: Vorgabe `allowed_bases` neuer Kampagnen | `consent` | `consent` | `consent` | `consent` | `consent` | R – nicht geprüft | Ist eine Bewertungsanfrage per E-Mail Werbung, die eine Einwilligung braucht? Ist die Bestandskunden-Ausnahme (DE: UWG § 7 Abs. 3) anwendbar? AT: TKG; CH: UWG (Massenwerbung). Bestehende Kampagnen ändert das Profil nie. |
| `requests.reminders_enabled` | `false` | `false` | `false` | `false` | `false` | R – nicht geprüft | Ist eine Erinnerung eine weitere Werbe-E-Mail? |
| `requests.cooldown_days` | `90` | `90` | `90` | `90` | `90` | R – nicht geprüft | Kontakthäufigkeit (Belästigung). |
| `requests.daily_limit` | `200` | `200` | `200` | `200` | `200` | – | – |
| `requests.send_window_start` / `send_window_end` | `09:00` / `18:00` | `09:00` / `18:00` | `09:00` / `18:00` | `09:00` / `18:00` | `09:00` / `18:00` | – (Zeitzone der Website) | – |
| künftig `requests.retention_days` (SET-RC-24) | Vorschlag `90` | `90` | `90` | `90` | `90` | R – nicht geprüft | Frist für Anfrage-Datensätze. |
| künftig Fußzeile/Impressum der Einladung (SET-RC-24) | `''` | `''` | `''` | `''` | `''` | R – nicht geprüft | Pflichtangaben in der E-Mail (DE: Anbieterkennzeichnung), Hinweis auf Widerspruchsrecht bei jeder Verwendung (DE: UWG § 7 Abs. 3 Nr. 4)? |
| Vorlage (Betreff/Text) | Standard der Sprachdatei | dto. | dto. | dto. | dto. | R – nicht geprüft | Neutrale Bitte ohne Anreiz; Abmeldelink wird immer angehängt (`TemplateRenderer`). |

## Künftige Werte aus offenen Aufgaben

Diese Werte gibt es noch nicht; ein Profil muss sie setzen, sobald sie gebaut sind.

| Aufgabe | Wert | Vorschlag für alle Profile | Wartet auf |
|---|---|---|---|
| SET-RC-03 | `display.allow_min_rating`, `display.filter_notice` (gebaut 24.11.2026) | Filter an, Hinweis `''` (Standardtext) | Rechtsprüfung (E3 entschieden) |
| SET-RC-12 | `display.label_incentivised` (immer sichtbar, wenn gesetzt) | `''` (Standardtext) | Inhaberin E7, Rechtsprüfung |
| SET-RC-11 | `submissions.captcha_provider` | `pow` (lokal, ohne Dritte) | Form-Sitzung, Rechtsprüfung |
| SET-RC-32 | `submissions.require_purchase`, `purchase_max_age_days` (gebaut 24.11.2026) | `false`, `0` | – (E6 entschieden) |

## Fragen an die Rechtsprüfung (gesammelt)

1. Transparenz (DE/AT/EU, CH gesondert): Reichen Hinweis „So prüfen wir Bewertungen“, Stufenbezeichnungen und Position? Gilt die Pflicht auch für Pro-Abzeichen, Plattform-Leiste und schwebendes Abzeichen, die nur Durchschnitt und Anzahl zeigen?
2. Gefilterte Anzeige (`min_rating`): Ist der Filter zulässig, und genügt der Hinweis `display.filter_notice` („Es werden nur Bewertungen ab X Sternen angezeigt“, seit 24.11.2026)? (Der Durchschnitt wird seit P3 immer über alle freigegebenen Bewertungen gerechnet.)
3. Vorteile für Bewertungen: Kennzeichnung „gegen Vorteil erhalten“ (E7); Suite-Plugins dürfen Vorteile nie von der Höhe der Bewertung abhängig machen (`docs/contracts/README.md`, Verwendungsregel `rating_value`).
4. Einwilligung zur Veröffentlichung: Wortlaut je Land, Nachweis (Zeitpunkt + Fassung des Textes, seit P3), Aufbewahrung des Textarchivs.
5. Einladungs-E-Mails: Rechtsgrundlage je Land, Pflichtangaben, Erinnerung, Abmeldung (Ein-Klick-Abmeldung per `List-Unsubscribe-Post` vorhanden).
6. Fristen: alle Zeilen „R“ in den Tabellen oben.
7. Datenschutzerklärung: der Vorschlag in „Datenschutz → Leitfaden“ (`PrivacyModule::policy_text()`) folgt seit P3 den Einstellungen (Moderation, Einwilligung, Fristen); der Wortlaut selbst ist nicht geprüft.
