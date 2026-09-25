---
id: 000-000-0100
title: Den hohlen Refresh-Test reparieren und die Warnung im Schema-Cache-Test beseitigen
status: review
depends_on: []
---

# Den hohlen Refresh-Test reparieren und die Warnung im Schema-Cache-Test beseitigen

## Context
**Aus `000-000-0096`.** Ein Lauf ohne `restrictWarnings` meldete zwei Warnungen im Testprozess,
„Undefined array key" — nicht 8.5-spezifisch, PHP meldet das seit 8.0. Mit der echten Konfiguration
bleiben sie unsichtbar, weil `tests/` nicht zu `<source>` gehört.

**`AuthApiTest::testDeactivatedUserGetsNoNewAccessJwt` kann nicht scheitern.** Er liest
`$login['refreshToken']`; seit `011-001-0004` steht das Token im Envelope unter `data`. Der Refresh
bekommt `null`, und die erwartete 401 kommt vom fehlenden Token, nicht vom deaktivierten Benutzer.
Die Route antwortet jede Ablehnung bewusst gleich — am Code allein ist die Ursache nicht zu erkennen.

**`SchemaCacheApiTest::testWithTheCacheOffTheFileIsNotRead` ist nicht hohl**, anders als in `0096`
zunächst notiert: Läse der Server die Cache-Datei, käme der Marker zurück, und der Test würde rot. Er
liest nur einen Schlüssel, den das frisch gebaute Schema nicht hat, und erzeugt dabei die Warnung.

## Acceptance criteria
- [x] Der Refresh-Test nimmt das Token aus dem Envelope und prüft, dass es eines ist.
- [x] Gegenprobe im Test: Ein aktiver Benutzer bekommt auf demselben Weg 200 — erst damit ist die 401 des deaktivierten Benutzers dessen Deaktivierung zuzuschreiben.
- [x] Gegenprobe am Code: Ohne die Prüfung auf `isActive` im Refresh wird der Test rot.
- [x] Der Schema-Cache-Test prüft dasselbe wie bisher, ohne einen fehlenden Schlüssel zu lesen.
- [x] Ein Lauf ohne `restrict*` meldet für beide Klassen keine Warnung.

## Verification
Beide Testklassen, einmal mit `phpunit.xml.dist`, einmal ohne `restrict*`; dazu die Gegenprobe am
Code und die volle Suite.

## Ergebnis (2026-09-25)
**Der Refresh-Test kann jetzt scheitern, und beide Klassen laufen ohne Warnung.**

- **`AuthApiTest::testDeactivatedUserGetsNoNewAccessJwt`:** Zwei neue Benutzer melden sich für JWT an;
  das Refresh-Token kommt aus dem Envelope (`data.refreshToken`), mit geprüfter Vorbedingung. Einer wird
  deaktiviert. Der aktive bekommt auf demselben Weg **200**, der deaktivierte **401**
  `contentfly_general_invalid_refresh_token`. Der Helfer `refreshTokenOfANewUser()` hält den Weg für
  beide gleich.
- **Gegenprobe am Code:** Ohne `!$user->getIsActive()` in `AuthController::refreshAction()` bekommt der
  deaktivierte Benutzer 200, und der Test ist rot. Die alte Fassung wäre dabei grün geblieben.
- **`SchemaCacheApiTest::testWithTheCacheOffTheFileIsNotRead`:** liest `settings['label'] ?? null`. Die
  Aussage ist unverändert: Läse der Server die markierte Datei, käme der Marker zurück.

**Geprüft:** beide Klassen mit `phpunit.xml.dist` und ohne `restrict*` grün, **ohne Warnung**; volle
Suite 862 grün (3 übersprungen wie auf `master`), PHPStan ohne Fehler.
