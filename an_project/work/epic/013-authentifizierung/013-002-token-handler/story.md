---
id: 013-002-0000
title: access_token-Authenticator mit verzweigendem TokenHandler
status: in-progress
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

**Zustandslos, mit einem Handler und zwei Extractoren.** Als Konfiguration sähe das so aus:

```yaml
firewalls:
    api:
        stateless: true
        access_token:
            token_handler: ContentflyTokenHandler
            token_extractors: ['header', LegacyHeaderExtractor]
```

**Dieses YAML gibt es hier aber nicht, und das ändert den Weg** (nachgemessen am 2026-09-10):
Der Baum hat kein `config/`-Verzeichnis und kein SecurityBundle — der Kernel ist der eigene aus
Epic `009` mit `before()`-Hooks je Provider. `AccessTokenAuthenticator` ist eine gewöhnliche
Klasse aus `symfony/security-http` und braucht keine Firewall, sondern einen Handler, einen
Extractor und optional einen Benutzerlader: `supports()` fragt den Extractor, `authenticate()`
liefert einen `SelfValidatingPassport` mit dem `UserBadge` des Handlers, `getUser()` löst ihn
auf. Gefahren wird er von einem eigenen Treiber. Das Ergebnis ist dasselbe, die
Firewall-Maschinerie mit `AuthenticatorManager`, `FirewallMap` und `TokenStorage` bleibt aussen
vor.

**Der verzweigende Handler.** Symfony erlaubt genau einen `token_handler` pro Firewall und bringt
keine Verkettung mit — die schreibt man selbst, es sind rund 20 Zeilen:

- drei punktgetrennte Segmente und plausibler Header → **JWT-Zweig**: Signatur und Claims prüfen,
  kein Datenbankzugriff
- sonst → **opaquer Zweig**: `pim_token` nachschlagen, Benutzer aktiv, Timeout prüfen

Beide Zweige münden in dasselbe `UserBadge`. Ein ungültiges Token darf in beiden Fällen
ununterscheidbar scheitern — verschiedene Fehlermeldungen verraten, welche Tokenart erwartet wird.

**Diese Story verifiziert JWT, sie stellt keine aus.** Die Ausstellung, die Schlüsselverwaltung
samt Wechsel und der Widerruf sind `013-003`. Hier reicht ein Signaturgeheimnis aus der
Umgebung; der Test signiert sich sein Token selbst.

**Der Legacy-Extractor ist nicht optional.** Bestandsprojekte schicken `appcms-token`,
`X-XSRF-TOKEN` oder `_token` im Request, nicht `Authorization: Bearer`. Ohne ihn bricht jeder
bestehende Ionic-Client beim Update. Wie lange er mitläuft, entscheidet Epic `007`.

Nachgezählt sind es **vier** Altquellen, und die Reihenfolge in `checkToken()` ist
`appcms-token` · `_token` aus dem Query-String · `_token` aus dem Rumpf · `X-XSRF-TOKEN`.
Symfonys `FormEncodedBodyExtractor` deckt die dritte **nicht** ab: Er verlangt
`application/x-www-form-urlencoded`, Contentfly schickt JSON.

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
- [ ] 013-002-0001 — Der Unterbau: security-http ohne Firewall-YAML
- [ ] 013-002-0002 — Die Extractoren, samt dem für Bestandsclients
- [ ] 013-002-0003 — Der verzweigende TokenHandler
- [ ] 013-002-0004 — Umschalten und checkToken() entfernen
