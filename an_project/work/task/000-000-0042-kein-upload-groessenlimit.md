---
id: 000-000-0042
title: Upload — das Framework kennt kein Größenlimit
status: review
depends_on: []
---

# Upload — das Framework kennt kein Größenlimit

## Context
**Gefunden bei `007-005-0003`** (Befund F-3). UFP hatte `FILE_MAX_UPLOAD_SIZE` (20 MB) als Patch im
alten Framework; mit dem Paket ist er weg, und Contentfly 2 liest keinen solchen Schlüssel. Es gilt
nur PHPs `upload_max_filesize`/`post_max_size` — eine Einstellung des Servers, nicht der Anwendung.

## Acceptance criteria
- [x] Entschieden: `FILE_MAX_UPLOAD_SIZE` im Framework (optional, `UploadValidator`, `413`) oder begründet verworfen.
- [x] Bei Umsetzung: Test, der eine zu grosse Datei abweist und keine Zeile hinterlässt.
- [x] `breaking-changes.md` nennt, was ein Projekt mit dem alten Patch-Schlüssel tut.

## Verification
Upload über der Grenze gegen eine Testinstallation.

## Ergebnis

**Entschieden am 2026-09-15: `FILE_MAX_UPLOAD_SIZE` kommt optional ins Framework.**

- `Classes\Config::$FILE_MAX_UPLOAD_SIZE` (Bytes, Vorgabe `null`). `UploadValidator` prüft die Grösse
  der gespeicherten Temporärdatei — nicht die Angabe des Clients — **vor** Name und Typ und vor jedem
  Schreiben: `413`, `contentfly_file_too_large`, die Grenze als Wert. Ein ungültiger Wert (`'20MB'`) ist ein
  Konfigurationsfehler mit Meldung, nicht „keine Grenze“; eine Ziffernfolge aus der Umgebung zählt.
- **Mitbehoben:** Ein Upload über PHPs `upload_max_filesize` antwortete `400 contentfly_general_missing_params`,
  weil PHP keine Datei anlegt. Jetzt `413 contentfly_file_too_large` mit PHPs Grenze.
- Vorlage `custom/config.php` liest `APP_FILE_MAX_UPLOAD_SIZE`; der Testserver läuft mit 1 MiB
  (`tools/ci/prepare-test-environment.sh`, `tests/README.md`), unter PHPs Vorgabe von 2M.

**Tests:** `UploadValidatorTest` fünf neue Fälle (ohne Grenze grosse Datei angenommen; genau an der Grenze
angenommen, ein Byte darüber `413` mit Wert; Grenze als Ziffernfolge; ungültige Grenze wirft; PHPs Grenze
`413`). `FileApiTest` drei neue gegen den Testserver: ein Byte über 1 MiB → `413`, **keine Zeile in
`pim_file`, kein Verzeichnis** unter `data/files/`; genau 1 MiB gespeichert; über `upload_max_filesize`
→ `413`. **Gegenprobe** mit dem `UploadValidator` von master: die beiden Abweisungs-Tests rot.

**Register:** Eintrag unter *Konfiguration* — neuer Schlüssel, was ein Projekt mit dem alten
Patch-Schlüssel tut (stehen lassen, er wirkt wieder), und die Änderung von `400` auf `413`. Phase 5 des
Leitfadens vermerkt, dass der vierte Patch-Schlüssel von UFP jetzt vom Framework kommt; `migration.md`
auf 113 Einträge.

**Verifiziert:** volle Suite `Tests: 601, Assertions: 1912, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0, `tools/check-template-config.sh` grün.
