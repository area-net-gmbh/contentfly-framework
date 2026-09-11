---
id: 013-004-0005
title: Der Bruch für Epic 007
status: done
depends_on: [013-004-0001, 013-004-0002, 013-004-0003, 013-004-0004]
---

# Der Bruch für Epic 007

## Context
Die alte `LoginManager`-Schnittstelle faellt, und jedes Bestandsprojekt, das einen hat, muss ihn
ueberfuehren. Was dabei zu tun ist, weiss nach dieser Story genau eine Person — solange es
niemand aufschreibt.

**Die Frage, die Epic `007` beantworten muss, gehoert hier gestellt und beantwortet, soweit es
geht:** Wie kommt ein vorhandener Manager auf den neuen Vertrag, und was passiert mit den
Benutzern, die `createManagedUser()` mit MD5-Praefix angelegt hat? Die Zeilen stehen in
`pim_user` und tragen einen Alias, den niemand mehr erzeugt.

## Acceptance criteria
- [x] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [x] Der Weg vom alten `LoginManager` zum neuen Vertrag ist beschrieben — Schritt fuer Schritt, nicht als Absichtserklaerung.
- [x] Ueber die Altbestaende mit MD5-Praefix ist entschieden und begruendet: uebernehmen, umschreiben oder stehenlassen.
- [x] `an_project/docs/technical.md` ist nachgezogen; Befund A-6 ist durchgestrichen.
- [x] Die Gates sind gruen: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen den
Code gelesen, nicht aus dem Gedaechtnis.

## Ergebnis

**Der Weg vom alten `LoginManager` zum neuen Vertrag steht als sechs Schritte da**, nicht als
Absichtserklärung — und die Altbestände sind entschieden, nicht offengelassen.

### Sechs Schritte, jeder mit dem, was sich ändert

Klasse umhängen · `auth()` zu `pruefen()` · Kennung nicht mehr verfremden · Gruppen aus dem Code
in die Konfiguration · Provider registrieren · Clients auf den Namen umstellen. Daneben steht
eine Tabelle, die alt und neu gegenüberstellt, damit jemand mit einem vorhandenen Manager sieht,
welche Zeile welcher entspricht.

`custom/Classes/Anmeldung/BeispielProvider.php` führt alle sechs an einem lauffähigen Beispiel
vor — das ist der Grund, warum `013-004-0004` vor diesem Task lag.

### Die Altbestände bleiben stehen, und das ist die Entscheidung

Konten, die `createManagedUser()` angelegt hat, tragen einen Alias `<md5>-<kennung>`,
`loginManager` mit dem **Klassennamen** und `externalId` leer. Das Framework schreibt sie nicht
um:

1. **Die Zuordnung ist nicht rückrechenbar.** Aus `3f2a…-mueller` lässt sich die alte Klasse nur
   erraten, indem man alle Klassennamen durchprobiert, die ein Projekt je hatte — und die kennt
   das Framework nicht.
2. **Ein automatischer Umschrieb änderte den Alias**, und der ist die Kennung, unter der ein JWT
   den Benutzer führt (`sub`).
3. **Ein Migrationsschritt, den niemand prüfen kann, ist schlimmer als einer, den jemand bewusst
   geht.**

Das `UPDATE` steht in `breaking-changes.md`, je Provider einmal, mit bekanntem Klassennamen und
bekanntem neuem Namen. Die `SUBSTRING(alias, 34)` darin ist nachgerechnet: 32 Zeichen MD5 plus
Bindestrich, MySQL zählt ab 1.

**Wer den Schritt auslässt, bekommt beim nächsten Login ein zweites Konto** — ärgerlich, nicht
gefährlich. Das steht dabei, weil es den Unterschied macht zwischen „muss vor dem Update
passieren" und „kann danach".

### Der Altbestand hat noch das alte Passwort, und das ist der wichtigere Satz

Die Konten von `createManagedUser()` tragen weiterhin `hash('sha256', <alias>.<salt>)` — also
ein Passwort, das der Benutzername ist. Neu angelegte sind gesperrt; **die alten sind es
nicht**, sie hängen weiter allein am Riegel. Ein einzeiliges `UPDATE` schliesst das, und es steht
als eigener Abschnitt da, nicht als Nebensatz:

```sql
UPDATE pim_user SET pass = '*' WHERE loginManager IS NOT NULL AND loginManager <> '';
```

Verlustfrei, denn diese Konten sollen sich ohnehin nur über ihr Fremdsystem anmelden.

### Gegen den Code gelesen

Die Verification verlangte es ausdrücklich. Nachgesehen: alle fünf genannten Klassen existieren,
die Signatur `pruefen(Request): ?Fremdkennung` stimmt wörtlich, `SECURITY_PROVIDER_GRUPPEN` und
`uniq_user_fremdkennung` stehen im Baum, `$app['anmeldeanbieter']` steht in der Vorlage, und die
`SUBSTRING`-Position ist mit `substr(md5('X').'-mueller', 33)` nachgerechnet.

`technical.md` ist nachgezogen: **A-6 ist durchgestrichen**, und ein neuer Abschnitt beschreibt
die fünf Stücke des Nachfolgers.

### Nachweis

| Probe | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Volle Suite | `OK (442 tests, 1098 assertions)` | `OK (442 tests, 1098 assertions)` |
| Deprecations | 0 | 0 |
| Postausgang | 0 Byte | 0 Byte |

Dazu PHPStan `[OK] No errors`, `composer audit --locked` ohne Advisories und
`tools/check-template-config.sh` mit Exit 0.

### Der 8.4-Lauf hat drei Anläufe gebraucht

Nicht wegen des Codes. Der Container startet gegen dieselbe Wegwerf-Datenbank, und der Weg
dorthin war zu:

1. `docker run` brach mit `unexpected EOF` ab.
2. Danach war `php:8.4-cli` nicht mehr lokal vorhanden — jeder Aufruf versuchte einen Pull.
3. `docker pull` hing ohne jede Ausgabe, auch mit `timeout`. Der Daemon antwortete auf
   `docker info`, konnte aber keine Images mehr holen.
4. Ein Neustart von Docker Desktop half beim ersten Mal; beim zweiten kam es nach drei Minuten
   nicht wieder hoch, beim dritten sofort. Danach lief der Pull durch und die Suite in einem Zug.

**Festgehalten, weil der Zwischenstand dieses Tasks kurzzeitig „8.4 nicht gemessen" lautete** —
und ein Kriterium ist erst abgehakt, wenn es gemessen wurde, nicht wenn man weiss, dass es
grün wäre.
