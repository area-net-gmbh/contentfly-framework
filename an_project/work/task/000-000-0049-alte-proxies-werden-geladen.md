---
id: 000-000-0049
title: Proxies aus einer älteren Version werden geladen statt ersetzt
status: review
depends_on: []
---

# Proxies aus einer älteren Version werden geladen statt ersetzt

## Context
**Gefunden bei der Messung zu `000-000-0041`** (2026-09-15), an der Datenkopie des Bestandsprojekts UFP.

Seit `000-000-0047` erzeugt Doctrine eine Proxy-Datei nur neu, wenn sie fehlt **oder älter ist als die
Entity-Datei** (`AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED`). Die Proxies liegen seit jeher in
`data/cache/doctrine` — demselben Verzeichnis, das Contentfly 1.x benutzt hat.

**Gemessen:** Eine Kopie von `data/` mit den ORM-2-Proxies des alten Betriebs, das migrierte Backend
darauf, `GET /file/get/<id>` →
`500 Interface "Doctrine\ORM\Proxy\Proxy" not found`. Die alte Datei war **jünger** als die Entity-Datei
und galt damit als aktuell. Mit `ALWAYS` hat das jeder Request überschrieben, deshalb fiel es vorher nicht auf.

**Das trifft nicht nur den Umstieg.** Kommt das Framework über Composer, tragen seine Dateien das Datum
aus dem Paketarchiv; eine Proxy aus dem laufenden Betrieb ist regelmässig jünger. Ein Framework-Update,
das eine Entity ändert, wird dann von einer veralteten Proxy bedient — ohne Meldung.

**Entschieden am 2026-09-15:** Ein Proxy-Verzeichnis **je Framework- und ORM-Stand**. Ein Datum
entscheidet dann über nichts mehr.

## Acceptance criteria
- [x] Die Proxies liegen unter `data/cache/proxies/<Kennung>`; die Kennung wechselt mit der installierten Version von `areanet/contentfly` und `doctrine/orm`.
- [x] Dateien in `data/cache/doctrine` werden nicht mehr geladen; das Verzeichnis darf gelöscht werden.
- [x] Test für die Kennung (stabil bei gleichem Stand, anders bei anderem) und dafür, dass der gebaute EntityManager in diesem Verzeichnis liegt.
- [x] An der UFP-Datenkopie mit den alten ORM-2-Proxies gemessen: das Backend antwortet wieder.
- [x] `breaking-changes.md` und `migration.md` nennen `data/cache/doctrine` als löschbaren Rest.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Unit-Test der Kennung, Integrationstest gegen den gebauten EntityManager, Messung am UFP-Probe-Backend
mit der unveränderten Datenkopie.

## Ergebnis

**Ein Proxy-Verzeichnis je Framework- und ORM-Stand.** `Classes\ORM\ProxyDirectory::path()` liefert
`data/cache/proxies/<Kennung>`; die Kennung sind zwölf Hex-Zeichen aus Version und Referenz von
`areanet/contentfly` und `doctrine/orm` (ohne Composer-Laufzeit: `APP_VERSION`). Benutzt vom Bootstrap
und von `appcms:install`. **`data/cache/doctrine` wird nicht mehr gelesen.** Alte Versionsverzeichnisse
bleiben absichtlich liegen — während eines Deployments bedient sich die noch laufende alte Installation
daraus — und dürfen jederzeit weg.

**Tests:** `tests/Unit/ORM/ProxyDirectoryTest.php` (gleiche Versionen → gleiches Verzeichnis; neuere
Framework-Version, andere Referenz bei gleicher Version, neuere ORM-Version → je ein anderes; der Pfad
liegt unter `data/cache/proxies/` und nicht in `cache/doctrine`).
`ProxyGenerationModeTest::testAProxyFromAnEarlierInstallationIsNotLoaded` legt eine Proxy-Datei mit
Zukunftsdatum in `data/cache/doctrine`, die beim Laden wirft, und baut dann einen Proxy: er entsteht im
neuen Verzeichnis, die alte Datei bleibt unberührt.

**Gegenprobe** mit dem `bootstrap.php` von master: derselbe Test rot, mit genau der Meldung aus der
alten Datei.

**Gemessen am UFP-Probe-Backend** mit einer unveränderten Kopie von `data/`, in der die ORM-2-Proxies aus
dem alten Betrieb liegen (`__CG__AreanetPIMEntityUser.php` und zwei weitere): Der Aufruf antwortet
wieder — vorher `500 Interface "Doctrine\ORM\Proxy\Proxy" not found`. Das neue Verzeichnis
`data/cache/proxies/<Kennung>` ist angelegt und trägt die Proxy; die alten Dateien bleiben unangetastet
liegen.

**Doku:** Register-Eintrag unter *Doctrine ORM 3*, der Eintrag zum Metadaten-Cache nennt
`data/cache/doctrine` mit; Leitfaden Phase 4 leert jetzt alle drei Verzeichnisse; `deployment.md` führt
die Proxies in der Cache-Tabelle. `migration.md` auf 115 Einträge.

**Verifiziert:** volle Suite `Tests: 608, Assertions: 1938, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.
