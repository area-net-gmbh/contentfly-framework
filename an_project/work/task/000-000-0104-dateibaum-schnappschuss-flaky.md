---
id: 000-000-0104
title: FileIdAsPathApiTest vergleicht den ganzen data/-Baum und ist dadurch flaky
status: review
depends_on: []
---

# FileIdAsPathApiTest vergleicht den ganzen data/-Baum und ist dadurch flaky

## Context
Gefunden in der Pipeline von Pull Request #91 (`015-000-0013`): `test: PHP 8.4` war rot, `8.3`
und `8.5` grün — bei identischem Stand. Der Fehlschlag hat mit `015-000-0013` nichts zu tun.

```
1) Tests\Integration\Api\FileIdAsPathApiTest::testInsertRefusesAnIdThatIsAPath
   with data set "backslash" ('..\cache')
   Failed asserting that two arrays are identical.
```

Der Test stammt aus `015-000-0005` und ist von mir. Sein Helfer `dataTree()` listet **jeden** Pfad
unter `data/cache`, `data/import` und `data/temp` und vergleicht den Schnappschuss vor und nach dem
Aufruf, um zu belegen: „ausserhalb von `data/files` wurde nichts geschrieben".

Unter `data/cache` liegen aber Doctrines `proxies/`, `query/`, `metadata/` und `doctrine/` sowie
`login-throttle/`. Jede Anfrage kann dort beiläufig eine Datei anlegen — ein Proxy beim ersten
Zugriff auf eine Entity, ein Eintrag der Login-Drossel durch den Login in `createTestUser()`.
Der Schnappschuss ändert sich also aus Gründen, die den Test nichts angehen.

**Die Zusicherung ist richtig, die Messung ist zu weit gefasst.** Geprüft werden soll, dass an
*dem Pfad, auf den die ID zeigt*, nichts entsteht — nicht, dass sich im ganzen Baum nichts rührt.

## Acceptance criteria
- [x] `FileIdAsPathApiTest` prüft gezielt, dass der Pfad, auf den die abgelehnte ID zeigt, nicht existiert, statt den ganzen `data/`-Baum zu vergleichen.
- [x] Die Zusicherung bleibt inhaltlich dieselbe: Ein abgelehnter Upload bzw. Insert legt ausserhalb von `data/files` nichts an.
- [x] Der Test ist nachweislich stabil — mehrere Läufe hintereinander grün, auch wenn die Caches unter `data/cache` dazwischen wachsen.

## Verification
Den Test zehnmal hintereinander laufen lassen, dazwischen den Doctrine-Cache leeren, sodass der
nächste Lauf Proxies neu schreibt. Vor dem Fix wird mindestens ein Lauf rot, nach dem Fix keiner.
