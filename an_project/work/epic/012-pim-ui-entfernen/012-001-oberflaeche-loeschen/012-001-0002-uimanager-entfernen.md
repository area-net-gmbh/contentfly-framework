---
id: 012-001-0002
title: UIManager entfernen
status: review
depends_on: [012-001-0001]
---

# UIManager entfernen

## Context
Der `UIManager` sammelt JS-/CSS-Dateien, Angular-Module und UI-Routen für die Oberfläche. Sein einziger Verbraucher war der `UiController`.

## Acceptance criteria
- [x] `lib/contentfly/Classes/Manager/UIManager.php` ist gelöscht.
- [x] Die Factory `$app['uiManager']` und der `use`-Import in `lib/contentfly/bootstrap.php` sind entfernt.
- [x] Verwendungen in `PluginManager` und anderswo sind aufgespürt und mit entfernt — was Plugins an UI registriert hat, entfällt.
- [x] Kein Verweis auf `UIManager` oder `uiManager` mehr im Baum.

## Verification
`grep -rn "UIManager\|uiManager" lib custom` liefert keine Treffer. Anwendung bootet.
