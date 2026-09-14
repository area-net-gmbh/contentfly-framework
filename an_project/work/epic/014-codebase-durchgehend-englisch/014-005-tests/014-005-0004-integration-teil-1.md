---
id: 014-005-0004
title: Integrationstests Teil 1 auf Englisch
status: review
depends_on: [014-005-0003]
---

# Integrationstests Teil 1 auf Englisch

## Context
Umfang: die grossen und die Security-nahen Integrationstests:
`AuthApiTest`, `SystemControllerApiTest`, `ContainerSchluesselTest`, `VorlageApiTest`,
`AnmeldeproviderApiTest`, `AnmeldebremseApiTest`, `ProviderSyncApiTest`, `LoginManagerApiTest`,
`FehlerantwortApiTest` und `tests/Integration/Command/ReencryptCommandTest.php`.

Deutsch benannte Klassen bekommen englische Namen.

## Acceptance criteria
- [x] Die Dateien des Tasks sind vollständig englisch: Klassen-, Methoden- und Helfernamen, Variablen, Assertion-Meldungen, Strings und Kommentare. Testklassen, deren Name deutsch ist, sind samt Datei umbenannt.
- [x] Die Prüfungen sind unverändert: Je Datei sind Test- und Assertion-Zahl vor und nach dem Task gleich. Die Ergebnis-Notiz enthält die Liste alt → neu aller umbenannten Testmethoden und -klassen.
- [x] Werte, die ein Test gegen Framework-Code oder gegen die Datenbank prüft (Quelltextzeilen, Meldungen, Tabellennamen), stimmen weiterhin mit dem Framework überein.
- [x] Alle Verweise auf umbenannte Tests und Helfer im Baum sind mitgezogen (`lib`, `custom`, `tests`, `tools`, `phpunit.xml.dist`, `rector.php`).
- [x] Volle Suite mit gleicher Gesamtzahl an Tests und Assertions, PHPStan `[OK]`, Deprecation-Gate grün.

## Verification
Vor dem Task: je Datei `phpunit <datei>` (Integrationstests gegen den laufenden Testserver) mit
Test- und Assertion-Zahl notieren. Danach dieselben Läufe, Zahlen vergleichen. Volle Suite, PHPStan
(Result-Cache geleert), Deprecation-Gate, Detektor des Sprachwächters über die Dateien des Tasks.

## Ergebnis

**Die grossen und die Security-nahen Integrationstests sind englisch.** Das sind zehn Dateien, fünf
Testklassen samt Datei umbenannt:

| alt | neu |
|---|---|
| `AnmeldeproviderApiTest` | `LoginProviderApiTest` |
| `AnmeldebremseApiTest` | `LoginThrottleApiTest` |
| `FehlerantwortApiTest` | `ErrorResponseApiTest` |
| `ContainerSchluesselTest` | `ContainerKeysTest` |
| `VorlageApiTest` | `TemplateApiTest` |

Vier Agenten haben übersetzt, parallel zu drei weiteren für `014-005-0005`. Damit die Tests sich
gegenseitig richtig nennen, waren die neuen Klassennamen vorab festgelegt. Ohne Testserver konnten
die Agenten nur Tests zählen (`--list-tests`) und die Assert-Aufrufe vergleichen. Die
Assertion-Zahl je Datei ist erst mit der vollen Suite gegen den Server nachgewiesen.

**Eine Falle, die ein Agent selbst gefunden hat:** Der Helfer für die Anmeldung über den Provider hiess
zuerst `logIn()`. PHP unterscheidet bei Methodennamen nicht nach Gross- und Kleinschreibung. Der
Name kollidierte deshalb mit `login()` aus `IntegrationTestCase`; als `protected` hätte er die
Methode der Basisklasse still ersetzt. Er heisst jetzt `loginThroughProvider()`.

**Bewusst deutsch geblieben:** die Altwerte `'Erstellt'` und `'Gelöscht'`, die Contentfly 1.x in
`pim_log.mode` schrieb, in einem Kommentar von `SystemControllerApiTest`. Dazu kommen Zitate früherer
Testnamen in Kommentaren („The test was called …"). Ob diese Zitate bleiben, entscheidet
`014-005-0006`, wenn der Sprachwächter `tests/` übernimmt.

**Nachgezogen:**
- Verweise auf die umbenannten Klassen: `lib/contentfly/bootstrap-web.php` und
  `Classes/Kernel/Application.php` (`ErrorResponseApiTest`, beide Ausnahmen des Sprachwächters
  entfernt), `IntegrationTestCase`, `LoginThrottleTest` und `OidcProviderTest` sowie `technical.md`
  und `dev-guide.md`.
- Ein Kommentar in `SelectType.php` nannte den Testwert `gibtsnicht`, der jetzt `doesnotexist`
  heisst.
- `ReencryptCommandTest` legt seine Probetabelle selbst an. Die Spalte darin heisst jetzt `secret`
  statt `geheim`.

**Umbenannt** (gepaart über die Deklarationsreihenfolge vor und nach dem Task):

**`LoginThrottleApiTest`** (vorher `AnmeldebremseApiTest`) · 5 Tests, 48 Assertions

| alt | neu |
|---|---|
| `testNachFuenfFehlversuchenWirdDieKennungGebremst` | `testAfterFiveFailedAttemptsTheIdentifierIsThrottled` |
| `testWerGebremstIstKommtAuchMitRichtigemPasswortNichtDurch` | `testAThrottledUserDoesNotGetThroughEvenWithTheCorrectPassword` |
| `testDieBremseVerraetNichtObDieKennungExistiert` | `testTheThrottleDoesNotRevealWhetherTheIdentifierExists` |
| `testWechselndeKennungenVonDerselbenAdresseWerdenGebremst` | `testChangingIdentifiersFromTheSameAddressAreThrottled` |
| `testEineGelungeneAnmeldungLoeschtDenZaehler` | `testASuccessfulLoginClearsTheCounter` |

**`LoginProviderApiTest`** (vorher `AnmeldeproviderApiTest`) · 8 Tests, 19 Assertions

| alt | neu |
|---|---|
| `testEineAnmeldungUeberDenProviderLiefertEinenToken` | `testLoginThroughTheProviderReturnsAToken` |
| `testDerBenutzerEntstehtMitGesperrtemPasswort` | `testTheUserIsCreatedWithALockedPassword` |
| `testDerAngelegteBenutzerHatKeinErratbaresPasswort` | `testTheCreatedUserHasNoGuessablePassword` |
| `testEinZweiterLoginLegtKeinenZweitenBenutzerAn` | `testASecondLoginDoesNotCreateASecondUser` |
| `testEinFalschesGeheimnisWirdAbgewiesen` | `testAWrongSecretIsRejected` |
| `testEineUnbekannteKennungWirdAbgewiesen` | `testAnUnknownIdentifierIsRejected` |
| `testOhneKonfigurationLaesstDieVorlageNiemandenHerein` | `testWithoutConfigurationTheTemplateLetsNobodyIn` |
| `testOhneAbbildungGibtEsWederGruppeNochAdminrechte` | `testWithoutMappingThereIsNeitherGroupNorAdminRights` |

**`AuthApiTest`** · 40 Tests, 88 Assertions

| alt | neu |
|---|---|
| `testAnmeldungMitKorrektenDatenLiefertEinToken` | `testLoginWithCorrectCredentialsReturnsToken` |
| `testAnmeldungMitFalschemPasswortLiefertKeinToken` | `testLoginWithWrongPasswordReturnsNoToken` |
| `testAnmeldungMitUnbekanntemBenutzerLiefertKeinToken` | `testLoginWithUnknownUserReturnsNoToken` |
| `testFehlermeldungVerraetObDerBenutzerExistiert` | `testErrorMessageRevealsWhetherUserExists` |
| `testGeschuetzteRouteMitGueltigemTokenLiefert200` | `testProtectedRouteWithValidTokenReturns200` |
| `testGeschuetzteRouteOhneTokenLiefertKeineDaten` | `testProtectedRouteWithoutTokenReturnsNoData` |
| `testGeschuetzteRouteMitErfundenemTokenLiefertKeineDaten` | `testProtectedRouteWithFabricatedTokenReturnsNoData` |
| `testAbmeldenMachtDenTokenUnbrauchbar` | `testLogoutMakesTokenUnusable` |
| `testEinAltesPasswortWirdBeimLoginUmgeschluesselt` | `testLegacyPasswordIsRehashedOnLogin` |
| `testNachDemUmschluesselnGehtDieAnmeldungWeiterhin` | `testLoginStillWorksAfterRehashing` |
| `testEinFalschesPasswortScheitertAuchNachDemUmschluesseln` | `testWrongPasswordStillFailsAfterRehashing` |
| `testJedeAnmeldungLiefertEinenNeuenToken` | `testEveryLoginReturnsNewToken` |
| `testKeinRequestStartetEinePhpSession` | `testNoRequestStartsPhpSession` |
| `testDerAusgelieferteTokenStehtNichtInDerTabelle` | `testIssuedTokenIsNotStoredInTable` |
| `testDerAusgelieferteTokenFunktioniertWeiterhin` | `testIssuedTokenStillWorks` |
| `testDerHashLaesstSichNichtAlsTokenVorzeigen` | `testHashCannotBePresentedAsToken` |
| `testDieRoutenUnterApiGibtEsNicht` | `testRoutesUnderApiDoNotExist` |
| `testDieRoutenUnterAuthFunktionierenWeiterhin` | `testRoutesUnderAuthStillWork` |
| `testJedeTokenquelleOeffnetEineGeschuetzteRoute` | `testEveryTokenSourceOpensProtectedRoute` |
| `testOhneTokenBleibtDieGeschuetzteRouteZu` | `testProtectedRouteStaysClosedWithoutToken` |
| `testEinErfundenerTokenOeffnetKeineQuelle` | `testFabricatedTokenOpensNoSource` |
| `testOhneAnforderungBleibtEsBeimOpaquenToken` | `testWithoutRequestTokenStaysOpaque` |
| `testAufAnforderungLiefertDerLoginEinJwtUndEinRefreshToken` | `testOnRequestLoginReturnsJwtAndRefreshToken` |
| `testDasAusgestellteJwtOeffnetEineGeschuetzteRoute` | `testIssuedJwtOpensProtectedRoute` |
| `testDasRefreshTokenOeffnetKeineGeschuetzteRoute` | `testRefreshTokenOpensNoProtectedRoute` |
| `testDasAusgestellteJwtTraegtNurDenFestgelegtenClaimSatz` | `testIssuedJwtCarriesOnlyTheDefinedClaimSet` |
| `testEinRefreshTokenLiefertEinFrischesAccessJwt` | `testRefreshTokenReturnsFreshAccessJwt` |
| `testDasVorgezeigteRefreshTokenWirdErsetzt` | `testPresentedRefreshTokenIsReplaced` |
| `testEinAccessJwtTaugtNichtAlsRefreshToken` | `testAccessJwtIsNotUsableAsRefreshToken` |
| `testEinOpaquesAnmeldetokenTaugtNichtAlsRefreshToken` | `testOpaqueLoginTokenIsNotUsableAsRefreshToken` |
| `testEinUnbekanntesRefreshTokenWirdAbgewiesen` | `testUnknownRefreshTokenIsRejected` |
| `testOhneRefreshTokenWirdAbgewiesen` | `testMissingRefreshTokenIsRejected` |
| `testJederFehlschlagAmRefreshSiehtGleichAus` | `testEveryRefreshFailureLooksTheSame` |
| `testEinGesperrterBenutzerBekommtKeinNeuesAccessJwt` | `testDeactivatedUserGetsNoNewAccessJwt` |
| `testNachDemAbmeldenGiltDasAccessJwtNichtMehr` | `testAccessJwtIsInvalidAfterLogout` |
| `testDasAbmeldenEntziehtDasMitgeschickteRefreshToken` | `testLogoutRevokesRefreshTokenSentAlong` |
| `testOhneMitgeschicktesRefreshTokenBleibtEsBestehen` | `testRefreshTokenRemainsWhenNotSentAlong` |
| `testEinFremdesRefreshTokenLaesstSichNichtAbmelden` | `testForeignRefreshTokenCannotBeLoggedOut` |
| `testEineBenutzersperrungWirktSofortUndOhneSperrliste` | `testUserDeactivationTakesEffectImmediatelyWithoutRevocationList` |
| `testGegenstandsloseSperrEintraegeWerdenAufgeraeumt` | `testObsoleteRevocationEntriesAreCleanedUp` |

**`ErrorResponseApiTest`** (vorher `FehlerantwortApiTest`) · 2 Tests, 7 Assertions

| alt | neu |
|---|---|
| `testEinPhpFehlerKommtAlsJsonUndNichtAlsHtmlSeite` | `testPhpErrorArrivesAsJsonNotAsHtmlPage` |
| `testDieWhoopsSeiteErscheintNicht` | `testWhoopsPageDoesNotAppear` |

**`LoginManagerApiTest`** · 8 Tests, 30 Assertions

| alt | neu |
|---|---|
| `testEinBenutzerOhneLoginManagerMeldetSichMitPasswortAn` | `testAUserWithoutLoginManagerLogsInWithPassword` |
| `testEinBenutzerMitLoginManagerKannSichNichtMitPasswortAnmelden` | `testAUserWithLoginManagerCannotLogInWithPassword` |
| `testDieEindeutigkeitKommtAusDerSpaltenbedingungStattAusEinemMd5Praefix` | `testUniquenessComesFromTheColumnConstraintInsteadOfAnMd5Prefix` |
| `testEinBereitgestellterBenutzerHatKeinErratbaresPasswort` | `testAProvisionedUserHasNoGuessablePassword` |
| `testDerRiegelIstDieZweiteSicherungUndStehtWeiterhin` | `testTheBoltIsTheSecondSafeguardAndStillHolds` |
| `testEinKlassennameWaehltKeineKlasseMehrAus` | `testAClassNameNoLongerSelectsAClass` |
| `testEinUnbekannterProvidernameFaelltNichtAufDasPasswortZurueck` | `testAnUnknownProviderNameDoesNotFallBackToThePassword` |
| `testOhneProvidernameLaeuftDieAnmeldungWieImmer` | `testWithoutProviderNameLoginWorksAsAlways` |

**`ProviderSyncApiTest`** · 5 Tests, 9 Assertions

| alt | neu |
|---|---|
| `testWerAusDemFremdsystemVerschwindetVerliertSeinenZugang` | `testWhoeverDisappearsFromTheExternalSystemLosesAccess` |
| `testOhneAuskunftWirdNiemandGesperrt` | `testWithoutAnAnswerNobodyIsLocked` |
| `testWerNochImFremdsystemStehtBleibtAktiv` | `testWhoeverIsStillInTheExternalSystemStaysActive` |
| `testDryRunZaehltUndSperrtNicht` | `testDryRunCountsAndDoesNotLock` |
| `testEinBenutzerOhneProviderWirdNichtAngefasst` | `testAUserWithoutAProviderIsNotTouched` |

**`SystemControllerApiTest`** · 29 Tests, 89 Assertions

| alt | neu |
|---|---|
| `testOhneTokenWirdAbgewiesen` | `testRequestWithoutTokenIsRejected` |
| `testEinUngueltigerTokenWirdAbgewiesen` | `testInvalidTokenIsRejected` |
| `testEinNichtAdminMitGueltigemTokenWirdAbgewiesen` | `testNonAdminWithValidTokenIsRejected` |
| `testDieAbsichtDesHooksKommtSeitDemStackWechselAnDenClientDurch` | `testHookIntentReachesClientSinceStackSwitch` |
| `testNurPostIstErlaubt` | `testOnlyPostIsAllowed` |
| `testDieAntwortTraegtMethodeZeitstempelUndErgebnis` | `testResponseCarriesMethodTimestampAndResult` |
| `testDerSystemEndpunktBenutztEineEigeneAntwortform` | `testSystemEndpointUsesItsOwnResponseShape` |
| `testEineUnbekannteMethodeEndetInEinerHtmlFehlerseite` | `testUnknownMethodEndsInHtmlErrorPage` |
| `testEineFehlendeMethodeEndetEbenfallsInEinemFehler` | `testMissingMethodAlsoEndsInError` |
| `testDasTorIstEineErlaubnislisteUndNichtMethodExists` | `testGateIsAllowlistNotMethodExists` |
| `testDoActionRuftSichNichtMehrSelbstAuf` | `testDoActionNoLongerCallsItself` |
| `testGenerateTokenLiefertHexZeichenUndSchreibtNichts` | `testGenerateTokenReturnsHexCharactersAndWritesNothing` |
| `testAddTokenLegtEineZeileAnUndProtokolliertSie` | `testAddTokenCreatesRowAndLogsIt` |
| `testAddTokenSchreibtDenLogeintragMitDerKonstanten` | `testAddTokenWritesLogEntryWithConstant` |
| `testAddTokenBrauchtReferrerTokenUndBenutzer` | `testAddTokenRequiresReferrerTokenAndUser` |
| `testAddTokenLehntEinenUnbekanntenBenutzerAb` | `testAddTokenRejectsUnknownUser` |
| `testEinBereitsVergebenerTokenWirdAbgelehnt` | `testAlreadyAssignedTokenIsRejected` |
| `testListTokensZeigtNurTokenMitReferrer` | `testListTokensShowsOnlyTokensWithReferrer` |
| `testEinReferrerTokenOeffnetDieApiWeiterhin` | `testReferrerTokenStillOpensApi` |
| `testDeleteTokenEntferntDieZeileUndProtokolliertEs` | `testDeleteTokenRemovesRowAndLogsIt` |
| `testDeleteTokenMeldetEinenUnbekanntenToken` | `testDeleteTokenReportsUnknownToken` |
| `testDerAnmeldeTokenDesLaufsUeberstehtDieTokenMethoden` | `testLoginTokenOfTestRunSurvivesTokenMethods` |
| `testAbgelaufeneAnmeldetokenLassenSichAufraeumen` | `testExpiredLoginTokensCanBeCleanedUp` |
| `testJedeAnmeldungLegtEineZeileAnDieNurBeimNaechstenGebrauchVerfaellt` | `testEveryLoginCreatesRowThatOnlyExpiresOnNextUse` |
| `testFlushSchemaCacheMeldetErfolgUndLaesstDasSchemaLesbar` | `testFlushSchemaCacheReportsSuccessAndLeavesSchemaReadable` |
| `testFlushSchemaCacheLeertDenAbfrageCacheWirklich` | `testFlushSchemaCacheReallyClearsQueryCache` |
| `testFlushSchemaCacheMeldetErfolgAuchWennEsNichtsZuLoeschenGibt` | `testFlushSchemaCacheReportsSuccessEvenWhenNothingToDelete` |
| `testDasNotschlossKenntNurNochUpdateDatabase` | `testEmergencyLockOnlyKnowsUpdateDatabase` |
| `testDasNotschlossGreiftNurBeiEinerInvalidFieldNameException` | `testEmergencyLockOnlyTriggersOnInvalidFieldNameException` |

**`TemplateApiTest`** (vorher `VorlageApiTest`) · 13 Tests, 55 Assertions

| alt | neu |
|---|---|
| `testDieBeispielEntityErscheintImSchemaUnterIhremUnterverzeichnis` | `testTheExampleEntityAppearsInTheSchemaUnderItsSubdirectory` |
| `testDieSelectOptionenDerVorlageStehenImSchema` | `testTheSelectOptionsOfTheTemplateAreInTheSchema` |
| `testDasJsonFeldDerVorlageStehtImSchema` | `testTheJsonFieldOfTheTemplateIsInTheSchema` |
| `testDieBeispielEntityIstUeberDieGenerischenEndpunkteBenutzbar` | `testTheExampleEntityIsUsableViaTheGenericEndpoints` |
| `testEineProjektEntityWirdWieJedeAndereProtokolliert` | `testAProjectEntityIsLoggedLikeAnyOther` |
| `testDasJsonFeldNimmtEinenVerschachteltenWertUndGibtIhnZurueck` | `testTheJsonFieldAcceptsANestedValueAndReturnsIt` |
| `testDieSelectAnnotationWeistEinenUnbekanntenWertAb` | `testTheSelectAnnotationRejectsAnUnknownValue` |
| `testEinErlaubterSelectWertGehtWeiterhinDurch` | `testAnAllowedSelectValueStillPasses` |
| `testDerBeispielEndpunktLiefertDenInhaltDerVorlage` | `testTheExampleEndpointReturnsTheTemplateContent` |
| `testDerZeitstempelDerVorlageIstFeinerAlsDerDesFrameworks` | `testTheTemplateTimestampIsFinerThanTheFrameworkTimestamp` |
| `testDieHooksDerVorlageLaufenUeberhaupt` | `testTheTemplateHooksRunAtAll` |
| `testDerBeforeHookDerVorlageHinterlaesstKeineBeobachtbareSpur` | `testTheTemplateBeforeHookLeavesNoObservableTrace` |
| `testDerBeispielCommandIstRegistriertUndTraegtDenCustomPraefix` | `testTheExampleCommandIsRegisteredAndCarriesTheCustomPrefix` |

**`ReencryptCommandTest`** · 5 Tests, 39 Assertions

| alt | neu |
|---|---|
| `testDerTrockenlaufAendertNichts` | `testTheDryRunChangesNothing` |
| `testDerEchteLaufSchluesseltUmUndDerZweiteFindetNichtsMehr` | `testTheRealRunReencryptsAndTheSecondFindsNothingMore` |
| `testErArbeitetInStapelnUndErwischtAlleZeilen` | `testItWorksInBatchesAndCatchesAllRows` |
| `testEinUnlesbarerWertBrichtDenStapelAbUndLaesstIhnUnveraendert` | `testAnUnreadableValueAbortsTheBatchAndLeavesItUnchanged` |
| `testHeuteGibtEsKeinFeldMitEncoded` | `testTodayThereIsNoFieldWithEncoded` |

**`ContainerKeysTest`** (vorher `ContainerSchluesselTest`) · 7 Tests, 63 Assertions

| alt | neu |
|---|---|
| `testJederZugesicherteSchluesselIstDa` | `testEveryGuaranteedKeyIsPresent` |
| `testDieDreiDatenbankSchluesselHaengenAnDerInstallation` | `testTheDatabaseKeysDependOnTheInstallation` |
| `testDerTokenGibtEsErstNachDerAnmeldung` | `testTheTokenOnlyExistsAfterLogin` |
| `testDerEntityManagerIstDaAberNullSolangeNichtInstalliertIst` | `testEntityManagerIsPresentButNullUntilInstalled` |
| `testJederRegistrierteSchluesselIstEingeordnet` | `testEveryRegisteredKeyIsClassified` |
| `testJederEingeordneteSchluesselWirdAuchRegistriert` | `testEveryClassifiedKeyIsActuallyRegistered` |
| `testDieListenStimmenMitDemDevGuideUeberein` | `testTheListsMatchTheDevGuide` |

**Nachweis.** Je Datei sind Test- und Assertion-Zahl vor und nach dem Task identisch; der Vergleich
läuft über die JUnit-Logs der vollen Suite gegen den Testserver, mit den Umbenennungen als Paaren.
Volle Suite `OK (528 tests, 1703 assertions)`, PHPStan `[OK] No errors` (Result-Cache geleert),
Deprecation-Gate grün, Template-Config unverändert. `STRUCTURE.md` nennt noch
`ContainerSchluesselTest`; die Datei wird mit `014-006` überarbeitet.
