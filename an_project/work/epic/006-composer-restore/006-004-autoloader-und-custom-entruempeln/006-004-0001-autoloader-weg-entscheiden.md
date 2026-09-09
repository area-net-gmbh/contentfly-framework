---
id: 006-004-0001
title: Den Autoloader-Weg entscheiden und begründen
status: todo
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
- [ ] Der Ist-Stand der vier genannten Überschneidungen ist gemessen und festgehalten — je mit
      der Quelle, aus der die Zahl stammt.
- [ ] `an_project/docs/architecture.md` trägt unter *Key decisions* die Entscheidung für zwei
      Bäume, mit der verworfenen Alternative und ihrer Begründung.
- [ ] Festgehalten, dass die Zusicherung an der Prüfung aus `006-004-0003` hängt.
- [ ] Der Widerspruch zwischen Story-Text und `006-001-0004` ist benannt, damit die
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
