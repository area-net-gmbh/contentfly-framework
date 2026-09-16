---
id: 011-002-0001
title: Das Repository nach GitHub umziehen
status: review
depends_on: []
---

# Das Repository nach GitHub umziehen

## Context
Das Quell-Repository liegt auf dem internen `gitlab.in.area-net.de` und ist damit nur aus dem
Firmennetz erreichbar. Für eine spätere Security- oder Codebase-Prüfung durch eine externe
Corporation braucht es aber einen Zugang, der sich **vergeben und wieder entziehen** lässt — ein
internes GitLab kann das nur über VPN-Konten oder Netzwerkausnahmen.

**Entschieden am 2026-09-16: eine Quelle, und die liegt auf GitHub** —
`https://github.com/area-net-gmbh/contentfly-framework.git`, privat im Company-Account. Das
GitLab-Repository wird danach abgeschaltet.

Dieser Task ist die Voraussetzung für alles Weitere in dieser Story: Ohne den Umzug gibt es keine
Actions-Pipeline (`0002`), keinen Split (`0003`) und kein Gate (`0004`).

## Acceptance criteria
- [x] `origin` zeigt auf `area-net-gmbh/contentfly-framework`; die vollständige Historie und alle Tags sind dort.
- [x] Der Push ist ausdrücklich freigegeben worden — er ist der erste dieses Repositories nach aussen.
- [x] Die Erwähnungen des alten Hosts sind gesichtet und je Datei entschieden: nachgezogen, bewusst historisch stehen gelassen, oder an `0002` abgegeben. Changelog- und Work-Item-Einträge beschreiben Vergangenes und werden **nicht** umgeschrieben; `.an_framework/` ist zur Laufzeit schreibgeschützt und wird nicht angefasst.
- [x] **Abgegrenzt gegen `0002`:** Was `.gitlab-ci.yml` beim Namen nennt (`tools/ci/*.sh`, `EnvironmentGuardTest`, `phpstan.neon.dist`, `technical.md`, `tests/README.md`), bleibt hier unberührt — es lässt sich erst richtig schreiben, wenn die Workflows existieren, und würde sonst zweimal geraten.
- [x] `architecture.md` trägt die Entscheidung unter *Key decisions*, mit dem Grund (externer Zugang) und der verworfenen Alternative (beides parallel).
- [x] Das GitLab-Repository bleibt bestehen, bis `0002` einmal grün gelaufen ist; das Abschalten ist ausdrücklich ein Schritt des Menschen, nicht dieses Tasks.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Frischer `git clone` von der GitHub-URL in ein leeres Verzeichnis, `git log --oneline | wc -l` und
`git tag` gegen den lokalen Stand vergleichen. Danach `grep -rin gitlab` über den Baum: Jeder
verbleibende Treffer ist benannt und begründet.

## Ergebnis

**Das Repository liegt auf GitHub, die Historie ist vollständig oben.**

| | |
|---|---|
| `origin` | `git@github.com:area-net-gmbh/contentfly-framework.git` |
| Commits | **534 remote, 534 lokal** |
| Tags | `v2.0.0-pre-security-2026-09-11`, `-14` |
| `gitlab` | das alte Remote, **umbenannt statt entfernt** |

Das alte Remote bleibt unter dem Namen `gitlab` stehen, bis `0002` grün ist: Solange die
Actions-Pipeline nicht gelaufen ist, ist das GitLab die einzige Stelle, an der die Prüfungen
nachweislich fahren. Es zu löschen ist ein Griff des Menschen, kein Schritt dieses Tasks.

### Der Zugang hat drei Anläufe gebraucht, und der Grund gehört ins Protokoll

Der erste Versuch scheiterte mit `Repository not found` — bei GitHub die Antwort auf ein privates
Repo **ohne Zugriffsrecht**, ununterscheidbar von „gibt es nicht". Die Gegenprobe gegen ein
öffentliches Repo (`git/git`) lieferte Refs, der Transportweg war also in Ordnung; es lag an der
Identität.

Alle drei Schlüssel auf dieser Maschine meldeten sich als `floksSchm`. Ein öffentlicher Schlüssel
darf bei GitHub nur an **einem** Konto hängen, also liess sich keiner davon bei
`areanet-foschmid` eintragen — es brauchte ein eigenes Paar.

**Und dann meldete sich auch das neue noch als `floksSchm`.** Die Ursache stand nicht im Schlüssel,
sondern im Agenten: `~/.ssh/config` hängt für `Host *` zusätzlich `id_rsa` an, und der Agent bot
seine Schlüssel zuerst an. Sichtbar wurde es erst mit `ssh -v`:

```
Server accepts key: …/id_ed25519_areanet
Hi areanet-foschmid
```

Verdrahtet ist er deshalb mit `IdentityAgent=none`, und zwar **nur in diesem Klon**:

```
core.sshCommand = ssh -i ~/.ssh/id_ed25519_areanet -o IdentitiesOnly=yes -o IdentityAgent=none
```

Nicht über `~/.ssh/config`, aus zwei Gründen: Der Zugang des anderen Kontos zu allen übrigen Repos
bleibt unangetastet, und `origin` behält die **echte** URL. Ein Host-Alias wie `git@github-areanet:…`
müsste jeder andere auf seinem Rechner nachbauen, und in der Doku stünde eine URL, die nirgends
sonst funktioniert.

### Was nachgezogen wurde — und was bewusst nicht

Von 133 Treffern auf „gitlab" im Baum sind die meisten **historisch** und bleiben: `CHANGELOG.md`,
die Work-Items der Epics `006`–`014` und `abhaengigkeiten-inventar.md` (eine als solche
gekennzeichnete Momentaufnahme vom 2026-09-08). Sie beschreiben, was damals galt; sie umzuschreiben
hiesse, die Historie zu fälschen. `.an_framework/` ist zur Laufzeit schreibgeschützt und wurde
nicht angefasst.

Geändert wurde, was **jetzt falsch** war:

| Datei | was |
|---|---|
| `architecture.md` | neue *Key decision* mit Grund, verworfener Alternative (beide Remotes) und den zwei geprüften Registry-Fakten |
| `deployment.md` | neuer Abschnitt *Wo das Repository liegt*; die Zeile „GitLab CI, weil der Remote GitLab ist" nennt ihren Satz jetzt als vergangen |
| `README.md` | **die Klon-URL zeigte auf `area-net-gmbh/contentfly-cms`** — Contentfly 1.x. Wer diesem Repository folgte und den Link klonte, bekam stillschweigend das falsche Produkt. |

An `0002` abgegeben ist alles, was `.gitlab-ci.yml` beim Namen nennt — `tools/ci/*.sh`,
`EnvironmentGuardTest`, `phpstan.neon.dist`, `technical.md`, `tests/README.md`,
`tools/language/find-old-names.py`. Das lässt sich erst richtig schreiben, wenn die Workflows
existieren; jetzt wäre es zweimal geraten.

**Verifiziert:** volle Suite `Tests: 615, Assertions: 2506, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0. Gegenprobe des Umzugs: `git ls-remote origin` zeigt `master` auf demselben
Commit wie lokal, und beide Tags sind da.
