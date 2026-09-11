---
id: 000-000-0030
title: referrer-Tokens — der Client wählt den Schlüssel, und er läuft nie ab
status: todo
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
- [ ] Der Weg ist gewählt und im Code begründet, nicht nur umgesetzt.
- [ ] Ein Token, der zu schwach ist, wird entweder abgewiesen oder gar nicht erst vom Aufrufer übernommen — und ein Test hält fest, welches von beidem gilt.
- [ ] Die Frage der Ablauffrist ist beantwortet: entweder umgesetzt, oder mit Begründung ausdrücklich verworfen und dort vermerkt, wo `timeoutGilt()` sie heute verneint.
- [ ] Ob das Vorzeigen eines Tokens gebremst gehört, ist entschieden und begründet — nicht stillschweigend beim Status quo belassen.
- [ ] Was sich für Bestandsprojekte ändert, steht in `an_project/docs/breaking-changes.md`.
- [ ] A-5 in `an_project/docs/technical.md` ist aufgelöst — durchgestrichen mit dem, was gilt, so wie A-1 bis A-4 und A-6.
- [ ] Die volle Suite bleibt grün.

## Verification
Einen Token mit einem schwachen Wert anlegen und die Antwort ansehen — vorher und nachher. Einen
mit `generateToken` erzeugten Token anlegen, vorzeigen und prüfen, dass er weiterhin trägt. Volle
Suite, PHPStan.
