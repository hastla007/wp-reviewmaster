# RC-015 – Medienrechte für Testimonials

**Status:** Entwurf – Einwilligungstext benötigt juristische Freigabe  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §5 Social-Proof-Wall, §8.10, §9 Qualitätsanforderungen](../../konzept.md), TODO RC-107 – RC-110

## Grundsatz

Ein Testimonial wird nur veröffentlicht, wenn für **jedes enthaltene Element** (Name, Bild, Stimme, Video, Text) ein gültiger, nachweisbarer Rechtsgrund vorliegt. Fehlender, abgelaufener oder widerrufener Nachweis → Inhalt wird automatisch nicht ausgegeben. Ein Review-Text wird nie ohne Zustimmung zu einem Testimonial, Voice-over oder Video umgearbeitet.

## Rechteelemente je Testimonial

| Element | Erforderlicher Nachweis | Pflichtfeld im Datensatz |
|---|---|---|
| Name / Bezeichnung | Einwilligung zur Namensnennung oder Pseudonym/Initialen | `display_name_mode` (voll, Vorname, Initialen, anonym) |
| Text/Zitat | Einwilligung zur Veröffentlichung; Freigabe einer gekürzten Fassung | `text_approved_version` |
| Bild/Foto | Recht am eigenen Bild (abgebildete Personen) + Urheber-/Nutzungsrecht am Foto | `image_consent`, `image_rights_holder` |
| Stimme/Audio | Einwilligung zur Nutzung der Stimme + Nutzungsrecht an der Aufnahme | `voice_consent` |
| Video | Bild- und Stimmrechte aller erkennbaren Personen + Nutzungsrecht an der Aufnahme | `video_consent`, `persons_count` |
| Untertitel/Transkript | Freigabe, dass Transkript dem Gesagten entspricht | `transcript_approved` |

## Nachweis (Rechtebeleg)

Jeder Beleg speichert: Art (digital unterschrieben, Formular-Opt-in, E-Mail-Bestätigung, Papier eingescannt), Datum, Textversion der Einwilligung, Umfang (Kanäle: Website / Social Media / Print), Gültigkeit (unbefristet / bis Datum), Referenz auf die Ablage (WordPress-Mediathek, privat, nicht öffentlich abrufbar), erfassende Person. Der Beleg selbst wird nie öffentlich ausgegeben.

## Ablauf

1. **Erfassung:** Testimonial als Entwurf anlegen (Medien in der Mediathek, nicht öffentlich verlinkt).
2. **Einwilligung einholen:** Vorlage (unten) an die Person; Rücklauf als Beleg anhängen.
3. **Prüfung:** Moderator prüft Vollständigkeit aller Elemente; Pflichtfelder steuern den Status – ohne vollständigen Beleg kein Statuswechsel auf „freigegeben“.
4. **Barrierefreiheit:** Video/Audio benötigen Untertitel bzw. Transkript und Posterbild vor Freigabe (RC-110).
5. **Veröffentlichung:** nur freigegebene Testimonials sind in Widgets auswählbar.
6. **Ablauf:** täglicher Job blendet Testimonials mit abgelaufener Gültigkeit aus und benachrichtigt Moderatoren 30 Tage vorher.
7. **Widerruf:** Moderator erfasst Widerruf mit Datum → sofortige Ausblendung, Medien werden nach konfigurierbarer Frist (Default 30 Tage) gelöscht; Cache-Invalidierung (TEC-012). Bereits gedruckte/geteilte Kopien außerhalb der Website liegen außerhalb des Plugins – Hinweis im Admin.
8. **Löschung/Eraser:** Privacy Eraser entfernt Testimonial und Medien der betroffenen Person.

## Vorlage Einwilligungstext (Entwurf, juristisch zu prüfen)

> Ich willige ein, dass [Unternehmen] meinen Erfahrungsbericht ☐ als Text ☐ mit Foto ☐ als Audioaufnahme ☐ als Video sowie meinen Namen in der Form ☐ vollständig ☐ Vorname ☐ Initialen auf der Website [Domain] veröffentlicht. Die Einwilligung gilt ☐ unbefristet ☐ bis [Datum]. Ich kann sie jederzeit mit Wirkung für die Zukunft per E-Mail an [Adresse] widerrufen; der Inhalt wird dann von der Website entfernt. Ich bestätige, dass ich die Rechte an den bereitgestellten Aufnahmen besitze bzw. alle erkennbaren Personen zugestimmt haben.

## Offen / Freigabe

- [ ] Juristische Freigabe Einwilligungstext (DE/AT), insbesondere Minderjährige (Default: nicht zulässig ohne Erziehungsberechtigte, Feld „Person volljährig“ Pflicht).
- [ ] Entscheidung, ob Social-Media-/Print-Umfang im Plugin überhaupt erfasst wird (Empfehlung: nur Website, Rest außerhalb).
