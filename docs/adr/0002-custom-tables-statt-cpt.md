# 0002 – Reviews in eigenen Tabellen statt Custom Post Type

- **Status:** vorgeschlagen
- **Datum:** 2026-11-16
- **Betrifft:** Schema
- **Tasks:** RC-018, RC-028, SEO-004, TEC-014

## Kontext
Reviews benötigen Unique-Constraints auf externe IDs (Idempotenz), Filter über Rating/Quelle/Status und sollen bis 100.000 Einträge performant bleiben. Ein CPT würde zudem Archive, Sitemaps und Yoast-Indexables erzeugen, die nicht indexiert werden sollen.

## Entscheidung
Eigene Tabellen `rc_*` (siehe docs/m1/datenmodell.md). Keine öffentlichen Einzel-URLs für Reviews in 1.0.

## Alternativen
CPT + Postmeta: einfache WP-Integration, aber keine Unique-Constraints, langsame Meta-Queries, ungewollte SEO-Nebenwirkungen.

## Folgen
Eigene Admin-Listen (WP_List_Table), eigene Privacy-Exporter, Yoast sieht Reviews nur über Widgets/Schema-Pieces.
