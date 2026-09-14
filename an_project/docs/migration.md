<!-- PURPOSE: Der Migrationsleitfaden — der Weg vom alten Contentfly auf die neue Version, geordnet nach dem, was ein Projekt zu tun hat. Das Register der Einzelheiten ist breaking-changes.md. -->

# Migration auf die neue Framework-Version

**Dieser Leitfaden ist der Weg. `an_project/docs/breaking-changes.md` ist das Register.**

Das Register hat **100 Einträge in 14 Abschnitten** (Stand 2026-09-11) und ist nach Epic und
Story geordnet — also danach, *wann wir etwas geändert haben*. Das ist die richtige Ordnung zum
Nachschlagen und die falsche zum Arbeiten. Hier steht die andere: **was ein Projekt tut, und in
welcher Reihenfolge.**

---

## Bevor Sie anfangen: was Sie verlieren

**Die PIM-Oberfläche ist ersatzlos gestrichen** (Epic `012`). Wer sie heute benutzt, um Inhalte
zu pflegen, verliert sie mit diesem Update — es gibt keinen Nachfolger im Framework und keine
Übergangsfrist.

**Was an ihre Stelle tritt:**

- **Die API.** Alles, was die Oberfläche tat, tut sie über `/api` — lesen, schreiben, löschen,
  Dateien. Ein Projekt, das eine Pflegeoberfläche braucht, baut sie darauf.
- **Die Console.** Installation, Schema, Aufräumarbeiten, der Abgleich mit Fremdsystemen.

**Was nicht an ihre Stelle tritt:** eine fertige Oberfläche. Contentfly ist nach `012` reine
Datenhaltung plus Core-Funktionen.

**Das ist die Entscheidung, die vor allen anderen steht.** Wer sie nicht treffen will, migriert
nicht — der Rest dieses Leitfadens setzt sie voraus.

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

---

# Der Weg

<!-- Ausgefüllt mit 007-004-0002. -->

## Phase 1 — Voraussetzungen und Sicherung

**Zu tun:**

1. **PHP 8.3 oder neuer.** Die Zielplattform ist 8.5; die Suite läuft grün auf 8.3 und 8.4.
2. **`ext-sodium` und `ext-openssl`** müssen vorhanden sein. Beide stehen jetzt im `require` —
   ohne sie schlägt schon `composer install` fehl, und das ist Absicht: Die Feldverschlüsselung
   hängt daran.
3. **Eine Sicherung der Datenbank.** Sie ist der Rückweg für Phase 9, und sie gehört *vor* den
   ersten Schritt, nicht vor den letzten.
4. **Eine Bestandsaufnahme:** Benutzt das Projekt eigene `Type`-Klassen? Eigene Plugins? Einen
   eigenen `LoginManager`? Jedes davon hat später eine eigene Stelle.

**Fertig, wenn:** `php -m` zeigt beide Erweiterungen, und die Sicherung liegt.

## Phase 2 — Bezugsweg: von der Kopie auf das Paket

**Das Framework kommt ab jetzt über Composer.** Wer `lib/` im eigenen Repo liegen hat, bekommt
eine neue Version nur durch Hineinkopieren — und verliert dabei jede eigene Änderung.

**Zu tun:** Die fünf Schritte in `an_project/docs/breaking-changes.md`, Abschnitt *Paketgrenze
(Epic `007`)*, Unterabschnitt *Der Ablauf, Schritt für Schritt*. Sie sind mit `007-001-0005`
einmal gegangen worden, an einem Projekt ohne eine Zeile Frameworkcode.

**Drei Dinge, die dabei umfallen und die man einzeln merkt:**

- `ROOT_DIR` gibt es nicht mehr; der Einstiegspunkt setzt `CONTENTFLY_PROJECT_DIR`.
- Der Einstiegspunkt lädt den Autoloader und ruft `Start` — nicht mehr umgekehrt.
- Ein liegengebliebenes `custom/vendor/` **bricht den Start ab**. Das ist Absicht: still
  übergangen sähe es aus wie etwas, das benutzt wird.

**Fertig, wenn:** `composer install` läuft durch und `vendor/areanet/contentfly` existiert.

## Phase 3 — Entities migrieren

**Ein Lauf über `Entity/` bringt Annotationen auf Attribute und entfernt, was mit Epic `012`
weggefallen ist.**

**Zu tun:** Der Rector-Lauf aus `an_project/docs/pim-annotationen-migration.md`, Abschnitt 7.
Dort steht der Aufruf, was die Regel abdeckt, was von Hand bleibt — und dass sie **zweimal**
laufen muss.

**Die Warnung, die dort ausführlich steht, hier in einem Satz:** Wer nur einmal läuft, hat keinen
halb migrierten Baum, sondern einen kaputten.

**Von Hand bleibt** unter anderem, drei entfallene `Type`-Klassen aus `APP_SYSTEM_TYPES` bzw.
`APP_CUSTOM_TYPES` zu streichen — das ist Phase 5, aber es fällt hier auf.

**Fertig, wenn:** ein Trockenlauf `Rector is done!` meldet.

## Phase 4 — Datenbankschicht nachziehen

**Zuerst, und das ist erzwungen: den Metadaten-Cache leeren.** Ein Cache im alten Format bringt
den ersten Start zu Fall, und die Meldung handelt dann von Doctrine und nicht vom Cache.

**Zu tun:** die Einträge unter *Doctrine ORM 3 (Story `010-003`)* und *Doctrine (Story
`009-005`)*. Die beiden, die am häufigsten treffen:

- **Eine Entity darf ein geerbtes Feld nicht wortgleich wiederholen.** ORM 3 lehnt das ab, wo
  ORM 2 es hinnahm.
- **Eigene DQL-Funktionen** müssen `getSql(): string` deklarieren.

**Fertig, wenn:** `php bin/console.php appcms:install` durchläuft oder das Schema validiert.

## Phase 5 — Konfiguration nachziehen

**Vor dem ersten Start.** Entfallene Felder melden sich sonst beim Start — mit einer Meldung, die
von der Klasse handelt und nicht vom Feld.

**Zu tun:** die Einträge unter *Konfiguration*. Dazu die drei `Type`-Klassen aus Phase 3.

**Die `FRONTEND_*`-Felder stehen an zwei Stellen, und das ist keine Doppelung:** Das Register
führt unter *Konfiguration* den Eintrag *Acht `FRONTEND_*`-Felder entfallen*;
`pim-annotationen-migration.md`, Abschnitt 6, nennt **zwei weitere**
(`FRONTEND_SHOW_ID_IN_LIST`, `FRONTEND_SHOW_OWNER_IN_LIST`) samt der beiden daraus abgeleiteten
Konstanten. Wer nur eine der beiden Listen abarbeitet, lässt Zeilen stehen.

**Neu hinzu kommt `SECURITY_JWT_SECRET`,** sobald das Projekt JWT ausstellen will — mindestens
32 Byte, sonst weist die Bibliothek den Schlüssel ab.

**Fertig, wenn:** die Anwendung startet und `/api/config` antwortet.

## Phase 6 — Code nachziehen

**Controller, Provider, Commands.** Erst jetzt sinnvoll: Bis hierhin startet die Anwendung nicht.

**Zu tun:** die Einträge unter *Kernel (Epic `009`)*. Der Abschnitt beginnt mit einer Liste
dessen, was sich **nicht** ändert — die zuerst lesen, sie ist die kürzere Arbeit.

**Was ein Projekt am ehesten trifft:**

- `$app` ist kein Action-Argument mehr.
- `$app['request']` und `$app['controllers_factory']` sind entfallen.
- Ein eigener Controller-Provider hat eine andere Schnittstelle und ruft `connect()` selbst.

**Was sich ausdrücklich nicht ändert:** der Zugriff `$app['orm.em']`. Er ist dauerhaft
zugesichert; welche Schlüssel dazugehören, steht in `an_project/docs/dev-guide.md` unter *Die
zugesicherten `$app[...]`-Schlüssel*. **Ein Projekt muss seine Controller deswegen nicht
anfassen.**

**Fertig, wenn:** die eigenen Routen antworten und die eigenen Commands laufen.

## Phase 7 — Authentifizierung nachziehen

**Der grösste zusammenhängende Block** — fünf Register-Abschnitte, Stories `013-001` bis
`013-005`.

**Zu tun, in dieser Reihenfolge:**

1. **`APP_MASTER_PASSWORD` entfernen.** Es gibt den Schalter nicht mehr, und ein Projekt, das
   ihn setzt, bekommt keine Warnung — nur keinen Zugang mehr darüber.
2. **Mit dem Ende aller Sitzungen rechnen.** Bestehende Token verfallen mit dem Update.
3. **Hinter einem Proxy `APP_TRUSTED_PROXIES` setzen.** Sonst trifft `LoginThrottle` den
   Proxy statt den Angreifer.
4. **Einen eigenen `LoginManager` auf den Provider-Vertrag umstellen** — die Klasse gibt es
   nicht mehr.

**Neu, aber optional:** JWT (`tokenType`), der Refresh-Endpunkt, LDAP und OIDC. Nichts davon ist
nötig, um wie bisher weiterzuarbeiten.

**Fertig, wenn:** Anmeldung und Abmeldung laufen und ein geschützter Endpunkt antwortet.

## Phase 8 — Den API-Vertrag prüfen

**Was die Clients merken.** 21 Einträge unter *API* — der grösste Abschnitt des Registers, und
der einzige, den ein Projekt nicht allein durch Codeänderungen erledigt: Ein Teil davon betrifft
Clients, die es nicht besitzt.

**Die Statuscodes, die sich geändert haben, zuerst:** 404 bei unbekannter Id, 405 statt 302 auf
unbekannten Pfaden, 409 bei einer `unique`-Verletzung, 429 bei zu vielen Anmeldeversuchen.

**Und die Formänderungen:** Der `frontend`-Block schrumpft, in `/api/config` fällt er ganz weg;
`export` und `extended` verschwinden aus dem `permissions`-Block.

**Der Envelope selbst bleibt vorerst.** Die Vereinheitlichung kommt mit dem Release (Epic `011`)
und ist bewusst nicht hier.

**Fertig, wenn:** die eigenen Clients gegen die neue Instanz laufen.

## Phase 9 — Daten migrieren

**Nur, wenn das Projekt verschlüsselte Felder hat** (`@PIM\Config(encoded: true)`). Sonst
entfällt diese Phase, und der Lauf sagt es wörtlich:

```
Kein Feld mit encoded: true — es gibt nichts umzuschluesseln.
```

**Zu tun:** der Ablauf in `an_project/docs/deployment.md` — Sicherung, Trockenlauf, echter Lauf,
zweiter Trockenlauf als Prüfung.

**Warum es zuletzt steht:** Der Lauf braucht eine laufende Anwendung mit funktionierendem
Schema.

**Ein abgebrochener Lauf ist kein Schaden.** Jeder Stapel ist eine Transaktion, beide Formate
bleiben lesbar, und ein Neustart macht dort weiter, wo er aufhörte.

**Fertig, wenn:** ein zweiter Trockenlauf `0 umgeschlüsselt` meldet — oder, ohne verschlüsselte
Felder, die Zeile oben.

---

## Wenn etwas fehlt

**Dieser Leitfaden führt den Weg; die Einzelheiten stehen im Register.** Wer einen Bruch sucht,
den hier niemand erwähnt, findet ihn in `an_project/docs/breaking-changes.md` — die Zuordnung
oben sagt, in welcher Phase sein Abschnitt abgearbeitet wird.

**Wer einen findet, der in keine der neun Phasen passt,** hat einen gefunden, den dieser
Leitfaden nicht führt. Dann gehört die Phasenliste erweitert und nicht der Eintrag
hineingezwängt.
