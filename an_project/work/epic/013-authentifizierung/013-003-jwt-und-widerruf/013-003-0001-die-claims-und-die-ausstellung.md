---
id: 013-003-0001
title: Die Claims und die Ausstellung
status: todo
depends_on: []
---

# Die Claims und die Ausstellung

## Context
Seit `013-002-0003` **verifiziert** der `Tokenhandler` HS256-JWT: Er liest `sub`, den Ablauf
prüft die Bibliothek. Mehr steht nicht drin, weil es nichts auszustellen gab. Das ändert dieser
Task.

**Der Claim-Satz gehört festgelegt, nicht gewachsen.** Hinein kommt, was sich nicht ändert,
solange der Token gilt: Kennung, Ausgeber, Ausstellungs- und Ablaufzeitpunkt und eine
Token-Kennung (`jti`) für den späteren Widerruf. **Nicht hinein kommen Rollen, Gruppen und
Berechtigungen** — sonst wirkt eine Rechteänderung erst nach Ablauf, und genau das ist der
klassische Fehler beim Umstieg auf zustandslose Tokens.

**Ohne Anforderung ändert sich nichts.** Der Login gibt weiterhin ein opaques Token aus;
Bestandsclients merken nichts. Das JWT kommt nur, wenn der Aufrufer es verlangt.

**Das Refresh-Token ist eine `pim_token`-Zeile — und muss als solche erkennbar sein.** Der
opaque Zweig nimmt heute *jede* Zeile: Wer sein Refresh-Token als `appcms-token` vorzeigt,
bekäme damit die ganze API. Ein Refresh-Token soll aber genau eines dürfen — ein neues
Access-JWT holen. Die Zeile braucht deshalb einen Vermerk über ihren Zweck, und der opaque
Zweig muss sie als Zugangstoken abweisen.

## Acceptance criteria
- [ ] Der Claim-Satz steht an **einer** Stelle im Code benannt: `sub`, `iss`, `iat`, `exp`, `jti`. Rollen, Gruppen und Berechtigungen stehen nicht darin, und ein Test hält das fest.
- [ ] `POST /auth/login` liefert auf Anforderung ein Access-JWT **und** ein Refresh-Token; ohne Anforderung unverändert ein opaques Token.
- [ ] Der Handler prüft `iss` und `exp`. Ein Token mit fremdem Ausgeber wird abgewiesen — ununterscheidbar wie jeder andere Fehlschlag.
- [ ] Das Refresh-Token ist in `pim_token` an seinem Zweck erkennbar, und der opaque Zweig weist es als Zugangstoken ab. Ein Test zeigt es vor und erwartet die Abweisung.
- [ ] Die Lebensdauer des Access-JWT ist konfigurierbar; die Vorgabe liegt zwischen 5 und 15 Minuten.
- [ ] Ohne gesetztes `SECURITY_JWT_SECRET` wird die **Ausstellung** verweigert, mit einer Meldung, die dem Betreiber sagt, was fehlt. Dem Aufrufer sagt sie nichts Zusätzliches.

## Verification
Integrationstests am Login: ohne Schalter ein opaques Token, mit Schalter ein JWT plus
Refresh-Token; das JWT öffnet `/api/schema`, das Refresh-Token nicht. Unit-Tests für den
Claim-Satz und für die `iss`-Prüfung. Volle Suite.
