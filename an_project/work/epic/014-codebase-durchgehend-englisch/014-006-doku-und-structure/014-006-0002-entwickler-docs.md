---
id: 014-006-0002
title: Entwickler-Docs auf die englischen Namen
status: done
depends_on: [014-006-0001]
---

# Entwickler-Docs auf die englischen Namen

## Context
Die Docs, nach denen ein Entwickler arbeitet, nennen noch alte Namen: `dev-guide.md` (14 Treffer,
darunter das Registrieren eines Login-Providers mit `$app['anmeldeanbieter']->eintragen()`),
`technical.md` (14), `architecture.md`, `deployment.md` und `runbook.md`. Wer danach einen
Provider einbindet, schreibt Code, der nicht läuft.

Umfang: alle Dateien unter `an_project/docs/` ausser den Migrations-Docs (`014-006-0003`) und der
Übergabenotiz (`014-006-0004`), dazu `an_project/project-description.md`. Die Prosa bleibt deutsch.

## Acceptance criteria
- [x] Jeder Code-Verweis in diesen Docs stimmt mit dem Code überein, auch Methoden, die nicht in der
  Tabelle des Epics stehen (etwa `Tokenhandler::timeoutGilt()`). Codebeispiele laufen gegen den
  heutigen Code.
- [x] Deutsche Begriffe, die wie ein alter Klassenname aussehen („Anmeldebremse", „Zugangstoken"),
  sind ersetzt: durch den Klassennamen, wo die Klasse gemeint ist, sonst durch einen Begriff, den
  die Suche nach alten Namen nicht trifft.
- [x] Die Suche nach den alten Namen findet in diesen Dateien nichts.
- [x] Tests, die Docs lesen (`ContainerKeysTest` gegen `dev-guide.md`), sind grün.

## Verification
Suche mit der Liste der alten Namen. Skript, das Code-Verweise aus den Docs zieht und auflöst.
Codebeispiele stichprobenhaft gegen die Klassen gelesen. Volle Suite grün.

## Ergebnis

**Die Entwickler-Docs nennen die heutigen Namen.** Geändert haben sich `dev-guide.md`,
`technical.md`, `deployment.md` und `architecture.md`. Die übrigen Dateien im Umfang
(`runbook.md`, `api-envelope.md`, `abhaengigkeiten-inventar.md`, `styleguide.md`, `guidelines.md`,
`tech-stack.md`, `git.md`, `project-description.md`) hatten keinen alten Namen.

**Was ein Entwickler danach abschreibt, läuft jetzt:**
- **Provider registrieren:** `$app['loginProviders']->register('ldap', …)` mit
  `LdapProvider::fromConfig()`.
- **Gruppen abbilden:** `SECURITY_PROVIDER_GROUPS` mit den Einträgen `groups`, `admin` und
  `default`.
- **LDAP-Filter:** Der Platzhalter heisst `{identifier}`. Mit `{kennung}` wäre er nie ersetzt
  worden.
- **Eigener Provider:** Die Signatur heisst `authenticate(Request): ?ExternalIdentity`.
- **Weiteres:** `appcms:provider:sync`, `UserExistenceCheck::knowsIdentifier()`, `ExampleProvider`
  unter `custom/Classes/Authentication/`, die Security-Klassen in `technical.md` und
  `JwtAccessToken::CLAIMS` in `deployment.md`.

**Zitierte Meldungen.** Der `dev-guide` zitiert zwei Container-Meldungen, und beide sind seit
`014-001` englisch. Die Namenssuche hätte sie nicht gefunden. Deshalb sind zusätzlich alle
deutschen String-Literale aus `lib/`, `custom/`, `bin/` und `index.php` vor dem Epic abgeleitet
(407), die es heute nicht mehr gibt, und in allen Docs gesucht. Treffer gab es nur diese zwei im
`dev-guide` und zwei in `breaking-changes.md`, die an `014-006-0003` gehen. Die Beispielschlüssel
`meine.service` und `rückruf` heissen wie in der Vorlage `my.service` und `callback`.

**Prosa.** „Anmeldeprovider" als Begriff steht jetzt als „Login-Provider" da (`architecture.md`,
`dev-guide.md`), `LoginThrottle` in der Befundtabelle von `technical.md` als Klasse.

**Wie gesucht wurde.** Die Liste alter Namen ist aus Git abgeleitet und nicht von Hand gepflegt: alle
Klassen, Methoden, Konstanten, Config-Keys, Container-Schlüssel, Commands und
Umgebungsvariablen, die es vor Epic `014` gab (`b93e5cf7`) und heute nicht mehr gibt. Das sind
807 Namen, dazu eine kurze Handliste für Strings und Verzeichnisse. Weil viele davon gewöhnliche
deutsche Wörter sind (`liste`, `daten`, `lesen`), sucht das Skript unterschiedlich:
- **Klassen- und Konstantennamen:** überall, auch in der Prosa.
- **Methoden:** nur als Code, also in Backticks, mit `(`, `->` oder `::`.
- **Deutsche Wörter, die zugleich Klassennamen waren** (`Pfade`, `Metadaten`): nur als Code.
Gegengeprüft ist es an einer Datei mit sieben absichtlichen Treffern und zwei Prosazeilen; es
meldet genau die sieben.

**Übrige Meldungen des Auflösungsskripts** sind bewusst historische Stellen: entfernte Pakete
(`silex/silex`, `sentry/sentry`), entfernte Klassen und Methoden (`ExportController`,
`checkToken()`, `LoginManager`, `Auth::init()`), Muster aus dem Kundenprojekt (`protect()`,
`tenantSecretResolver`) und Pfade der Tech-Stack-Vorlage (`src/modules`). Sie beschreiben, was
war, nicht was ist.

**Nachweis.** Suche nach alten Namen über alle Dateien des Tasks: 0 Treffer. Volle Suite
`OK (528 tests, 1703 assertions)` inklusive `ContainerKeysTest` gegen den `dev-guide`, PHPStan
`[OK] No errors`, Deprecation-Gate grün.
