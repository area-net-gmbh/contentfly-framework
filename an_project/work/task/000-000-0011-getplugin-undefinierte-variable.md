---
id: 000-000-0011
title: PluginManager::getPlugin() referenziert eine undefinierte Variable
status: todo
depends_on: []
---

# PluginManager::getPlugin() referenziert eine undefinierte Variable

## Context
Beim Durchsehen der Manager-Schicht (`012-006-0002`) aufgefallen. Im Fehlerpfad von
`getPlugin()` steht:

```php
public function getPlugin($pluginName){
    if(!isset($this->plugins[$pluginName])){
        throw new ContentflyException(Messages::contentfly_general_unknown_plugin, $key);
    }

    return $this->plugins[$pluginName];
}
```

`$key` existiert in dieser Methode **nicht** — gemeint ist offensichtlich `$pluginName`.

## Warum das heute nicht auffällt
`getPlugin()` hat im gesamten Baum **keinen Aufrufer**. Der Fehlerpfad wird nie betreten. Ein
Bestandsprojekt, das die Methode nutzt, bekäme unter PHP 8 statt der gedachten
`ContentflyException` einen `Error: Undefined variable $key` — also eine andere Ausnahme mit
anderem Statuscode und ohne die vorgesehene Meldung.

## Acceptance criteria
- [ ] Der Fehlerpfad wirft die vorgesehene `ContentflyException` mit dem Plugin-Namen.
- [ ] Ein Test belegt es — die Plugin-Infrastruktur ist über `008-004` ohnehin abgedeckt.
- [ ] Geprüft, ob dieselbe Verwechslung anderswo vorkommt (`grep` über die
      `ContentflyException`-Aufrufe).

## Verification
Ein Aufruf von `getPlugin()` mit unbekanntem Namen liefert die `ContentflyException`, nicht
einen `Error`.
