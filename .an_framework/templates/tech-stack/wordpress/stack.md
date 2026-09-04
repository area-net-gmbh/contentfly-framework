<!-- PURPOSE: TECH-STACK-Vorlage (kopiert von /new-project nach an_project/docs/tech-stack.md).
     Freie Prosa, KEIN Work-Item-Frontmatter. Verengt die Baseline für dieses Projekt. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: WordPress

## Versionen
- WordPress **6.x** (aktuelle Stable), PHP **8.2+**, MySQL **8.0** / MariaDB **10.11**
- Node **20 LTS** für den Theme-Asset-Build (dart-sass), Composer optional für Tooling

## Build / Test / Run
- Lokale Umgebung: `wp-env`, Local, oder projekteigenes Docker-Setup.
- Theme-CSS: SCSS unter `assets/scss/` → `assets/css/main.css` via dart-sass
  (`npm run build` / `npm run watch`).
- WP-CLI für Routineaufgaben: `wp core version`, `wp plugin list`, `wp cache flush`.

## Struktur & Konventionen
- Eigener Code lebt im **Custom-Theme** (`wp-content/themes/<theme>/`) und in
  projekteigenen Plugins (`wp-content/plugins/<plugin>/`) — nie im Core.
- **Alle Assets lokal**: keine Google-Fonts-/JS-/CSS-CDNs; Fonts als WOFF2 unter
  `assets/fonts/`, Einbindung über `functions.php` (`wp_enqueue_*`).
- Kompiliertes `main.css` nicht von Hand editieren; Styles nur in SCSS.
- WordPress Coding Standards (WPCS/PHPCS), Textdomain projektweit einheitlich.

## Verengung der Baseline
- Commit-Scopes fachlich, z. B. `theme`, `nav`, `blocks`, `seo`, `perf`.
- Kein Inline-CSS/JS im Template; keine Styles über Page-Builder-Panels.
