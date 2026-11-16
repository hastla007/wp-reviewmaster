# Rollen- und Capability-Modell (RC-029)

Status: Spezifikation für Core v1, Stand 2026-11-16. Die Capabilities selbst sind verbindlich in [`conventions.md`](conventions.md) festgelegt; dieses Dokument ergänzt Zuordnung zu Seiten/Aktionen, Installation und Multisite. Neue Capabilities nur per ADR.

## 1. Capabilities

| Capability | Zweck | Standardrollen |
|---|---|---|
| `review_captain_moderate` | Reviews/Testimonials ansehen, anlegen, moderieren | administrator, editor |
| `review_captain_manage_sources` | Quellen/Profile verbinden/trennen | administrator |
| `review_captain_manage_requests` | Kampagnen/Anfragen (Pro) | administrator |
| `review_captain_view_reports` | Auswertungen/Diagnose lesen | administrator |
| `review_captain_manage_trust` | Trustmarks/Badges (Pro) | administrator |
| `review_captain_manage_privacy` | Export/Import/Löschung, Aufbewahrung | administrator |
| `review_captain_manage_settings` | globale Einstellungen | administrator |

Grundsätze:

- Prüfung immer mit `current_user_can( '<capability>' )`, nie über Rollennamen.
- Jede schreibende Aktion: Capability **und** Nonce `review_captain_{action}` (`check_admin_referer()` bzw. `wp_verify_nonce()`); REST: explizite `permission_callback` + REST-Nonce (`X-WP-Nonce`).
- Pro-Capabilities (`manage_requests`, `manage_trust`) werden vom Core installiert, damit Rollen unabhängig vom Lizenzstatus stabil bleiben. Das **Entitlement** prüft Pro zusätzlich; eine Capability ersetzt nie die Lizenzprüfung und umgekehrt.
- Lesende Anzeige einer Seite verlangt dieselbe Capability wie ihre Hauptaktion, sofern unten nicht anders angegeben.

## 2. Mapping Seiten → Capability

Menü-Hauptpunkt „Review Captain“ (`add_menu_page`) wird angezeigt, wenn der Nutzer mindestens eine Capability besitzt; Capability des Hauptmenüs ist die erste Unterseite, auf die er Zugriff hat.

| Seite (Slug-Vorschlag) | Ansicht | Herkunft |
|---|---|---|
| Dashboard (`review-captain`) | `review_captain_moderate` **oder** `review_captain_view_reports`; Karten werden je Capability ein-/ausgeblendet | Core |
| Reviews (`review-captain-reviews`) | `review_captain_moderate` | Core |
| Testimonials & Vertrauenssignale (`review-captain-trust`) | Text-Testimonials: `review_captain_moderate`; Trustmarks/Badges: `review_captain_manage_trust` | Core/Pro |
| Anfragen (`review-captain-requests`) | `review_captain_manage_requests` | Pro |
| Widgets (`review-captain-widgets`) | `review_captain_moderate` | Core |
| Quellen / Profile & Apps (`review-captain-sources`) | `review_captain_manage_sources` | Core (Link-Profile), Pro (API) |
| Integrationen (`review-captain-integrations`) | `review_captain_manage_sources` | Pro |
| Analytics (`review-captain-analytics`) | `review_captain_view_reports` | Pro |
| Einstellungen (`review-captain-settings`) | `review_captain_manage_settings`; Tab „Datenschutz & Aufbewahrung“ zusätzlich `review_captain_manage_privacy` | Core |
| Diagnose (`review-captain-diagnostics`, read-only) | `review_captain_view_reports` | Core |
| Import/Export (`review-captain-tools`) | `review_captain_manage_privacy` | Core |
| Pro-Lizenz | `review_captain_manage_settings` (Pro kann zusätzlich `activate_plugins` verlangen) | Pro |

## 3. Mapping Aktionen → Capability

| Aktion | Capability | Nonce-Action |
|---|---|---|
| Review/Testimonial manuell anlegen, bearbeiten | `review_captain_moderate` | `review_captain_save_review` |
| Freigeben, ablehnen, markieren, archivieren | `review_captain_moderate` | `review_captain_moderate_review` |
| Review endgültig löschen / anonymisieren | `review_captain_manage_privacy` | `review_captain_delete_review` |
| Widget-/Shortcode-Builder, Vorschau | `review_captain_moderate` | – (nur lesend) |
| Block-Editor-Vorschau (REST `GET /review-captain/v1/preview`) | `edit_posts` (**Arbeitsannahme**, liefert nur öffentlich renderbare, freigegebene Inhalte) | REST-Nonce |
| Profil (Link/CTA) anlegen/ändern | `review_captain_manage_sources` | `review_captain_save_profile` |
| Quelle verbinden (OAuth starten, Credential speichern) | `review_captain_manage_sources` | `review_captain_connect_source` |
| Quelle trennen, Token widerrufen | `review_captain_manage_sources` | `review_captain_disconnect_source` |
| Sync jetzt ausführen, Sync-Job wiederholen | `review_captain_manage_sources` | `review_captain_run_sync` |
| Einladungs-Job wiederholen/abbrechen | `review_captain_manage_requests` | `review_captain_retry_request` |
| Kampagnen anlegen/aktivieren, Testversand | `review_captain_manage_requests` | `review_captain_save_campaign` |
| Trustmark/Badge anlegen, Gültigkeit setzen | `review_captain_manage_trust` | `review_captain_save_trust_asset` |
| CSV-Import, Import-Rollback | `review_captain_manage_privacy` | `review_captain_import`, `review_captain_import_rollback` |
| CSV-Export | `review_captain_manage_privacy` | `review_captain_export` |
| Aufbewahrungsfristen ändern | `review_captain_manage_privacy` | `review_captain_save_retention` |
| WordPress Datenschutz-Export/-Löschung (Werkzeuge) | WordPress-Core-Rechte (`export_others_personal_data`, `erase_others_personal_data`); Review Captain registriert nur Exporter/Eraser | WP-Core |
| Globale Einstellungen speichern | `review_captain_manage_settings` | `review_captain_save_settings` |
| Rollen-Tab: Capabilities Rollen zuweisen | `review_captain_manage_settings` **und** `promote_users`; nur Capabilities, die der handelnde Nutzer selbst besitzt | `review_captain_save_roles` |
| Diagnose ansehen, Debug-Export (ohne Secrets) | `review_captain_view_reports` | `review_captain_debug_export` |
| Dead-Letter-Job allgemein neu einreihen (Diagnose) | Capability des Jobbereichs (Sync → `manage_sources`, Anfrage → `manage_requests`, Import/Retention → `manage_privacy`, sonst `manage_settings`) | `review_captain_requeue_job` |
| Restore-/Klon-Sperre aufheben ([reconciliation.md](reconciliation.md)) | `review_captain_manage_settings` | `review_captain_confirm_instance` |
| Daten bei Deinstallation löschen (Option) | `review_captain_manage_settings` **und** `review_captain_manage_privacy` | `review_captain_save_settings` |
| Frontend-Einreichung (`admin-post.php`) | keine Capability (öffentlich); Nonce + Honeypot + Rate-Limit | `review_captain_submit_review` |

WP-CLI läuft ohne Nutzerkontext; schreibende CLI-Befehle sind Betreiberwerkzeuge (Shell-Zugriff = Vertrauensgrenze) und protokollieren `actor_type = cli` im `audit_log`. `--user=<id>` wird respektiert, wenn angegeben.

## 4. Installation und Upgrade der Rechte

- Aktivierung (und jede Migration) fügt fehlende Capabilities den Standardrollen hinzu (`WP_Role::add_cap`), sofern die Rolle existiert.
- `review_captain_caps_installed` merkt sich, welche Capability welcher Rolle bereits einmal vergeben wurde. Hat ein Betreiber eine Capability bewusst entfernt, wird sie bei späteren Upgrades **nicht** erneut vergeben.
- Fehlt die Rolle `editor` (Custom-Rollenmodell), wird nichts erzwungen; die Diagnose zeigt, welche Rollen welche Capability besitzen.
- Deinstallation mit Löschoption entfernt die Capabilities aus allen Rollen (`wp_roles()->role_objects`).
- Pro/Connectoren dürfen **keine** eigenen Capabilities außerhalb dieser Liste einführen, ohne ADR im Core.

## 5. Multisite

- Rollen und Capabilities sind in WordPress pro Site gespeichert (`{prefix}user_roles`); Installation erfolgt je Site (siehe [multisite.md](multisite.md)).
- **Super Admins** bestehen in Multisite jede `current_user_can()`-Prüfung (außer `do_not_allow`) und haben damit alle Review-Captain-Rechte auf jeder Site – auch ohne Rolle auf der Site. Das ist gewollt; Aktionen werden mit `actor_user_id` auditiert.
- Site-Administratoren erhalten die Rechte nur für ihre Site. Es gibt in v1 **keine** Netzwerkadmin-Seite; netzwerkweite Vorgaben (z. B. `REVIEW_CAPTAIN_ENCRYPTION_KEY`) erfolgen über Konstanten in `wp-config.php`.
- `promote_users` ist in Multisite für Site-Admins standardmäßig vorhanden; der Rollen-Tab darf trotzdem keine Capability vergeben, die der Nutzer selbst nicht hat.

## Abnahme

- [ ] Nach Aktivierung besitzt `administrator` alle sieben, `editor` nur `review_captain_moderate`.
- [ ] Jede Admin-Seite und jede Aktion aus §2/§3 ist per Test mit einem Nutzer ohne die Capability geprüft: kein Seiteninhalt, HTTP 403/`wp_die`, keine Datenänderung.
- [ ] Jede schreibende Aktion schlägt mit gültiger Capability, aber fehlender/falscher Nonce fehl.
- [ ] Jede REST-Route hat eine explizite `permission_callback` (statischer Test über `rest_get_server()->get_routes()`).
- [ ] Entfernte Capability bleibt nach Upgrade entfernt.
- [ ] Editor kann keine Quelle verbinden, keinen Export auslösen und keine Einstellungen ändern.
- [ ] Multisite: Admin von Site 2 kann auf Site 3 nichts; Super Admin kann auf beiden Sites alles.
- [ ] Rollen-Tab verhindert Rechteausweitung (Nutzer ohne `promote_users` oder ohne die Capability selbst kann sie nicht vergeben).
