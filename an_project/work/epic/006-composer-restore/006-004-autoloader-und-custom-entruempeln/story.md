---
id: 006-004-0000
title: Autoloader klären und custom/ entrümpeln
status: done
depends_on: [006-002-0000]
---

# Autoloader klären und custom/ entrümpeln

## Goal
Zwei Composer-Bäume werden im selben Prozess geladen, ohne dass irgendwo festgelegt wäre, wer bei
einer Überschneidung gewinnt. Diese Story legt es fest — entweder als bewussten Zwei-Baum-Bootstrap
ohne überlappende Pakete oder durch Zusammenführung auf einen Autoloader — und räumt `custom/`
auf den Rest zurück, der wirklich projektspezifisch ist.

## Umfang

### A — Der heutige Zustand
`lib/contentfly/bootstrap.php` lädt beide Autoloader nacheinander:

```php
require_once ROOT_DIR.'/vendor/autoload.php';
if(file_exists(ROOT_DIR.'/custom/vendor/autoload.php')){
    require_once ROOT_DIR.'/custom/vendor/autoload.php';
}
```

PSR-4-Präfixe, die **beide** registrieren, werden vom **zuerst geladenen** Baum bedient — der Root
gewinnt immer, unabhängig davon, welche Version aktueller ist. Das ist nirgends dokumentiert und
niemandem aufgefallen.

### B — Die Überschneidungen
Drei Pakete liegen in beiden Bäumen in inkompatiblen Majors:

| Paket | Root | `custom/` |
|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 |

Bei `psr/log` ist das nicht kosmetisch: Zwischen 1.x und 3.x haben sich die Signaturen der
`LoggerInterface`-Methoden geändert (Rückgabetypen, `string|\Stringable`). Was gegen 3.0.2
geschrieben ist, läuft gegen 1.1.3 nicht zwingend.

**Und eine vierte, die keine `installed.json` zeigt:** `PHPMailer\PHPMailer\` ist in beiden
Autoloadern registriert — Root `vendor/composer/autoload_psr4.php:43`, `custom/`
`vendor/composer/autoload_psr4.php:20`. Die Root-Fassung stammt aus einem von Hand
hineinkopierten Verzeichnis, das in keiner `installed.json` steht; die `custom/`-Fassung ist das
gepflegte `^6.10`. Heute gewinnt die handkopierte. Nach `006-001` gehört `phpmailer` ins Root —
die Dublette wird also zugunsten des Roots aufgelöst, aber mit einer **von Composer verwalteten**
Version.

### C — `custom/` zurückschneiden
`custom/composer.json` trägt heute Kern-Infrastruktur mit. Nach der Zuordnung aus `006-001`
bleibt dort nur, was wirklich projektspezifisch ist — nach heutigem Stand `stripe/stripe-php` und
`onelogin/php-saml` samt `robrichards/xmlseclibs`. `custom/vendor` ist damit **initial leer**: ein
Slot, den ein Projekt füllt, nicht ein zweiter Framework-Baum.

`autoload-dev` mit `Custom\Tests\` bleibt, solange die Testsuite darüber lädt — oder wandert mit
in das Root-Manifest, wenn die Zusammenführung gewählt wird.

### D — Die Entscheidung
Zwei Wege, einer ist zu wählen und zu begründen:

1. **Zwei Bäume, klare Regel.** Root zuerst, `custom/` ergänzend, und die Bedingung „keine
   überlappenden Pakete" wird geprüft — idealerweise durch einen Check, der bei einer
   Überschneidung fehlschlägt, statt sie stillschweigend hinzunehmen.
2. **Ein Autoloader.** `custom/` bekommt kein eigenes `vendor/` mehr; projektspezifische Pakete
   stehen im Root-Manifest. Einfacher zur Laufzeit, aber es verwischt die Grenze zwischen
   Framework und Projekt — genau die Grenze, die Epic `007` für Bestandsprojekte braucht.

Die Wahl ist in `an_project/docs/architecture.md` unter *Key decisions* festzuhalten, weil sie
die Vorlage für jedes Folgeprojekt prägt.

## Fertig, wenn
- Der Bootstrap-Weg für die Autoloader ist entschieden, umgesetzt und in
  `an_project/docs/architecture.md` begründet.
- Keine der vier Überschneidungen besteht mehr — die drei aus den `installed.json` und die über
  `PHPMailer\PHPMailer\`.
- Bei Variante 1: Eine Überschneidung fällt künftig auf, statt still zu bleiben.
- `custom/composer.json` enthält nur noch Projektspezifisches; `custom/vendor` ist initial leer.
- Die Anwendung bootet und die Testsuite ist grün — gemessen an dem Testnetz, das Epic `008`
  vorher gespannt hat.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 006-004-0001 — Den Autoloader-Weg entscheiden und begründen
- [ ] 006-004-0002 — custom/composer.json auf den Projekt-Slot zurückschneiden
- [ ] 006-004-0003 — Den Bootstrap festzurren und eine Überschneidung auffallen lassen
- [ ] 006-004-0004 — Custom\Tests\ durch ein PSR-4-Mapping im Root ersetzen
