---
id: 013-002-0002
title: Die Extractoren, samt dem für Bestandsclients
status: done
depends_on: [013-002-0001]
---

# Die Extractoren, samt dem für Bestandsclients

## Context
`checkToken()` liest den Token heute aus vier Quellen. **Die Reihenfolge stand hier zunächst
falsch** — nachgelesen an der Zeile
`$request->headers->get(TOKEN_HEADER_KEY_ALT, $tokenParameter)`: Der `X-XSRF-TOKEN`-Header ist
der Wert, `_token` nur dessen Vorgabe. Er schlägt `_token` also, statt danach zu kommen. Richtig
ist:

1. `appcms-token` (Header)
2. `X-XSRF-TOKEN` (Header)
3. `_token` (Query-String)
4. `_token` (Rumpf)

**Keine davon kennt RFC 6750.** Ohne einen Extractor, der genau diese Quellen bedient, bricht jeder
bestehende Ionic-Client beim Update — deshalb ist der Legacy-Extractor nicht optional. Wie lange
er mitläuft, entscheidet Epic `007`.

Symfony bringt `HeaderAccessTokenExtractor`, `QueryAccessTokenExtractor` und
`FormEncodedBodyExtractor` mit, `ChainAccessTokenExtractor` verkettet sie. **Der Body-Extractor
passt nicht:** Er verlangt `application/x-www-form-urlencoded`, Contentfly schickt JSON, und der
`before()`-Hook legt den dekodierten Rumpf erst in den `request`-Beutel. Diese Quelle braucht
einen eigenen Extractor.

## Acceptance criteria
- [x] `Authorization: Bearer <token>` wird gelesen.
- [x] Alle vier Altquellen werden gelesen, in derselben Reihenfolge wie bisher in `checkToken()`; die Reihenfolge ist im Code als übernommene benannt, nicht stillschweigend nachgebaut.
- [x] Je Quelle ein Test. Eine Tabelle im Ergebnis hält fest, welche Quelle woher stammt und warum sie mitläuft.
- [x] Kein Token in irgendeiner Quelle → der Extractor liefert `null`, und der Treiber greift nicht.
- [x] Der alte Weg ist weiterhin unverändert.

## Verification
Unit-Tests je Quelle mit einem selbst gebauten `Request` — fünf Quellen, fünf Tests, plus einer
für „gar kein Token". Volle Suite.

## Ergebnis

**Fünf Quellen, vier davon geerbt.**

| # | Quelle | woher | warum sie mitläuft |
|---|---|---|---|
| 1 | `Authorization: Bearer <token>` | RFC 6750 | Der Weg, auf den alles zuläuft |
| 2 | `appcms-token` (Header) | `checkToken()` | Was Bestandsclients schicken |
| 3 | `X-XSRF-TOKEN` (Header) | `checkToken()` | Erbe der Session-Zeit |
| 4 | `_token` (Query-String) | `checkToken()` | Ein Link, den jemand anklickt |
| 5 | `_token` (Rumpf) | `checkToken()` | Ein POST, der ihn mitbringt |

### Die Reihenfolge stand im Task falsch

Der Task nannte `appcms-token · _token Query · _token Rumpf · X-XSRF-TOKEN`. Nachgelesen an der
Zeile

```php
$tokenString = $request->headers->get(self::TOKEN_HEADER_KEY_ALT, $tokenParameter);
```

ist `X-XSRF-TOKEN` der **Wert** und `_token` nur dessen **Vorgabe** — der Header schlägt den
Parameter also, statt nach ihm zu kommen. Die Task-Beschreibung ist richtiggestellt, und ein
Test hält die Reihenfolge fest, indem er einen Request mit allen fünf Quellen schickt und dann
Quelle für Quelle wegnimmt. Wer sie beim Nachbauen umdreht, ändert für jeden Client, der beides
mitschickt, still das Ergebnis.

### Zwei eigene Extractoren, und zwar begründet

**`RohkopfExtractor` statt Symfonys `HeaderAccessTokenExtractor`** für die Altquellen. Der
Symfony-Extractor prüft den Wert gegen `[a-zA-Z0-9\-_+~\/.]+=*`. Für ein Bearer-Token nach
RFC 6750 ist das richtig; für die Altquellen wäre es eine **stille Verengung**: `checkToken()`
nimmt den Header, wie er kommt, und ein Projekt darf sich seinen API-Token über `addToken` frei
wählen. Ein Token mit einem Zeichen ausserhalb dieser Menge würde ab sofort nicht mehr erkannt —
und niemand bekäme zu sehen, warum. Ein Test schickt `projekt:token mit leerzeichen!` durch
beide Altheader.

**`RumpfExtractor` statt `FormEncodedBodyExtractor`.** Der verlangt
`application/x-www-form-urlencoded` und POST. Contentfly schickt `application/json`, und der
Rumpf steht erst im `request`-Beutel, nachdem der `before()`-Hook ihn dorthin dekodiert hat —
genau von dort liest `checkToken()` ihn heute auch.

Für die beiden übrigen Quellen tut es Symfony: `HeaderAccessTokenExtractor` für Bearer,
`QueryAccessTokenExtractor` für `_token` im Query-String. Der liest ohne Muster und passt
deshalb.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (340 tests, 884 assertions)`, 0 übersprungen (vorher 331) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| `checkToken()`-Aufrufer | weiterhin unverändert, alle fünf |

Neun Tests: einer je Quelle, einer für „gar kein Token", einer für die Reihenfolge, einer gegen
die stille Verengung, einer dafür, dass ein leerer Header als abwesend zählt — wie `empty()` in
`checkToken()`.
