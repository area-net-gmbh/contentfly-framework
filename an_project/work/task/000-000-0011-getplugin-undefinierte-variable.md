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

## Was tatsächlich passiert — gemessen in `008-004-0002`
Meine erste Einschätzung („unter PHP 8 ein `Error`") war **falsch**. Eine undefinierte Variable
ist in PHP 8 eine **Warning**, kein Fehler; der Ausdruck ergibt `null`.

Die `ContentflyException` wird also geworfen wie vorgesehen — aber **ohne den Namen des
gesuchten Plugins**. Wer den Fehler untersucht, erfährt nicht, wonach gesucht wurde. Dazu
kommt eine PHP-Warning ins Log, die je nach Konfiguration den Ablauf abbricht: Die Testsuite
setzt `failOnWarning`, ein Test muss sie eigens abfangen.

Der Charakterisierungstest
`PluginManagerTest::testGetPluginVerliertDenPluginNamenAusDerFehlermeldung()` hält beides
fest — die Ausnahme **und** die Warning.

## Warum das heute nicht auffällt
`getPlugin()` hat im gesamten Baum **keinen Aufrufer**. Der Fehlerpfad wird nie betreten.

## Acceptance criteria
- [ ] Der Fehlerpfad wirft die vorgesehene `ContentflyException` **mit** dem Plugin-Namen und **ohne** PHP-Warning.
- [ ] Der Charakterisierungstest aus `008-004-0002` ist auf das neue Verhalten gedreht — bewusst, nicht durch Löschen.
- [ ] Geprüft, ob dieselbe Verwechslung anderswo vorkommt (`grep` über die
      `ContentflyException`-Aufrufe).

## Verification
Ein Aufruf von `getPlugin()` mit unbekanntem Namen liefert die `ContentflyException` mit dem
Namen im Parameter, und der Testlauf meldet **keine** Warning mehr — die Suite setzt
`failOnWarning`, das ist also unmittelbar sichtbar.
