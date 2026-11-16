# Später prüfen: Captain-Google-App („Verbinden“ ohne eigenes Google-Projekt) – RC-026A

**Status:** zurückgestellt (Inhaberin, 16.11.2026). In 1.0 legt jeder Kunde sein **eigenes Google-Cloud-Projekt** an ([Anleitung](../user/platform-setup.md#google-business-profile)). Diese Seite sammelt alles, was für eine spätere zentrale App nötig wäre.

## Was die App bringen würde

Kundinnen und Kunden klicken in Review Captain nur auf **„Mit Google verbinden“**, melden sich mit ihrem Google-Konto an und wählen ihren Standort. Sie müssen **kein** Google-Cloud-Projekt anlegen, keine API freischalten lassen und keine Client-ID/Secret kopieren. Das senkt die Einstiegshürde deutlich (heute: ca. 20–40 Minuten Einrichtung plus Wartezeit auf Googles Freischaltung).

## Warum es nicht einfach im Plugin geht

- Google erlaubt nur fest registrierte Weiterleitungsadressen (Redirect-URIs). Jede Kundenseite hat eine andere Domain – eine zentrale App braucht deshalb einen **eigenen Vermittlungsserver** („Broker“), über den die Anmeldung läuft und der zur Kundenseite zurückleitet.
- Das Client-Secret der App darf nicht im Plugin stehen (sonst könnte jeder es auslesen und missbrauchen) – der Token-Tausch muss auf dem Broker passieren.
- Die App greift mit dem Scope `business.manage` auf fremde Unternehmensprofile zu; dafür verlangt Google eine **OAuth-Verifizierung** (Prüfung der App, Datenschutzerklärung, ggf. Video der Nutzung) und die **Freischaltung der Business Profile APIs** für das Projekt.

## Was dafür nötig wäre

| Bereich | Inhalt | Wer |
|---|---|---|
| Google-Cloud-Projekt | eigenes Projekt des Herstellers, Business Profile APIs freigeschaltet, Kontingent für alle Kunden ausreichend (ggf. Erhöhung beantragen) | Inhaberin/Team |
| OAuth-Verifizierung | Zustimmungsbildschirm „Extern“, Domain-Nachweis, Datenschutzerklärung und Nutzungsbedingungen der App, Begründung des Scopes; Dauer laut Erfahrungswerten mehrere Wochen (ungeprüft) | Inhaberin/Team |
| Broker-Server | dauerhaft betriebener HTTPS-Dienst: startet die Anmeldung, tauscht den Code gegen Tokens, gibt den Refresh-Token **verschlüsselt** an die Kundenseite weiter und **speichert selbst keine Tokens**; signierte Kommunikation Site↔Broker; Rate-Limits; Monitoring | Entwicklung + Betrieb |
| Recht | Auftragsverarbeitungsvertrag (AVV) bzw. Prüfung, ob der Broker personenbezogene Daten verarbeitet; Datenschutzerklärung des Brokers | Recht/Inhaberin |
| Plugin | zweiter Verbindungsweg „über Captain-App“ neben „eigenes Projekt“; Token-Refresh weiterhin auf der Kundenseite (direkt bei Google, wenn der Broker nur den Erst-Tausch macht – zu klären) | Entwicklung (Pro) |
| Kosten | Server-Betrieb, Wartung, Google-Kontingent; bei Ausfall des Brokers können **neue** Verbindungen nicht hergestellt werden (bestehende laufen weiter, wenn der Refresh direkt bei Google erfolgt) | – |

## Entscheidungskriterien für später

- Wie viele Supportfälle entstehen bei der Einrichtung des eigenen Projekts (OPS-003/OPS-008)?
- Brechen Kunden die Einrichtung ab (Pilot OPS-006)?
- Wünschen Agenturen eine zentrale App für viele Kundenstandorte?

Bei Bedarf wird ein neues ADR geschrieben (Bedingungen aus [ADR-0008](../adr/0008-auth-modell-betreiber-credential.md) gelten).
