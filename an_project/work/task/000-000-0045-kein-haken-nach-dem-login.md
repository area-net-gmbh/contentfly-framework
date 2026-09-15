---
id: 000-000-0045
title: Kein Haken nach dem Login — Projektdaten und tempData
status: review
depends_on: []
---

# Kein Haken nach dem Login — Projektdaten und tempData

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-7).

Der `LoginProvider`-Vertrag aus `013-004` gibt einem Provider **eine** Pflicht: gegen das
Fremdsystem prüfen. Er fasst die Datenbank nicht an, Anlage und Gruppen macht das Framework. Das
deckt einen reinen Anmeldeweg ab — nicht, was die sechs LoginManager von UFP beim Login zusätzlich
tun:

- **Felder am Benutzer setzen:** `loginDate`, `isPublic`, `publicAlias`, Consent-Felder,
  `dynamicFields` (aus Link-Parametern oder dem Community-Profil), `oAuth2Token`, `communityToken`.
- **`tempData` liefern.** Der Login antwortet mit `data` aus `$user->getTempData()` — das tut
  `AuthController` weiterhin. Aber gesetzt wird `tempData` nur vom Manager, und den gibt es nicht
  mehr. **Die App liest bei jedem Login `res.data.role`**; ohne `data` bricht die Anmeldung im Client.
- Auch der **Passwort-Weg** braucht `tempData` (Rolle, Consent-Stand, Erstanmeldung).

Einen Haken dafür gibt es nicht: `AuthController::loginAction()` löst kein Event aus. Der einzige
Umweg wäre ein Listener auf `pim.controller.after.auth.login`, der die fertige JSON-Antwort
umschreibt, mit Werten, die der Provider über Request-Attribute herüberreicht.

**Entschieden am 2026-09-15:** Das Framework löst nach einem erfolgreichen Login ein Event aus.

## Acceptance criteria
- [x] `AuthController::loginAction()` löst `pim.auth.after.login` aus — auf dem Passwort- und auf dem Provider-Weg, nach Provisionierung, Gruppenzuordnung und Aktiv-Prüfung, **vor** dem Ausstellen des Tokens.
- [x] Das Event trägt `user`, `request`, `provider` (Name oder `null`) und `identity` (`ExternalIdentity` oder `null`), dazu `app` wie die übrigen `pim.*`-Events.
- [x] Was ein Listener am Benutzer ändert und als `tempData` setzt, steht in der Antwort (`user`, `data`) — auch mit `tokenType=jwt`.
- [x] Ein abgelehnter Login löst das Event nicht aus.
- [x] Tests für beide Wege und für den abgelehnten Fall.
- [x] `breaking-changes.md` (Abschnitt LoginManager) nennt das Event als Ort für das, was ein alter Manager über die Prüfung hinaus tat; die Vorlage `custom/app.php` zeigt einen Listener.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Integrationstest gegen `/auth/login` mit registriertem Listener. Am UFP-Probe-Backend: Login
eines Probe-Benutzers antwortet mit `data.role`.

## Ergebnis

**`AuthController::loginAction()` löst `pim.auth.after.login` aus** — direkt vor `new Token()`, also
nach Passwort- oder Provider-Prüfung, Provisionierung, Gruppenzuordnung, Aktiv-Prüfung **und** der
JWT-Konfigurationsprüfung. So läuft ein Listener nie bei einem Login, der danach noch mit `500`
scheitert. Parameter: `user`, `request`, `provider` (normalisierter Name oder `null`), `identity`
(`ExternalIdentity` oder `null`), `app`. Was ein Listener am Benutzer ändert, geht mit dem Token in
denselben `flush()`; `user` und `data` der Antwort entstehen danach.

**Vorlage** `custom/app.php`, Abschnitt 6: ein lauffähiger Listener, der `tempData` auf Providername
und gemeldete Gruppen setzt. **Registriert mit `$app->on()`** — der erste Entwurf nahm
`$app['dispatcher']->addListener()`, und die Konsole starb: Das Lesen friert den Dispatcher ein, das
`extend()` des `ConsoleManager` wirft. Der Hinweis steht in Vorlage und Register.

**Test** `tests/Integration/Api/LoginEventApiTest.php`, über HTTP gegen die laufende Instanz:
Passwort-Weg (`provider`/`identity` leer), Provider-Weg (Name und Gruppen der Identität), JWT-Login
(`data` neben `refreshToken`), abgelehnter Login auf beiden Wegen (kein `data`). **Gegenprobe** ohne
`dispatch()`: drei rot, der Ablehnungsfall grün.

**Register:** Im Abschnitt LoginManager ein neuer Schritt 5 („was der Manager darüber hinaus tat, in
einen Listener"; Werte nur des Providers über `ExternalIdentity::$attributes`), dazu ein Eintrag zum
Event mit Beispiel. Kopf von `migration.md` auf 108.

**Am UFP-Probe-Backend belegt** (Projekt-Branch `migration/contentfly-2`, Framework-Stand dieses
Branches): Die sechs Manager sind Provider mit `completeLogin()`, aufgerufen aus einem Listener. Alle
fünf aufgezeichneten Login-Wege (Standard, Umfrage, Ideen, Insight, UX-Share) antworten `200` mit
`data`; die Schlüssel von `data` und die Rolle sind dieselben wie beim alten Backend. Die im Listener
gesetzte Gruppe steht in `user.group`, und `isPublic`, `publicAlias`, Consent und Gruppe liegen mit dem
Token in `pim_user`. (Die Antworten tragen dort noch die Warnung aus `000-000-0046` vor dem JSON.)

**Verifiziert:** volle Suite `Tests: 569, Assertions: 1834, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0, `tools/check-template-config.sh` grün.
