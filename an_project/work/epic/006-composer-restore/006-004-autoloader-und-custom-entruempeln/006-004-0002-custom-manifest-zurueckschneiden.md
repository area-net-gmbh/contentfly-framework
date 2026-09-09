---
id: 006-004-0002
title: custom/composer.json auf den Projekt-Slot zurückschneiden
status: review
depends_on: [006-004-0001]
---

# custom/composer.json auf den Projekt-Slot zurückschneiden

## Context
`custom/` ist eine **Vorlage**, kein halbes Projekt (`an_project/docs/technical.md`). Sein
Manifest trägt aber bis heute Kern-Infrastruktur und Reste aus der Kundenanwendung, aus der die
Vorlage geschnitten wurde.

Solange das so bleibt, ist die Entscheidung aus `006-004-0001` nur eine Behauptung: Ein
`composer install` in `custom/` baut den zweiten Baum sofort wieder auf — mit `sentry/sentry`,
das `psr/log ^3` zieht, während der Root auf 2.0.0 steht. Genau die Doppelung, die
`006-002` gerade aufgelöst hat.

## Umfang

### Was hinausfällt — und warum
Die Zuordnung steht seit `006-001-0004` in `tools/dependency-assignment.json`. Sie ist die
Quelle, nicht der Story-Text:

| Paket | Kategorie | Grund (verkürzt) |
|---|---|---|
| `phpmailer/phpmailer` | **root** | `lib/contentfly/Classes/Mailer.php` benutzt es — liegt bereits im Root-Manifest |
| `vlucas/phpdotenv` | **root** | Die ausgelieferte `custom/config.php` benutzt es, und die gehört zum Framework |
| `firebase/php-jwt` | entfällt | Wird nirgends benutzt; Epic `013` nimmt es wieder auf |
| `sentry/sentry` | entfällt | Wird nirgends benutzt; Rest aus der Kundenanwendung |
| `stripe/stripe-php` | entfällt | Einziger Treffer ist ein projektfremder Kommentar (`000-000-0017`) |
| `onelogin/php-saml` | entfällt | Wird nirgends benutzt |
| `robrichards/xmlseclibs` | entfällt | Transitiv von `onelogin/php-saml`, fällt mit ihm |
| `phpunit/phpunit` (dev) | **root require-dev** | Liegt seit `006-002` dort |
| `mockery/mockery` (dev) | entfällt | Von keinem Test benutzt |

**Übrig bleibt: nichts.** `require` und `require-dev` sind danach leer — `custom/vendor` ist
initial leer, wie es das Erfolgskriterium von Epic `006` verlangt.

> **Der Story-Text sagt etwas anderes** — dort bleiben Stripe, SAML und xmlseclibs. Er ist
> älter als `006-001-0004`. Maßgeblich ist die Zuordnungsdatei.

### `autoload-dev` bleibt vorerst
Der Block mappt `Custom\Tests\` auf `../tests/`. Er wird hier **nicht** angefasst — das macht
`006-004-0004`, und zwar erst, wenn der Ersatz im Root steht. Ein Manifest ohne Pakete, aber
mit einem noch gebrauchten Autoload-Eintrag, ist ein gültiger Zwischenstand.

### Der Lock
`custom/composer.lock` ist versioniert (`git ls-files custom/composer.lock`) und beschreibt den
alten Satz. Zu entscheiden und zu begründen ist, was mit ihm geschieht:

- **neu erzeugen** — ein Lock über eine leere Anforderungsliste. Ehrlich, aber es ist eine
  Datei, die nichts sagt.
- **entfernen** — dann muss die `.gitignore`-Ausnahme `!custom/composer.lock` aus
  `006-002-0003` mit weg, und ein Projekt, das später Pakete einträgt, erzeugt seinen eigenen.

Was auch gewählt wird: Die `.gitignore` und der Lock müssen danach zueinander passen. Die
Ausnahme aus `006-002-0003` zeigt sonst auf eine Datei, die es nicht gibt.

### Die Vorlage soll lehren, nicht nur leer sein
Ein leeres `require` ohne ein Wort dazu liest sich wie ein Versehen. In die Datei gehört ein
Kommentar, der sagt, dass hier **projektspezifische** Pakete hineingehören und dass Pakete des
Frameworks im Root stehen — mit dem Verweis auf die fünfstufige Regel in
`tools/dependency-assignment.json`.

## Abgrenzung
Keine Änderung an `lib/contentfly/bootstrap.php` oder `tests/bootstrap.php` — das ist
`006-004-0003`. Kein Umbau des `Custom\Tests\`-Mappings — das ist `006-004-0004`. Keine
Entfernung des `class_exists(\Dotenv\Dotenv::class)`-Schutzes in `custom/config.php`; er wird
mit `006-004-0003` betrachtet, wo der Autoloader-Weg festgezurrt ist.

## Acceptance criteria
- [x] `custom/composer.json` enthält keine `require`- und keine `require-dev`-Pakete mehr.
- [x] Jede Entfernung deckt sich mit `tools/dependency-assignment.json`; Abweichungen sind
      begründet.
- [x] Der Umgang mit `custom/composer.lock` ist entschieden, umgesetzt und begründet; die
      `.gitignore`-Ausnahme aus `006-002-0003` passt danach dazu.
- [x] Die Datei erklärt sich selbst — ein Kommentar sagt, was hier hineingehört und was nicht.
- [x] Ein `composer install` in `custom/` erzeugt **kein** Paket in `custom/vendor`.
- [x] Die Anwendung bootet und die Suite liefert unverändert die 7 bekannten Failures.

## Verification
Die entscheidende Gegenprobe ist die, die das Risiko nachstellt:

```sh
cd custom && composer install --no-interaction && find vendor -type f | wc -l   # erwartet: 0
```

Dazu der volle Ablauf aus `an_project/docs/runbook.md` bis zur Suite: 247 Tests, genau die 7
bekannten Failures aus `000-000-0019` und `000-000-0020`, 0 übersprungen. Mehr wäre ein Befund
über den Schnitt.

## Ergebnis
**Neun Pakete raus, null übrig.** `custom/composer.json` fordert nichts mehr an; ein
`composer install` in `custom/` installiert kein einziges Paket. Die Entscheidung aus
`006-004-0001` ist damit keine Behauptung mehr — der zweite Baum kann nicht mehr entstehen.

| | vorher | jetzt |
|---|---|---|
| `require` | 7 Pakete | **0** |
| `require-dev` | 2 Pakete | **0** |
| `custom/composer.lock` | 20 + 28 dev | **0 + 0** |
| installierte Pakete in `custom/vendor` | — | **0** |

Jede der neun Entfernungen deckt sich mit `tools/dependency-assignment.json`. Es gab keine
Abweichung zu begründen: zwei nach Root (`phpmailer/phpmailer`, `vlucas/phpdotenv`, beide dort
bereits vorhanden), eines lag schon im Root-`require-dev` (`phpunit/phpunit`), sechs entfallen.

### Der Lock: neu erzeugt, nicht gelöscht
Beide Wege waren offen. Gewählt ist **neu erzeugen**, aus einem Grund, der erst beim Ausprobieren
sichtbar wurde: Composer prüft beim Installieren, ob der Lock zum Manifest passt, und meldet
sonst einen Stand, der nicht mehr stimmt. Ein Manifest ohne Lock daneben erzeugt genau diese
Reibung bei jedem, der die Vorlage anfasst — für eine Datei, die als Referenz gelesen wird, ist
das der falsche erste Eindruck.

Die `.gitignore`-Ausnahme `!custom/composer.lock` aus `006-002-0003` bleibt deshalb stehen und
zeigt weiter auf eine existierende Datei. **Sie hätte auch bei der Löschung bleiben müssen** —
das war beim Schreiben des Tasks noch anders gedacht: Sobald ein Projekt sein erstes Paket
einträgt, ist sie es, die den Lock in die Historie lässt. Eine Regel, die man beim Leeren
entfernt und beim ersten Paket wieder braucht, gehört nicht entfernt.

### Die Vorlage erklärt sich jetzt
Ein leeres `require` ohne ein Wort dazu liest sich wie ein Versehen. Der Hinweis steht in
`extra.hinweis`, im selben Format, das das Root-Manifest seit `006-002-0002` benutzt — JSON
kennt keine Kommentare, und eine Vorlage, deren Begründung nur im Changelog steht, wird beim
Lesen nicht gefunden.

Er sagt vier Dinge: dass der Slot **absichtlich** leer ist; was hier hineingehört und was ins
Root; was vorher darin stand und warum es weg ist; und dass die fünfstufige Einordnungsregel
in `tools/dependency-assignment.json` steht — verwiesen, nicht verdoppelt.

### Ein Nebeneffekt, mit dem der Task nicht gerechnet hat
`composer install` in `custom/` erzeugt trotz null Paketen ein **`custom/vendor/autoload.php`**
— Composers eigenes Gerüst, 11 Dateien. Damit ist der zweite Autoloader wieder **da**, obwohl
kein Paket darin liegt: Das `file_exists`-Tor in `lib/contentfly/bootstrap.php:6` greift
wieder.

Das ist kein Schaden, aber es korrigiert eine Aussage aus `006-004-0001`. Dort steht, das Tor sei
geschlossen — das galt nur, solange niemand in `custom/` installiert hatte. Nachgemessen, was
der zweite Autoloader jetzt registriert:

```
'Custom\Tests\' => array($baseDir . '/../tests'),
```

**Ein einziges Präfix, gegen 50 im Root, Überschneidung: keine.** Programmatisch verglichen, nicht
per Augenschein. Und dieses eine Präfix verschwindet mit `006-004-0004` — danach ist auch
`autoload-dev` leer.

Für `006-004-0003` heisst das: Die Prüfung darf sich **nicht** darauf verlassen, dass
`custom/vendor` fehlt. Sie muss den Fall „zweiter Autoloader vorhanden, aber ohne
Überschneidung" als grünen Normalfall behandeln — genau der Fall, der jetzt eingetreten ist.

### Tote Reste des alten Baums entfernt
In `custom/vendor/` lagen noch sechs Dateien aus dem eingefrorenen Stand: vier verschachtelte
`composer.lock` in `mockery/`, `phpunit/`, `phar-io/` und `theseer/` sowie zwei Werkzeugdateien.
Sie überlebten `006-003`, weil die alte `.gitignore` sie nie erfasst hatte — Verzeichnisse mit
einem Paketnamen, aber ohne Paket darin. Entfernt; sie liegen nur lokal und sind in keinem
Klon.

### Verification
Der Ablauf aus dem Runbook, gegen eine eigene Wegwerf-Datenbank auf Port 3327 — der Container
des Hauptarbeitsverzeichnisses blieb unberührt und ist nach dem Lauf unverändert.

| Prüfung | Ergebnis |
|---|---|
| `php bin/console.php list` | Exit 0 |
| `appcms:install` | Schema und Basisdaten angelegt |
| Testserver `/__test/sendmail-path` | HTTP 200 |
| Suite mit `CI=true` | **247 Tests / 595 Assertions, 7 Failures, 0 übersprungen** |
| Postausgang der Versandfalle | 0 Byte |
| `cd custom && composer install` | 0 Pakete, 11 Dateien (nur Composer-Gerüst) |
| `custom/config.php` nach dem Lauf | über `git checkout HEAD --` wiederhergestellt |

Die 7 Failures sind unverändert die bekannten aus `000-000-0019` und `000-000-0020`. Der Schnitt
hat nichts angefasst, was die Suite sieht — was zu erwarten war, weil keines der neun Pakete
benutzt wurde.
