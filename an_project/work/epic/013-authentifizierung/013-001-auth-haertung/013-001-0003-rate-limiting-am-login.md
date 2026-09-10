---
id: 013-001-0003
title: Rate-Limiting am Login, pro Kennung und pro IP
status: done
depends_on: []
---

# Rate-Limiting am Login, pro Kennung und pro IP

## Context
`CHECK_LOGIN_INTERVAL` ist eine `false`-Konstante — fest aus. Selbst eingeschaltet wäre es ein
60-Sekunden-Intervall **pro Benutzer**, gemessen am letzten ausgestellten Token; gegen das Raten
über viele Konten hinweg hilft das nicht, und gegen das Raten vieler Passwörter zu **einem**
Konto nur, wenn zwischendurch nie ein Token entstand.

Es braucht eine Begrenzung **pro Kennung und pro IP**, mit ansteigender Verzögerung.

**Der Speicher ist da:** `symfony/cache` liegt seit `010-002` im Baum. `symfony/rate-limiter`
kommt dazu — es ist die Komponente, die Symfonys eigener Login-Throttling-Mechanismus benutzt,
und sie arbeitet gegen einen Cache-Pool.

**Die IP ist der Haken.** `Request::getClientIp()` liefert hinter einem Proxy dessen Adresse,
solange `setTrustedProxies()` nicht gesetzt ist — und das ist es nirgends im Baum. Eine
Begrenzung pro IP bremste dann den Proxy statt den Angreifer, und zwar alle Benutzer dahinter.
Das gehört in diesen Task, nicht daneben.

**Ohne Konfiguration darf nichts kaputtgehen.** Wer keine Proxies konfiguriert, betreibt die
Anwendung direkt — dann stimmt die IP ohnehin.

## Acceptance criteria
- [x] Fehlversuche werden nachweislich gebremst, **pro Kennung und pro IP**, mit ansteigender Verzögerung.
- [x] Ein erfolgreicher Login setzt den Zähler für diese Kennung zurück.
- [x] Die Begrenzung greift auch, wenn der Benutzername gar nicht existiert — sonst ist sie ein Orakel dafür, welche Konten es gibt.
- [x] `setTrustedProxies()` ist konfigurierbar und aus der Konfiguration gespeist; ohne Angabe verhält sich die Anwendung wie bisher.
- [x] Die Antwort auf eine Bremsung nennt keinen Grund, der einem Angreifer hilft — insbesondere nicht, ob die Kennung existiert.
- [x] `CHECK_LOGIN_INTERVAL` und `MIN_LOGIN_INTERVAL` sind entfallen; der tote Zweig bleibt nicht stehen.

## Verification
Ein Test, der in Folge falsche Passwörter schickt und ab der festgelegten Zahl ein 429 (oder
den festgelegten Statuscode) erwartet — einmal mit gleicher Kennung, einmal mit wechselnden
Kennungen von derselben IP. Dazu ein Test, dass ein erfolgreicher Login danach wieder geht.
Volle Suite.

## Ergebnis

**Eine Bremse pro Kennung und pro IP, mit ansteigender Verzögerung.**
`Areanet\PIM\Classes\Security\Anmeldebremse` sitzt vor der Anmeldung; `CHECK_LOGIN_INTERVAL`,
`MIN_LOGIN_INTERVAL` und der tote Zweig dahinter sind entfallen.

### Zwei Achsen, weil eine jede für sich umgehbar ist

| Achse | fängt | Grenzen |
|---|---|---|
| Kennung | den Angriff auf **ein** Konto, auch von vielen Adressen aus | 5 / Minute · 20 / Viertelstunde · 50 / Stunde |
| IP | das Durchprobieren **vieler** Kennungen von einer Adresse | 20 / Minute · 60 / Viertelstunde · 200 / Stunde |

Die Achse IP ist weiter gefasst, weil hinter einer Adresse ein ganzes Büro sitzen kann. Eine
Grenze, die dort so eng steht wie bei einer einzelnen Kennung, bremst die Nachbarn statt den
Angreifer.

### Die Staffel ist die Verzögerung

Drei Fenster übereinander statt einer Grenze. Wer das erste reisst, wartet die Minute; wer
weitermacht, die Viertelstunde; wer dann noch weitermacht, die Stunde. Gemessen:

| Fehlversuche | Wartezeit |
|---|---|
| 5 | 60 s |
| 20 | 900 s |
| 50 | 3600 s |

**Der erste Entwurf lieferte hier dreimal 60 Sekunden.** Er las die Fenster über Symfonys
`CompoundLimiter`, und der meldet den **knappsten** Stand zurück — nach zwanzig Fehlversuchen
ist das die Minutengrenze mit ihren -15 Token, und deren Wartezeit ist nie länger als eine
Minute. Der Test hat es sofort gezeigt (`Failed asserting that 60 is greater than 60`). Jetzt
wird jedes Fenster einzeln gelesen und die **längste** Wartezeit gemeldet.

### Nur Fehlversuche zählen, und die Prüfung verbraucht nichts

Zwei Entscheidungen, die zusammengehören:

**Eine gelungene Anmeldung verbraucht nichts** und löscht ausserdem den Zähler der Kennung.
Sonst wäre es keine Bremse, sondern eine Nutzungsobergrenze — sie sperrte irgendwann genau die
Benutzer aus, die alles richtig machen. Der Zähler der **Adresse** bleibt stehen: Sonst genügte
dem Angreifer ein einziges gültiges Konto, sein eigenes, um sich nach jedem Block
freizuschalten.

**`wartezeit()` liest den Stand, ohne ihn zu verändern.** Würde die Prüfung selbst zählen,
verlängerte jeder abgewiesene Versuch die Sperre — der Block liefe nie ab, und ein Angreifer
könnte ein fremdes Konto dauerhaft sperren, indem er gegen die geschlossene Tür weiterläuft.
Gelesen wird dafür die Zahl der verbleibenden Token und nicht `isAccepted`: Das ist bei
`consume(0)` fest `true`.

### Erst bremsen, dann prüfen

Die Reihenfolge ist nachgemessen, nicht behauptet: Wer gebremst ist, bekommt `429` **auch mit
dem richtigen Passwort**. Stünde die Bremse hinter der Prüfung, kostete jeder abgewiesene
Versuch weiterhin einen Argon2id-Durchlauf — die Sperre wäre dann selbst der Hebel für eine
Überlastung.

Alle fünf Fehlschlag-Zweige laufen über **eine** Stelle (`$abweisen`). Vorher standen fünf
`return new JsonResponse(..., 401)` nebeneinander; wer jedem einzeln ein `fehlversuch()`
voranstellt, vergisst irgendwann eines, und ein ungezählter Zweig ist der Weg an der Bremse
vorbei.

### Die IP musste erst stimmen

`setTrustedProxies()` wurde im ganzen Baum **nirgends** gerufen. Ohne diese Angabe liefert
`getClientIp()` die Adresse des nächsten Hops — hinter einem Loadbalancer also dessen eigene.
Die Bremse hätte den Proxy getroffen und damit alle Benutzer dahinter.

`APP_TRUSTED_PROXIES` und `APP_TRUSTED_HEADERS` speisen jetzt `VertrauteProxies::anwenden()`,
aufgerufen ganz oben in `bootstrap-web.php` — die Angabe hängt an der Klasse `Request` und muss
gelten, bevor irgendein Request entsteht. **Leer heisst unverändert:** Ohne Eintrag wird
`setTrustedProxies()` gar nicht erst gerufen. Die Vorgabe für die Header ist die enge, nur
X-Forwarded-*; `forwarded` schaltet um, beide gleichzeitig gibt es nicht. Ein unbekannter Wert
wird abgewiesen statt stillschweigend auf die Vorgabe zurückgeführt — dieselbe Regel wie beim
entfallenen Cache-Treiber `apc`.

### Nebenher: der Cache-Treiber steht jetzt an einer Stelle

Die Bremse braucht einen Speicher, der Requests überlebt. Die Auswahl nach `APP_CACHE_DRIVER`
lag bisher im Doctrine-Block; sie ist als `$cachePoolBauen` herausgezogen und wird von drei
Stellen benutzt. **Die Bremse bekommt ihren Pool immer** — anders als die Doctrine-Caches, die
im Debug-Modus und auf der Konsole abgeschaltet sind. Eine Bremse, die sich mit `APP_DEBUG`
selbst abschaltet, wäre keine.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (310 tests, 835 assertions)`, 0 übersprungen (vorher 285) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen, 0 Ausnahmen |
| `composer audit --locked` | 0 Advisories |
| Postausgang | 0 Byte |
| A-1 und A-3 in `technical.md` | durchgestrichen |
| Bruchstellen | zwei Einträge in `breaking-changes.md` |

Sechzehn neue Tests: zehn ohne HTTP (`AnmeldebremseTest`, `VertrauteProxiesTest` misst zusätzlich
die Wirkung an einem echten `Request`), fünf gegen die laufende Instanz
(`AnmeldebremseApiTest`), einer hält fest, dass es die beiden Intervall-Konstanten nicht mehr
gibt — ein toter Zweig, den man stehen lässt, sieht beim nächsten Lesen wie eine vorhandene
Sicherung aus.
