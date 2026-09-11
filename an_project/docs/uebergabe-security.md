<!-- PURPOSE: Übergabenotiz für eine externe Sicherheitsprüfung des neuen Framework-Stands. Sagt, was geprüft wird, was bekannt offen ist, und wie ein Fund zurückkommt. -->

# Übergabe an die IT-Security

**Stand:** `v2.0.0-pre-security-2026-09-11` · **Repo:** `areanet/contentfly-framework`

Diese Notiz begleitet die Übergabe des neuen Contentfly-Frameworks an die Sicherheitsprüfung.
Sie sagt in dieser Reihenfolge: was Sie vor sich haben, was schon bekannt ist, was noch nicht
endgültig ist, und wie ein Fund zurückkommt.

---

## 1. Was Sie vor sich haben

**Contentfly ist Datenhaltung plus Core-Funktionen — kein CMS mehr.** Die mitgelieferte
PIM-Oberfläche ist mit Epic `012` ersatzlos gestrichen. Zugriff gibt es über die HTTP-API und
über die Console; eine Weboberfläche bringt das Framework nicht mit.

| | |
|---|---|
| Kernel | Symfony 7.4 LTS (Silex 2 ist seit Epic `009` aus dem Baum) |
| Persistenz | Doctrine ORM 3.7 über DBAL 3.10, Entities mit PHP-Attributen |
| PHP | ≥ 8.3, geprüft auf 8.3 und 8.4; Zielplattform 8.5 |
| Umfang | 152 Dateien Frameworkcode, 18 Laufzeit-Abhängigkeiten |
| Suite | 59 Testdateien, 524 Tests |

**Das Repo ist zugleich Paket und Projektgerüst.** `lib/contentfly/` ist das Composer-Paket
`areanet/contentfly`; die Wurzel darum herum ist das, was ein Projekt vor sich hat. Was zu
welchem gehört, steht in `an_project/docs/architecture.md` unter *Key decisions*, 2026-09-11.

### Was **nicht** dazugehört

- **Projektcode.** `custom/` ist eine ausgelieferte Vorlage, kein produktives Projekt. Was ein
  Kundenprojekt dort hineinschreibt, ist nicht Teil dieser Übergabe.
- **Die Alt-Version.** Die Befunde, die dieses Projekt ausgelöst haben, stammen aus dem alten
  Contentfly. Dieser Stand ist der Nachfolger, nicht derselbe Code.
- **Zugangsdaten.** `custom/config.php` ist eine Vorlage ohne Werte; die Geheimnisse in der
  Pipeline (`.gitlab-ci.yml`) sind Wegwerfwerte für eine Wegwerf-Datenbank und als solche
  gekennzeichnet.

---

## 2. Was seit dem Review vom 4. September behoben ist

Das Review fand sechs Befunde in der Authentifizierung. **Fünf sind behoben**, jeder mit einem
eigenen Commit und Tests; die Einzelheiten stehen in `an_project/docs/technical.md` unter
*Befunde*, durchgestrichen mit dem Verweis auf den Task, der sie aufgelöst hat.

| | Vorher | Jetzt |
|---|---|---|
| A-1 | Passwörter als `sha256($pass.$salt)`, ohne Arbeitsfaktor | Argon2id über `password_hash()`; Bestandshashes werden beim ersten Login umgeschlüsselt |
| A-2 | `APP_MASTER_PASSWORD` akzeptierte den Login für **jeden** Benutzer | ersatzlos entfernt — nicht abschaltbar gemacht, sondern entfernt |
| A-3 | kein Rate-Limiting; die vorgesehene Konstante war `false` | Anmeldebremse pro Kennung **und** pro IP, ansteigend 60 s → 900 s → 3600 s |
| A-4 | `pim_token.token` im Klartext | SHA-256; der Klartext verlässt das System genau einmal, bei der Anmeldung |
| A-6 | `createManagedUser()` setzte `setPass($alias)` — das Passwort war der Benutzername | ein über ein Fremdsystem angelegter Benutzer hat ein gesperrtes Passwort |

**Drei Dinge, die beim Beheben nebenbei herauskamen und ebenfalls behoben sind:**

- Der Klartext-Token stand nicht nur in der Tabelle, sondern auch in `pim_log.model_label` —
  ein Protokoll lebt länger als die Sitzung, die es beschreibt.
- `POST /system/do` löste seine Methode über `method_exists()` auf und war damit für alles
  erreichbar, was die Klasse mitbrachte. Jetzt entscheidet eine ausgeschriebene Erlaubnisliste.
- Feldverschlüsselung lief über AES-CBC ohne Authentifizierung; ein manipulierter Wert lieferte
  Unsinn statt zu werfen. Jetzt XChaCha20-Poly1305.

---

## 3. Was bekannt offen ist

**Diese Liste gehört zur Übergabe.** Was hier steht, brauchen Sie nicht zu suchen — es ist
gefunden, aufgeschrieben und terminiert.

### A-5 — `referrer`-Tokens sind ratbare Dauerschlüssel

**Der einzige offene Befund aus dem Review.** Task `000-000-0030`. Er hat drei Teile, und nur
der erste steht in der Befundtabelle:

1. **Der Schlüssel kommt vom Aufrufer.** `SystemController::addToken()` nimmt `token` aus dem
   Request und schreibt ihn, wie er ist. Geprüft wird nur, dass das Feld nicht leer ist —
   `token=test` wird angenommen.
2. **Er läuft nie ab.** `Tokenhandler::timeoutGilt()` gibt für jede Zeile mit `referrer`
   `false` zurück: „ein API-Token verfällt nicht".
3. **Beim Vorzeigen bremst nichts.** Die Anmeldebremse aus A-3 hängt am Login; im Weg über
   `Tokenhandler::ausDatenbank()` kommt sie nicht vor. Ein schwacher API-Token lässt sich
   ungedrosselt durchprobieren.

**Was den Befund begrenzt:** `/system/do` verlangt Anmeldung **und** Adminrecht. In der Tabelle
steht seit A-4 nur ein SHA-256. Und das Framework bringt mit `generateToken` längst den
richtigen Wert mit — `bin2hex(random_bytes(64))`. **Es bietet ihn an, aber es verlangt ihn
nicht**, und ein ungesalzener SHA-256 ist für 64 zufällige Bytes genau richtig und für `test`
wertlos.

### `000-000-0024` — ein Fehler im Bootstrap antwortet mit null Byte

Eine Ausnahme, die fällt, **bevor** der Kernel steht, erreicht keinen Fehler-Handler. Der
Aufrufer bekommt HTTP 500 mit leerem Rumpf; die Meldung steht nur im Serverlog. Gemessen mit
einem fehlkonfigurierten Cache-Treiber.

*Für Sie relevant als Gegenprobe:* Es ist kein Informationsleck, sondern das Gegenteil — aber
es erschwert die Abgrenzung, ob eine Instanz falsch konfiguriert oder angegriffen ist.

### Nicht sicherheitsrelevant, der Vollständigkeit halber

`000-000-0025` (Datenmodell `BaseI18nTree`) und `000-000-0031` (eine gedeckelte Abhängigkeit).

---

## 4. Was noch nicht endgültig ist

**Epic `011` vergibt erst die Releaseversion.** Mit ihm wird das Antwortformat der API
vereinheitlicht — heute gibt es sieben verschiedene Formen, danach eine (`data`, `errors`,
`meta`). **Was Sie am Envelope finden, kann bis dahin gegenstandslos werden.** Die Entscheidung
und der Zeitpunkt stehen in `an_project/docs/api-envelope.md`.

**Alles andere ist stabil:** Authentifizierung, Token, Rechte, Dateiauslieferung,
Feldverschlüsselung, Konfiguration.

**Ein Stand, kein Zustand.** Hier wird weitergearbeitet. Der Tag markiert genau das, was Sie
bekommen haben; alles danach ist nicht Teil dieser Prüfung.

---

## 5. Wie Sie den Stand zum Laufen bringen

**Nicht hier — die Anleitungen stehen an einer Stelle, und zwei Beschreibungen desselben Laufs
laufen auseinander:**

| Was | Wo |
|---|---|
| Vom Checkout zur laufenden Instanz | `an_project/docs/runbook.md` |
| Die Testsuite fahren | `tests/README.md` |
| Die drei Gates (Audit, Deprecations, PHPStan) | `an_project/docs/deployment.md`, *Die Gates* |

**Der Stand, den Sie bekommen, ist grün:** 524 Tests, PHPStan `[OK] No errors`,
`composer audit --locked` ohne Advisories und ohne abandoned Pakete, 0 Deprecations bei 0
Ausnahmen.

---

## 6. Wie ein Fund zurückkommt

**Bitte nicht als Mail.** Ein Fund, der in einem Postfach liegt, ist kein Fund.

1. **Kurzbeschreibung, Reproduktion, betroffene Datei oder Route.** Was Sie getan haben, was
   passiert ist, was Sie erwartet hätten.
2. **Einschätzung der Wirkung** — und ruhig auch dann, wenn Sie unsicher sind: Die Einordnung
   machen wir gemeinsam.
3. **Ob er im alten Contentfly auch besteht.** Das entscheidet, ob das gestoppte Projekt sofort
   betroffen ist oder erst nach der Migration.

Daraus wird hier ein Task mit Kontext, Abnahmekriterien und einer Verifikation — so wie A-1 bis
A-6. **Was behoben wird, wird gemessen behoben:** Jeder der fünf erledigten Befunde hat einen
Test, der ihn festhält, und die Befundtabelle sagt, welcher Task ihn aufgelöst hat.

---

## 7. Ein Vorschlag

**Das gestoppte Projekt ist genau das, was uns fehlt.** Die letzte offene Story des
Migrations-Epics (`007-005`) wartet auf ein reales Bestandsprojekt, an dem sich der
Migrationsleitfaden durchspielen lässt — ohne eines ist nicht belegt, dass der Weg vollständig
beschrieben ist.

**Nötig wäre ein Abzug, kein Zugriff auf den Produktivstand:** das `Entity/`-Verzeichnis, die
Konfiguration und ein Datenbankabzug. Wenn die Sicherheitsprüfung ohnehin mit dieser Codebase
arbeitet, liesse sich beides aus demselben Anlass erledigen.
