---
id: 007-005-0002
title: Das Ist-Verhalten des alten Projekts aufzeichnen
status: review
depends_on: [007-005-0001]
---

# Das Ist-Verhalten des alten Projekts aufzeichnen

## Context
Das Projekt hat vermutlich wenige automatische Tests. Ohne eine Aufzeichnung, was das **alte**
Backend antwortet, lässt sich nach der Migration nicht belegen, dass die App nichts merkt — der
Vergleich in Phase 8 des Leitfadens hätte keinen Massstab.

Dasselbe Vorgehen wie Epic `008` für das Framework, zugeschnitten auf das, was die
Ionic/Angular-App tatsächlich aufruft.

## Acceptance criteria
- [x] **Die Aufrufe der App sind erhoben** — aus dem Frontend-Code (`ApiService`, Interceptors, Guards), nicht geschätzt: Endpunkte, Methoden, welche Token-Quelle, welche Antwortfelder die App liest.
- [x] **Referenzantworten** des alten Backends für diese Aufrufe liegen reproduzierbar vor — Statuscode, Envelope, Felder — mit einem Skript, das denselben Satz später gegen das migrierte Backend fährt.
- [x] **Flüchtige Werte** (Zeitstempel, Tokens, Ids) sind im Vergleich ausdrücklich ausgenommen, nicht stillschweigend.
- [x] **Der Anmeldeweg ist beschrieben**, wie er heute läuft: welcher LoginManager für welchen Einstieg, OAuth-2.0-Fluss mit Profilabfrage an die Community-API, was lokal ohne echten Identity-Provider durchspielbar ist und was nicht.
- [x] Im Projekt-Repo ist nichts geändert; das Aufzeichnungsskript liegt in diesem Repo unter `tools/migration/`.

## Verification
Das Skript zweimal hintereinander gegen das alte Backend fahren: identisches Ergebnis nach
Abzug der ausgenommenen Werte.

## Offene Fragen
- ~~Lässt sich der OAuth-2.0-Fluss lokal durchspielen (Client-Zugangsdaten, Redirect-URL auf
  `localhost`), oder braucht es einen Test-Identity-Provider?~~ **Beantwortet am 2026-09-15
  (Auftraggeber): Ja, OAuth 2.0 funktioniert auch auf `localhost`.**

## Ergebnis

### Die Aufrufe der App — erhoben aus dem Frontend-Code

Rund **230 Aufrufstellen**, alle über `ApiService` (ein einziger `HttpClient`), plus ein
`navigator.sendBeacon` und `<img>`-Abrufe von `/file/get/…`. Erhoben per Durchsicht des
Frontend-Codes mit Datei und Zeile; die Liste ist nicht als eigene Datei abgelegt — was davon lesend
ist, steckt im Generator des Szenarios. Hier das, was für die Migration zählt:

- **Token:** Header `APPCMS-Token` aus `StateService` (localStorage). Kein `Authorization: Bearer`,
  keine Cookies, kein `withCredentials`.
- **Envelope:** gelesen werden `data`, `options`, `apiResponse.code`/`translateKey`, `message`
  (nur bei `/oauth2/user`), `token` und `user` (Logins). `ts` und `status` liest die App nicht.
- **Generische Contentfly-Endpunkte:** nur `/api/update`, `/api/delete` und `/file/upload`.
  `/api/list` und `/api/single` ruft die App **nicht** auf; fast alles läuft über die
  projekteigenen Routen unter `/api/v2/…`.
- **Anmeldung:** Der Standard-Login ist im Code vorhanden, aber von keiner Seite aufgerufen. Wirklich
  benutzt werden der OAuth-2.0-Fluss und vier anonyme Einstiege über `POST /auth/login` mit
  `loginManager` = `PublicUserLoginManager`, `PublicUserFeatureRequestLoginManager`,
  `InsightLoginManager`, `UXLoginManager`.

### Der Anmeldeweg, wie er heute läuft

1. Die App setzt ein `state`-Cookie auf `APP_DOMAIN` und schickt den Browser zum TeamViewer-Autorisierungsendpunkt, `redirect_uri` = `APP_BACKEND_URL/oauth2/login`.
2. `GET /oauth2/login?code&state` prüft das Cookie gegen `state` und ruft intern `POST /auth/login` mit `loginManager=OAuth2LoginManager`.
3. `OAuth2LoginManager` tauscht den Code gegen ein Token, holt das TeamViewer-Konto, meldet sich bei der Community-API an, holt dort das Profil, legt den Benutzer an bzw. aktualisiert ihn und füllt dynamische Felder aus beiden Quellen.
4. Weiterleitung auf `APP_FRONTEND_URL/auth/result/<token>` — **der Token steht in der URL** (im Code als TODO vermerkt).
5. Die App ruft `POST /oauth2/user {token}` und prüft `message === 'Login successful'`.

**Lokal durchspielbar: ja** (Auskunft Auftraggeber, gemessen am 2026-09-15). Das alte Backend
muss dafür auf Port **55111** laufen, weil die Redirect-URL dort registriert ist; die App lief per
`ng serve` auf 8100. Ein echter SSO-Login gegen die Probe hat einen Token für einen Benutzer mit
Rolle `super-admin` erzeugt.

### Die Aufzeichnung

**Werkzeug:** `tools/migration/record-api.php record|compare` — generisch, im Framework-Repo, mit
`RecordApiToolTest` (Maskierung, Platzhalter, Diff und ein echter Lauf gegen einen kleinen Server).
Szenario und Passwörter sind getrennt: `{{…}}`-Platzhalter kommen aus einer Vars-Datei.

**Projektspezifisches liegt ausserhalb beider Repos**, unter `…/Ionic/.ufp-probe/`:
`reset-and-record.sh` (Kopie frisch aus dem Original, Probe-Benutzer, Szenario, zwei Läufe,
Vergleich), die beiden Generatoren, `scenario.json`, `vars.json` und `probe-users.json` (beide
Modus 600) und die Aufzeichnungen. Grund: Das Szenario nennt Projektdaten, die Vars nennen
Passwörter und den SSO-Token.

**Sessions:** je ein Probe-Benutzer pro Rolle (`super-admin`, `admin`, `editor`, `user`,
`read-only`) mit dem alten SHA-256-Passwortverfahren, angelegt nur in der Kopie; die vier anonymen
Einstiege; ein ungültiger Token; der echte SSO-Benutzer.

**Umfang: 164 Aufrufe.** Die Logins und ihre Fehlerfälle, Konfiguration und CMS, öffentliche
Umfragen, alle acht Umfragetypen (`fetch`, `answers`, `evaluate` als Admin und als Insight,
`additional-info`, `trend`, Statistik, Berechtigungen), Listen je Rolle, dynamische Felder,
Einstellungen, Benutzer, Forschungsinitiativen, Feature-Requests, Suche, UX-Dashboards und
-Scorecards, die Dateiauslieferung und die SSO-Session. Die Listen senden dieselbe
Spaltenkonfiguration, die die App vorher von `/api/v2/jsondata/listconfig` holt — mit leeren
Spalten scheiterte das alte Backend an SQL-Fehlern, und die Aufzeichnung hätte das Szenario gemessen
statt des Backends.

**Ausdrücklich nicht aufgezeichnet, mit Grund:**

| Was | Warum |
|---|---|
| Schreibende Aufrufe (`create`, `save`, `update`, `delete`, `answers/save`, Uploads, Tracking) | Sie verändern die Kopie zwischen zwei Läufen; eine Referenz, die sich selbst verschiebt, misst nichts. Für `0004` als Durchlauf in der App vorgesehen. |
| `/api/v2/ai/*`, `/openai/*`, `/rag/*` | Rufen externe, kostenpflichtige Dienste auf. |
| `GET /api/v2/user/consent`, `GET /user/consent` | **Schreiben**, obwohl sie `GET` sind: Sie setzen die Einwilligung. Gemessen — der Standard-Login antwortete im zweiten Lauf anders. |

**Flüchtige Werte, ausdrücklich ausgenommen:** Tokens (Schlüssel `token` und jede 128-stellige
Hex-Zeichenkette), Zeitstempel (`created`, `modified`, `loginDate`, `ts`, `datetime`, die vier
Datumsdarstellungen, …), `hash`, `debug`; bei den anonymen Logins zusätzlich `id`, `alias`,
`publicAlias`, weil jeder Aufruf einen neuen Benutzer anlegt; bei `/oauth2/user` `loginDate` und
`isFirstLogin`. Die Benutzerliste ist nach `alias` statt nach `loginDate` sortiert — jeder
Probe-Login schreibt `loginDate` neu, und die App-Reihenfolge wäre zwischen zwei Läufen
gewandert. Die Probe-Benutzer tragen ein `loginDate`, sonst meldete der erste Login
`isFirstLogin: true`.

**Verifiziert:** Nach frischem Aufsetzen aus dem Original zwei Läufe →
`identical 164 · differing 0`. 130 Aufrufe antworten 200; die übrigen sind gewollte
Fehlerfälle oder Fehler des alten Backends selbst (unten). In keiner Aufzeichnung steht der echte
SSO-Token.

### Was die Referenz schon jetzt für `0004` sagt

- **Die App erkennt einen abgelaufenen Login am deutschen Text.** Das alte Backend antwortet auf
  fehlenden oder ungültigen Token mit `401 {"message":"Zugriff verweigert", …, "0":401}`, und der
  `ErrorInterceptor` meldet genau dann ab. Das neue Framework antwortet `Invalid token.` — nach der
  Migration bliebe die App mit einem abgelaufenen Token eingeloggt, ohne Fehlermeldung. **Das ist
  der wichtigste Vertragsbruch für Phase 8.**
- **Der Status steht im alten Fehlerrumpf unter `"0"`**, nicht unter `status` (`000-000-0006`).
  Die App liest ihn nicht; ein anderer Client könnte es.
- **Das alte Backend scheitert schon heute** an: `research-initiative/users/fetch` (SQL-Fehler auch
  mit echter Spaltenkonfiguration), `ueq/additional-info` (`Invalid argument supplied for
  foreach()`), `jsondata/listconfig` ohne Key (`404`). SQL-Fehlertexte werden sich mit DBAL 3
  ändern — solche Abweichungen sind in `0004` als „vorher schon Fehler" einzuordnen.
- **CORS:** Die App schickt bei jedem Aufruf nicht-einfache Header (`APPCMS-Token`,
  `Cache-Control`, `Strict-Transport-Security`, `X-Content-Type-Options`); jeder Aufruf braucht einen
  Preflight. Mit `000-000-0039` muss `APP_ALLOW_ORIGIN` die App-Herkunft nennen und
  `APP_ALLOW_HEADERS_SDK` die vier Header. In der Live-Konfiguration des Projekts steht
  `APP_ALLOW_ORIGIN` auf der **Backend**-Adresse, mit Tippfehler — zu prüfen.

### Projektseitig, am Rand

- OAuth-Client-Secrets stehen, wie die Datenbank-Zugangsdaten, versioniert in `custom/config.php`.
- `OAuth2LoginManager` schreibt ein „TEMP DEBUG"-Log nach `data/oauth-debug.log` — `data/` wird
  direkt ausgeliefert; Tokens darin sind maskiert, Antworten der Community-API nicht.
- Zugangspasswörter für die Insight-Auswertung stehen im Klartext in `survey.accessPassword`.
- Der OAuth-Token wird in der URL übergeben.

### Ein eigener Fehltritt, festgehalten

Die Datenbankkopie lag zuerst unter `/private/tmp`. MySQL blieb dort beim Öffnen der Tabellen hängen,
und das Beenden des Containers hat Docker Desktop blockiert; nach Rückfrage neu gestartet. Die Kopie
liegt jetzt unter `/Users` neben dem Projekt. Ein Docker-Volume ging nicht: Das Datenverzeichnis
wurde auf macOS mit `lower_case_table_names=2` angelegt, und MySQL verlangt auf einem Linux-Volume
`0`. Das Original war zu jedem Zeitpunkt unverändert (Fingerabdruck vor und nach jedem Schritt).

**Suite:** Unit `OK (269 tests)`; im vollen Lauf (545) schlug nur der Sprachwächter an einem
deutschen Beispieltext im neuen Test an, ersetzt. PHPStan `[OK] No errors` (das Werkzeug per
`scanFiles`), Deprecation-Gate 0.
