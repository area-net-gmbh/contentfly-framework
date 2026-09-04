---
id: 012-002-0003
title: InstallController und Twig entfernen
status: review
depends_on: [012-002-0002]
---

# InstallController und Twig entfernen

## Context
Mit dem Command ist der letzte UI-Verbraucher ersetzt. Damit fällt auch Twig — der Installer war sein letzter Nutzer im Framework.

## Acceptance criteria
- [x] `lib/contentfly/Controller/InstallController.php`, `install.twig` und das leere `lib/contentfly-ui/` sind gelöscht.
- [x] Die Routen `APP_INSTALLER_URL` und die Definition `$app['install.controller']` sind entfernt.
- [x] Die Twig-Registrierung in `bootstrap.php` (`twig.path`, Service-Provider) ist entfernt.
- [x] Die Twig-Nutzung in `Classes/Controller/BaseController.php` ist entfernt.
- [x] Keine Twig-Verwendung mehr im Code — das Paket kann in Epic 006 aus dem Manifest fallen.
- [x] `custom/Views/partials/_email_layout.twig` ist entschieden: Es hat heute **keinen** PHP-Verbraucher (`Mailer.php` nutzt kein Twig) und stirbt mit Twig — es sei denn, HTML-Mails sollen weiterhin ein Template bekommen. Dann braucht es einen Ersatz ohne Twig.

## Verification
`grep -rn "twig\|Twig\|InstallController" lib custom bin index.php` liefert keine Treffer. Anwendung bootet, API antwortet.
