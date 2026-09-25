---
id: 011-000-0000
title: Release der neuen Framework-Version
status: done
depends_on: [007-000-0000, 009-000-0000, 010-000-0000]
---

# Release der neuen Framework-Version

## Goal
Die neue Contentfly-Version ist veröffentlicht, dokumentiert und für Bestandsprojekte beziehbar.
Kein Cutover im Produktivsinn — aus diesem Repo wird nichts ausgerollt (siehe *Scope* in
`an_project/project-description.md`); „fertig" heißt hier: eine Version, die ein Projekt
tatsächlich einsetzen und auf die es migrieren kann.

## Erfolgskriterien
- **Silex ist weg** — kein `silex/silex`, kein `pimple`, keine Symfony-2/3-Komponente mehr im
  Baum. Prüfbar, nicht behauptet.
- **`composer audit --locked` sauber** unter der Zielplattform PHP 8.5.
- **Version vergeben und Bruchstellen benannt:** eine neue Hauptversion mit vollständiger Liste
  der Breaking Changes; `version.php` und die Framework-Metadaten stimmen überein.
- **Beziehbar:** Der in 007 entschiedene Bezugsweg (Composer-Paket statt kopiertem `lib/`-Baum)
  ist umgesetzt und einmal aus Projektsicht durchgespielt — frischer Checkout, Installation,
  lauffähig.
- **Vorlage stimmt:** `custom/` zeigt den Zielzustand — Example-Controller, -Entity, -Service und
  -Command laufen auf der neuen Version und taugen wieder als Referenz.
- **Doku nachgezogen:** `README.md`, `an_project/docs/runbook.md`, `technical.md`, `dev-guide.md`
  und der Migrationsleitfaden aus 007 beschreiben den neuen Stand, nicht den alten.
- **Der Antwort-Envelope ist vereinheitlicht:** Die sieben Antwortformen der API sind auf
  `data`/`errors`/`meta` gebracht, je Endpunkt wie in `an_project/docs/api-envelope.md`
  beschrieben, und der Bruch steht vollständig im Migrationsleitfaden aus `007`. Entschieden
  mit `000-000-0014`; bewusst hierher und nicht nach `009` gelegt, weil die
  Charakterisierungstests aus `008` die Abnahmegrundlage des Kernel-Wechsels sind.
- **Upgrade-Pfad festgehalten:** Symfony 8.4 LTS (erwartet Nov 2027) als geplanter nächster
  Schritt, abgesichert durch das CI-Gate „0 Deprecations" aus 009.

## Bilanz — jedes Kriterium mit seinem Beleg
<!-- Erstellt mit 011-004-0002 am 2026-09-17. -->

| Kriterium | Beleg | Stand |
|---|---|---|
| **Silex ist weg** | `LockGuaranteesTest::testTheLockCarriesNoSilexOrPimplePackage` prüft die Paketnamen im Lock; `NoSilexTypesTest` hält die Typnamen aus dem Code. Beide blockierend. | ✅ |
| **Keine Symfony-2/3-Komponente** | siehe unten — die fünf `v3.7.x`-Einträge sind `*-contracts` und **keine** Symfony-3-Komponenten | ✅ |
| **`composer audit --locked` sauber** | Job `check: composer audit` in der Pipeline, blockierend; dazu `audit-ausnahmen-pruefen.sh`, das eine nicht mehr greifende Ausnahme rot macht | ✅ |
| **…unter der Zielplattform PHP 8.5** | `LockGuaranteesTest::testNoPackageCapsPhpBelowTheTargetPlatform` für die Constraint-Seite; **gelaufen mit `000-000-0054`** auf PHP 8.5.11: `check-platform-reqs` für alle 17 Anforderungen `success`, `composer audit --locked` ohne Befund, Suite 833 grün (2026-09-25). Die Pipeline fährt 8.5 noch nicht — siehe *Was offen bleibt* | ✅ |
| **Version vergeben, `version.php` und Metadaten stimmen überein** | `PackageManifestTest`; `v2.0.0` steht seit `011-004-0003` auf `635325db`, das Paket-Repository trägt ihn als `b8e22a7a` | ✅ |
| **Bruchstellen vollständig benannt** | `breaking-changes.md`, 119 Einträge in 14 Abschnitten; `MigrationGuideTest` hält Zahl und Abschnitts-Zuordnung | ✅ |
| **Beziehbar, einmal aus Projektsicht durchgespielt** | `011-002-0004`: frisches Verzeichnis ausserhalb des Repos, `composer install` gegen die echte URL, Installation, API-Aufruf. Als Job `check: Bezugsweg von aussen` bei **jedem** Lauf wiederholt. | ✅ |
| **Vorlage stimmt** | `011-003-0001`: `custom/` antwortet im Envelope; `TemplateApiTest` und `RouteSecurityApiTest` messen es | ✅ |
| **Doku nachgezogen** | `011-003`: README neu, `runbook.md` mit *So startet ein neues Projekt*, `dev-guide.md` mit der Antwortform, `technical.md` mit dem Vertrag, `migration.md` fortgeschrieben | ✅ |
| **Antwort-Envelope vereinheitlicht** | `011-001`: 17 Antwortstellen plus `/auth`, `/file`, `/system`, plus Fehlerform. `EnvelopeApiTest` wertet alle mit **einer** Leserfunktion aus | ✅ |
| **Upgrade-Pfad festgehalten** | `architecture.md`, *Key decisions*, Eintrag vom 2026-09-17 | ✅ |

### Zu „keine Symfony-2/3-Komponente" — was eine Versionsprüfung falsch liest

Im Lock stehen fünf Pakete mit einer Version `v3.7.x`:

```
symfony/cache-contracts  symfony/deprecation-contracts  symfony/event-dispatcher-contracts
symfony/http-client-contracts  symfony/service-contracts
```

**Das sind keine Symfony-3-Komponenten.** Die `*-contracts`-Pakete versionieren eigenständig;
`service-contracts` 3.x ist die Linie, die Symfony **7.4** benutzt. Wer nur auf die Zahl sieht,
kommt hier zum falschen Schluss — deshalb steht es hier und nicht als Fussnote.

Die eigentlichen Komponenten stehen alle auf `v7.4.x`.

### Das Register — was es trägt, und warum ein Epic fehlen **darf**

Nachgezählt am 2026-09-17: **119 Einträge in 14 Abschnitten.** `migration.md` nennt beide Zahlen
im Kopf, `MigrationGuideTest` hält sie zusammen. Nach Epic:

| Epic | im Register | |
|---|---|---|
| `007` Paketgrenze | eigener Abschnitt | ✅ |
| `009` Kernel | eigener Abschnitt + *Doctrine (Story `009-005`)* | ✅ |
| `010` Entity-Layer | drei Abschnitte (`010-001`, `010-003`, `010-004`) | ✅ |
| `011` Envelope | im Abschnitt *API*, dem grössten mit 24 Einträgen; dazu *Die Vorlage antwortet im Envelope* | ✅ |
| `012` Oberfläche entfernt | verteilt, 7 Fundstellen | ✅ |
| `013` Authentifizierung | fünf Abschnitte (`013-001`…`013-005`) | ✅ |
| `014` Codebase englisch | **kein Eintrag** | ✅ **richtig so** |

**Zu `014`:** Das Epic hat rund 45 Klassen und 150 Methoden umbenannt — und trotzdem gehört kein
Wort davon ins Register. Der Grund steht im Epic selbst: *„Keiner dieser Namen war je in einem
Release. Heute kostet das Umbenennen keinen Bruch für Bestandsprojekte, nach dem Release wäre es
einer."* Ein Bestandsprojekt kommt von Contentfly 1.x und hat `Kernel\Pfade` nie gesehen. Ein
Eintrag „`Pfade` heisst jetzt `Paths`" würde jemanden nach Code suchen lassen, den er nicht hat.

Das ist der Punkt, an dem eine Vollständigkeitsprüfung von selbst das Falsche tut: Sie hakt Epics
ab und meldet `014` als Lücke. Deshalb steht hier die Begründung und nicht nur das Häkchen.

### Was offen bleibt, und warum es benannt und nicht abgehakt ist

- ~~**PHP 8.5 in der Pipeline.**~~ **Erledigt mit `000-000-0096` (2026-09-25):** `test: PHP 8.5`
  läuft als dritte Spalte der Matrix, blockierend. Erhoben hatte es `000-000-0054` — Lock, Audit und
  Suite auf 8.5.11 grün, das Gate rot an `imagedestroy()` im eigenen Code, einem Aufruf, der seit
  PHP 8.0 nichts mehr tut. 8.3 bleibt, solange das Manifest `^8.3` zusagt. *Überholt ist der frühere Grund — ein fehlendes
  8.5-Image: `php:8.5-cli` gibt es seit spätestens 2026-09-17, und alle Erweiterungen bauen darauf.*
- **Die `@api`-Blöcke der API-Doku** tragen noch Antwortbeispiele von **vor** Epic `011`. Benannt
  in `dev-guide.md`, Abschnitt *API-Dokumentation*.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 011-001-0000 — Den Antwort-Envelope der API vereinheitlichen
- [x] 011-002-0000 — Das Framework aus Projektsicht beziehbar machen
- [x] 011-003-0000 — Vorlage und Doku auf den Zielzustand bringen
- [x] 011-004-0000 — Version, Gates und Upgrade-Pfad festschreiben
