---
id: 015-000-0020
title: Hochgeladenes HTML/SVG wird inline aus dem Web-Root ausgeliefert
status: done
depends_on: []
---

# Hochgeladenes HTML/SVG wird inline aus dem Web-Root ausgeliefert

## Context
**Security-Scan 2026-09, LOW. Finding F34.**

Ohne `FILE_ALLOWED_TYPES` (Vorgabe `null`) sperrt `UploadValidator` nur serverseitig ausführbare
Endungen. `.html`, `.htm`, `.xhtml` und `.svg` landen unter `data/files/<id>/`
(`Controller/FileController.php:272`), und Apache liefert sie direkt aus, weil `.htaccess` nur
nicht existierende Dateien umschreibt. Im readfile-Modus gibt `/file/get` ausserdem den vom Client
angegebenen MIME-Typ als `Content-Type` zurück. Die CSP aus `bootstrap-web.php` gilt für Dateien,
die Apache selbst ausliefert, nicht.

Folge: Ein Benutzer mit Upload-Recht legt `page.html` oder ein SVG mit Skript ab und schickt den
Link `/data/files/<id>/page.html` an ein Opfer. Das Skript läuft im Origin der Anwendung. Die
Authentifizierung nutzt Bearer-Header statt Cookies, und das Framework liefert keine Oberfläche
mit. Die Wirkung beschränkt sich deshalb auf Content-Spoofing und auf ein Frontend, das ein Projekt
selbst im selben Origin betreibt.

## Acceptance criteria
- [x] Dateien unter `data/files` werden mit `Content-Disposition: attachment` und `X-Content-Type-Options: nosniff` ausgeliefert, etwa über eine `.htaccess` in `data/files`, oder Markup-Typen werden beim Upload gesperrt.
- [x] `/file/get` leitet den `Content-Type` aus dem Inhalt ab, nicht aus der Angabe des Clients.
- [x] Bilder und PDFs bleiben inline anzeigbar, sofern das gewollt ist. Die Entscheidung ist dokumentiert.

## Verification
Test: Eine `.html`-Datei hochladen und über `/data/files/<id>/…` und `/file/get` abrufen. Vor dem
Fix kommt `text/html` inline, nach dem Fix `attachment` mit `nosniff`.
