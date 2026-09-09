---
id: 006-004-0002
title: custom/composer.json auf den Projekt-Slot zurückschneiden
status: todo
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
- [ ] `custom/composer.json` enthält keine `require`- und keine `require-dev`-Pakete mehr.
- [ ] Jede Entfernung deckt sich mit `tools/dependency-assignment.json`; Abweichungen sind
      begründet.
- [ ] Der Umgang mit `custom/composer.lock` ist entschieden, umgesetzt und begründet; die
      `.gitignore`-Ausnahme aus `006-002-0003` passt danach dazu.
- [ ] Die Datei erklärt sich selbst — ein Kommentar sagt, was hier hineingehört und was nicht.
- [ ] Ein `composer install` in `custom/` erzeugt **kein** Paket in `custom/vendor`.
- [ ] Die Anwendung bootet und die Suite liefert unverändert die 7 bekannten Failures.

## Verification
Die entscheidende Gegenprobe ist die, die das Risiko nachstellt:

```sh
cd custom && composer install --no-interaction && find vendor -type f | wc -l   # erwartet: 0
```

Dazu der volle Ablauf aus `an_project/docs/runbook.md` bis zur Suite: 247 Tests, genau die 7
bekannten Failures aus `000-000-0019` und `000-000-0020`, 0 übersprungen. Mehr wäre ein Befund
über den Schnitt.
