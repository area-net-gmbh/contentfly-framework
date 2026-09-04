---
id: 013-002-0000
title: access_token-Authenticator mit verzweigendem TokenHandler
status: todo
depends_on: [013-001-0000, 009-000-0000]
---

# access_token-Authenticator mit verzweigendem TokenHandler

## Goal
Beide Authentifizierungsarten laufen über **einen** Mechanismus: Symfonys
`access_token`-Authenticator, mit einem TokenHandler, der nach der Form des Tokens verzweigt.
Damit ist „stateful oder stateless" keine Endpunkt-Entscheidung mehr, sondern eine Eigenschaft
des ausgestellten Tokens — und alle nachgelagerten Berechtigungsprüfungen bleiben unverändert.

Braucht die Security-Komponente, also den Symfony-Kernel aus Epic `009`.

## Umfang

**Firewall**, zustandslos, mit einem Handler und zwei Extractoren:

```yaml
firewalls:
    api:
        stateless: true
        access_token:
            token_handler: ContentflyTokenHandler
            token_extractors: ['header', LegacyHeaderExtractor]
```

**Der verzweigende Handler.** Symfony erlaubt genau einen `token_handler` pro Firewall und bringt
keine Verkettung mit — die schreibt man selbst, es sind rund 20 Zeilen:

- drei punktgetrennte Segmente und plausibler Header → **JWT-Zweig**: Signatur und Claims prüfen,
  kein Datenbankzugriff
- sonst → **opaquer Zweig**: `pim_token` nachschlagen, Benutzer aktiv, Timeout prüfen

Beide Zweige münden in dasselbe `UserBadge`. Ein ungültiges Token darf in beiden Fällen
ununterscheidbar scheitern — verschiedene Fehlermeldungen verraten, welche Tokenart erwartet wird.

**Der Legacy-Extractor ist nicht optional.** Bestandsprojekte schicken `appcms-token`,
`X-XSRF-TOKEN` oder `_token` im Request, nicht `Authorization: Bearer`. Ohne ihn bricht jeder
bestehende Ionic-Client beim Update. Wie lange er mitläuft, entscheidet Epic `007`.

**Berechtigungen bleiben, wie sie sind.** Das Contentfly-eigene Modell (`Permission`,
`I18nPermission`, `Group`, `isAdmin`) wird nicht durch Symfony-Rollen ersetzt; abgebildet wird nur,
was der Firewall zum Zugriffsschutz braucht. Das Berechtigungsmodell umzubauen ist ein eigenes
Thema und gehört nicht in diese Story.

## Fertig, wenn
- Ein opaques Token aus `pim_token` und ein JWT authentifizieren denselben Benutzer über
  denselben Firewall.
- Ein Bestandsclient mit `appcms-token`-Header funktioniert unverändert.
- Der Sliding-Expiration-Write passiert nur noch im opaquen Zweig — der JWT-Zweig fasst die
  Datenbank nicht an, nachweislich (ein Request, kein Query auf `pim_token`).
- Ungültige, abgelaufene und manipulierte Tokens werden in beiden Zweigen abgewiesen, mit
  gleicher Antwort.
- Das Testnetz aus Epic `008` bleibt grün.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
