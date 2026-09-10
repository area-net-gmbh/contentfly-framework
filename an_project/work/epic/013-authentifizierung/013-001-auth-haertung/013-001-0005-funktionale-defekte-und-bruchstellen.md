---
id: 013-001-0005
title: Die funktionalen Defekte und die Bruchstellen
status: todo
depends_on: [013-001-0001, 013-001-0002, 013-001-0003, 013-001-0004]
---

# Die funktionalen Defekte und die Bruchstellen

## Context
Der Rest der Story, und die Buchführung.

**Tote Routen.** `POST /api/login` und `POST /api/logout` zeigen auf
`api.controller:loginAction` beziehungsweise `logoutAction` — beide Methoden gibt es im
`ApiController` **nicht**. Ein Aufruf endet nicht mit 404, sondern mit einem Fehler aus dem
Controller-Resolver. Zu entscheiden: entfernen oder auf `auth.controller` zeigen lassen. Die
funktionierenden Routen sind `/auth/login` und `/auth/logout`.

**Verdrehter Plugin-Zweig.** `substr($loginProviderClassName, 7) == 'Plugins'`
(`AuthController.php:158`) schneidet **ab** Position 7, statt die ersten sieben Zeichen zu
prüfen. Für `Plugins\Auth\Ldap` ergibt das `\Auth\Ldap`; die Bedingung greift nie, und der
Name wird fälschlich zu `Custom\Classes\Plugins\Auth\Ldap`. **Login-Manager aus Plugins
funktionieren dadurch nicht** — und das fällt niemandem auf, weil `plugins/` leer ist.

**Die Bruchstellen** aus dieser Story gehören gesammelt nach `breaking-changes.md`: das
Master-Passwort, die Token-Spalte, die Passwort-Spalte, und dass alle Sitzungen enden.

## Acceptance criteria
- [ ] Über die toten Routen ist entschieden und begründet; ein Aufruf liefert danach eine verständliche Antwort statt eines Resolver-Fehlers.
- [ ] Der Plugin-Zweig prüft die ersten sieben Zeichen; ein Test belegt die Auflösung für einen Namen mit und einen ohne `Plugins`-Präfix.
- [ ] Dass der Pfad mangels Plugin nicht end-to-end prüfbar ist, steht als Einschränkung im Ergebnis — nicht als stillschweigende Lücke.
- [ ] Alle Bruchstellen der Story stehen in `breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [ ] Die Gates bleiben grün, und der Lauf auf PHP 8.4 ebenfalls.

## Verification
Volle Suite auf beiden PHP-Versionen, `composer audit --locked`, PHPStan. Für die Routen ein
Aufruf über HTTP: Was antwortet `/api/login` vorher, was nachher? Für den Plugin-Zweig ein
Unit-Test auf die Namensauflösung.
