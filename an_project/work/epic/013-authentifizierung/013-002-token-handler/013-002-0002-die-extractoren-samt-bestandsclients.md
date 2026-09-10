---
id: 013-002-0002
title: Die Extractoren, samt dem für Bestandsclients
status: todo
depends_on: [013-002-0001]
---

# Die Extractoren, samt dem für Bestandsclients

## Context
`checkToken()` liest den Token heute aus vier Quellen, in dieser Reihenfolge: `appcms-token`,
dann `_token` aus dem Query-String, dann `_token` aus dem Rumpf, dann `X-XSRF-TOKEN`. **Keine
davon kennt RFC 6750.** Ohne einen Extractor, der genau diese Quellen bedient, bricht jeder
bestehende Ionic-Client beim Update — deshalb ist der Legacy-Extractor nicht optional. Wie lange
er mitläuft, entscheidet Epic `007`.

Symfony bringt `HeaderAccessTokenExtractor`, `QueryAccessTokenExtractor` und
`FormEncodedBodyExtractor` mit, `ChainAccessTokenExtractor` verkettet sie. **Der Body-Extractor
passt nicht:** Er verlangt `application/x-www-form-urlencoded`, Contentfly schickt JSON, und der
`before()`-Hook legt den dekodierten Rumpf erst in den `request`-Beutel. Diese Quelle braucht
einen eigenen Extractor.

## Acceptance criteria
- [ ] `Authorization: Bearer <token>` wird gelesen.
- [ ] Alle vier Altquellen werden gelesen, in derselben Reihenfolge wie bisher in `checkToken()`; die Reihenfolge ist im Code als übernommene benannt, nicht stillschweigend nachgebaut.
- [ ] Je Quelle ein Test. Eine Tabelle im Ergebnis hält fest, welche Quelle woher stammt und warum sie mitläuft.
- [ ] Kein Token in irgendeiner Quelle → der Extractor liefert `null`, und der Treiber greift nicht.
- [ ] Der alte Weg ist weiterhin unverändert.

## Verification
Unit-Tests je Quelle mit einem selbst gebauten `Request` — fünf Quellen, fünf Tests, plus einer
für „gar kein Token". Volle Suite.
