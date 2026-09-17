---
id: 000-000-0054
title: Den Lauf auf PHP 8.5 vorbereiten und die Zielplattform einlösen
status: todo
depends_on: []
---

# Den Lauf auf PHP 8.5 vorbereiten und die Zielplattform einlösen

## Context
**PHP 8.5 ist die Zielplattform dieses Projekts — belegt ist bisher nur die Constraint-Seite.**
`LockGuaranteesTest` (`011-004-0001`) prüft, dass kein Paket PHP unter 8.5 deckelt. Ob die Suite
dort grün ist, sagt nur ein Lauf, und die Pipeline geht bis 8.4. In der Bilanz von Epic `011`
steht dieses Kriterium deshalb als ⚠️ und nicht als ✅.

**Die Begründung in jener Bilanz ist bereits überholt.** Sie sagt: *„Erst muss es ein
PHP-8.5-Image geben, das alle Erweiterungen mitbringt."* Nachgesehen am 2026-09-17:
**`php:8.5-cli` existiert**, aktuell `8.5.10-cli`. Das Image ist nicht mehr der Grund.

**Der Stand, erhoben statt vermutet:**

| | |
|---|---|
| Paket-Manifest | `"php": "^8.3"` — 8.5 ist damit erlaubt |
| `config.platform.php` | `8.3.0` — sagt Composer, wofür aufgelöst wird |
| Pipeline-Container | `php:8.3-cli` für die drei Prüf-Jobs, Matrix `['8.3', '8.4']` für `test` |
| Erweiterungen | `pdo_mysql`, `gd`, `ldap` über `docker-php-ext-install`, dazu `git` und `openssh-client` |
| Lock | kein Paket deckelt unter 8.5 (Gate, blockierend) |

## Was zu klären ist, bevor eine Zeile geändert wird
- **Bauen die Erweiterungen auf 8.5?** `pdo_mysql` und `gd` sind unkritisch; `ldap` ist die, die
  bei einem PHP-Sprung am ehesten klemmt. Das prüft ein Lauf von
  `tools/ci/install-php-extensions.sh` gegen `php:8.5-cli` — mehr braucht es dafür nicht.
- **Was 8.5 an Deprecations mitbringt.** Das Gate „0 Deprecations" ist blockierend und liest das
  Laufzeit-Log. Neue Meldungen aus 8.5 machen den Lauf rot — **das ist gewollt**, aber es ist
  Arbeit, die vorher benannt gehört und nicht im Pull Request überrascht.
- **Ob 8.5 eine dritte Matrix-Spalte wird oder 8.3 ablöst.** Drei Spalten kosten zwei Minuten
  mehr je Pull Request; 8.3 zu streichen hiesse, die Untergrenze aufzugeben, die das Manifest mit
  `^8.3` zusagt. **Solange `^8.3` im Manifest steht, muss 8.3 geprüft bleiben** — sonst sichert
  das Paket eine Version zu, die niemand fährt.
- **Ob `config.platform.php` mitzieht.** Sie steht bewusst nicht gleich der Zielplattform:
  `LockGuaranteesTest` erklärt den Unterschied — die Plattform sagt, worauf gefahren wird, das
  Ziel, wohin es geht. Wer sie anhebt, ändert die Auflösung für alle.

## Acceptance criteria
- [ ] Erhoben und festgehalten, ob `install-php-extensions.sh` auf `php:8.5-cli` durchläuft — mit der Ausgabe als Beleg.
- [ ] Die Deprecations eines Laufs auf 8.5 sind gezählt und benannt; jede bekommt einen Verursacher (eigener Code oder Abhängigkeit).
- [ ] Entschieden, ob 8.5 als dritte Matrix-Spalte kommt oder 8.3 ablöst — mit dem Bezug zu `^8.3` im Paket-Manifest.
- [ ] Entschieden, ob `config.platform.php` mitzieht, und die Entscheidung steht dort, wo `LockGuaranteesTest` sie sucht.
- [ ] Die Bilanz in `an_project/work/epic/011-release-neue-version/epic.md` ist nachgezogen: Das ⚠️ wird zu ✅ oder trägt einen neuen, zutreffenden Grund.

## Verification
Ein Lauf der vollständigen Suite unter PHP 8.5 — lokal in Docker mit `php:8.5-cli` genügt für die
Erhebung; die Pipeline folgt erst mit der Entscheidung zur Matrix. Grün **und** 0 Deprecations,
oder eine Liste dessen, was dazwischensteht.

## Abgrenzung
**Dieser Task ändert die Zielplattform nicht und hebt keine Constraint.** Er erhebt, entscheidet
und schreibt auf. Die eigentliche Umstellung ist die Folge — und sie wird erst geschnitten, wenn
die Erhebung sagt, wie gross sie ist.
