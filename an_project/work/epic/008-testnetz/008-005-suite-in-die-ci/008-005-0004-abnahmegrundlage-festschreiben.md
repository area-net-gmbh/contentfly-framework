---
id: 008-005-0004
title: Die Suite als Abnahmegrundlage festschreiben
status: todo
depends_on: [008-005-0001]
---

# Die Suite als Abnahmegrundlage festschreiben

## Context
Epic `008` hat 232 Tests und 564 Assertions hervorgebracht. Damit ist die Suite mehr als ein
Testordner — sie ist die **Beschreibung des heutigen Verhaltens**, gegen die der Kernel-Tausch
geprüft wird.

Das steht heute nirgends. Ohne eine schriftliche Festlegung ist beim Umbau nicht entschieden,
was ein roter Test bedeutet: einen Fehler im Umbau — oder einen Test, der „eben angepasst
werden muss". Genau diese Unterscheidung ist der Grund, warum das Testnetz vor dem Umbau
gebaut wurde.

## Umfang

### Der Kernsatz
Eine Formulierung, die keinen Interpretationsspielraum lässt:

> Der Kernel-Tausch gilt als gelungen, wenn diese Suite **ohne inhaltliche Änderung** grün
> bleibt. Eine Testanpassung ist ein **Verhaltenswechsel** und braucht eine Begründung — sie
> ist kein Wartungsschritt.

Dazu gehört die Abgrenzung, was **keine** inhaltliche Änderung ist: ein geänderter
Namensraum, ein anderer Aufrufweg für dieselbe Zusicherung, ein umbenannter Helfer in
`IntegrationTestCase`. Ohne diese Abgrenzung wird der Satz entweder ignoriert oder er
blockiert legitime Umbauten.

### Für wen das gilt
- **Epic `009` (Kernel-Tausch).** Die Suite ist das Abnahmekriterium. Was heute grün ist,
  muss nach dem Tausch grün sein.
- **Epic `011` (Release).** Dieselbe Suite als Release-Kriterium.
- **Epic `007` (Migration der Bestandsprojekte).** Die Suite als Referenz, an der ein
  Bestandsprojekt prüfen kann, ob sein eigener Umstieg geglückt ist.

### Der ehrliche Teil: was die Suite nicht abdeckt
Eine Abnahmegrundlage, die ihre eigenen Grenzen verschweigt, wiegt in Sicherheit. Epic `008`
hat mehrfach dokumentiert, wo die Charakterisierung **nicht** hinreicht — das gehört
gesammelt an eine Stelle, nicht verstreut in Testkommentaren:

- **Codepfade ohne Auslöser**, für die nur die Vorbedingung geprüft ist: `excludeFromSync`,
  `i18n_universal`, `encoded`, die OneJoin-Kaskade, die Schreibprüfung des `MultijoinType`,
  `canExport`, `getExtended`.
- **Das Notschloss** im `before`-Hook des `SystemController` — nicht scharf geprüft, weil es
  ein beschädigtes Schema der gemeinsamen Testdatenbank verlangt (`008-004-0003`).
- **Der `before`-Hook der Vorlage** — setzt einen Wert, den niemand liest, und ist deshalb von
  aussen nicht nachweisbar (`008-004-0005`).
- **Sprachen und i18n** — die Vorlage konfiguriert keine Sprachen und bringt keine konkrete
  `BaseI18n`-Entity mit; abgedeckt ist nur die Logik im Unit-Test (`008-001-0005`).
- **Der Erfolgsfall von `/api/mail`** — existiert nicht, weil der Endpunkt an einer
  undefinierten Konstanten scheitert (`008-004-0004`).

Diese Liste ist keine Schwäche des Testnetzes, sondern seine Bedienungsanleitung: Wer beim
Umbau eine dieser Stellen anfasst, weiß, dass kein Test ihn auffängt.

### Was nachzuziehen ist
- **`an_project/docs/technical.md`** — der neue Abschnitt mit Kernsatz, Abgrenzung und der
  Liste der Lücken.
- **`an_project/docs/deployment.md`** — die Pipeline aus `008-005-0001` beschreiben; der
  Abschnitt *Umgebungen* ist heute ein leerer Platzhalter.
- **`an_project/docs/runbook.md`** — der lokale Ablauf auf den Stand nach Epic `008`,
  einschliesslich der Versandfalle und des `config.php`-Hooks aus `008-005-0003`.
- **`tests/README.md`** — Verweis auf die Abnahmerolle; die Datei beschreibt heute nur das
  Ausführen.

## Abgrenzung
Die Epic-Dateien `009`, `011` und `007` werden **nicht** angefasst. Ein Verweis von dort auf
die Abnahmegrundlage gehört in deren eigenes Refinement — sonst greift diese Story in die
Planung anderer Epics ein.

## Acceptance criteria
- [ ] `an_project/docs/technical.md` enthält den Kernsatz, wörtlich und ohne Weichmacher.
- [ ] Die Abgrenzung, was **keine** inhaltliche Teständerung ist, steht daneben.
- [ ] Die Rolle der Suite ist für `009`, `011` und `007` je in einem Satz benannt.
- [ ] Die Liste dessen, was die Suite **nicht** abdeckt, ist vollständig aus den Stories
      `008-001` bis `008-004` zusammengetragen, je mit Verweis auf den Task, der es
      festgestellt hat.
- [ ] `deployment.md`, `runbook.md` und `tests/README.md` sind auf dem Stand nach Epic `008`.
- [ ] Keine Änderung an den Epic-Dateien `007`, `009` oder `011`.

## Verification
Kein Testlauf — dies ist ein Dokumentationstask. Der Nachweis ist eine Durchsicht:

- Jede Lücke in der Liste lässt sich auf einen Testkommentar oder ein Task-Ergebnis in
  `008-001` bis `008-004` zurückführen; Stichproben belegen das.
- Die in `runbook.md` und `tests/README.md` beschriebenen Befehle werden **ausgeführt** und
  laufen wie beschrieben — eine Anleitung, die niemand nachgespielt hat, ist keine.
