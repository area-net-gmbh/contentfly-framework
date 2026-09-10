---
id: 013-001-0003
title: Rate-Limiting am Login, pro Kennung und pro IP
status: todo
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
- [ ] Fehlversuche werden nachweislich gebremst, **pro Kennung und pro IP**, mit ansteigender Verzögerung.
- [ ] Ein erfolgreicher Login setzt den Zähler für diese Kennung zurück.
- [ ] Die Begrenzung greift auch, wenn der Benutzername gar nicht existiert — sonst ist sie ein Orakel dafür, welche Konten es gibt.
- [ ] `setTrustedProxies()` ist konfigurierbar und aus der Konfiguration gespeist; ohne Angabe verhält sich die Anwendung wie bisher.
- [ ] Die Antwort auf eine Bremsung nennt keinen Grund, der einem Angreifer hilft — insbesondere nicht, ob die Kennung existiert.
- [ ] `CHECK_LOGIN_INTERVAL` und `MIN_LOGIN_INTERVAL` sind entfallen; der tote Zweig bleibt nicht stehen.

## Verification
Ein Test, der in Folge falsche Passwörter schickt und ab der festgelegten Zahl ein 429 (oder
den festgelegten Statuscode) erwartet — einmal mit gleicher Kennung, einmal mit wechselnden
Kennungen von derselben IP. Dazu ein Test, dass ein erfolgreicher Login danach wieder geht.
Volle Suite.
