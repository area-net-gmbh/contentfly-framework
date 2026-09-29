---
id: 000-000-0103
title: Alte SHA-256-Hashes ablösen — Konten sperren, die sich seit 013-001-0001 nicht angemeldet haben
status: done
depends_on: []
---

# Alte SHA-256-Hashes ablösen — Konten sperren, die sich seit 013-001-0001 nicht angemeldet haben

## Context
Gefordert als dritte Zeile von `015-000-0012`, dort ausdrücklich als eigener Task: „Entschieden und
dokumentiert, wie mit den verbliebenen alten SHA-256-Hashes umgegangen wird (Zwangs-Reset oder
Massenmigration). **Eine Umsetzung davon ist ein eigener Task.**" Die Entscheidung steht im
Register unter *API*, Eintrag *Geheime Felder sind aus Filter, Sortierung und Gruppierung
ausgeschlossen*. Dieser Task setzt sie um.

**Die Entscheidung, kurz:** Ein alter Hash lässt sich nicht migrieren, ohne das Passwort zu kennen
— `013-001-0001` hat deshalb den Weg gewählt, ihn beim nächsten Login zu ersetzen. Wer sich seither
nicht angemeldet hat, trägt ihn weiterhin. Eine Massenmigration ist unmöglich, also bleibt der
Zwangs-Reset.

**Warum das jetzt zählt und vorher nicht:** Mit `015-000-0012` ist der Weg zu, über den sich ein
Hash auslesen liess. Ein SHA-256-Hash ohne Arbeitsfaktor bleibt trotzdem der schwächste Punkt
jeder Installation, die noch welche hat — jedes Leck an anderer Stelle, ein Backup, ein
Datenbank-Dump, macht ihn mit GPU-Geschwindigkeit knackbar. Die Zahl der betroffenen Konten kennt
nur der Betreiber:

```sql
SELECT COUNT(*) FROM pim_user WHERE pass NOT LIKE '$%' AND pass <> '*';
```

Auf der Testinstallation dieses Repositories: 0 von 1 (gemessen 2026-09-28).

## Acceptance criteria
- [x] Ein Console-Command (Vorschlag: `appcms:security:lock-legacy-passwords`) sperrt jedes Konto mit einem Hash im alten Format über `User::lockPassword()` und meldet, wie viele es waren.
- [x] Der Lauf hat `--dry-run` und zählt dann nur, ohne zu schreiben.
- [x] Ein gesperrtes Konto lässt sich weder mit dem alten noch mit einem leeren Passwort anmelden — das deckt `015-000-0001` bereits ab, ein Test belegt es für diesen Weg.
- [x] Der Betreiber erfährt aus der Ausgabe, wie die Betroffenen wieder hineinkommen (Passwort durch einen Admin setzen lassen).
- [x] Registereintrag und Migrationsleitfaden nennen den Command als Schritt.

## Verification
Integrationstest: Zwei Konten anlegen, eines mit einem SHA-256-Hash, eines mit Argon2id. Der
Command mit `--dry-run` schreibt nichts und meldet 1. Ohne `--dry-run` trägt danach genau das alte
Konto `*`, das andere ist unverändert, und ein Login mit dem vormals richtigen Passwort antwortet
401. Gegenprobe gegen den Stand ohne Command rot.
