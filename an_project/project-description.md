<!-- PURPOSE: Der Projekt-Charter — was dieses Projekt ist (Titel, Beschreibung, Ziel). Ausfüllen, dann /refine darauf laufen lassen. -->

# Contentfly Framework

## Scope
**Dieses Repo dient ausschließlich dem Update und der Migration des Contentfly-Frameworks
selbst.** Hier läuft kein Produktivbetrieb — es gibt nichts, was durch einen Umbau gestört
werden könnte. Breaking Changes, das Entfernen von `vendor/` aus Git oder das Abschalten von
Silex brauchen deshalb keine Rücksicht auf Ausfallzeiten.

**Die eine Randbedingung, die zählt:** Projekte, die das alte Contentfly einsetzen, müssen auf
die neue Framework-Version **migrierbar** sein. Nicht rückwärtskompatibel — migrierbar, auf
einem dokumentierten und möglichst werkzeuggestützten Weg.

## Description
Contentfly ist eine PHP/MySQL-Plattform mit CMS/PIM und Synchronisations-Schnittstelle für
Ionic-Apps. Der Unterbau ist stehen geblieben: **Silex 2 ist seit 2018 EOL** und deckelt die
Symfony-Komponenten auf 3.4 (EOL Nov 2020, nie für PHP 8 freigegeben), Doctrine ORM hängt auf
einem Dev-Branch, und `vendor/` liegt ohne `composer.json` in Git — eine Notlösung, um das
Framework ohne großes Update produktiv weiterbetreiben zu können.

Dieses Projekt holt das nach: **Silex wird durch einen Symfony-7.4-LTS-Kernel ersetzt**,
Abhängigkeiten kommen zurück unter Composer mit PHP 8.5 als Basis, der Entity-Layer wandert von
Doctrine-Annotationen auf PHP-Attribute (ORM 3), und Bestandsprojekte bekommen einen
beschriebenen Weg auf die neue Version.

## Goal
Fertig ist das Update, wenn:

- **Silex und Pimple aus dem Baum verschwunden sind** — keine Symfony-2/3-Komponente mehr, der
  Kernel ist Symfony 7.4 LTS.
- **Composer wieder die Quelle ist**: Root-`composer.json` + Lock, `vendor/` nicht mehr in Git,
  `composer audit --locked` sauber unter PHP 8.5.
- **Der Entity-Layer auf PHP-Attributen steht** und Doctrine auf einer releasten, gepflegten
  Version läuft — kein Dev-Branch-Pin, kein abandoned `doctrine/annotations`.
- **Das Testnetz grün ist**: Was das Framework vor dem Umbau tat, tut es danach ebenso.
- **Ein Bestandsprojekt migrieren kann** — Leitfaden, Werkzeuge und mindestens ein real
  durchgespielter Fall.
- **Die Vorlage `custom/` den Zielzustand zeigt** und als Referenz für neue Projekte taugt.

## Produktentscheidung: keine Oberfläche mehr
Die **PIM-CMS-Oberfläche wird komplett und ersatzlos gestrichen** — `lib/contentfly-ui`,
`UiController`, `InstallController`, `ExportController`, `UIManager` und die sessionbasierte
Admin-Auth. Contentfly ist danach **reine Datenhaltung plus Core-Funktionen**: Datenmodell,
Sync-API, Auth, Dateiablage, Console. Umgesetzt in Epic `012-000-0000`.

Das verkleinert den Umbau erheblich — und es ist zugleich der härteste Bruch für
Bestandsprojekte, die die Oberfläche heute nutzen. Wie sie damit umgehen, klärt Epic
`007-000-0000`.
