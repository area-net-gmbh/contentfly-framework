---
id: 000-000-0030
title: referrer-Tokens — der Client wählt den Schlüssel, und er läuft nie ab
status: review
depends_on: []
---

# referrer-Tokens — der Client wählt den Schlüssel, und er läuft nie ab

## Context
**A-5 aus dem Review vom 2026-09-04** — der einzige der sechs Befunde, den Epic `013` nicht
aufgelöst hat. Das Epic ist geschlossen, der Befund nicht; deshalb steht er hier und nicht
weiter in einer Tabelle mit dem Vermerk „offen".

Der Befund hat **drei Teile**, und nur der erste steht in der Tabelle. Nachgesehen im Baum:

1. **Der Schlüssel kommt vom Aufrufer.** `SystemController::addToken()` nimmt `token` aus dem
   Request-Rumpf und schreibt ihn, wie er ist. Geprüft wird nur, dass das Feld nicht leer ist —
   nicht seine Länge, nicht seine Zufälligkeit. `token=test` wird angenommen.
2. **Er läuft nie ab.** `Tokenhandler::timeoutGilt()` gibt für jede Zeile mit `referrer` `false`
   zurück: „Ein Token mit `referrer` ist ein API-Token und verfällt nicht." Das steht dort seit
   jeher und ist beim Umbau in `013-002` bewusst unverändert übernommen worden.
3. **Beim Vorzeigen bremst nichts.** Die Anmeldebremse aus `013-001-0003` hängt am
   `AuthController`, also am Login. Im Weg über `Tokenhandler::ausDatenbank()` kommt sie nicht
   vor — nachgesehen. Ein schwacher API-Token lässt sich also ungedrosselt durchprobieren.

**Was schon stimmt, und weshalb der Befund kleiner ist, als er klingt:**

- `/system/do` verlangt Anmeldung **und** Adminrecht (`SystemControllerProvider`). Den Token legt
  ein Administrator an, kein Fremder.
- In der Tabelle steht seit `013-001-0004` nur ein SHA-256, nicht der Klartext.
- Das Framework bringt den richtigen Wert bereits mit: `generateToken` liefert
  `bin2hex(random_bytes(64))`. Es **bietet** ihn an, aber es **verlangt** ihn nicht.

**Das ist der eigentliche Punkt.** Der ungesalzene SHA-256 ist für 64 zufällige Bytes genau
richtig und für `test` wertlos — er ist offline in Sekunden zurückzurechnen. Die Sicherheit der
Tabelle hängt damit an einer Entscheidung, die der Aufrufer trifft, und nicht an einer, die das
Framework durchsetzt.

**Drei Wege stehen offen, und die Wahl gehört begründet:**

1. `addToken()` erzeugt den Token selbst und ignoriert ein mitgeschicktes `token`. Am
   gründlichsten — der Aufrufer kann nichts mehr falsch machen. Bricht aber jeden Ablauf, der
   den Wert vorher kennt (etwa weil er in der Konfiguration eines anderen Systems steht).
2. Ein mitgeschickter Token wird auf ein Mindestmaß geprüft und sonst abgewiesen. Hält den
   bestehenden Ablauf offen, verlangt aber eine Grenze, die man begründen muss.
3. Nur dokumentieren. Billig, und verschiebt das Problem auf den nächsten Betreiber.

**Die Ablauffrist ist eine eigene Frage** und nicht zwingend dieselbe Antwort: Ein API-Token, der
im Betrieb eines Fremdsystems steckt, soll gerade nicht nach einer Stunde Untätigkeit verfallen.
Ein Ablauf ist nicht dasselbe wie ein Timeout — eine optionale, beim Anlegen gesetzte Frist wäre
etwas anderes als die Sliding Expiration der Sitzungstokens.

## Acceptance criteria
- [x] Der Weg ist gewählt und im Code begründet, nicht nur umgesetzt.
- [x] Ein Token, der zu schwach ist, wird entweder abgewiesen oder gar nicht erst vom Aufrufer übernommen — und ein Test hält fest, welches von beidem gilt.
- [x] Die Frage der Ablauffrist ist beantwortet: entweder umgesetzt, oder mit Begründung ausdrücklich verworfen und dort vermerkt, wo `timeoutGilt()` sie heute verneint.
- [x] Ob das Vorzeigen eines Tokens gebremst gehört, ist entschieden und begründet — nicht stillschweigend beim Status quo belassen.
- [x] Was sich für Bestandsprojekte ändert, steht in `an_project/docs/breaking-changes.md`.
- [x] A-5 in `an_project/docs/technical.md` ist aufgelöst — durchgestrichen mit dem, was gilt, so wie A-1 bis A-4 und A-6.
- [x] Die volle Suite bleibt grün.

## Verification
Einen Token mit einem schwachen Wert anlegen und die Antwort ansehen — vorher und nachher. Einen
mit `generateToken` erzeugten Token anlegen, vorzeigen und prüfen, dass er weiterhin trägt. Volle
Suite, PHPStan.

## Ergebnis

**Gewählt: Weg 2 mit dem sicheren Weg als Vorgabe.** Die Wahl hat der Auftraggeber getroffen
(2026-09-15), die Begründung steht an `SystemController::addToken()`, samt der beiden verworfenen
Wege.

- **`token` ist optional.** Fehlt es, erzeugt `addToken` den Wert über `generateToken`
  (64 Zufallsbytes als Hex) und gibt ihn einmal zurück.
- **Ein mitgeschickter Token braucht mindestens 32 Zeichen, davon mindestens 10 verschiedene**,
  sonst `400` und keine Zeile. 32 Zeichen sind als Hex aus einer Zufallsquelle 128 Bit; die
  10 verschiedenen Zeichen fangen Wiederholungen wie `aaaa…` oder `abab…`. Ein zufälliger
  Hex-String mit 32 Zeichen hat etwa 14 verschiedene. **Das ist eine Untergrenze, kein
  Zufallsnachweis**, und das steht so im Code: Wer bewusst schwach wählt, kommt womöglich durch.
  Der sichere Weg ist, `token` wegzulassen.
- **Abgewiesen, nicht ersetzt.** Ein Aufrufer, der seinen Wert vorher kennt, muss erfahren, dass
  er nicht übernommen wurde.

**Ablauffrist: begründet verworfen**, vermerkt an `TokenHandler::timeoutApplies()`. Ein API-Token
steckt in der Konfiguration eines Fremdsystems, und eine Frist hielte die Integration an einem Tag
an, auf den niemand schaut. Ein Ablauf ist ein zweiter Mechanismus neben dem Timeout, mit eigener
Spalte. Gegen einen durchgesickerten Token hilft der Widerruf über `deleteToken`, der sofort
wirkt; eine Frist wirkt erst irgendwann.

**Bremse beim Vorzeigen: begründet verworfen**, vermerkt an `TokenHandler::fromDatabase()`. Was
`addToken` jetzt annimmt, ist online nicht durchprobierbar. Eine IP-Bremse vor jeder API-Anfrage
würde hinter einem geteilten Proxy alle Clients derselben Adresse aussperren, sobald einer falsch
konfiguriert ist. Ein bewusst schwach gewählter Token wird aus einem Tabellen-Dump offline
zurückgerechnet, und dagegen hilft keine Bremse.

**Vorher und nachher, gemessen** (`POST /system/do`, `addToken` mit `token=test`, Testserver
jeweils neu gestartet):

| Stand | Antwort |
|---|---|
| master | `HTTP 200`, Zeile angelegt, `"token":"test"` |
| dieser Branch | `HTTP 400`, `The token is too weak: at least 32 characters with at least 10 different ones. …` |

Ein erster Messversuch ohne Neustart zeigte die Ergebnisse vertauscht: Der eingebaute Server las
die gerade getauschte Datei noch nicht. Die dabei in der Test-Datenbank angelegten Probezeilen sind
entfernt, die Messung ist mit Neustart wiederholt.

**Tests** in `SystemControllerApiTest`:

- `testAddTokenRejectsAWeakToken`: vier schwache Werte (kurz; lang mit einem Zeichen; lang mit vier
  Zeichen; zufällig, aber 21 Zeichen), je `400` mit Begründung, keine Zeile.
- `testAddTokenWithoutTokenGeneratesOneThatOpensTheApi`: 128 Hex-Zeichen, als Hash gespeichert,
  und `GET /api/schema` mit dem Wert antwortet `200`.
- `testAddTokenRequiresReferrerTokenAndUser` heisst jetzt `…RequiresReferrerAndUser` und prüft
  zusätzlich den fehlenden `referrer`.
- `testAddTokenRejectsUnknownUser` schickte einen 21-Zeichen-Token und endete damit am Token statt
  am Benutzer. Er schickt jetzt einen ausreichend starken Wert.
- `testReferrerTokenStillOpensApi` (von Hand gewählter Wert, 37 Zeichen) bleibt unverändert grün.

**Gegenprobe:** Mit dem Controller von master sind genau die zwei neuen Tests rot.

**Verifiziert:** volle Suite `Tests: 530, Assertions: 1717, Skipped: 3`, PHPStan
`[OK] No errors`, Deprecation-Gate 0. A-5 ist in `technical.md` durchgestrichen, die Bruchstelle
steht in `breaking-changes.md` (inklusive: von Hand gewählte Bestandstokens neu anlegen), der Kopf
von `migration.md` ist auf 101 Einträge gezogen.

**Nicht angefasst:** `an_project/docs/uebergabe-security.md` beschreibt A-5 als offen. Das ist
die Notiz zum Übergabestand vom 2026-09-14 und bleibt so, wie sie übergeben wurde.
