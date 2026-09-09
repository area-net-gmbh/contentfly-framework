---
id: 006-004-0003
title: Den Bootstrap festzurren und eine Überschneidung auffallen lassen
status: review
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
- [x] Beide Bootstraps benennen die Ladereihenfolge als Entscheidung, mit Verweis auf
      `an_project/docs/architecture.md`.
- [x] Eine Überschneidung der Autoloader-Präfixe lässt den Lauf fehlschlagen; der Ort der
      Prüfung ist begründet, die Alternativen sind genannt.
- [x] Die Prüfung vergleicht **Präfixe**, nicht Paketnamen — der `PHPMailer`-Fall ist der
      Beleg, warum.
- [x] Ohne `custom/vendor` ist die Prüfung **grün**, nicht übersprungen.
- [x] Über den Dotenv-`class_exists`-Schutz ist entschieden und begründet.
- [x] Anwendung bootet, Suite unverändert bei den 7 bekannten Failures.

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

## Ergebnis
**Die Zusicherung aus `006-004-0001` hat jetzt einen Wächter.**
`tests/Unit/AutoloaderUeberschneidungTest.php` lässt eine Überschneidung der beiden Bäume
fehlschlagen, und beide Bootstraps sagen, warum sie laden, wie sie laden. Die Unit-Suite wächst
von 39 auf 41 Tests, die Gesamtsuite von 247 auf 249 — die 7 bekannten Failures bleiben
unverändert.

### A — Die Ladereihenfolge ist jetzt benannt
`lib/contentfly/bootstrap.php` und `tests/bootstrap.php` tragen denselben Vorspann: Root
zuerst, `custom/` ergänzend, bei einem gemeinsamen Präfix gewinnt der Root, und das ist
Zusicherung statt Nebenwirkung. Beide verweisen auf `an_project/docs/architecture.md` und auf
den Test.

Die Reihenfolge selbst ist **unverändert** — sie war schon die gewollte. Was fehlte, war der
Satz, dass ein Tausch der beiden Blöcke die Zusicherung umkehrt.

Dass der Testbootstrap den Anwendungsbootstrap spiegelt, steht dort ausdrücklich als Absicht:
Ein Testlauf, der anders lädt als die Anwendung, prüft eine andere Anwendung.

### B — Die Prüfung, und wo sie sitzt
Ein Unit-Test. Die drei erwogenen Orte stehen im Klassenkommentar samt der Abwägung:

| Ort | fängt | kostet |
|---|---|---|
| **Unit-Test (gewählt)** | jeden Testlauf, lokal und in der CI | nichts zur Laufzeit |
| Prüfung im Bootstrap | jeden Request | Laufzeit in Produktion |
| Skript in `tools/`, aus der Pipeline | jeden CI-Lauf | einen weiteren Job-Schritt |

Ausschlaggebend: Der Fehler entsteht beim **Installieren**, nicht beim Ausliefern. Eine Prüfung
im Bootstrap ließe jeden Request dafür zahlen. Und als Teil der Suite, die Epic `008` als
Abnahmegrundlage festgeschrieben hat, wandert der Test mit ihr mit.

Verglichen werden **Präfixe und Klassennamen, nicht Paketnamen** — über alle drei Karten, mit
denen Composer eine Klasse findet: `autoload_psr4.php`, `autoload_namespaces.php` (PSR-0) und
`autoload_classmap.php`. Der Grund ist der PHPMailer-Fall: Die Root-Fassung stand in **keiner**
`installed.json`, eine Prüfung über Paketnamen hätte ausgerechnet die langlebigste
Überschneidung verfehlt.

Ein zweiter Test sichert den ersten ab: Sind die Root-Karten leer, vergleicht der
Überschneidungstest nichts und wäre aus dem falschen Grund grün.

### Der erste Lauf war rot — und zwar zu Recht
Die Prüfung fand sofort eine echte Doppelung:

```
Doppelt:
  Classmap: Composer\InstalledVersions
```

Das ist keine Aussage über die Manifeste. **Jeder** generierte Baum trägt eine eigene
`vendor/composer/InstalledVersions.php`; die Doppelung entsteht bei jedem `composer install` und
liesse sich nur abstellen, indem man auf den zweiten Baum verzichtet — also indem man die
Entscheidung aufgibt, die hier abgesichert werden soll.

Ausgefiltert wird deshalb über den **Quellpfad**, nicht über den Namen: Was in das
`composer/`-Verzeichnis desselben Baums zeigt, ist Composers eigenes Gerüst. Ein Paket, das
zufällig `Composer\` heisst, bliebe damit weiterhin sichtbar. Ein Filter auf den Namensraum
hätte diese Lücke gerissen.

### Beide Richtungen belegt, nicht angenommen
Dieselbe Regel, nach der `008-004-0004` die Versandfalle abgenommen hat:

| Gegenprobe | Ergebnis |
|---|---|
| `PHPMailer\PHPMailer\` von Hand in `custom/vendor/composer/autoload_psr4.php` eingetragen | **rot**, Meldung nennt `PSR-4: PHPMailer\PHPMailer\` |
| danach zurückgesetzt | grün |
| `custom/vendor` ganz entfernt | **grün als bestandener Test** — zwei Punkte, kein `S`-Marker |

Die dritte Zeile ist die wichtigste. Ohne zweiten Baum gibt es keine Überschneidung, und das
ist ein Ergebnis, kein fehlender Lauf. Ein `markTestSkipped()` wäre derselbe stille Durchwinker,
gegen den `008-005-0002` den Umgebungswächter gebaut hat.

Der Normalfall ist inzwischen der mit vorhandenem, leerem `custom/vendor`: Seit `006-004-0002`
erzeugt `composer install` dort ein `autoload.php` samt Karten, nur ohne Paket darin. Beide
Zustände sind gültig und beide sind grün.

### C — Der Dotenv-Schutz bleibt
`tools/dependency-assignment.json` hält fest, das `class_exists(\Dotenv\Dotenv::class)` erübrige
sich, sobald `vlucas/phpdotenv` im Root liegt. **Für dieses Repo stimmt das** — der Zweig ist
hier seit `006-002` immer wahr.

Entfernt wird er trotzdem nicht. `custom/config.php` ist die **Vorlage**, die ein
Bestandsprojekt bekommt, und dessen Root-Manifest kennt das Paket erst nach der Migration. Ohne
Schutz stürbe die Anwendung dort beim Start mit einem `Class not found` — einer Meldung, die
den Grund nicht nennt. Wie ein Projekt an das Root-Manifest kommt, entscheidet Epic `007` und
ist offen.

Behalten ist die umkehrbare Wahl, Entfernen nicht. Die Begründung steht als Kommentar an der
Stelle, mit einem *Revidieren, wenn* auf `007` — sonst liest die nächste Person die
Zuordnungsdatei und kürzt den Schutz als erledigt weg.

### Ein Fehlgriff, der die Arbeit fast gefressen hätte
Nach dem Suite-Lauf habe ich `git checkout HEAD -- custom/config.php` ausgeführt — der feste
Schritt aus dem Runbook, der die vom Installer hineingeschriebenen Zugangsdaten entfernt. Er
holt die Datei aber aus `HEAD`, und der eben erst geschriebene Dotenv-Kommentar war **nicht
committet**. Er war damit weg.

Aufgefallen beim Nachzählen (`grep -c 006-004-0003 custom/config.php` → 0), neu geschrieben, und
diesmal **nach** dem Testlauf. Die Lehre steht hier, weil sie wiederkehrt: Der
Wiederherstellungsschritt des Runbooks ist unverträglich mit uncommitteten Änderungen an
derselben Datei — wer beides in einem Task hat, ordnet sie hintereinander.

Gegengeprüft, dass die Vorlage sonst unberührt ist: Der Diff zeigt **15 Zeilen, alle
Kommentar, keine einzige Löschung**, und `tools/check-template-config.sh` endet mit 0.

### Verification
| Prüfung | Ergebnis |
|---|---|
| `php bin/console.php list` | Exit 0 |
| `appcms:install` gegen die Wegwerf-Datenbank | Schema und Basisdaten angelegt |
| Unit-Suite | **OK (41 tests, 58 assertions)** |
| volle Suite mit `CI=true` | **249 Tests / 598 Assertions, 7 Failures, 0 übersprungen** |
| Postausgang der Versandfalle | 0 Byte |
| `tools/check-template-config.sh` | Exit 0 |

Die Suite ist um genau die zwei neuen Tests gewachsen, die Failures sind unverändert die
bekannten aus `000-000-0019` und `000-000-0020`.
