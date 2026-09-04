<!-- PURPOSE: Schritt-für-Schritt-Anleitung, um ein bestehendes Projekt auf eine neue Framework-Version zu heben. EINZIGE Quelle der Update-Befehle — how-to-use.md §8 verweist nur hierher, README.md verlinkt nur. Keine Setup-Befehle fuer NEUE Projekte: die stehen ausschliesslich in quickstart.md. -->

# Framework aktualisieren — Schritt für Schritt

Dein Projekt trägt seine Framework-Version als **mitcommitteten Ordner** `.an_framework/`.
Ein Update heißt: diesen Ordner gegen einen neueren getaggten Stand tauschen, die neuen
Skelett-Dateien nachziehen und beides als **eigenen** Commit abschicken.

Rechne mit **10 Minuten**. Du brauchst keine Vorkenntnisse außer Terminal öffnen und
Befehle einfügen.

> **Falsche Datei?**
> - Du richtest ein **neues** Projekt ein, oder baust das Framework zum **ersten Mal** in
>   ein bestehendes → [quickstart.md](quickstart.md), Teil B bzw. Teil C.
> - Du hast das Projekt gerade nur geklont → gar nichts tun. Die Version kam mit.
>   [quickstart.md](quickstart.md), Teil D.
> - Du willst wissen, **warum** es so läuft → [how-to-use.md](how-to-use.md) §8.

---

## Das Wichtigste in vier Sätzen

1. Der Kern `.an_framework/` wird **komplett ersetzt**, nicht gemerged.
2. Deine Projektdateien (`an_project/`, `CLAUDE.md`, `.claude/`, dein Code) fasst das
   Update **nicht** an.
3. Neue Slash-Commands und neue Doku-Vorlagen kommen **nicht** automatisch mit — dafür
   läuft `init.sh` noch einmal (Schritt 5). Es überschreibt nie etwas Gefülltes.
4. Framework-Update und Projektarbeit gehören **nie** in denselben Commit.

---

## Schritt 0 — Sauber starten

Öffne das Terminal in der **Projektwurzel** (dort, wo `CLAUDE.md` und `an_project/`
liegen) und prüfe, dass nichts Unfertiges herumliegt:

```sh
git status
```

Erwartet: **„nichts zu committen, Arbeitsverzeichnis unverändert"**. Steht dort etwas
anderes, committe oder verwirf deine Arbeit zuerst — sonst mischt sich dein Stand in den
Update-Commit. Bist du mitten in einem Task, ist `/commit` der richtige Weg dafür.

Wechsle außerdem auf deinen Integrationsbranch (meist `master`) oder lege einen eigenen
Branch für das Update an. Auf einem fremden Task-Branch hat ein Framework-Update nichts
zu suchen.

**Merke dir deine aktuelle Version** — du willst nachher sehen, was sich geändert hat:

```sh
cat .an_framework/VERSION
```

## Schritt 1 — Die neue Version heraussuchen

Öffne im Browser die **Tags-Seite** des Framework-Projekts in GitLab und nimm den
**obersten** `v`-Eintrag. Schreib **keine** Versionsnummer aus dieser Anleitung ab — sie
ist morgen veraltet.

Ohne Browser geht es auch im Terminal:

```sh
git ls-remote --tags http://gitlab.in.area-net.de/intern/ki-dev-framework.git
```

Die letzte Zeile mit einem `v…`-Namen ist die neueste Version.

> Ist die gefundene Version **dieselbe** wie in Schritt 0 — fertig, du bist aktuell.
> Hier aufhören.

## Schritt 2 — Ist etwas zu beachten?

Sieh **vor** dem Tausch in die Release-Hinweise der neuen Version (siehe *Was welche
Version verlangt* am Ende dieser Datei). Manche Versionen benennen einen Command um oder
brauchen einen Handgriff mehr. Überspring das nicht — es ist der einzige Schritt, den
`init.sh` dir nicht abnimmt.

## Schritt 3 — Den Kern austauschen

**Setze deine Version statt `v2.3.0` ein.**

```sh
rm -rf .an_framework
git clone --depth 1 --branch v2.3.0 http://gitlab.in.area-net.de/intern/ki-dev-framework.git .an_framework
rm -rf .an_framework/.git
```

Was die drei Zeilen tun:

- Zeile 1 wirft den alten Kern weg. Das ist ungefährlich: er enthält **nur** Framework-
  Dateien, nie etwas von dir. Alles, was dir gehört, liegt außerhalb.
- Zeile 2 holt den neuen Stand. GitLab fragt nach **Benutzername und Passwort** — deine
  normalen Firmen-Zugangsdaten. Beim Tippen des Passworts bewegt sich nichts, kein
  Sternchen. Das ist Absicht.
- Zeile 3 macht aus dem geklonten Framework gewöhnliche Dateien deines Projekts. Dadurch
  bringt später ein einfaches `git clone` bei allen anderen genau diese Version mit — kein
  Submodule, kein Sonderwissen.

**Prüfung:**

```sh
cat .an_framework/VERSION
```

Hier muss jetzt die neue Nummer stehen. Kommt `No such file or directory`, hat der Clone
nicht geklappt → *Wenn etwas schiefgeht*, Fall 1.

## Schritt 4 — Kurz hinsehen, was neu ist

```sh
git status --short .an_framework
```

Das listet dir die geänderten Kern-Dateien. Interessant sind vor allem drei Orte:

| Ort | Bedeutung |
|---|---|
| `.an_framework/skeleton/root/.claude/commands/` | Kam ein **Slash-Command** dazu? |
| `.an_framework/skeleton/root/.claude/settings.json` | Kamen **Permissions** dazu? |
| `.an_framework/skeleton/an_project/docs/` | Kam eine **Doku-Vorlage** dazu? |

Alles unter `skeleton/` wandert **nicht** von allein in dein Projekt — dafür ist der
nächste Schritt da.

## Schritt 5 — Die neuen Skelett-Dateien nachziehen

**Das `--update` ist Pflicht.** Ohne die Option lässt das Skript ein bereits eingerichtetes
Projekt bewusst in Ruhe und macht gar nichts.

```sh
bash .an_framework/init.sh --update
```

Es zeigt `Mode : UPDATE` und **ergänzt nur, was fehlt**: neue Command-Stubs, neue
Permissions (deine bestehenden werden mit den neuen vereinigt, nichts fällt weg), fehlende
Doku-Vorlagen. **Gefüllte Dateien von dir überschreibt es nie**, und einen Commit macht es
auch nicht.

Ob dein Projekt **slim** oder **full** ist, liest es aus `an_project/.framework-profile` —
du wirst nicht erneut gefragt, und ein Slim-Projekt bekommt keine schweren Doku-Dateien
untergeschoben.

Es schreibt einen Bericht nach `an_project/SETUP-REPORT.md`. **Lies ihn** — dort steht
namentlich, was dazugekommen ist und ob irgendwo ein Konflikt gemeldet wurde:

```sh
cat an_project/SETUP-REPORT.md
```

Meldet der Bericht einen **Command-Konflikt** (deine eigene Fassung eines Stubs weicht von
der des Frameworks ab), bleibt **deine** aktiv und die des Frameworks liegt als Kopie
daneben. Entscheide von Hand, welche du behalten willst.

## Schritt 6 — Prüfen, dass alles da ist

```sh
cat .an_framework/VERSION
ls -A .claude/commands
```

Die Versionsnummer ist die neue, und in `.claude/commands` liegt für **jeden** Command aus
`.an_framework/commands/` ein Stub. Fehlt einer, ist Schritt 5 nicht gelaufen.

Starte danach Claude einmal neu (`claude` in der Projektwurzel) und tippe `/` — die neuen
Commands müssen in der Liste auftauchen. Tun sie das nicht, hast du Claude vermutlich aus
einem **Unterordner** gestartet.

## Schritt 7 — Als eigenen Commit abschicken

Das Update ist ein **eigener** `chore(framework)`-Commit. Niemals zusammen mit
Projektarbeit — deshalb weigert sich `/commit` auch, ein gestagtes `.an_framework`
als Projektarbeit aufzunehmen.

`init.sh` hat dir den fertigen Befehl am Ende von Schritt 5 bereits ausgedruckt — nimm den.
Er lautet:

```sh
git add -- .an_framework .claude an_project
git commit -m "chore(framework): Framework auf 2.3.0"
```

`.claude` gehört mit hinein, weil Schritt 5 dort die neuen Stubs angelegt hat, `an_project`
wegen des Setup-Berichts und eventueller neuer Doku-Vorlagen. Beachte: **`git add` mit
ausdrücklichen Pfaden**, nie `git add -A` oder `git add .` — sonst rutscht unfertige
Projektarbeit mit in den Framework-Commit.

> Nur hier ist ein `git commit -m` in Ordnung: Der Text ist einzeilig, ohne Anführungs-
> oder Sonderzeichen, und stammt nicht aus einem Modell. Für Projektarbeit bleibt es bei
> `/commit`.

Zum Schluss hochladen, damit dein Team dieselbe Version bekommt:

```sh
git push
```

Ab jetzt holt sich jede Kollegin die neue Framework-Version mit einem gewöhnlichen
`git pull` — ohne Zusatzbefehl.

---

## Wenn etwas schiefgeht

**1 · `Could not find remote branch v… / Remote branch not found`**
Der Tag existiert nicht. Fast immer ein Tippfehler oder eine ausgedachte Nummer. Zurück zu
Schritt 1 und die Version wirklich aus GitLab holen. Steht `.an_framework` jetzt leer oder
gar nicht da: einfach Schritt 3 mit der richtigen Version wiederholen.

**2 · `fatal: destination path '.an_framework' already exists`**
Das `rm -rf .an_framework` aus Zeile 1 hat gefehlt oder ist fehlgeschlagen. Führe es
einzeln aus, dann den Clone erneut.

**3 · „Framework not initialised" beim Aufruf eines Commands**
Claude findet `.an_framework/` nicht. Zwei Ursachen: der Clone ist schiefgegangen
(Schritt 3 prüfen), oder du hast Claude aus einem **Unterordner** gestartet. Beende mit
`/exit` und starte neu in der Projektwurzel.

**4 · Ein Command fehlt in der `/`-Liste**
Schritt 5 wurde übersprungen oder ohne `--update` gelaufen. Meldet das Skript *„This
project already looks initialised … Nothing to do"*, hast du die Option vergessen:
`bash .an_framework/init.sh --update` nachholen und Claude neu starten.

**5 · Du willst zurück auf die alte Version**
Ein Rollback ist genau dasselbe Verfahren mit der **alten** Nummer: Schritt 3 mit dem
alten Tag, dann Schritt 7. Neue Skelett-Dateien, die `init.sh` bereits angelegt hat,
bleiben liegen — sie stören nicht, du kannst sie von Hand löschen.

**6 · Etwas an deinem Projekt wurde überschrieben**
Sollte nicht passieren — weder der Kern-Tausch noch `init.sh` fassen gefüllte
Projektdateien an. Falls doch: Du hast noch nichts committet, also holt
`git checkout -- <datei>` den letzten committeten Stand zurück.

---

## Was welche Version verlangt

Zusätzliche Handgriffe über die Schritte oben hinaus. Steht deine Zielversion nicht in der
Tabelle, sind keine nötig.

| Version | Zusätzlich zu tun |
|---|---|
| **v2.3** | Story- und Task-Nummern werden jetzt **pro Elternteil** vergeben statt projektweit durchgezählt. **Keine zusätzlichen Handgriffe, nichts wird umnummeriert** — deine vorhandenen IDs, Ordnernamen, Branch-Namen und `Ref:`-Zeilen bleiben unverändert gültig. Was du merken wirst: `/new-story` und `/new-task` vergeben ab jetzt **kleinere** Nummern als früher, und in der bisherigen Nummerierung bleiben Lücken stehen. Beides ist richtig so. Was du dir merken musst: Eine ID gilt nur noch **vollständig**. `001-001-0001` und `001-002-0001` sind zwei verschiedene Tasks — ein Vertipper in `depends_on` sieht dadurch gültig aus. Und verschieb kein Item mehr zwischen Epics: In der ID steckt jetzt sein Elternteil. Details in [how-to-use.md, „IDs verstehen"](how-to-use.md). |
| **v2.2** | Aus `/implement-task` wurde **`/implement`** — es nimmt jetzt Task-, Story- und Epic-IDs. Schritt 5 ist hier **Pflicht**, sonst fehlt der neue Stub und `/implement` existiert in deinem Projekt nicht. Dein alter Stub `.claude/commands/implement-task.md` bleibt liegen und funktioniert weiter (im Kern steht ein Alias). Du kannst ihn löschen, sobald sich alle an den neuen Namen gewöhnt haben. Neu ist außerdem `init.sh --update` selbst: vor v2.2 hat ein zweiter Lauf gar nichts getan. Kommst du von v2.1, benutze deshalb das `init.sh` aus dem **neuen** Kern — genau das tust du, wenn du Schritt 3 vor Schritt 5 machst. |
| **v2.1** | Neuer Command `/done`. Schritt 5 nötig, damit sein Stub ankommt. Projekte, die über Push + Merge Request integrieren, brauchen ihn nicht. |
| **v2.0** | Kein Update, sondern ein Umbau: das Framework war vorher ein **Git-Submodule** und ist jetzt ein mitcommitteter Ordner. Diese Anleitung passt dafür **nicht** — sprich das im Team ab, bevor du etwas tust. |
