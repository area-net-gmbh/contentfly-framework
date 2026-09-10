---
id: 013-003-0002
title: Der Refresh-Weg
status: todo
depends_on: [013-003-0001]
---

# Der Refresh-Weg

## Context
Ein Access-JWT ist kurzlebig — das ist seine ganze Sicherheitsleistung. Ohne Erneuerung müsste
sich ein Benutzer alle paar Minuten neu anmelden. `POST /auth/refresh` nimmt das Refresh-Token
entgegen und gibt ein frisches Access-JWT zurück.

**Das Refresh-Token wird dabei ersetzt.** Ein Refresh-Token, das mehrfach gilt, ist ein
langlebiges Geheimnis: Wer es abgreift, kann sich damit beliebig lange frische Zugangstokens
holen, und niemand sieht es. Wird es bei jedem Gebrauch getauscht, fällt ein zweiter Gebrauch
desselben Tokens auf.

**Die Anmeldebremse gilt auch hier.** `013-001-0003` bremst `POST /auth/login` pro Kennung und
pro IP. Ein Endpunkt, der Zugangstokens ausgibt, ist dasselbe Ziel — ihn ungebremst zu lassen
hiesse, die Bremse an der Vordertür anzubringen und die Seitentür offen zu lassen.

## Acceptance criteria
- [ ] `POST /auth/refresh` liefert gegen ein gültiges Refresh-Token ein frisches Access-JWT.
- [ ] Das Refresh-Token wird dabei ersetzt; das vorgezeigte gilt danach nicht mehr. Ein Test benutzt es zweimal und erwartet beim zweiten Mal eine Abweisung.
- [ ] Ein unbekanntes, abgelaufenes oder bereits verbrauchtes Refresh-Token wird abgewiesen — alle drei mit derselben Antwort.
- [ ] Ein Access-JWT taugt nicht als Refresh-Token, und ein Refresh-Token nicht als Zugangstoken. Beide Richtungen sind geprüft.
- [ ] Ein gesperrter Benutzer bekommt kein neues Access-JWT.
- [ ] Der Endpunkt unterliegt der Anmeldebremse aus `013-001-0003`.

## Verification
Integrationstest: mit JWT anmelden, das Refresh-Token einlösen, mit dem neuen Access-JWT
`/api/schema` öffnen, dann dasselbe Refresh-Token ein zweites Mal einlösen wollen. Dazu ein
Test, der einen gesperrten Benutzer erneuern lassen will. Volle Suite.
