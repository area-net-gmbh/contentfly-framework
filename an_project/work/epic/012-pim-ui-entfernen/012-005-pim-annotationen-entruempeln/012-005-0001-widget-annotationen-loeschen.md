---
id: 012-005-0001
title: Widget-Annotationen löschen
status: review
depends_on: []
---

# Widget-Annotationen löschen

## Context
Zwölf der fünfzehn Klassen in `Classes/Annotations/` beschreiben Formularelemente — `Rte` trägt eine TinyMCE-Toolbar-Zeile. Ohne Oberfläche haben sie keinen Zweck.

## Acceptance criteria
- [x] `Rte`, `Checkbox`, `Radio`, `Select`, `Textarea`, `MatrixChooser`, `Datetime`, `Time`, `Password`, `EntitySelector`, `Virtualjoin` sind je einzeln geprüft und gelöscht, sofern nur UI daran hängt.
- [x] `Permissions` und `I18nPermissions` bleiben — sie tragen Berechtigungen, also Datenverhalten.
- [x] Wo eine der gelöschten Annotationen doch Datenverhalten trug, ist das benannt und der Anteil erhalten geblieben.
- [x] Die Entities des Frameworks und der Vorlage sind entsprechend bereinigt.

## Ergebnis der Einzelprüfung

Die Annahme der Story — „zwölf beschreiben reine Formularelemente" — hält nur zur Hälfte.
Eine Annotation wählt über `doMatch()` die zuständige **Type-Klasse** aus, und die trägt
`fromDatabase()`/`toDatabase()`. Wer die Annotation löscht, ändert damit, wie Daten gelesen
und geschrieben werden. Sieben sind gegangen, vier bleiben.

### Gelöscht (7) — reines UI

| Annotation | Warum gefahrlos |
|---|---|
| `Rte` | trug nur `toolbar`/`extend` (TinyMCE). Keine Entity nutzte sie; `RteType` mit gelöscht — text-Spalten fängt `TextareaType` ab, inklusive `encoded`. |
| `Textarea` | trug nur `lines`. `TextareaType` **bleibt** — er matcht ohnehin über `@ORM\Column(type="text")` und trägt die `encoded`-Verschlüsselung. |
| `Datetime` | trug nur `format`. `DatetimeType::fromDatabase()` benutzt es nicht — die Ausgabe ist fest (`LOCAL`, `ISO8601`, `TIMESTAMP`). Type matcht über den Spaltentyp. |
| `Time` | trug `format`, und `TimeType::fromDatabase()` **liest es** → als `TimeType::DEFAULT_FORMAT` erhalten. Keine Entity nutzte die Annotation, Ausgabe unverändert. |
| `Password` | `PasswordType` setzte nur `dbtype='string'`; kein Hashing, kein Ausgabe-Schutz hing daran. `pass` fällt auf `StringType` — identisches Verhalten. |
| `MatrixChooser` | ohne Type-Klasse, ohne Entity — nur ein toter `use`-Import im `ApiController` und eine `registerFile()`-Zeile in `bootstrap.php`. |
| `EntitySelector` | reiner Marker ohne `processSchema`. `NavItem::$entity` fällt auf `StringType` — identisches Verhalten. |

### Behalten (4) — tragen Datenverhalten

| Annotation | Datenanteil |
|---|---|
| `Checkbox` | `CheckboxType` löst die ManyToMany-Collection auf, filtert nach Leseberechtigung und **legt OptionGroup-Zeilen an**; `group` benennt sie. `Api.php` verzweigt an zwei Stellen auf `type == 'checkbox'`. |
| `Radio` | dito für ManyToOne, ebenfalls mit OptionGroup und Permission-Prüfung. |
| `Select` | `options` ist der Wertebereich (Datenvertrag, genutzt in `Group`, `Log`, `Example`); `SelectType::toDatabase()` castet abhängig davon nach `integer`. |
| `Virtualjoin` | `targetEntity` definiert den Join; trägt `users`/`groups` in `Base` — das ist das Permission-Sharing, nicht Darstellung. |

Aus den vier behaltenen sind die reinen Darstellungsfelder entfernt: `Checkbox.horizontalAlignment`,
`Checkbox.columns`, `Radio.horizontalAlignment`, `Radio.columns`, `Radio.select` — samt der
Schema-Keys, die sie erzeugt haben.

## Verification
`grep -rn "@PIM\\\\Rte\|@PIM\\\\Checkbox" lib custom` liefert keine Treffer. Anwendung bootet, Schema wird erzeugt.

- [x] Grep über `lib` und `custom`: keine Treffer.
- [x] `php bin/console.php list` bootet (die `registerFile()`-Zeile für `MatrixChooser` in
      `bootstrap.php` war dabei der einzige Nachzügler und ist entfernt).
- [x] Alle 20 registrierten Type-Klassen existieren und finden ihre Annotationsdatei; der
      Doctrine-AnnotationReader liest alle 22 Entity-Klassen fehlerfrei. Verbliebene
      `@PIM`-Annotationen: `Config`, `Select`, `Virtualjoin`, `Permissions`, `I18nPermissions`.
- [x] Installation gegen die Dev-Datenbank durchgelaufen, `GET /api/schema` liefert HTTP 200
      mit 14 Entities. `File.description` hat kein `lines` mehr, `User.pass` steht auf
      `type: string` statt `password` — beides ohne Leser, also ohne Verhaltensänderung.
- [x] Testsuite grün: 26 Tests, 45 Assertions (6 Unit, 20 Integration).
