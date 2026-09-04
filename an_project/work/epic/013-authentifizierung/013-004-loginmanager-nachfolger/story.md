---
id: 013-004-0000
title: Nachfolger des LoginManagers
status: todo
depends_on: [013-002-0000]
---

# Nachfolger des LoginManagers

## Goal
Die Grundidee des `LoginManager` bleibt und bekommt einen tragfähigen Unterbau: **Das Framework
stellt Vertrag, Benutzer-Provisionierung und Rollenabbildung; wie geprüft wird, programmiert das
Projekt.** Genau so ist es gedacht, und genau so soll es bleiben — nur ohne die Konstruktionsfehler
der heutigen Umsetzung.

## Was heute steht und was daran nicht trägt

`Areanet\PIM\Classes\Manager\LoginManager` ist abstrakt mit genau einer Pflichtmethode `auth()`
und dem Helfer `createManagedUser($alias, $group, $isAdmin)`. Das reicht für einen einfachen Fall
und endet dort auch:

| Heute | Problem |
|---|---|
| Klassenname kommt als Request-Parameter `loginManager` und wird zu `Custom\Classes\<Name>` aufgelöst | Der Client bestimmt, welche Klasse instanziiert wird. Der Präfix und die `instanceof`-Prüfung begrenzen den Schaden, aber die Auswahl gehört nicht in die Hand des Aufrufers |
| `createManagedUser()` setzt `setPass($alias)` — **das Passwort ist der Benutzername** | Befund A-6. Heute durch den Riegel „nur über LoginManager authorisierbar" entschärft; jeder Pfad, der ihn umgeht, ist eine triviale Kontoübernahme |
| `alias` wird zu `md5(Klassenname)-alias` verfremdet | Benutzer sind nicht mehr wiedererkennbar; die Kennung des Fremdsystems geht verloren |
| Nur `auth()` — kein Logout, keine Claims, keine Rollenabbildung | Alles, was über „gibt einen User zurück" hinausgeht, muss jedes Projekt selbst erfinden |

## Umfang

- **Vertrag neu fassen:** Was ein Projekt implementieren muss (Prüfung gegen das Fremdsystem) und
  was das Framework beisteuert (Benutzer finden oder anlegen, Gruppen und Adminflag setzen,
  Token ausstellen, abmelden).
- **Auswahl über eine Allowlist** registrierter Provider statt über einen Klassennamen aus dem
  Request.
- **JIT-Provisioning ohne setzbares Passwort:** Ein über ein Fremdsystem angelegter Benutzer
  bekommt einen unbrauchbaren Passwort-Hash, nicht seinen eigenen Namen. Die Kennung des
  Fremdsystems wird nachvollziehbar gespeichert statt in einen MD5-Präfix gepackt.
- **Rollen- und Gruppenabbildung** als Teil des Vertrags: Was das Fremdsystem liefert (Gruppen,
  Attribute), wird auf Contentfly-Gruppen abgebildet — an einer Stelle, nicht in jedem Projekt neu.
- **Auf Symfony aufsetzen, nicht danebenbauen:** Ein Projekt-Provider wird über
  `UserProviderInterface` und, wo nötig, einen eigenen Authenticator eingehängt. Mehrere
  Authenticator auf einem Firewall sind vorgesehen (`custom_authenticators`); sobald mehrere einen
  Einstiegspunkt anbieten, muss genau einer als `entry_point` benannt werden — für einen
  zustandslosen API-Firewall stellt sich die Frage in der Regel nicht.
- **Die Beispiel-Vorlage** in `custom/` zeigt einen Provider, der wirklich läuft.

## Fertig, wenn
- Ein Projekt kann einen eigenen Provider registrieren und sich damit anmelden, ohne
  Framework-Code zu ändern.
- Kein über ein Fremdsystem angelegter Benutzer hat ein erratbares Passwort (Befund A-6 ist
  geschlossen).
- Der Klassenname aus dem Request wählt keine Klasse mehr aus.
- Gruppen und Adminflag kommen aus der Abbildung, nicht aus Zufall.
- Der Bruch gegenüber der alten `LoginManager`-Schnittstelle ist für Epic `007` beschrieben —
  inklusive der Frage, wie ein Bestandsprojekt seinen vorhandenen Manager überführt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
