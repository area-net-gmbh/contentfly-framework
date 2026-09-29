---
id: 000-000-0102
title: StringType liest '0' als leer und speichert einen leeren String
status: done
depends_on: []
---

# StringType liest '0' als leer und speichert einen leeren String

## Context
Gefunden beim Umsetzen von `015-000-0001`, dort aber nicht behoben: Der Befund liegt ausserhalb
der Akzeptanzkriterien jenes Tasks und trifft **jedes** String-Feld, nicht nur `pass`.

`StringType::toDatabase()` (`Classes/Types/StringType.php:53`) beginnt mit

```php
if(empty($value)){
    $object->$setter('');
    return;
}
```

`empty()` ist in PHP für `'0'` **wahr**. Wer `{"data":{"title":"0"}}` schickt, bekommt deshalb
nicht `'0'` gespeichert, sondern `''`. Dasselbe gilt für `'0'` als Artikelnummer, Hausnummer,
Etage, Zählerstand oder Sortierwert — überall dort, wo eine Null als Zeichenkette ein gültiger
Inhalt ist. Die Feldverschlüsselung ist ebenfalls betroffen: Der `encoded`-Zweig wird für `'0'`
nie erreicht.

Die Stelle ist alt und bis `015-000-0001` nie aufgefallen, weil die Lücke dort über `pass` lief.
Die Passwort-Wege sind inzwischen an `User::setPass()` abgefangen; für gewöhnliche String-Felder
ist der stille Datenverlust geblieben.

**Der Umfang ist zu prüfen, nicht zu raten.** `TextareaType` und die übrigen Typen benutzen
dasselbe Muster möglicherweise ebenfalls; ob eine Änderung dort dieselbe ist oder eine andere,
gehört in die Umsetzung.

## Acceptance criteria
- [x] `StringType::toDatabase()` unterscheidet einen leeren Wert (`null`, `''`, `[]`) von `'0'`; `'0'` wird als `'0'` gespeichert.
- [x] Der `encoded`-Zweig (Feldverschlüsselung) wird für `'0'` ebenfalls erreicht.
- [x] Die übrigen `Classes/Types/*` sind auf dasselbe `empty()`-Muster durchgesehen; jede Fundstelle ist entweder mitbehoben oder mit Begründung stehengelassen.
- [x] Tests belegen `'0'` beim Schreiben und Zurücklesen — unverschlüsselt und verschlüsselt —, und dass `null`, `''` und `[]` weiterhin als leer ankommen.
- [x] Ist das Verhalten für Bestandsprojekte sichtbar, trägt `breaking-changes.md` einen Eintrag und `migration.md` die fortgeschriebene Zahl.

## Verification
`POST /api/insert` mit `'0'` in einem gewöhnlichen String-Feld, danach `/api/single`: Vor dem Fix
kommt `''` zurück, danach `'0'`. Dasselbe für ein Feld mit `encoded`. Gegenprobe gegen den alten
Code rot. Volle Suite grün, PHPStan ohne Fehler.
