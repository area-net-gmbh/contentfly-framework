---
id: 007-000-0000
title: Migrationspfad für Bestandsprojekte
status: todo
depends_on: [009-000-0000, 010-000-0000]
---

# Migrationspfad für Bestandsprojekte

## Goal
Ein Projekt, das heute auf dem alten Contentfly läuft, kommt auf einem dokumentierten und
werkzeuggestützten Weg auf die neue Framework-Version. **Das ist die einzige externe
Randbedingung dieses Repos** (siehe *Scope* in `an_project/project-description.md`) — und
gleichzeitig der Punkt, an dem sich entscheidet, ob das Update überhaupt etwas nützt: Ein neues
Framework, auf das niemand migrieren kann, ist ein Rewrite ohne Abnehmer.

Ziel ist ausdrücklich **nicht** Rückwärtskompatibilität. Breaking Changes sind erlaubt, solange
für jeden ein Migrationsschritt existiert, der beschrieben und — wo möglich — automatisiert ist.

## Erfolgskriterien
- **Migrationsleitfaden** im Repo: Schritt für Schritt vom alten Contentfly auf die neue Version,
  mit einer vollständigen Liste der Breaking Changes und je Eintrag „vorher → nachher".
- **Die Streichung der PIM-Oberfläche ist der größte Brocken** (012) und muss zuerst beantwortet
  werden: Ein Bestandsprojekt, das die Admin-UI heute *benutzt*, verliert sie ersatzlos. Der
  Leitfaden sagt klar, was an ihre Stelle tritt (API-Zugriff, Console) — und was nicht.
  Gleichzeitig fallen die UI-Anteile der `@PIM\Config`-Annotationen in *deren* Entities weg —
  **hart, ohne Duldungsphase** (entschieden am 2026-09-04). Dieses Epic schuldet dafür die
  Rector-Regel, mit der ein Projekt sein `Entity/`-Verzeichnis in einem Lauf bereinigt, und einen
  Leitfaden, der den Schnitt als solchen benennt.
- **Werkzeugunterstützung, wo sie sich lohnt** — allen voran die Entity-Umstellung von
  Doctrine-Annotationen auf PHP-Attribute (`@ORM\*` und der verbliebene `@PIM\*`-Teil).
  `rector/rector` liegt bereits im Baum; ein Regelsatz, den ein Bestandsprojekt auf sein eigenes
  `Entity/`-Verzeichnis loslässt, ersetzt hunderte Handgriffe pro Projekt — und kann die
  gestrichenen UI-Annotationen gleich mit entfernen.
- **Verhalten der `$app['…']`-Bridge festgelegt:** Bleibt der `ArrayAccess`-Zugriff dauerhaft
  Teil der öffentlichen Framework-API, oder ist er eine befristete Migrationshilfe mit
  Deprecation-Frist? Ohne diese Festlegung weiß kein Bestandsprojekt, ob es seine Controller
  anfassen muss.
- **Bezugsweg entschieden:** Framework als Composer-Paket (`areanet/contentfly`) statt kopiertem
  `lib/`-Baum — inklusive der Frage, wie ein Projekt seinen `custom/`-Teil davon trennt.
  Vorbedingung dafür, dass ein Bestandsprojekt künftig überhaupt updaten *kann*.
- **Datenmigration beschrieben:** Der Re-Encrypt-Lauf von AES-CBC auf AEAD (Finding C-4) läuft
  in jedem Bestandsprojekt auf dessen eigenen Daten. Er braucht ein ausführbares Kommando,
  einen Trockenlauf und einen Rollback-Weg — nicht nur eine Anleitung.
- **Am echten Fall verifiziert:** Der Leitfaden wird mindestens einmal an einem realen
  Bestandsprojekt durchgespielt, nicht nur an der `custom/`-Vorlage. Was dabei hakt, fließt zurück.
- **Vorlage nachgezogen:** `custom/` bleibt die Referenz dafür, wie ein Projekt auf der neuen
  Version aussieht — die Example-Artefakte werden mitmigriert und zeigen den Zielzustand.

## Abgrenzung
Die Migration eines konkreten Kundenprojekts findet in dessen eigenem Repo statt, nicht hier.
Dieses Epic liefert Weg, Werkzeuge und Doku.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
