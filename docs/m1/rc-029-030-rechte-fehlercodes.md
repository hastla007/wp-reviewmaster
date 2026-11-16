# Rollen, Capabilities und Fehlercodes – RC-029, RC-030

**Status:** Entwurf – wird in RC-037 implementiert  
**Stand:** 2026-11-16

## RC-029 – Capabilities

| Capability | Erlaubt | Administrator | Editor | Shop Manager (Woo) | Neue Rolle `rc_review_manager` |
|---|---|---|---|---|---|
| `rc_view_reviews` | Reviews-Liste und Dashboard sehen | ✓ | ✓ | ✓ | ✓ |
| `rc_moderate_reviews` | freigeben, ablehnen, archivieren, Spam | ✓ | ✓ | ✓ | ✓ |
| `rc_manage_reviews` | manuell erfassen, bearbeiten (lokal), löschen, importieren, exportieren | ✓ | – | ✓ | ✓ |
| `rc_manage_widgets` | Widgets anlegen/ändern | ✓ | ✓ | ✓ | ✓ |
| `rc_manage_testimonials` | Testimonials, Rechtebelege | ✓ | – | – | ✓ |
| `rc_manage_trust` | Trustmarks/Awards (Pro-Folge) | ✓ | – | – | ✓ |
| `rc_connect_sources` | Quellen verbinden/trennen, Profile | ✓ | – | – | – |
| `rc_manage_requests` | Kampagnen anlegen/ändern, Suppression einsehen | ✓ | – | ✓ | – |
| `rc_send_requests` | Kampagnen aktivieren, manuell erneut senden | ✓ | – | – | – |
| `rc_view_reports` | Analytics, Diagnose | ✓ | – | ✓ | ✓ |
| `rc_manage_privacy` | Aufbewahrung, Export/Löschung, Gefahrenzone | ✓ | – | – | – |
| `rc_manage_settings` | globale Einstellungen, Integrationen, Connectoren | ✓ | – | – | – |

- Vergabe bei Aktivierung; Änderung über Tab „Zugriff & Rollen“ (Rollenmatrix), gespeichert direkt in den WP-Rollen; Reset stellt obige Defaults her.
- Multisite: Super-Admin hat alle Rechte implizit; Rechte gelten je Site.
- Jede Admin-Seite und jede schreibende Aktion prüft genau eine Capability plus Nonce (`check_admin_referer( 'rc_{action}' )`).
- Deinstallation entfernt Capabilities und die Rolle nur bei aktiver Löschoption.

## RC-030 – Fehlercodes und Integrationsstatus

Format: `snake_case`, stabil, dokumentiert; UI übersetzt Code → Text (UX-004). Klasse steuert TEC-006.

| Code | Klasse | Bedeutung | Automatik | Adminaktion |
|---|---|---|---|---|
| `ok` | – | erfolgreich | – | – |
| `incompatible_contract` | permanent | Vertragsversion unbekannt | pausieren | Plugins aktualisieren |
| `invalid_payload` | data | Payload verletzt Schema | verwerfen, Zähler | Quelle prüfen |
| `auth_failed` | auth | Credential ungültig (401) | pausieren | neu verbinden |
| `auth_expired` | auth | Token abgelaufen, Refresh scheitert | pausieren | neu verbinden |
| `auth_forbidden` | auth | fehlende Berechtigung/API-Freigabe (403) | pausieren | Berechtigung beantragen |
| `quota_exceeded` | rate_limit | 429 / Kontingent | warten bis `retry_after` | ggf. Intervall erhöhen |
| `timeout` | temporary | Zeitüberschreitung | Retry mit Backoff | – |
| `provider_unavailable` | temporary | 5xx / Netzwerk | Retry mit Backoff | – |
| `not_found` | permanent | Profil/App nicht mehr vorhanden (404) | pausieren | Profil neu wählen |
| `review_removed` | info | Review beim Anbieter gelöscht | tombstone | – |
| `crypto_key_missing` | permanent | Entschlüsselung nicht möglich | pausieren | Schlüssel/Neuverbindung |
| `source_unavailable` | paused | Quell-Plugin inaktiv | pausieren | Plugin aktivieren |
| `license_inactive` | paused | Pro-Recht fehlt | pausieren | Lizenz |
| `entitlement_missing` | paused | Recht nicht im Plan | pausieren | Lizenz |
| `consent_missing` | skip | keine Versandgrundlage | skip | – |
| `suppressed` | skip | abgemeldet/gesperrt | skip | – |
| `recipient_unresolved` | skip | Kontakt unbekannt/gelöscht | skip | – |
| `cooldown_active` | skip | Mindestabstand | skip | – |
| `duplicate` | info | Idempotenz griff | ignorieren | – |
| `sender_missing` | config | Absender nicht eingerichtet | pausieren | Einstellungen |
| `transport_failed` | temporary | Mailtransport lehnt ab | Retry, dann `failed` | Transport prüfen |
| `cron_delayed` | warning | Jobs überfällig | – | System-Cron |
| `retry_exhausted` | permanent | max. Versuche erreicht | dead-letter | manuell wiederholen |

**Integrationsstatus** (Quellen, Suite-Adapter, Connectoren): `not_installed`, `inactive`, `incompatible_contract`, `not_connected`, `connected`, `warning` (letzter Lauf mit temporärem Fehler oder Quote > Warnschwelle), `error` (auth/permanent), `paused` (Lizenz/Quelle/Admin).
