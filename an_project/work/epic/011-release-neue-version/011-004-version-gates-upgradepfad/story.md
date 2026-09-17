---
id: 011-004-0000
title: Version, Gates und Upgrade-Pfad festschreiben
status: in-progress
depends_on: [011-001-0000, 011-002-0000, 011-003-0000]
---

# Version, Gates und Upgrade-Pfad festschreiben

## Goal
**Der Stand bekommt eine Nummer, und die Aussagen über ihn sind geprüft statt behauptet.** Das
ist der Abschluss des Epics und kommt deshalb zuletzt: Erst wenn der Envelope steht (`011-001`),
das Paket beziehbar ist (`011-002`) und die Doku stimmt (`011-003`), beschreibt eine Version
etwas Vollständiges.

**Was dazugehört:**

- **Die Version vergeben.** `version.php` und das Paket-Manifest stehen heute beide auf `2.0.0` —
  zu entscheiden ist, ob das die Release-Nummer bleibt, und beide Stellen samt Tag
  zusammenzuführen.
- **Die Bruchstellen vollständig.** `breaking-changes.md` ist die Liste, an der ein Projekt
  arbeitet; sie muss den Envelope-Bruch und alles aus den Epics `009` bis `014` tragen. Der
  Leitfaden zählt ihre Grösse — die Zahl gehört geprüft, nicht geschätzt.
- **`composer audit --locked` sauber unter der Zielplattform PHP 8.5.** Heute läuft das Gate auf
  8.3 und 8.4; für 8.5 ist offen, ob alle Abhängigkeiten es mitmachen.
- **Silex-Freiheit prüfbar.** Im Lock stehen heute null Treffer für `silex` und `pimple` — ein
  Gate hält das fest, statt es einmal gemessen zu haben.
- **Der Upgrade-Pfad festgehalten:** Symfony 8.4 LTS (erwartet Nov 2027) als geplanter nächster
  Schritt, abgesichert durch das bestehende Gate „0 Deprecations".

**Fertig, wenn** die Version vergeben ist, jedes Erfolgskriterium des Epics einen Beleg hat und
das Release beschrieben ist.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. Geschnitten beim Start der Story. -->
- [ ] 011-004-0001 — Die Gates schärfen: Silex-Freiheit und PHP 8.5
- [ ] 011-004-0002 — Das Release beschreiben: Bruchstellen, Erfolgskriterien, Upgrade-Pfad
- [ ] 011-004-0003 — Die Version vergeben, taggen und @RC entfernen
