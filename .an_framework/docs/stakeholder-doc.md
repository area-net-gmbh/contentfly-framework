<!-- PURPOSE: Framework-Baseline für die Stakeholder-Doku unter an_project/stakeholder/ — wer sie liest, was in welches Kapitel gehört, wann sie nachgezogen wird. Gelesen von /doc, /new-project und /done; von /implement nur, wenn ein Pflege-Trigger anschlägt. -->

# Stakeholder-Doku — Framework-Baseline

`an_project/stakeholder/` enthält die Projektdokumentation **für Außenstehende**: Auftraggeber,
Designer, Führungskräfte, neue Kolleginnen. Sie wird mit
`.an_framework/scripts/build-stakeholder-doc.py` zu einer HTML-Dokumentation gebaut, die ohne
Server, ohne Internet und ohne Vorkenntnisse im Browser geöffnet werden kann.

**Overlay-Regel:** Weicht ein Projekt ab, steht das in `an_project/stakeholder/` selbst — die
Kapitel sind die Projektzone und gehören dem Projekt. Diese Datei beschreibt nur die Baseline.

## Wer das liest — und was daraus folgt

Die Leser verstehen ihr Geschäft, aber nicht unsere Technik. Daraus folgen vier Schreibregeln:

1. **Jeder Fachbegriff wird beim ersten Auftreten in einem Halbsatz erklärt.** Nicht
   „WordPress 6.8", sondern „WordPress 6.8 — das Redaktionssystem, in dem Inhalte gepflegt
   werden". Es gibt kein Glossar; die Erklärung steht dort, wo man stolpert.
2. **Kein Quellcode, keine Signaturen, keine Konfigurationsbeispiele.** Wer das braucht, liest
   `an_project/docs/`. Ein Code-Block in der Stakeholder-Doku ist fast immer ein Fehler.
3. **Wirkung vor Mechanik.** Nicht „Cronjob läuft alle 5 Minuten", sondern „Anfragen werden
   spätestens nach fünf Minuten zugestellt".
4. **Deutsch**, wie alles, was das Framework produziert. Etablierte Lehnwörter bleiben
   (Deployment, Hosting, Plugin, Staging) — aber erklärt, siehe Regel 1.

## Vier harte Regeln

- **Keine Geheimnisse.** Nie ein Passwort, einen Schlüssel oder ein Token. Nur: *wo* es liegt
  und *wer* herankommt. Das Dokument geht an Kunden; alles darin ist als weitergegeben zu
  behandeln.
- **Keine Preise, Verträge oder interne Kalkulation.**
- **Nie erfinden.** Was nicht bekannt ist, bleibt leer oder steht als offener Punkt. Eine
  plausibel erfundene Angabe ist später nicht mehr von einer bestätigten zu unterscheiden —
  dieselbe Regel wie im `new-project`-Interview.
- **Keine zweite Wahrheit.** Die Doku ist eine *Übersetzung* dessen, was im Projekt schon
  dokumentiert ist, kein zweiter Ablageort. Widersprechen sich Kapitel und Quelle, gilt die
  Quelle und das Kapitel wird korrigiert.

## Die Kapitel und ihre Quelle

`steckbrief.md` trägt den Dokumentkopf: nur `schlüssel: wert`, Reihenfolge und Beschriftung
liegen im Generator, damit jede Projektdokumentation identisch aufgebaut ist. Leere Werte
erscheinen als „offen" — das ist erwünscht, es macht Lücken sichtbar.

## Drei Zustände, nicht zwei

Ein Feld im Steckbrief und ein Kapitel-`status:` kennen **drei** Zustände, und der dritte ist der
wichtigste:

| Zustand | Bedeutung | Wird nachgefragt? |
|---|---|---|
| gefüllt / `status: gepflegt` | steht da, ist bestätigt | nein |
| leer / `status: offen` | fehlt noch, soll aber hinein | **ja**, bei jedem `doc`- und `done`-Lauf |
| `entfällt` | gibt es in diesem Projekt nicht — bewusst abgewählt | **nie wieder** |

Ohne den dritten Zustand ist eine Angabe, die es schlicht nicht gibt, von einer vergessenen nicht
zu unterscheiden. Ein laufendes Wartungsprojekt hat kein Releasedatum und kein „fertig" — bleibt
das Feld leer, fragt ANNA bis in alle Ewigkeit danach. Deshalb: **was der Nutzer abwählt, wird
`entfällt`, nicht leer.** Umgekehrt gilt genauso streng: `entfällt` wird nur gesetzt, wenn der
Nutzer es gesagt hat. Es von sich aus zu setzen, macht aus einer Lücke eine Unwahrheit.

Für Kapitel schreibt man es ins Frontmatter (`status: entfällt`, ASCII `entfaellt` geht auch). Im
Dokument steht dann „Dieser Abschnitt ist für dieses Projekt nicht relevant", und `--check`
listet das Kapitel nicht mehr als ungepflegt. Zurücknehmen ist jederzeit möglich: `status:` wieder
auf `offen` setzen.

| Kapitel | Quelle im Projekt |
|---|---|
| Überblick & Zweck | `an_project/project-description.md` |
| Funktionen & Features | `an_project/work/` — Epics und Stories, in Nutzersprache übersetzt |
| Technik im Überblick | `an_project/docs/tech-stack.md`, `architecture.md` |
| Schnittstellen & externe Dienste | `an_project/docs/technical.md` + Absprachen |
| Module & Erweiterungen | `composer.json`, `package.json`, Plugin-Liste des Systems |
| Prozesse & Abläufe | **nur hier dokumentiert** |
| Organisation & Zusammenarbeit | **nur hier dokumentiert** |
| Deployment & Betrieb | `an_project/docs/deployment.md` |
| Daten & Datenschutz | `an_project/docs/technical.md` + Absprachen |
| Stand & Roadmap | `status:` der Items in `an_project/work/`, `an_project/CHANGELOG.md` |
| Risiken & offene Punkte | Items mit `status: blocked`, offene ADR-Fragen, Absprachen |
| Entscheidungen | `an_project/docs/adr/` — übersetzt, nicht kopiert |
| Quellen & Pflege | diese Mechanik; im Grundgerüst bereits gefüllt |

Die zwei mit **nur hier dokumentiert** markierten Kapitel haben keine Quelle im Repo. Sie
entstehen aus Gesprächen und sind der eigentliche Mehrwert des Dokuments — niemand sonst
schreibt sie auf.

## Wann nachgezogen wird

Die Doku wird **nicht** auf Zuruf gepflegt, sondern an den Stellen, an denen sich ohnehin etwas
ändert. Ein Task ist stakeholder-relevant, wenn er einen dieser Punkte berührt:

1. ein externer Dienst oder eine Schnittstelle kommt dazu, fällt weg oder wird anders angebunden
2. ein Modul, Plugin oder Paket mit fachlicher Wirkung kommt dazu oder fällt weg
3. Deployment, Umgebungen, Voraussetzungen oder Zugänge ändern sich
4. eine für Nutzer sichtbare Funktion entsteht, verschwindet oder verhält sich anders
5. eine Entscheidung wird getroffen oder eine ADR angelegt
6. die Verarbeitung personenbezogener Daten ändert sich

Schlägt keiner an, bleibt die Doku unberührt — das ist der Normalfall und keine Nachlässigkeit.

Schlägt einer an: **nur das betroffene Kapitel** anfassen, `stand:` im Frontmatter auf das
heutige Datum setzen, `status:` auf `gepflegt`, sobald es wirklich ausgefüllt ist. Die Änderung
gehört in denselben Commit wie die Arbeit, die sie ausgelöst hat — getrennt committet wäre sie
nicht mehr zuzuordnen.

Beim Abschluss einer Story oder eines Epics kommt ein vollständiger Durchlauf dazu: alle
Kapitel gegen den tatsächlichen Stand prüfen, Funktionsstatus, Roadmap, Risiken und den
Steckbrief (Version, Status, Stand) nachziehen.

## Der Markdown-Dialekt

Der Generator versteht bewusst nur einen Teil von Markdown. Was er kann:

- Überschriften `#` bis `####` (Anker und Seiten-Inhaltsverzeichnis entstehen automatisch)
- Absätze, `**fett**`, `*kursiv*`, `` `code` ``, Links, Bilder
- Listen mit einer Verschachtelungsebene, numeriert oder nicht, auch Checklisten `- [ ]`
- Tabellen im üblichen Pipe-Format (`\|` für ein Pipe im Text; Ausrichtungs-Doppelpunkte
  werden gelesen, aber nicht angewendet)
- Zitatblöcke `>`, waagerechte Linien, Code-Blöcke mit ```` ``` ````
- **Diagramme**: ein Block ```` ```mermaid Beschriftung ```` wird zum Diagramm mit Lightbox
  und aufklappbarem Quelltext. Flowcharts für Abläufe, Organigramme für Zuständigkeiten.
- HTML-Kommentare tragen Hinweise für die Pflege und erscheinen **nicht** im Browser.
- Eine Zeile, die mit `<` beginnt, wird als HTML durchgereicht; HTML *innerhalb* eines Absatzes
  wird als Text angezeigt, nicht ausgeführt.

Nicht unterstützt: Fußnoten, Definitionslisten, verschachtelte Zitate, mehr als eine
Listenebene. Eine gefährliche Adresse (`javascript:`) bricht den Build ab.

## Bauen und ansehen

```
python3 .an_framework/scripts/build-stakeholder-doc.py [--open] [--check] [--clean]
```

Der `doc`-Workflow ruft das auf. Das Verzeichnis `an_project/stakeholder/html/` ist reine
Ausgabe: es wird bei jedem Lauf neu erzeugt und niemals von Hand bearbeitet. `--check` meldet
per Exit-Code 1, wenn die Ausgabe nicht zur Quelle passt.

## Slim-Projekte

Im Slim-Profil kopiert `init.sh` die Kapitel *Prozesse & Abläufe*, *Stand & Roadmap* und
*Entscheidungen* nicht mit. Die Nummerierung bleibt lückenlos, weil der Generator die
Kapitelnummern aus der Position errechnet. Ein fehlendes Kapitel ist kein Fehler — es wird
auch nicht nachträglich angelegt, nur weil ein Inhalt dafür auftaucht; dann gehört er in das
thematisch nächste vorhandene Kapitel.
