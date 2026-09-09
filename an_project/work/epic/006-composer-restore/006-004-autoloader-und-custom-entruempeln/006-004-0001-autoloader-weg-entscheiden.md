---
id: 006-004-0001
title: Den Autoloader-Weg entscheiden und begründen
status: review
depends_on: []
---

# Den Autoloader-Weg entscheiden und begründen

## Context
Zwei Composer-Bäume werden im selben Prozess geladen, ohne dass irgendwo festgelegt wäre, wer
bei einer Überschneidung gewinnt. Der Story-Text nennt zwei Wege; **gewählt ist Variante 1 —
zwei Bäume mit einer Prüfung.** Dieser Task hält die Wahl fest, mit dem Ist-Stand als Beleg.

Das ist keine Formalie: Die Entscheidung prägt, wie ein Bestandsprojekt in Epic `007` seine
eigenen Pakete deklariert. Ein Projekt, dem die Grenze zwischen Framework und Projekt fehlt,
kann sein `custom/` nicht vom mitgelieferten `lib/` unterscheiden.

## Umfang

### Zuerst nachmessen — der Story-Text ist an zwei Stellen überholt
`006-002` und `006-003` sind seit dem Schreiben der Story durchgelaufen. Was noch stimmt und
was nicht, gehört gemessen statt abgeschrieben:

| Story sagt | Stand nachprüfen |
|---|---|
| `psr/log` 1.1.3 gegen 3.0.2 | Der Root trägt heute **2.0.0** — der Lock hat es aufgelöst |
| `symfony/polyfill-ctype` v1.14.0 gegen v1.37.0 | Root steht auf **v1.37.0** |
| `symfony/polyfill-mbstring` v1.14.0 gegen v1.38.2 | Root steht auf **v1.38.2** |
| handkopiertes `PHPMailer\PHPMailer\` im Root | Kommt aus `vendor/phpmailer/phpmailer`, **6.12.0**, von Composer verwaltet |
| in `custom/` bleiben Stripe, SAML, xmlseclibs | `006-001-0004` hat **alle drei als entfallend** eingeordnet |

Belegt wird das aus `vendor/composer/installed.json`, `vendor/composer/autoload_psr4.php` und
`tools/dependency-assignment.json` — nicht aus dem Story-Text.

**Die Überschneidungen sind damit gegenstandslos, das Risiko ist es nicht.**
`custom/composer.json` listet weiterhin sieben Pakete, und `custom/composer.lock` liegt
versioniert daneben. Ein `composer install` in `custom/` holt die Doppelung jederzeit zurück.
Genau dagegen richtet sich die gewählte Variante.

### Die Entscheidung festhalten
In `an_project/docs/architecture.md` unter *Key decisions*, im Format der dort bereits
festgehaltenen Entscheidungen. Hinein gehört:

- **Was gilt:** Root zuerst, `custom/` ergänzend. Der Root gewinnt bei Gleichstand — das ist
  ab jetzt Zusicherung, nicht Nebenwirkung der Ladereihenfolge.
- **Warum nicht ein Autoloader:** Er wäre zur Laufzeit einfacher, verwischt aber die Grenze
  zwischen Framework und Projekt. Epic `007` braucht sie, um Bestandsprojekten zu sagen, was
  sie selbst deklarieren müssen und was sie mitgeliefert bekommen.
- **Der Preis:** Die Bedingung „keine überlappenden Pakete" gilt nur, solange sie geprüft wird.
  Ungeprüft ist sie eine Absichtserklärung. Die Prüfung baut `006-004-0003`.
- **Die Regel für ein neues Paket:** Sie steht schon in `tools/dependency-assignment.json` als
  fünfstufige Entscheidung. Von hier aus darauf verweisen, nicht sie verdoppeln.

## Abgrenzung
Keine Codeänderung. Kein Anfassen von `custom/composer.json` — das ist `006-004-0002`. Keine
Prüfung bauen — das ist `006-004-0003`.

Auch **keine** Korrektur des Story-Textes: Eine Story wird nicht rückwirkend umgeschrieben. Was
sich seit ihrem Schnitt geändert hat, steht im Ergebnis dieses Tasks.

## Acceptance criteria
- [x] Der Ist-Stand der vier genannten Überschneidungen ist gemessen und festgehalten — je mit
      der Quelle, aus der die Zahl stammt.
- [x] `an_project/docs/architecture.md` trägt unter *Key decisions* die Entscheidung für zwei
      Bäume, mit der verworfenen Alternative und ihrer Begründung.
- [x] Festgehalten, dass die Zusicherung an der Prüfung aus `006-004-0003` hängt.
- [x] Der Widerspruch zwischen Story-Text und `006-001-0004` ist benannt, damit die
      Folge-Tasks nicht der überholten Liste folgen.

## Verification
Die Messungen laufen gegen den installierten Baum, nicht gegen die Story:

```sh
php -r '$j=json_decode(file_get_contents("vendor/composer/installed.json"),true);
        foreach($j["packages"] as $p){ echo $p["name"]," ",$p["version"],"\n"; }' \
  | grep -E "psr/log|polyfill-(ctype|mbstring)|phpmailer"
grep -n "PHPMailer" vendor/composer/autoload_psr4.php
```

Und die Gegenprobe, dass das Risiko real ist: `custom/composer.json` und `custom/composer.lock`
listen die Pakete noch, `git ls-files custom/composer.lock` liefert einen Treffer.

## Ergebnis
**Alle vier Überschneidungen aus dem Story-Text bestehen nicht mehr.** Der Lock aus `006-002`
hat sie aufgelöst, und zwar durchweg zugunsten der neueren Fassung — nicht, wie die alte
Ladereihenfolge es getan hätte, zugunsten der älteren.

| Paket | Story-Text: Root / `custom/` | gemessen im Root | Quelle |
|---|---|---|---|
| `psr/log` | 1.1.3 / 3.0.2 | **2.0.0** | `vendor/composer/installed.json` |
| `symfony/polyfill-ctype` | v1.14.0 / v1.37.0 | **v1.37.0** | dieselbe |
| `symfony/polyfill-mbstring` | v1.14.0 / v1.38.2 | **v1.38.2** | dieselbe |
| `phpmailer/phpmailer` | handkopiert / ^6.10 | **v6.12.0** | `installed.json` + `vendor/phpmailer/phpmailer/composer.json` |

Die vierte Zeile ist die aussagekräftigste. `vendor/composer/autoload_psr4.php:40` führt
`PHPMailer\PHPMailer\` heute auf `$vendorDir . '/phpmailer/phpmailer/src'`, und dort liegt ein
`composer.json` — das Verzeichnis ist also von Composer verwaltet, nicht mehr von Hand
hineinkopiert. Genau das, was `006-001-0004` als Nebeneffekt der Root-Zuordnung angekündigt hat.

**Der zweite Autoloader wird zur Laufzeit nicht mehr geladen.** `custom/vendor/` enthält noch
sieben Reste des alten Baums — sechs verschachtelte `composer.lock` und ein
`tmp-*.zip~` —, aber **kein `autoload.php`**. Das `file_exists`-Tor in
`lib/contentfly/bootstrap.php:6` ist damit geschlossen. Es ist geschlossen, weil `006-003` den
Baum nicht mehr baut, nicht weil jemand es entschieden hätte.

### Das Risiko besteht unverändert
Genau darum geht es bei dieser Entscheidung. `custom/composer.json` fordert weiterhin an:

```
require:     firebase/php-jwt, phpmailer/phpmailer, sentry/sentry, stripe/stripe-php,
             vlucas/phpdotenv, onelogin/php-saml, robrichards/xmlseclibs
require-dev: phpunit/phpunit, mockery/mockery
```

Daneben liegt ein versionierter `custom/composer.lock` mit **20 Paketen plus 28 dev-Paketen**.
Ein `composer install` in `custom/` baut den zweiten Baum in Sekunden wieder auf — mitsamt
`sentry/sentry`, das `psr/log ^3` zieht, während der Root auf 2.0.0 steht. Die Doppelung wäre
zurück, und nach der Ladereihenfolge gewänne wieder der Root, also die *ältere* Fassung.

### Die Entscheidung
Festgehalten in `an_project/docs/architecture.md` unter *Key decisions*, datiert 2026-09-09:
**zwei Bäume, Root vor `custom/`**, mit der verworfenen Alternative „ein Autoloader" und drei
Gründen — Epic `007` braucht die Grenze zwischen Framework und Projekt, `custom/` ist die
Vorlage und lehrt nur als vorhandener Slot, und der Preis ist klein geworden, weil nach
`006-001-0004` ohnehin kein Paket dorthin gehört.

Ausdrücklich mit aufgenommen ist der **Preis**: Die Zusicherung hängt vollständig an der
Prüfung aus `006-004-0003`. Fällt die weg, fällt die Entscheidung mit — eine Bedingung, die
niemand prüft, ist keine Zusicherung. Der alte Zustand ist genau daran gescheitert, jahrelang
und ohne dass es auffiel.

Die Einordnungsregel für ein neues Paket wurde **nicht** nach `architecture.md` kopiert. Sie
steht als fünfstufige Entscheidung in `tools/dependency-assignment.json`, zusammen mit der
Begründung je Paket; eine zweite Fassung liefe auseinander.

### Der Widerspruch, dem die Folge-Tasks nicht folgen dürfen
Abschnitt C der Story lässt `stripe/stripe-php`, `onelogin/php-saml` und
`robrichards/xmlseclibs` in `custom/`. `006-001-0004` hat später **alle drei als entfallend**
eingeordnet, jedes mit eigener Begründung — der Stripe-Treffer im Baum ist wörtlich einer der
projektfremden Kommentare aus `000-000-0017`.

Maßgeblich ist `tools/dependency-assignment.json`, nicht der Story-Text. Nach ihr bleibt in
`custom/composer.json` **kein einziges Paket**. Das steht so in `006-004-0002`, damit der
Schnitt dort nicht der überholten Liste folgt.

Der Story-Text selbst bleibt unverändert — eine Story wird nicht rückwirkend umgeschrieben.
