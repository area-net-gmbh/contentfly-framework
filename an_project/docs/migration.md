<!-- PURPOSE: Der Migrationsleitfaden — der Weg vom alten Contentfly auf die neue Version, geordnet nach dem, was ein Projekt zu tun hat. Das Register der Einzelheiten ist breaking-changes.md. -->

# Migration auf die neue Framework-Version

**Dieser Leitfaden ist der Weg. `an_project/docs/breaking-changes.md` ist das Register.**

Das Register hat **100 Einträge in 14 Abschnitten** (Stand 2026-09-11) und ist nach Epic und
Story geordnet — also danach, *wann wir etwas geändert haben*. Das ist die richtige Ordnung zum
Nachschlagen und die falsche zum Arbeiten. Hier steht die andere: **was ein Projekt tut, und in
welcher Reihenfolge.**

---

## Die Phasen

<!-- Ausgefüllt mit 007-004-0001; der Text je Phase kommt mit 007-004-0002. -->

Die Reihenfolge ist nicht Geschmack. Wo sie erzwungen ist, steht der Grund dabei.

| # | Phase | Warum hier |
|---|---|---|
| 1 | **Voraussetzungen und Sicherung** | Alles Folgende setzt PHP 8.3, `ext-sodium` und `ext-openssl` voraus. Und die Sicherung gehört vor den ersten Schritt, nicht vor den letzten. |
| 2 | **Bezugsweg: von der Kopie auf das Paket** | **Erzwungen.** Die Rector-Regel für Phase 3 kommt mit dem Paket. Wer die Entities zuerst migriert, hat das Werkzeug noch nicht. |
| 3 | **Entities migrieren** | Nach dem Paket, vor allem anderen: Ohne lesbare Metadaten startet nichts, was danach kommt. |
| 4 | **Datenbankschicht nachziehen** | **Teilweise erzwungen.** Der Metadaten-Cache muss *vor* dem ORM-Upgrade geleert werden — ein Cache im alten Format bringt den ersten Start zu Fall. |
| 5 | **Konfiguration nachziehen** | Vor dem ersten Start: Entfallene Felder melden sich erst beim Start, und dann mit einer Meldung, die von der Klasse handelt und nicht vom Feld. |
| 6 | **Code nachziehen** | Controller, Provider, Commands. Erst jetzt sinnvoll, weil die Anwendung bis hierhin nicht startet. |
| 7 | **Authentifizierung nachziehen** | Nach dem Code, weil sie an ihm hängt — und vor der API-Prüfung, weil jeder API-Aufruf sich ausweisen muss. |
| 8 | **Den API-Vertrag prüfen** | Was die Clients merken. Zuletzt im Code, weil erst hier eine laufende Anwendung antwortet. |
| 9 | **Daten migrieren** | **Erzwungen.** Der Re-Encrypt-Lauf braucht eine laufende Anwendung mit funktionierendem Schema. |

## Welcher Register-Abschnitt in welcher Phase

**Jeder der 14 Abschnitte gehört genau einer Phase.** Das ist die Zusicherung, die
`tests/…` hält: Kein Abschnitt fällt heraus.

**Ein Abschnitt gehört in die Phase, in der seine Handlung liegt** — nicht in jede, die er
berührt. *Feldverschlüsselung* etwa fordert `ext-sodium` schon in Phase 1; sein eigentlicher
Schritt ist der Re-Encrypt-Lauf, also steht er in Phase 9. Wo ein Abschnitt früher hineinragt,
sagt es der Text der Phase.

| Phase | Register-Abschnitt |
|---|---|
| 1 | *(keiner — Phase 1 ist Vorarbeit)* |
| 2 | Paketgrenze (Epic `007`) |
| 3 | Annotationen · Entity-Layer (Story `010-001`) |
| 4 | Doctrine ORM 3 (Story `010-003`) · Doctrine (Story `009-005`) |
| 5 | Konfiguration |
| 6 | Kernel (Epic `009`) |
| 7 | Authentifizierung (Story `013-001`) · Authentifizierung, Teil 2 (Story `013-002`) · Authentifizierung, Teil 3 (Story `013-003`) · Authentifizierung, Teil 4 — der LoginManager (Story `013-004`) · Authentifizierung, Teil 5 — LDAP und OIDC (Story `013-005`) |
| 8 | API |
| 9 | Feldverschlüsselung (Story `010-004`) |

### Die Granularität, und ihr Preis

**Zugeordnet wird abschnittsweise, nicht eintragsweise** — 14 Zuordnungen statt 100
Markierungen. Entschieden am 2026-09-11.

**Der Preis:** Ein neuer Eintrag in einem bereits zugeordneten Abschnitt gilt damit automatisch
als abgedeckt. Das ist vertretbar, solange das Register die Einzelheiten trägt und dieser
Leitfaden den Weg — aber es ist eine Entscheidung und keine Selbstverständlichkeit. Wer einen
Bruch aufnimmt, der in keine der neun Phasen passt, hat einen gefunden, den dieser Leitfaden
nicht führt: Dann gehört die Phasenliste erweitert und nicht der Eintrag hineingezwängt.
