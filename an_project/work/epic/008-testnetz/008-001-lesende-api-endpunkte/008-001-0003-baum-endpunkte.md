---
id: 008-001-0003
title: Baum-Endpunkte /api/tree und /api/tree2
status: todo
depends_on: [008-001-0002]
---

# Baum-Endpunkte /api/tree und /api/tree2

## Context
Zwei Endpunkte liefern Baumstrukturen, auf zwei völlig verschiedenen Wegen — und keiner der
beiden ist getestet. `tree2` hat in `012-005-0003` zusätzlich sein Verhalten geändert; genau
solche frischen Änderungen wollen festgenagelt sein, bevor der Kernel darunter getauscht wird.

## Umfang

### `/api/tree`
Baut den Baum über die Entities selbst und hängt Kinder als `treeChilds` an — rekursiv, begrenzt
durch `DB_NESTED_LEVELS`. Der Request kann `properties` mitgeben und die Feldmenge einschränken.

Festzuhalten: Aufbau der Verschachtelung, Wirkung der `properties`-Einschränkung, Verhalten bei
einer Entity ohne Baum-Eigenschaft.

### `/api/tree2`
Geht einen anderen Weg: eine einzige SQL-Abfrage gegen `pim_tree` (bzw. `pim_i18n_tree`), danach
Sortierung im Speicher über `treeSort()`. Die Antwort trägt `childs` statt `treeChilds`, dazu
`parent` und `sorting`.

**Zwei Punkte aus `012-005-0003`, die hier ihren Regressionsschutz bekommen:**

1. **Die Spaltenauswahl kam früher aus `showInList`** — der Listenposition der gelöschten
   Oberfläche. Seit `012-005` liefert die Route **alle skalaren Eigenschaften**. Für Clients war
   das additiv: Jedes Feld, das vorher kam, kommt weiterhin. Ein Test hält genau das fest.
2. **Spaltennamen werden gequotet.** Ohne Quoting brach die Abfrage, sobald `groups` in der
   Auswahl landete — in MySQL 8 ein reserviertes Wort. Ein Baum-Abruf über eine Entity, die von
   `Base` erbt (und damit `users` und `groups` trägt), ist der Regressionstest dafür.

### Gemeinsam
Beide Endpunkte sind gesichert (`isSecure`) — der Abruf ohne Token gehört mit festgehalten.

## Acceptance criteria
- [ ] `/api/tree`: Erfolgsfall mit mindestens zwei Ebenen, die `properties`-Einschränkung und
      ein Fehlerfall sind festgehalten.
- [ ] `/api/tree2`: Erfolgsfall mit mindestens zwei Ebenen, `childs`/`parent`/`sorting` in ihrer
      heutigen Form.
- [ ] Ein Test belegt, dass `/api/tree2` alle skalaren Felder liefert — mit Verweis auf
      `012-005-0003` im Kommentar.
- [ ] Ein Test ruft `/api/tree2` über eine von `Base` erbende Entity ab und schützt damit das
      Quoting der Spaltennamen gegen einen Rückfall.
- [ ] Der Abruf ohne Token ist für beide Routen festgehalten.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Zur Gegenprobe für den Quoting-Test: Das Quoting in `Api::getTree2()` versuchsweise entfernen —
der Test muss rot werden. Danach zurücknehmen.
