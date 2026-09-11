---
id: 013-005-0004
title: Die Buchführung
status: in-progress
depends_on: [013-005-0001, 013-005-0002, 013-005-0003]
---

# Die Buchführung

## Context
Was ein Projekt konfigurieren muss, steht nach den drei Tasks im Code — und damit nirgends, wo
jemand es sucht. Die Story verlangt es ausdruecklich im `dev-guide.md`.

**Dazu eine ueberholte Aussage im Story-Text:** Der Umfang sagt, `onelogin/php-saml` liege
„bereits im Baum (heute in `custom/composer.json`)". Epic `006` hat es gestrichen —
`006-004-0002` fuehrt es unter „entfaellt: wird nirgends benutzt" —, und `custom/composer.json`
hat heute ein leeres `require`. Der Satz gehoert richtiggestellt, nicht stehengelassen: Er
verspricht einen Baustein, den es nicht gibt.

## Acceptance criteria
- [x] `an_project/docs/dev-guide.md` sagt, was ein Projekt fuer LDAP und fuer OIDC konfigurieren muss — Variablen, Werte, und wo der Provider eingetragen wird.
- [x] Die Einschraenkungen stehen dort, wo sie jemand liest: LDAP gegen Doppelgaenger geprueft, OIDC gegen einen HttpClient-Doppelgaenger.
- [x] Der SAML-Satz im Story-Text ist richtiggestellt.
- [x] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [x] `an_project/docs/technical.md` ist nachgezogen.
- [x] Die Gates sind gruen: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen den
Code gelesen: Jede genannte Variable und jeder genannte Konfigurationsschluessel muss sich im
Baum wiederfinden.

## Ergebnis

**Was ein Projekt konfigurieren muss, steht jetzt im `dev-guide.md`** — und nicht mehr nur im
Code.

### Der Abschnitt, den es vorher nicht gab

`dev-guide.md` war bis hierhin unausgefüllte Vorlage: vier Überschriften mit Platzhaltern. Der
neue Abschnitt *Anmeldung über ein Fremdsystem* ist der erste echte Inhalt darin und deckt ab:

| Frage | Antwort im Leitfaden |
|---|---|
| Wie trage ich einen Provider ein? | vier Schritte, mit dem Codeausschnitt aus `custom/app.php` |
| Was konfiguriere ich für LDAP? | acht Felder als Tabelle, plus der Hinweis auf `ext-ldap` |
| Was konfiguriere ich für OIDC? | drei Felder, plus warum `sub` und nicht `email` |
| Was, wenn jemand aus dem Verzeichnis fällt? | `appcms:provider:abgleich`, mit `--dry-run` zuerst |
| Wie schreibe ich einen eigenen? | eine Pflichtmethode, und was das Framework beisteuert |

### Die Einschränkungen stehen dort, wo jemand sie liest

Ein eigener Unterabschnitt, nicht eine Fussnote: **LDAP ist gegen Doppelgänger geprüft, OIDC
gegen `MockHttpClient`.** Mit dem Satz, auf den es ankommt — wer einen der beiden Wege produktiv
nimmt, testet ihn einmal gegen sein echtes System, denn die Suite nimmt ihm das nicht ab.

Eine grüne Suite, die mehr zu versprechen scheint, als sie hält, ist schlimmer als eine mit einer
benannten Lücke.

### Der SAML-Satz ist richtiggestellt

Der Story-Umfang sagte, `onelogin/php-saml` liege „bereits im Baum (heute in
`custom/composer.json`)". **Epic `006` hat es gestrichen** — `006-004-0002` führt es unter
„entfällt: wird nirgends benutzt" —, und `custom/composer.json` hat heute ein leeres `require`.
Der Satz versprach einen Baustein, den es nicht gibt; jetzt sagt er, dass ein Projekt das Paket
neu aufnehmen müsste, und dass Epic `006` die Frage „Framework oder Projekt" bereits mit
„Projekt" beantwortet hat, indem es aus dem Root-Manifest herausblieb.

### Der Gate-Lauf hat einen Fehler gefunden, den kein Test finden konnte

**`composer install` scheiterte im CI-Image** — und der 8.4-Job starb still mit Exit 2, weil die
Meldung in einer Umleitung unterging (`> /tmp/i.log`). Sichtbar wurde sie erst, als ich die
Schritte einzeln und ohne Umleitung fuhr:

> Alternatively, you can run Composer with `--ignore-platform-req=ext-ldap`

`symfony/ldap` verlangt die **Systemerweiterung `ext-ldap`**, und `composer install` prüft die
Plattformanforderungen aller Pakete. Mit dem Paket in `require` hätte **jede
Contentfly-Installation** die Erweiterung gebraucht — auch die, die nie ein Verzeichnis anfasst.
Das war in `013-005-0001` nicht bedacht.

**Korrigiert, nicht umgangen:**

| | |
|---|---|
| `symfony/ldap` | von `require` nach `require-dev` plus `suggest` |
| `tools/ci/install-php-extensions.sh` | baut `ldap` mit, weil `LdapProviderTest` es braucht |
| `LdapProvider::ausKonfiguration()` | wirft mit klarem Hinweis, wenn das Paket fehlt |
| `dev-guide.md`, `breaking-changes.md` | sagen, was ein Projekt selbst aufzunehmen hat |

Ein Produktivlauf mit `composer install --no-dev` kommt damit ohne `ext-ldap` aus.
`symfony/http-client` bleibt in `require`: Es ist reines PHP und zieht keine Systemerweiterung
nach — der Unterschied ist der Grund für die verschiedene Behandlung.

**Dass der Job still starb, ist der zweite Teil des Funds.** `job84.sh` leitet die Ausgabe jedes
Schritts in eine Datei um und läuft unter `set -e`; ein Fehlschlag beendet ihn ohne ein Wort.
Das gehört in einen eigenen Task — es ist das Werkzeug, nicht das Framework.

### Ein verwaister Testserver, und was er kostete

Zwischendurch meldete die Suite **210 Fehler**. Die Ursache war ein `php -S`-Prozess auf Port
8170 aus einem abgebrochenen Lauf: Der neue Server konnte nicht binden, und die Tests fragten
den alten — mit altem Code und einer Datenbank, die es nicht mehr gab. Nach `pkill` lief alles
durch.

Festgehalten, weil die Zahl beim ersten Hinsehen nach einem Einsturz aussieht und keiner war.

### Gegen den Code gelesen

Alle elf `SECURITY_LDAP_*`- und `SECURITY_OIDC_*`-Felder kommen in `Classes/Config.php` vor, der
Befehlsname `appcms:provider:abgleich` steht im Command, und beide Signaturen —
`pruefen(Request): ?Fremdkennung` und `kenntKennung(string): ?bool` — stimmen wörtlich mit den
Interfaces überein.

### Nachweis

| Probe | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Volle Suite | `OK (471 tests, 1150 assertions)` | `OK (471 tests, 1150 assertions)` |
| Deprecations | 0 | 0 |
| Postausgang | 0 Byte | 0 Byte |

Dazu PHPStan `[OK] No errors`, `composer audit --locked` ohne Advisories und
`tools/check-template-config.sh` mit Exit 0.
