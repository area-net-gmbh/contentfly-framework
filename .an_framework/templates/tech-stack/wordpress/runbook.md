<!-- PURPOSE: RUNBOOK-Vorlage für ein WordPress-Projekt/Theme (kopiert von /new-project
     nach an_project/docs/runbook.md). Freie Prosa, KEIN Work-Item-Frontmatter. -->

# Runbook — lokales Setup (WordPress)

## Voraussetzungen
- Lokale WP-Umgebung: wp-env, Local (by Flywheel) oder Docker
- Node.js für den Theme-Build, WP-CLI

## 1. Umgebung hochfahren
```sh
# wp-env
npx wp-env start
# oder Docker
docker compose up -d
```

## 2. Theme-Assets bauen (areanet-theme)
```sh
npm ci
npm run build      # einmalig
npm run watch      # während der Entwicklung
```
<!-- SCSS aus assets/scss/ → assets/css/main.css (dart-sass). -->

## 3. WP-CLI-Routine
```sh
wp core install --url=... --title=... --admin_user=... --admin_email=...
wp plugin activate --all
wp rewrite flush
```

## 4. Zugriff
- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin

## Troubleshooting
<!-- Permalinks (wp rewrite flush), Datei-Rechte, Debug-Log (WP_DEBUG). -->
