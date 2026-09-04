---
id: 012-001-0004
title: UI-Assets und app.twig löschen
status: done
depends_on: [012-001-0001,012-001-0002,012-001-0003]
---

# UI-Assets und app.twig löschen

## Context
Nach dem Entfernen der Controller ist `lib/contentfly-ui/` bis auf `install.twig` toter Ballast — 31 MB Twig-Template und Angular-Assets.

## Acceptance criteria
- [x] `lib/contentfly-ui/app.twig` und `lib/contentfly-ui/assets/` sind gelöscht.
- [x] `lib/contentfly-ui/install.twig` bleibt erhalten — der `InstallController` braucht es bis 012-002.
- [x] Der Twig-Pfad in `lib/contentfly/bootstrap.php:205` zeigt nur noch auf Verzeichnisse, die es gibt.
- [x] `custom/Views/` ist geprüft: Was nur die gelöschte Oberfläche bedient hat, ist mit entfernt.
- [x] Die Repo-Größe ist entsprechend gesunken.

## Verification
Anwendung bootet, der Installer unter `APP_INSTALLER_URL` rendert weiterhin. `du -sh lib/` zeigt den Rückgang.
