---
id: 008-003-0005
title: Master-Passwort und die nicht durchgesetzten Rechte
status: done
depends_on: [008-003-0001]
---

# Master-Passwort und die nicht durchgesetzten Rechte

## Context
Zwei Dinge, die das Testnetz festhalten soll, obwohl beide auf dem Weg nach draußen sind: eine
Hintertür, die `013-001` entfernt, und zwei Berechtigungsfelder, die veröffentlicht, aber
nirgends durchgesetzt werden.

## Umfang

### A — Das Master-Passwort
`AuthController` prüft:

```php
$globalPass = Adapter::getConfig()->APP_MASTER_PASSWORD;

if($globalPass){
    if(!$user->isPass($request->get('pass')) && $globalPass != $request->get('pass')){
        return new JsonResponse(array('message' => '…'), 401);
    }
}else{
    if(!$user->isPass($request->get('pass'))){ … }
}
```

Ist `APP_MASTER_PASSWORD` gesetzt, genügt es **für jeden Benutzer**. Story `013-001` entfernt
das ersatzlos, mit der Begründung: „Ein Schalter, der Vollzugriff gewährt, ist auch
ausgeschaltet eine Hintertür."

Standardwert ist `null`, der Pfad ist also heute inaktiv. Prüfbar ist damit die
**Vorbedingung**, nicht die Wirkung — ein Integrationstest kann die Konfiguration zur Laufzeit
nicht ändern.

Festzuhalten:
- `APP_MASTER_PASSWORD` ist in der Vorlage nicht gesetzt.
- Die Anmeldung mit falschem Passwort scheitert (bereits durch `AuthApiTest` belegt) — hier
  kommt der Verweis dazu, dass dieses Verhalten *nur* deshalb gilt, weil kein Master-Passwort
  gesetzt ist.

Der Test bekommt einen Kommentar, der auf `013-001` zeigt, damit er dort **bewusst umgedreht**
wird — und nicht verwundert gelöscht.

### B — `canExport` und `getExtended`: veröffentlicht, nicht durchgesetzt
Beide erscheinen im `permissions`-Block des Schemas (`Api.php:1369-1370` und `1451-1452`) und
werden **an keiner Stelle geprüft**:

| Feld | wo es hingehörte |
|---|---|
| `canExport` | Der Konsument war der `ExportController` — mit `012-001-0003` gelöscht. |
| `getExtended` | Nie ein Durchsetzungspunkt im Framework; die JSON-Struktur war für die Maske gedacht. |

Damit stehen sie auf derselben Stufe wie das in `012-005-0002` gestrichene `readonly`: im
Schema veröffentlicht, im Verhalten wirkungslos. Der Unterschied ist, dass sie eine
**Datenbankspalte** haben (`pim_permission.export`, `pim_permission.extended`) und dass ein
Client sie aus dem Schema lesen und selbst anwenden könnte.

Festzuhalten ist beides: dass die Werte im Schema ankommen, **und** dass sie das Verhalten der
API nicht ändern.

> Das sind der fünfte und sechste Fall dieser Art in Epic `008` — nach `excludeFromSync`,
> `i18n_universal`, `encoded` und der OneJoin-Kaskade. Die Häufung gehört in die
> Zusammenfassung der Story: Das Framework trägt eine ganze Reihe von Feldern, deren einzige
> Nutzer die gelöschte Oberfläche waren.

## Acceptance criteria
- [x] Ein Test belegt, dass `APP_MASTER_PASSWORD` in der Vorlage nicht gesetzt ist, und
      verweist im Kommentar auf `013-001`.
- [x] Ein Test belegt, dass `canExport` und `getExtended` im Schema ankommen.
- [x] Ein Test belegt, dass ein Benutzer **ohne** `export`-Recht trotzdem alles kann, was die
      API anbietet — die Wirkungslosigkeit ist damit gemessen, nicht behauptet.
- [x] Die Häufung der veröffentlichten, aber nicht durchgesetzten Felder ist in der
      Zusammenfassung der Story benannt.

## Verification
Mehrere vollständige Läufe. Der Nachweis der Wirkungslosigkeit läuft über einen Benutzer, dem
`export` auf `0` steht und der dennoch lesen, schreiben und löschen kann — soweit die anderen
Rechte es zulassen.

## Ergebnis — 7 Tests in `tests/Integration/Api/UnenforcedPermissionApiTest.php`

Gesamtsuite: **154 Tests, 366 Assertions**, vier Läufe grün.

### Das Master-Passwort
Der Standardwert ist `null` — die Hintertür ist zu, aber vorhanden. Ein Integrationstest kann
die Konfiguration zur Laufzeit nicht ändern; geprüft ist deshalb die **Vorbedingung**, und der
zweite Test hält fest, dass die falsche Anmeldung *nur deshalb* scheitert. Beide Kommentare
verweisen auf `013-001`, damit die Tests dort **umgedreht** und nicht verwundert gelöscht
werden.

### `canExport` und `getExtended`: veröffentlicht, wirkungslos
Belegt in beide Richtungen: Die Werte kommen im `permissions`-Block des Schemas an — auch für
einen Nicht-Admin und mit dekodiertem `extended`-JSON —, **und** ein Benutzer mit `export = 0`
kann trotzdem lesen, schreiben und löschen. `extended` schränkt die Antwort nicht ein; das
volle Objekt kommt zurück.

### Ein Befund, der beim Schreiben auffiel: `canExport` folgt eigenen Regeln
`readable`, `writable` und `deletable` laufen über `Permission::is()` und melden ihren
Stufenwert als Integer. **`canExport` hat eine eigene Implementierung** und kollabiert die vier
Stufen auf ein Boolean:

```php
return ($permission->getExport() == 2);
```

Die Spalte ist derselbe Integer mit derselben Vierstufen-Semantik, aber nur `ALL` (2) gilt als
erlaubt. Und weil die Konstanten **nicht aufsteigend geordnet** sind, ergibt ausgerechnet
`GROUP` (3) ein `false` — wer „mehr als ALL" meint, sperrt sich aus. Beide Fälle sind als Test
festgehalten.

## Verification
- [x] Vier vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Die Wirkungslosigkeit ist **gemessen**, nicht behauptet: ein Benutzer mit `export = 0`
      führt Lesen, Schreiben und Löschen erfolgreich aus.
- [x] Datenbank nach den Läufen vollständig auf dem Ausgangsstand.
