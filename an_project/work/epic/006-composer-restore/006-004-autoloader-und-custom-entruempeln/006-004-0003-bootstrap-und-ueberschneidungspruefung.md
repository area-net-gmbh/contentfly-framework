---
id: 006-004-0003
title: Den Bootstrap festzurren und eine Überschneidung auffallen lassen
status: todo
depends_on: [006-004-0002]
---

# Den Bootstrap festzurren und eine Überschneidung auffallen lassen

## Context
`006-004-0001` hat entschieden: zwei Bäume, Root zuerst, `custom/` ergänzend. `006-004-0002`
hat `custom/` leergeräumt. Damit ist die Bedingung „keine überlappenden Pakete" heute erfüllt —
aber nur heute, und nur weil niemand etwas einträgt.

**Eine Bedingung, die niemand prüft, ist keine Zusicherung.** Genau daran ist der alte Zustand
gescheitert: Drei Pakete lagen jahrelang in inkompatiblen Majors doppelt im Prozess, und es ist
niemandem aufgefallen — bis `006-001-0002` sie ausgezählt hat.

## Umfang

### A — Der Bootstrap sagt, was er tut
`lib/contentfly/bootstrap.php` lädt heute wortlos zwei Autoloader:

```php
require_once ROOT_DIR.'/vendor/autoload.php';
if(file_exists(ROOT_DIR.'/custom/vendor/autoload.php')){
    require_once ROOT_DIR.'/custom/vendor/autoload.php';
}
```

Die Reihenfolge **ist** die Entscheidung aus `006-004-0001` — sie steht nur nirgends. Ein
Kommentar gehört daneben: Root zuerst, `custom/` ergänzend, der Root gewinnt bei Gleichstand,
und das ist Absicht, kein Zufall. Wer die beiden Zeilen tauscht, kehrt die Zusicherung um.

Dasselbe in `tests/bootstrap.php`, das den Aufbau spiegelt.

**Die Ladereihenfolge selbst ändert sich nicht.** Sie ist bereits die gewollte; hier wird sie
nur festgeschrieben.

### B — Die Prüfung
Eine Überschneidung muss auffallen, statt still zu bleiben. Zu entscheiden ist, **wo** sie
greift — die Wahl ist zu begründen, nicht nur zu treffen:

| Ort | Fängt | Kostet |
|---|---|---|
| Test in `tests/Unit` | jeden Testlauf, lokal und in der CI | nichts zur Laufzeit |
| Prüfung im Bootstrap | jeden Request | Laufzeit in Produktion |
| Skript in `tools/`, aufgerufen aus der Pipeline | jeden CI-Lauf | ein weiterer Job-Schritt |

Ein Unit-Test ist der naheliegende Ort: Er läuft ohne Datenbank, ist Teil der Suite, die Epic
`008` als Abnahmegrundlage festgeschrieben hat, und wandert mit ihr mit. Die Alternativen sind
zu nennen und die Wahl zu begründen.

**Was die Prüfung vergleicht:** die PSR-4- und Classmap-Präfixe beider Bäume, also
`vendor/composer/autoload_psr4.php` gegen `custom/vendor/composer/autoload_psr4.php` — nicht
die Paketnamen aus den `installed.json`. Der Grund steht im Story-Text: `PHPMailer\PHPMailer\`
war in **beiden** Autoloadern registriert, ohne in einer `installed.json` zu stehen. Eine
Prüfung über Paketnamen hätte genau diesen Fall verfehlt.

**Sie muss ohne zweiten Baum grün sein**, nicht übersprungen: Fehlt `custom/vendor`, gibt es
keine Überschneidung, und das ist das erwartete Ergebnis. Ein `markTestSkipped()` an dieser
Stelle wäre derselbe stille Übersprung, gegen den `008-005-0002` den Wächter gebaut hat.

### C — Der Dotenv-Schutz
`custom/config.php` umgibt seinen Dotenv-Aufruf mit `class_exists(\Dotenv\Dotenv::class)`. Die
Begründung in `tools/dependency-assignment.json`: Der Schutz war nur nötig, **solange das Paket
in `custom/` lag und fehlen konnte**. Im Root erübrigt er sich.

Zu prüfen, nicht ungeprüft zu entfernen: `custom/config.php` ist die Vorlage, die ein
Bestandsprojekt bekommt, und `vlucas/phpdotenv` steht in dessen Root-Manifest erst, wenn es auf
die neue Version migriert ist. Fällt die Entscheidung gegen das Entfernen, gehört der Grund als
Kommentar an die Stelle.

## Abgrenzung
Kein Umbau des `Custom\Tests\`-Mappings — das ist `006-004-0004`. Keine Änderung an den
Manifesten; die stehen nach `006-004-0002` fest.

## Acceptance criteria
- [ ] Beide Bootstraps benennen die Ladereihenfolge als Entscheidung, mit Verweis auf
      `an_project/docs/architecture.md`.
- [ ] Eine Überschneidung der Autoloader-Präfixe lässt den Lauf fehlschlagen; der Ort der
      Prüfung ist begründet, die Alternativen sind genannt.
- [ ] Die Prüfung vergleicht **Präfixe**, nicht Paketnamen — der `PHPMailer`-Fall ist der
      Beleg, warum.
- [ ] Ohne `custom/vendor` ist die Prüfung **grün**, nicht übersprungen.
- [ ] Über den Dotenv-`class_exists`-Schutz ist entschieden und begründet.
- [ ] Anwendung bootet, Suite unverändert bei den 7 bekannten Failures.

## Verification
Die Prüfung ist erst belegt, wenn sie **beide** Richtungen gezeigt hat — dieselbe Regel, nach
der `008-004-0004` die Versandfalle abgenommen hat:

1. **Sie schlägt an.** Von Hand ein `custom/vendor/composer/autoload_psr4.php` mit einem
   Präfix anlegen, das der Root ebenfalls führt — der Lauf muss rot werden und das Präfix
   nennen. Danach wieder entfernen.
2. **Sie winkt nicht durch.** Ohne `custom/vendor` grün, und zwar als bestandener Test, nicht
   als Übersprung — `phpunit --testsuite unit` zeigt keinen `S`-Marker.

Dazu der Ablauf aus dem Runbook bis zur vollen Suite: 247 Tests, 7 bekannte Failures, 0
übersprungen.
