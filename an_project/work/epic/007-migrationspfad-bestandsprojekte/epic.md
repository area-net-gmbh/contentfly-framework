---
id: 007-000-0000
title: Migrationspfad für Bestandsprojekte
status: done
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

## Was seit dem Schreiben dieses Epics schon vorliegt

Nachgesehen am 2026-09-11, vor dem Schnitt in Stories. Drei der Erfolgskriterien sind ganz oder
zum Teil eingelöst, bevor das Epic beginnt — sie werden **eingesammelt, nicht neu gebaut**:

- **Der Re-Encrypt-Lauf ist fertig.** `appcms:security:reencrypt` gibt es seit `010-004`, mit
  `--dry-run`, einstellbarer Stapelgrösse und einem in `an_project/docs/deployment.md`
  beschriebenen Rückweg. Ein abgebrochener Lauf ist kein Schaden: Jeder Stapel ist eine
  Transaktion, beide Formate bleiben lesbar, ein Neustart macht dort weiter, wo er aufhörte. Das
  Kriterium „ausführbares Kommando, Trockenlauf und Rollback-Weg — nicht nur eine Anleitung" ist
  damit erfüllt; `007-004` verweist darauf.
- **Die Grundlage der Rector-Regel steht.** `an_project/docs/pim-annotationen-migration.md`
  listet vollständig, was mit Epic `012` entfallen ist: 7 Annotationen, 14 Felder von
  `@PIM\Config`, die Plugin-Schnittstelle und die `FRONTEND_*`-Konfiguration. Die Datei nennt
  sich selbst Grundlage dieses Epics. Was fehlt, ist die Regel — nicht die Liste.
- **Das Rohmaterial des Leitfadens liegt vor, aber falsch herum sortiert.**
  `an_project/docs/breaking-changes.md` hat 96 Einträge in 13 Abschnitten, geordnet nach Epic und
  Story — also danach, wann *wir* etwas geändert haben. Ein Bestandsprojekt braucht die
  umgekehrte Ordnung: was es in welcher Reihenfolge zu tun hat. Die Datei bleibt als Register;
  der Leitfaden ist der Weg.

Wirklich zu bauen sind damit der Bezugsweg als Composer-Paket, die Rector-Regel selbst und die
Festlegung zur `$app[…]`-Bridge.

### Die Funktionen ohne Auslöser — eine Frage, die dieses Epic beantworten muss
Epic `008` hat beim Aufbau des Testnetzes **sieben Codepfade** gefunden, deren Wirkung sich im
heutigen Stand nicht beobachten lässt, weil es im Framework und in der Vorlage keinen Auslöser
gibt:

| Funktion | warum nicht auslösbar | festgestellt in |
|---|---|---|
| `@PIM\Config(i18n_universal)` | `APP_LANGUAGES` ist leer, keine konkrete `BaseI18n`-Entity | `008-001-0005` |
| `I18nPermission` samt `Group::lang*` | dito — als Unit-Test abgedeckt | `008-003-0004` |
| `@PIM\Config(encoded)` | keine Entity nutzt es, `SECURITY_CIPHER_KEY` ist `null` | `008-002-0005` |
| OneJoin-Kaskade beim Löschen | keine `@ORM\OneToOne`-Beziehung im Baum | `008-002-0005` |
| Schreibprüfung in `MultijoinType` | braucht `acceptFrom`, das keine Eigenschaft trägt | `008-003-0003` |
| `canExport` | Durchsetzungspunkt war der gelöschte `ExportController` | `008-003-0005` |
| `getExtended` | nie ein Durchsetzungspunkt im Framework | `008-003-0005` |

Jede hat einen Test auf die **Vorbedingung**, der anschlägt, sobald ein Projekt sie benutzt.

**Die Frage, die hierher gehört:** Sind das Fähigkeiten, die Bestandsprojekte tatsächlich
nutzen — dann müssen sie erhalten bleiben und der Leitfaden muss sie benennen — oder sind es
Überbleibsel der Oberfläche, die mit dem Umstieg fallen dürfen? Das lässt sich nicht aus diesem
Repo beantworten, sondern nur an einem realen Bestandsprojekt. Es gehört damit zu dem
Erfolgskriterium „am echten Fall verifiziert".

Für `canExport` und `getExtended` läuft die Entscheidung separat über `000-000-0012`; sie hängen
nicht an einem Bestandsprojekt, weil ihr Konsument nachweislich gelöscht ist.

**Entschieden mit `007-005-0005` (2026-09-15)**, am Bestandsprojekt UFP. Beleg: das Inventar-Werkzeug
über den alten Stand (`007-005-0001`) und erneut über den migrierten Stand (`007-005-0005`) — beide Male
„not used“ für alle sieben Muster.

| Funktion | vom Projekt benutzt? | Entscheidung |
|---|---|---|
| `@PIM\Config(i18n_universal)` | nein | **Kandidat zum Entfernen** |
| `I18nPermission` samt `Group::lang*` | nein | **Kandidat zum Entfernen** — gemeinsam mit `i18n_universal`, beide hängen an `BaseI18n` |
| `@PIM\Config(encoded)` | nein | **Kandidat zum Entfernen** — mit dem Vorbehalt, dass `010-004` es neu gebaut hat; das Entfernen verwirft diese Arbeit und gehört deshalb in einen eigenen Entscheid |
| OneJoin-Kaskade beim Löschen | nein | **Kandidat zum Entfernen** |
| Schreibprüfung in `MultijoinType` (`acceptFrom`) | nein | **Kandidat zum Entfernen** |
| `canExport` | nein | **entfernt** mit `000-000-0012` |
| `getExtended` | nein | **entfernt** mit `000-000-0012` |

**Ein Projekt ist ein Datenpunkt, keine Statistik.** „Kandidat“ heisst deshalb: Der Leitfaden nennt diese
Pfade nicht als Migrationsschritt, die Vorbedingungs-Tests aus Epic `008` bleiben und schlagen an, sobald
ein Projekt einen davon benutzt — und entfernt wird erst mit einem eigenen Ticket, das mindestens ein
weiteres Bestandsprojekt prüft oder das Entfernen als bewusst in Kauf genommenen Bruch ins Register
schreibt.

## Abgrenzung
Die Migration eines konkreten Kundenprojekts findet in dessen eigenem Repo statt, nicht hier.
Dieses Epic liefert Weg, Werkzeuge und Doku.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 007-001-0000 — Das Framework als Composer-Paket beziehbar machen
- [x] 007-002-0000 — Die Rector-Regel für das Entity-Verzeichnis
- [x] 007-003-0000 — Die $app[...]-Bridge festlegen
- [x] 007-004-0000 — Der Migrationsleitfaden
- [x] 007-005-0000 — Am echten Bestandsprojekt durchspielen

`007-001` bis `007-003` hängen nicht voneinander ab und können in beliebiger Reihenfolge laufen.
`007-004` beschreibt, was sie entschieden haben, und kommt danach. `007-005` ist die Probe auf
`007-004`.

**`007-005` steht auf `blocked`, nicht auf `todo`** — sie braucht ein reales Bestandsprojekt,
und das liegt ausserhalb dieses Repos. Ein `todo`, das niemand anfangen kann, sieht im Board wie
verfügbare Arbeit aus und verdeckt, dass dieses Epic ohne eine Zulieferung von aussen nicht
fertig wird. Die restlichen vier Stories sind ohne diese Zulieferung abschliessbar.
