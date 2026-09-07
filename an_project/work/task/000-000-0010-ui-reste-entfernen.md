---
id: 000-000-0010
title: UI-Reste aus Konfiguration und Schema entfernen
status: todo
depends_on: []
---

# UI-Reste aus Konfiguration und Schema entfernen

## Context
Epic `012` hat die PIM-Oberfläche entfernt, aber vier Rückstände bewusst stehen lassen, weil
sie außerhalb des jeweiligen Story-Umfangs lagen. Das Testnetz aus Epic `008` hat sie einzeln
belegt. Jetzt gibt es die Absicherung, um sie ohne Risiko zu ziehen.

## Umfang

### A — Die übrigen `FRONTEND_*`-Einstellungen
`Classes/Config.php` führt weiterhin `FRONTEND_UI`, `FRONTEND_TITLE`,
`FRONTEND_CUSTOM_NAVIGATION`, `FRONTEND_WELCOME`, `FRONTEND_URL`, `FRONTEND_LOGIN_REDIRECT`,
`FRONTEND_CUSTOM_LOGO`, `FRONTEND_CUSTOM_LOGIN_BG`, `FRONTEND_FORM_IMAGE_SQUARE_PREVIEW` und
`FRONTEND_ITEMS_PER_PAGE`. Story `012-005-0004` hat nur die drei entfernt, die sie selbst
verwaist hatte, und den Rest ausdrücklich als eigenen Task vermerkt.

### B — `customLogo` in `/api/config`
`Api::getExtendedSchema()` liefert im `frontend`-Block noch `customLogo`. Das ist der
**einzige öffentlich erreichbare Endpunkt** (keine Token-Pflicht) — dass er eine Eigenschaft
der gelöschten Oberfläche bewirbt, hat `008-003-0004` festgehalten.

### C — Die Schema-Keys `multipe` und `multiple`
An neun Stellen gesetzt, davon sechs unter dem Tippfehler `multipe`, und **an keiner Stelle
gelesen**. Ein reiner Widget-Hinweis. Story `012-006-0001` hat sie stehen gelassen, weil ihre
Entfernung das API-Schema geändert hätte und jene Story das ausschloss.

### D — `Type::$insertCallback` und `$updateCallback`
Zwei öffentliche Felder der abstrakten Basisklasse, die **niemand setzt und niemand liest**
(festgestellt in `012-006-0001`).

## Warum das jetzt geht
Die Punkte B und C ändern das **API-Schema**. Bis Epic `008` gab es dafür keinen Maßstab;
inzwischen halten `ReadApiTest`, `TreeApiTest` und die übrigen fest, was die API liefert. Eine
Änderung fällt damit auf, statt still zu passieren.

## Acceptance criteria
- [ ] Die übrigen `FRONTEND_*`-Einstellungen sind entfernt — oder es ist begründet
      festgehalten, welche bleiben und warum.
- [ ] `/api/config` bewirbt kein `customLogo` mehr; der `frontend`-Block ist entweder leer
      oder ganz entfallen.
- [ ] `multipe` und `multiple` sind aus allen Typ-Klassen entfernt.
- [ ] `Type::$insertCallback` und `$updateCallback` sind entfernt.
- [ ] Die betroffenen Charakterisierungstests sind **bewusst umgedreht**, nicht gelöscht —
      insbesondere `RouteSecurityApiTest::testApiConfigIstOhneTokenErreichbar()`.
- [ ] Die Änderungen am API-Schema sind in `an_project/docs/pim-annotationen-migration.md` als
      Breaking Change für Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün, mit den umgedrehten Zusicherungen. Zusätzlich das Schema vor
und nach der Änderung vergleichen, wie es `012-005-0003` getan hat: Es dürfen **nur** die vier
oben genannten Schlüssel verschwinden.
