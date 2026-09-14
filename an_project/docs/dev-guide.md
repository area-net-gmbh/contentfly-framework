<!-- PURPOSE: Entwickler-Leitfaden für DIESES Projekt — Backend-/Frontend-Struktur und wie
     man neue Bausteine (z. B. eine API-Route) anlegt. Overlay: das Projekt füllt es aus.
     Die KONSTANTEN Struktur-Regeln (Symfony = immer Bundles, Angular = src/modules + src/shared)
     stehen read-only in .an_framework/templates/tech-stack/<slug>/stack.md → an_project/docs/tech-stack.md;
     hier steht die konkrete Ausprägung für dieses Projekt. Nur bei Full-Projekten vorhanden
     (im Slim-Profil wird diese Datei nicht angelegt). -->

# Dev-Guide — Struktur & neue Bausteine

## Backend-Struktur
<!-- Die Bundles dieses Projekts und ihre Verantwortlichkeiten. Symfony wird IMMER in
     Bundles organisiert (wie Shopware 6), egal ob 1 oder 10 Bundles — siehe tech-stack.md. -->

| Bundle | Verantwortung |
|---|---|
| <!-- z. B. CatalogBundle --> | <!-- Produkte, Kategorien --> |

## Frontend-Struktur
<!-- Modul-Map: src/modules/<feature> + src/shared. Angular (mit/ohne Ionic) wird IMMER so
     aufgebaut — siehe tech-stack.md. -->

| Modul | Verantwortung |
|---|---|
| <!-- z. B. src/modules/checkout --> | <!-- Warenkorb, Bezahlung --> |
| `src/shared` | Wiederverwendbare Components, Services, Pipes |

## Neue API-Route hinzufügen
<!-- Konkrete Schritte für DIESEN Code: in welchem Bundle der Controller liegt, welches
     Route-Attribut, wie die Route getestet wird. -->
1. Controller im zuständigen Bundle anlegen (`#[Route(...)]`).
2. <!-- Request/Response-DTO, Validierung, Service-Aufruf. -->
3. API-Doku neu generieren (siehe unten).

## API-Dokumentation
<!-- Die API-Doku wird AUTOMATISCH generiert, nicht von Hand gepflegt. Womit (z. B.
     nelmio/api-doc-bundle bzw. api-platform für Symfony; compodoc für Angular), welcher
     Befehl sie neu baut, und wo das Ergebnis (OpenAPI/Swagger) liegt. -->
- Generator: <!-- z. B. nelmio/api-doc-bundle -->
- Befehl: <!-- z. B. bin/console nelmio:apidoc:dump -->
- Ausgabe: <!-- z. B. http://localhost:8000/api/doc -->

## Die zugesicherten `$app[...]`-Schlüssel

<!-- Ausgefüllt mit 007-003-0002. -->

**Der Zugriff `$app['orm.em']` ist dauerhaft Teil der öffentlichen API** — entschieden am
2026-09-11, Begründung und verworfene Alternativen in `an_project/docs/architecture.md` unter
*Key decisions*. Ein Projekt muss seine Controller dafür nicht anfassen.

**Diese Liste ist der Gegenstand der Zusicherung.** Was hier steht, bleibt; was nicht hier
steht, ist interne Verdrahtung und kann sich ändern. `tests/Integration/ContainerSchluesselTest.php`
hält beide Richtungen fest.

### Immer verfügbar

| Schlüssel | Was er liefert |
|---|---|
| `is_installed` | ob `appcms:install` schon gelaufen ist — `bool` |
| `debug` | der Wert von `APP_DEBUG` |
| `database` | eine DBAL-Verbindung für direktes SQL, getrennt von der des EntityManagers |
| `mailer` | der PHPMailer-Dienst |
| `routeManager` | der Weg, auf dem ein Projekt Routen registriert |
| `consoleManager` | die Registrierung eigener Console-Commands |
| `request_stack` | Symfonys `RequestStack`; der aktuelle Request über `getCurrentRequest()` |
| `dispatcher` | der `EventDispatcher` |
| `auth.user` | der angemeldete Benutzer, **`null` solange niemand angemeldet ist** |
| `orm.em` | der Doctrine-EntityManager, **`null` solange nicht installiert ist** |
| `loginProviders` | das Verzeichnis der Anmeldeprovider (Story `013-004`) |

### Erst wenn die Anwendung installiert ist

Diese beiden stehen in einem `if ($app['is_installed'])`. Auf einem frischen Checkout gibt es sie
**nicht** — wer sie ohne Prüfung liest, bekommt eine `InvalidArgumentException`. Das ist Absicht:
`appcms:install` muss selbst laufen können, bevor es eine Datenbank gibt.

| Schlüssel | Was er liefert |
|---|---|
| `db` | die DBAL-Verbindung des Standard-Mandanten |
| `dbs` | alle Verbindungen, nach Mandant |

**`orm.em` gehört ausdrücklich nicht hierher, und der Unterschied ist eine Falle.** Er ist
*immer* da; ohne Installation setzt `bootstrap.php` ihn im `else`-Zweig auf `null`. „Fehlt"
meldet sich mit einer Exception, die den Namen nennt — `null` meldet sich mit *Call to a member
function createQueryBuilder() on null*, einer Meldung, die von der Methode handelt und nicht
davon, dass nichts installiert ist. **Wer ihn vor der Installation benutzen könnte, prüft
`$app['is_installed']`.**

### Erst nach der Anmeldung

| Schlüssel | Was er liefert |
|---|---|
| `auth.token` | die Token-Zeile der laufenden Sitzung |

**Vorher gibt es ihn nicht** — nicht `null`, sondern gar nicht. Gesetzt wird er in
`BaseControllerProvider`, nachdem ein Request sich ausgewiesen hat. In einem Console-Lauf gibt
es ihn nie.

### Was **nicht** zugesichert ist

Alles andere, was das Framework registriert, ist interne Verdrahtung: `dbs.options`, `auth`,
`console`, `helper`, `loginThrottle`, `tokenAuthenticator`, `userProvisioning`,
`groupMapping`, `tokenHandler`, `thumbnailSettings`, `schema`, `typeManager`,
`pluginManager` — dazu `kernel`, `resolver` und `argument_resolver`, die aus
`Classes/Kernel/Application` kommen und den HttpKernel verdrahten.

Sie existieren, sie funktionieren, und sie können sich ohne Vorwarnung ändern. **Wer einen davon
braucht, sagt Bescheid** — dann wird er zugesichert oder bekommt einen richtigen Zugang. Ihn
still zu benutzen ist die einzige Variante, die schiefgeht.

**Dazu kommt eine Gruppe, die gar keine Liste haben kann:** Jeder gemountete Controller bekommt
einen Eintrag `<präfix>.controller`. Das Framework legt vier an (`api`, `auth`, `file`,
`system`), und ein Projekt legt für jede eigene Route einen weiteren an — die Vorlage erzeugt so
`api/v1/example/.controller`. Sie sind Verdrahtung des `ControllerResolver` und kein Dienst, den
jemand liest.

### Vier Regeln, an denen man sich sonst die Finger verbrennt

**1. Was ein Projekt selbst setzt, sichert niemand zu.** `$app['meine.service'] = …` ist
erlaubt und bleibt es — die Vorlage zeigt es in `custom/app.php` vor. Aber es ist Sache des
Projekts: Das Framework kennt den Schlüssel nicht und räumt ihn nicht auf.

**2. Ein unbekannter Schlüssel wirft.**

```
Der Container kennt "tippfehler" nicht.
```

Und das ist die Zusicherung, nicht ihr Gegenteil: Ein Tippfehler fällt laut auf und liefert
nicht still `null`. Wer prüfen will, ob es einen Schlüssel gibt, nimmt `isset($app['x'])` —
`ArrayAccess` beantwortet das, ohne den Dienst aufzulösen.

**3. Eine Closure gilt als Factory, nicht als Wert.**

```php
$app['rückruf'] = function () { return 'A'; };
$app['rückruf'];   // 'A' — die Closure wurde AUFGERUFEN, nicht zurückgegeben
```

Das ist Pimples Regel, und sie hat eine Kehrseite: **Wer einen Callback ablegen will, bekommt
ihn ausgeführt.** Pimple hat dafür `protect()`; hier gibt es das nicht, weil im Framework
niemand eine Closure als Wert ablegt. Wer es braucht, packt sie in ein Objekt oder ein Array.

**4. Ein einmal gelesener Dienst ist eingefroren.** Danach wirft `extend()`:

```
Der Dienst "dispatcher" ist bereits ausgelesen und laesst sich nicht mehr erweitern.
Wer extend() benutzt, muss es tun, bevor jemand den Dienst anfasst.
```

**Das ist mit Absicht so und nicht bloss eine Einschränkung.** Ein Container, der das
stillschweigend erlaubte, würde den Fehler verstecken: Die Erweiterung liefe ins Leere, und der
Dienst bliebe der alte. `000-000-0006` ist einmal genau darüber gestolpert. Praktisch heisst
das: Wer `$app['dispatcher']` erweitern will, tut es in `custom/app.php`, bevor irgendein
Controller läuft.

> Die Entscheidung, dass dieser Zugriff dauerhaft gilt, steht in
> `an_project/docs/architecture.md` unter *Key decisions*, 2026-09-11.

## Eine neue Entity anlegen

<!-- Ausgefüllt mit 000-000-0028. -->

Eine Entity erbt in aller Regel von `Areanet\PIM\Entity\Base` — damit bringt sie GUID,
`created`, `modified` und die Felder mit, die die API erwartet. Wer **nicht** von `Base` erbt
(`Token` und `RevokedToken` tun das nicht, sie sind Infrastruktur), sollte das hier wissen:

**`modified` entscheidet über einen Index.** `Classes/Events/LoadMetadata` hängt an jede Entity,
die kein Baum ist, einen `modified_index` — aber nur, wenn das Feld existiert. Fehlt es, wird die
Entity übersprungen: kein Index, kein Fehler.

Der Index ist nicht Zierde. Die Sync-Endpunkte `/api/all` und `/api/deleted` filtern nach
`modified`; ohne ihn ist das ein Tabellenscan je Abfrage. **Wer also eine Entity baut, die über
die Sync-API gelesen wird, braucht `modified`** — und bekommt den Index dann von selbst.

**Bis `000-000-0028` brach eine Entity ohne `modified` die Installation.** Die Meldung lautete
„There is no column with name `modified` on table `…`" und nannte den Listener nicht. Wer auf
einen alten Stand stösst und diesen Fehler sieht: Das ist die Ursache.

## Anmeldung über ein Fremdsystem

<!-- Ausgefüllt mit Story 013-005. Der Rest dieser Datei ist noch Vorlage. -->

Contentfly bringt zwei Provider mit, LDAP/Active Directory und OIDC. **Beide sind nicht
eingetragen** — ein Projekt trägt ein, was es braucht, und solange nichts eingetragen ist, gibt
es keinen Weg an der Passwortprüfung vorbei.

### Der Ablauf, einmal für beide

1. **Provider registrieren** in `custom/app.php`, unter einem Namen:

   ```php
   $app['anmeldeanbieter']->eintragen('ldap', function () {
       return \Areanet\PIM\Classes\Security\LdapProvider::ausKonfiguration();
   });
   ```

2. **Konfigurieren** über die `SECURITY_*`-Felder (unten). Geheimnisse gehören in die Umgebung,
   nicht in eine committete Datei.
3. **Gruppen abbilden** über `SECURITY_PROVIDER_GRUPPEN` — je Anbietername ein Eintrag mit
   `gruppen`, `admin` und `vorgabe`.
4. **Der Client schickt den Namen** als `loginManager` an `POST /auth/login`. Ein Name, den
   niemand eingetragen hat, wird abgewiesen.

Der Rest ist gleich wie bei einer lokalen Anmeldung: Contentfly legt den Benutzer an, wenn es
ihn noch nicht gibt, und stellt sein eigenes Token aus. **Ein Client merkt nicht, woher der
Benutzer kam.**

### LDAP / Active Directory

| Feld | Bedeutung |
|---|---|
| `SECURITY_LDAP_HOST`, `SECURITY_LDAP_PORT` | Das Verzeichnis |
| `SECURITY_LDAP_ENCRYPTION` | `none`, `ssl` oder `tls` |
| `SECURITY_LDAP_BASE_DN` | Basis der Suche |
| `SECURITY_LDAP_SEARCH_DN`, `SECURITY_LDAP_SEARCH_PASSWORD` | Dienstkonto; leer heisst anonyme Suche |
| `SECURITY_LDAP_FILTER` | Vorgabe `(sAMAccountName={kennung})`; für OpenLDAP meist `(uid={kennung})` |
| `SECURITY_LDAP_GRUPPEN_ATTRIBUT` | Vorgabe `memberOf` |

**Das Paket kommt nicht mit.** `symfony/ldap` steht **nicht** im `require` des Frameworks,
sondern in `suggest` — es verlangt die Systemerweiterung `ext-ldap`, und die jeder Installation
abzuverlangen, die nie ein Verzeichnis anfasst, wäre die falsche Richtung. Wer den Provider
benutzt, nimmt beides selbst auf:

```sh
composer require symfony/ldap    # im Projekt
# und ext-ldap ins PHP-Image
```

Ohne das Paket wirft `LdapProvider::ausKonfiguration()` mit genau diesem Hinweis.

**Der Weg ist suchen, dann binden**, nicht der direkte Bind mit einem aus der Kennung gebauten
DN. Der funktioniert nur, solange alle Benutzer flach in einer OU liegen; im Active Directory
tun sie das nicht, und angemeldet wird dort mit `sAMAccountName`, der im DN gar nicht vorkommt.

### OIDC

| Feld | Bedeutung |
|---|---|
| `SECURITY_OIDC_USERINFO_ENDPOINT` | Vollständige URL des Userinfo-Endpunkts |
| `SECURITY_OIDC_KENNUNG_CLAIM` | Vorgabe `sub` |
| `SECURITY_OIDC_GRUPPEN_CLAIM` | Vorgabe `groups`; leer heisst keine Gruppen |

Der Client holt sein Access-Token beim Identity-Provider und schickt es als `accessToken` — oder
als `pass`, wenn er dasselbe Formular benutzt wie für ein Passwort.

**`sub` und nicht `email`.** Die Kennung muss stabil sein: `email` und `preferred_username` sind
änderbar, und wer darauf abbildet, bekommt ein neues Konto, sobald jemand heiratet.

**Geprüft wird am Userinfo-Endpunkt, nicht lokal gegen ein JWKS.** Entschieden mit `013-005-0003`
— drei leichte Pakete statt fünf, und ein Widerruf wirkt sofort. Der Preis ist eine HTTP-Anfrage
je Anmeldung; sie betrifft nur die Anmeldung, weil Contentfly danach ein eigenes Token ausstellt.

### Wer aus dem Fremdsystem verschwindet

Er kommt nicht mehr herein — aber sein Konto bleibt, und ein laufendes Refresh-Token holt bis zu
seinem Zeitlimit weiter frische Access-JWT. Dagegen gibt es

```sh
php bin/console.php appcms:provider:abgleich --dry-run   # erst ansehen
php bin/console.php appcms:provider:abgleich             # dann sperren
```

Der Befehl setzt `isActive` auf false, wo das Fremdsystem die Kennung nicht mehr kennt. Er
**sperrt, statt zu löschen**: umkehrbar, und `pim_log` behält seinen Bezug.

**Ein Ausfall sperrt niemanden.** Kann ein Provider keine Auskunft geben, wird der Benutzer
übersprungen und das in der Ausgabe genannt. Ein OIDC-Provider kann die Frage grundsätzlich
nicht beantworten — er prüft ein Token, das der Client mitbringt — und wird ebenfalls
übersprungen, sichtbar.

Gehört in einen Cron, zusammen mit `appcms:token:cleanup`.

### Einen eigenen Provider schreiben

`Areanet\PIM\Classes\Security\Anmeldeprovider` hat **eine** Pflicht:

```php
public function pruefen(Request $request): ?Fremdkennung;
```

`null` heisst abgelehnt. Der Provider fasst die Datenbank **nicht** an — Benutzer anlegen,
Gruppen setzen und Token ausstellen macht das Framework. Wer zusätzlich sagen kann, ob es eine
Kennung noch gibt, implementiert `Bestandspruefung`.

`custom/Classes/Anmeldung/BeispielProvider.php` führt beides an einem lauffähigen Beispiel vor.

### Was in der Testabdeckung fehlt

**LDAP ist gegen Doppelgänger geprüft, nicht gegen ein laufendes Verzeichnis.** Bind-Reihenfolge,
Maskierung des Suchfilters und die Trefferbehandlung sind gemessen; dass ein echtes Active
Directory so antwortet, wie der Provider es erwartet, ist es nicht.

**OIDC ist gegen `MockHttpClient` geprüft**, nicht gegen einen echten Identity-Provider.

Beides ist eine bewusste Entscheidung (`013-005`) und keine Lücke, die niemand bemerkt hat. Wer
einen der beiden Wege produktiv nimmt, testet ihn einmal gegen sein echtes System — die Suite
nimmt ihm das nicht ab.

