# RC-006 – Kompatibilitätsziele

**Status:** **Freigegeben** (Entscheidung der Inhaberin am 16.11.2026); konkrete Versionsnummern werden am Feature-Freeze nach der Regel unten eingesetzt  
**Stand:** 2026-11-16  
**Quellen:** [Konzept §10, §15 Nr. 6](../../konzept.md), TODO RC-133

## Versionsregel

| Komponente | Regel | Vorschlag Stand 2026-11 (vor Freeze verifizieren) |
|---|---|---|
| WordPress | **Minimum 7.0** (Entscheidung der Inhaberin 17.11.2026, einheitlich für alle Captain-Plugins; WP 6.8 erzeugt unter PHP 8.5 Core-Deprecations) | „Tested up to“ = aktuelle Version (7.1) |
| PHP | niedrigste PHP-Version, die von WordPress empfohlen und noch von verbreiteten Hostern ausgeliefert wird; keine EOL-Version ohne Begründung | Minimum **8.5** (Entscheidung der Inhaberin 16.11.2026, MINVER-1); CI testet 8.5 und 8.6 |
| WooCommerce (optional) | **Minimum 11.2.2** (Entscheidung 17.11.2026, einheitlich; 11.2.2 statt 11.2.0 wegen Store-API-Checkout-Datenleck, woocommerce#70637) | „WC tested up to“ 11.3 |
| MySQL / MariaDB | WordPress-Mindestanforderung | empfohlen **MySQL 9.7 LTS** bzw. **MariaDB 12.3 LTS** (MINVER-1); ältere Server: nur Warnung in Diagnose/`doctor`, keine Sperre. Unique-Indizes und `utf8mb4` erforderlich |
| Review Captain Core für Pro | Pro deklariert `Requires Plugins: review-captain` und eine Mindest-Core-Version | Pro 1.0 verlangt Core 1.0 |
| Yoast SEO (optional) | siehe SEO-001 | aktuelle und vorherige Major-Version |

Die Plugin-Header `Requires at least`, `Requires PHP`, `WC requires at least`, `WC tested up to` werden aus einer zentralen Build-Konfiguration erzeugt und in CI geprüft (RC-033).

## Zielmatrix

| Bereich | Ziel | Status 1.0 | Prüfung |
|---|---|---|---|
| WooCommerce HPOS | kompatibel, Kompatibilität deklariert (`FeaturesUtil::declare_compatibility('custom_order_tables')`) | Pflicht (Pro, WooCommerce-Adapter) | E2E mit HPOS an und aus, inkl. Sync-Modus |
| WooCommerce Classic Checkout | unterstützt | Pflicht | Bestellung → Statuswechsel → Anfrage geplant |
| WooCommerce Blocks Checkout | unterstützt, Checkout wird **nicht** verändert; Kompatibilität für `cart_checkout_blocks` deklariert | Pflicht | wie oben |
| Gutenberg / Block-Themes | Blocks, FSE-Templates | Pflicht | Block-Rendering SSR, Editor-Vorschau |
| Klassische Themes | Shortcode | Pflicht | Shortcode-Ausgabe, Assets nur bei Nutzung |
| Multisite | Site-Aktivierung und Netzwerkaktivierung; Daten je Site getrennt | Pflicht | TEC-009, SET-016 |
| Mehrsprachigkeit WPML / Polylang | Übersetzbare Strings, Locale für Anfragen; Produktzuordnung über offizielle APIs | Basis Pflicht, Produktzuordnung Pro 1.0 „best effort“, dokumentiert | Testseite je Plugin |
| Page Builder Elementor | **natives Widget im Core 1.0** (RC-119A, `Integrations/Elementor`), Shortcode bleibt Fallback | Pflicht | Smoke-Test mit echtem Elementor |
| Page Builder Divi / Bricks / Beaver | Shortcode funktioniert; keine native Integration | Shortcode „getestet“, sonst „nicht unterstützt“ dokumentiert | Smoke-Test |
| Caching (Seiten-/Objektcache, CDN) | keine personenbezogenen Daten in öffentlich gecachten Antworten; Widgets cachefähig | Pflicht | TEC-012 |
| Consent-Plugins | externe Embeds nur nach Interaktion bzw. über einen dokumentierten Consent-Hook | Pflicht für Embeds | TEC-013 |
| Action Scheduler | genutzt, wenn vorhanden (z. B. mit WooCommerce); sonst eigener Runner | Pflicht | TEC-001 |
| Browser Frontend | aktuelle zwei Versionen von Chrome, Firefox, Safari, Edge; iOS Safari, Android Chrome | Pflicht | RC-137 |

## Nicht unterstützt (1.0, ausdrücklich dokumentieren)

- PHP-Versionen unter dem Minimum (Plugin aktiviert sich nicht und zeigt Hinweis, RC-032).
- Headless-Ausgabe über REST für öffentliche Widgets (keine öffentlichen Lese-Endpunkte in 1.0).
- Native Builder-Widgets außer Gutenberg.

## Offen / Freigabe

- ~~Entwicklung bestätigt PHP-Minimum 8.1~~ – ersetzt durch MINVER-1 (PHP 8.5). Offen: Hosting-Stichprobe der Pilotkunden (OPS-006), ob PHP 8.5 dort verfügbar ist.
- Konkrete WP-/Woo-Versionsnummern am Feature-Freeze eintragen.
