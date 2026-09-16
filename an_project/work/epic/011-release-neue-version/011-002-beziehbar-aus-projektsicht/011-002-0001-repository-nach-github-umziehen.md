---
id: 011-002-0001
title: Das Repository nach GitHub umziehen
status: todo
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
- [ ] `origin` zeigt auf `area-net-gmbh/contentfly-framework`; die vollständige Historie und alle Tags sind dort.
- [ ] Der Push ist ausdrücklich freigegeben worden — er ist der erste dieses Repositories nach aussen.
- [ ] Die Erwähnungen des alten Hosts im Baum sind nachgezogen (Doku, `tools/ci/*.sh`, `EnvironmentGuardTest`) oder als bewusst historisch stehen geblieben — Changelog- und Work-Item-Einträge beschreiben Vergangenes und werden **nicht** umgeschrieben.
- [ ] `architecture.md` trägt die Entscheidung unter *Key decisions*, mit dem Grund (externer Zugang) und der verworfenen Alternative (beides parallel).
- [ ] Das GitLab-Repository bleibt bestehen, bis `0002` einmal grün gelaufen ist; das Abschalten ist ausdrücklich ein Schritt des Menschen, nicht dieses Tasks.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Frischer `git clone` von der GitHub-URL in ein leeres Verzeichnis, `git log --oneline | wc -l` und
`git tag` gegen den lokalen Stand vergleichen. Danach `grep -rin gitlab` über den Baum: Jeder
verbleibende Treffer ist benannt und begründet.
