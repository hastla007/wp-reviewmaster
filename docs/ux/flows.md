# Nutzerabläufe (UX-002)

Status: Entwurf zur Abnahme, Stand 2026-11-16. Grundlage: konzept.md §8, §9, §17; [wireframes.md](wireframes.md); [states.md](states.md).
Pro-Schritte sind mit **(Pro)** markiert. Alle Abläufe funktionieren ohne Frontend-JavaScript; Admin-JS ist progressive Verbesserung.

## F1 Erstinstallation und Setup-Assistent

Wiederaufnehmbar, jeder Schritt überspringbar, keine externe Verbindung wird automatisch aufgebaut. Fortschritt in Option `review_captain_onboarding` (Vorschlag, nicht Teil von `review_captain_settings`).

```mermaid
flowchart TD
    A[Plugin aktivieren] --> B{Mindestversion PHP/WP erfüllt?}
    B -- nein --> B1[Admin-Notice mit Versionen, Plugin bleibt inaktiv funktionslos] --> Z0[Ende]
    B -- ja --> C[Schema installieren, Caps an Rollen]
    C --> D[Weiterleitung einmalig zum Dashboard mit Setup-Checkliste]
    D --> S1[Schritt 1: Marke & Sprache bestätigen]
    S1 --> S2[Schritt 2: Erstes Review anlegen ODER Formular aktivieren ODER CSV importieren]
    S2 --> S3[Schritt 3: Quelle / Profil-Link hinzufügen]
    S3 --> S3a{Pro aktiv?}
    S3a -- nein --> S3b[Profil-Link + CTA speichern]
    S3a -- ja --> S3c[Offizielle Verbindung starten → F3]
    S3b --> S4
    S3c --> S4[Schritt 4: Erstes Widget erstellen → F5]
    S4 --> S5[Schritt 5: Datenschutzhinweis prüfen]
    S5 --> S6{Pro aktiv?}
    S6 -- ja --> S7["Schritt 6 (Pro): Anfragepolitik & Absender → F6"]
    S6 -- nein --> E[Checkliste erledigt, Assistent ausblendbar]
    S7 --> E
    S1 & S2 & S3 & S4 & S5 -.->|Überspringen / Später| D
```

Regeln: Checkliste bleibt auf dem Dashboard, bis alle Schritte erledigt oder bewusst ausgeblendet sind. Überspringen speichert nichts und verändert keine Defaults.

## F2 Review erfassen

### F2a Manuell (eigenes Testimonial oder externer Auszug)

```mermaid
flowchart TD
    A[Reviews → Neu hinzufügen] --> B{Art}
    B -- "Eigenes Testimonial" --> C["kind=testimonial, platform=local"]
    B -- "Externe Bewertung (Auszug)" --> D[Plattform wählen]
    D --> D1[Pflicht: Originalbewertung + Skala, Datum, Profil-/Review-Link, Quellenhinweis]
    C --> E[Text, Name, optional Titel/Kontext]
    D1 --> E
    E --> F[Status wählen: Prüfung ausstehend / Freigegeben]
    F --> G{Validierung ok?}
    G -- nein --> E
    G -- ja --> H[Speichern → review_events + Audit]
    H --> I[Hinweis: Zu Widget hinzufügen]
```

### F2b Lokale Einreichung über Formular

```mermaid
sequenceDiagram
    participant B as Besucher
    participant F as Formular (Block/Shortcode)
    participant W as admin-post.php
    participant M as Moderation
    B->>F: Bewertung, Text, Name, E-Mail, Einwilligung
    F->>W: POST mit Nonce + Honeypot
    W->>W: enabled? Nonce, Honeypot, Rate-Limit, Längen, Consent
    alt Fehler
        W-->>B: Formular mit Fehlermeldungen (Eingaben erhalten)
    else ok
        W->>M: Review mit status = new_review_status, Default pending
        W-->>B: Danke-Seite „Wird nach Prüfung veröffentlicht“
        M-->>M: optional Admin-Mail ohne Text
    end
```

### F2c Moderation

```mermaid
stateDiagram-v2
    [*] --> pending
    pending --> approved: Freigeben
    pending --> rejected: Ablehnen (Grund)
    pending --> flagged: Melden
    flagged --> approved
    flagged --> rejected
    approved --> archived: Archivieren
    approved --> pending: Zurück in Prüfung
    archived --> approved
    rejected --> [*]: nach retention_rejected_days gelöscht
    approved --> removed: Quelle hat entfernt (nur extern)
```

## F3 Plattform verbinden (Pro)

```mermaid
flowchart TD
    A[Quellen → Quelle hinzufügen] --> B[Plattform wählen]
    B --> C[Erklärung: nötiges Konto/Tarif, Rechte, Kosten, Datenlöschung]
    C --> D{Offizielle API verfügbar und gewünscht?}
    D -- nein --> FB[Fallback: Profil-Link, offizielles Embed nach Consent, manuelle Auswahl]
    D -- ja --> E[OAuth / Zugangsdaten eingeben]
    E --> F{Callback/State gültig, Token erhalten?}
    F -- nein --> F1[Fehlerzustand mit Ursache → states.md] --> E
    F -- ja --> G[Verbindung testen: Berechtigung, Profilzugriff]
    G --> H{Test ok?}
    H -- nein --> F1
    H -- ja --> I[Profile/Standorte/Apps auswählen]
    I --> J[Sync-Optionen: Intervall, Zeitraum Initialimport]
    J --> K[Vorschau: Anzahl, Beispiele, Attribution]
    K --> L[Initialimport als Job einplanen]
    L --> M[Status „Synchronisiert“ + nächste Ausführung]
```

## F4 Syncfehler beheben

```mermaid
flowchart TD
    A[Fehler-Notice auf Dashboard/Quellen] --> B[Quelle öffnen → Diagnose-Abschnitt]
    B --> C{Fehlerklasse rc.*}
    C -- rc.auth.invalid / Token abgelaufen --> D[„Neu verbinden“] --> R
    C -- rc.perm.failed --> E[Rollen/Freigaben beim Anbieter prüfen, Link zur Anleitung] --> R
    C -- rc.quota.exceeded --> F[Warten bis angezeigter Zeit oder Limits senken] --> R
    C -- rc.net.timeout / rc.temp.unavailable --> G[automatischer Retry, nichts tun] --> R
    C -- rc.contract.incompatible --> H[Plugin/Adapter aktualisieren] --> R
    C -- rc.data.invalid_payload --> I[Diagnose-Export ohne Secrets an Support]
    R[Verbindung testen] --> S{ok?}
    S -- ja --> T[Pause aufheben, Job erneut einplanen]
    S -- nein --> B
```

Garantie im gesamten Ablauf: vorhandene Reviews bleiben sichtbar; nichts wird wegen eines Fehlers gelöscht.

## F5 Widget veröffentlichen

```mermaid
flowchart TD
    A[Widgets → Neu] --> B["Quelle(n) wählen"]
    B --> C[Auswahl: alle freigegebenen / gefiltert / handverlesen]
    C --> D["Layout: Grid oder Liste (Pro: weitere)"]
    D --> E[Anzeige: erbt Darstellung, Abweichungen optional]
    E --> F[Vorschau Desktop/Mobil + Leerzustand]
    F --> G[Speichern]
    G --> H{Wie einbinden?}
    H -- Block-Editor --> I[Block „Review Captain: Reviews“ wählen, Widget auswählen]
    H -- Classic/Page Builder --> J["Shortcode kopieren: [review_captain v=&quot;1&quot; id=&quot;123&quot;]"]
    I --> K[Seite veröffentlichen]
    J --> K
    K --> L[Widget-Liste zeigt „Verwendet auf: Seite X“]
```

## F6 Anfrage konfigurieren (Pro)

```mermaid
flowchart TD
    A[Einstellungen › Anfragen & Zustellung] --> A1[Absender + Testmail, Fenster, Limits]
    A1 --> B[Anfragen → Neue Kampagne]
    B --> C[1 Auslöser: Woo erfüllt / Voucher eingelöst / Pass Check-in]
    C --> D[2 Empfänger + Verzögerung]
    D --> E[3 Ziel: Plattform oder lokales Formular – für alle gleich]
    E --> F[4 Nachricht: Vorlage, Sprache, Abmeldelink, Vorschau]
    F --> G[5 Prüfen: Fairness-Checks, Ausschlüsse, Testversand]
    G --> H{Testversand ok?}
    H -- nein --> F
    H -- ja --> I[Kampagne aktivieren – Default aus]
    I --> J[Queue: geplant → gesendet / übersprungen mit Grund]
```

Keine Verzweigung nach Rating/Zufriedenheit; Refund/Absage/Opt-out stoppen offene Einladungen.

## F7 Pro-Lizenz verwalten (Pro)

```mermaid
flowchart TD
    A[Review Captain → Lizenz] --> B{Status}
    B -- keine Lizenz --> C[Schlüssel eingeben → Aktivieren]
    C --> D{Aktivierung ok?}
    D -- nein: Limit erreicht --> D1[Hinweis: Website im Kundenkonto freigeben]
    D -- nein: ungültig --> D2[Fehler mit Prüfhinweis]
    D -- ja --> E[Aktiv: Plan, Sites, Ablauf, Updates]
    B -- aktiv --> E
    E --> F[Deaktivieren / Website wechseln]
    B -- abgelaufen --> G[Hinweis: Pro-Aktionen pausiert, Daten & Einstellungen bleiben] --> H[Verlängern im Kundenkonto]
    H --> E
```

## F8 CSV-Import mit Prüflauf (Core)

```mermaid
flowchart LR
    A[Reviews → Importieren] --> B[Datei wählen] --> C[Prüflauf: Fehler/Duplikate je Zeile]
    C --> D{übernehmen?}
    D -- nein --> A
    D -- ja --> E[Import als Job, import_batch] --> F[Ergebnis + „Rückgängig machen“]
```

Details: [../migration.md](../migration.md).

## Abnahme

Entspricht UX-002, wenn:

- [ ] Erstinstallation, Review erfassen, Plattform verbinden, Syncfehler beheben, Widget veröffentlichen, Anfrage konfigurieren und Pro-Lizenz verwalten als Flows vorliegen (F1–F7) und mit Produkt abgestimmt sind;
- [ ] jeder Flow Fehler-/Abbruchpfade enthält, die auf einen Zustand in [states.md](states.md) verweisen;
- [ ] kein Flow eine externe Verbindung automatisch aufbaut, Reviews bei Fehlern löscht oder nach Bewertung filtert;
- [ ] die Flows in den Usability-Tests (UX-006) verwendet wurden.
