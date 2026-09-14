---
id: 014-005-0003
title: Unit-Tests Security und Migration samt Fixtures auf Englisch
status: review
depends_on: [014-005-0002]
---

# Unit-Tests Security und Migration samt Fixtures auf Englisch

## Context
Umfang: alle Dateien unter `tests/Unit/Security/` und `tests/Unit/Migration/` sowie die Fixtures
unter `tests/Fixtures/RectorMigration/`.

- `SchluesselwechselTest` → englischer Klassenname
- `MigrationsleitfadenTest` und `RectorRegelTest` → englische Klassennamen
- Fixture-Verzeichnisse `alt/` und `soll/` → `before/` und `after/`
- Fixture-Klassen `Artikel`, `Rubrik` und `AndererAlias` → englische Namen, samt Aliasen

**Achtung:** `RectorRegelTest` fährt die Rector-Regel gegen die Fixtures und vergleicht mit dem
Soll-Stand Zeichen für Zeichen. Umbenennungen müssen in `before/` und `after/` identisch sein.

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

**Die Unit-Tests für Security und Migration sind samt Fixtures englisch.** Das umfasst 16 Testdateien
unter `tests/Unit/Security/` und `tests/Unit/Migration/` sowie die sechs Fixtures unter
`tests/Fixtures/RectorMigration/`. Drei Agenten haben übersetzt; nachgeprüft ist das über die
Zahlen je Datei, einen Scan auf deutsche Reste und `rector --dry-run`.

**Umbenannte Dateien** (`git mv`):

| alt | neu |
|---|---|
| `tests/Unit/Security/SchluesselwechselTest.php` | `tests/Unit/Security/KeyRotationTest.php` |
| `tests/Unit/Migration/MigrationsleitfadenTest.php` | `tests/Unit/Migration/MigrationGuideTest.php` |
| `tests/Unit/Migration/RectorRegelTest.php` | `tests/Unit/Migration/RectorRuleTest.php` |
| `tests/Fixtures/RectorMigration/alt/` · `soll/` | `before/` · `after/` |
| `Artikel.php` · `Rubrik.php` · `AndererAlias.php` | `Article.php` · `Category.php` · `OtherAlias.php` |

In den Fixtures ist alles gleichlautend in `before/` und `after/` übersetzt: Namensraum `…\Before`,
Aliase `Mapping` und `Other`, Tabellen `fixture_article`, `fixture_category`,
`fixture_category_article`, `fixture_other_alias`, Properties, Beschriftungen und Optionswerte. Die
Feldnamen des Frameworks, die die Tests gegen `pim-annotationen-migration.md` abgleichen, sind
unverändert. `rector --dry-run` über `custom/Entity` meldet nichts, und die Alias-Prüfung greift
weiter: Die Rector-Ausgabe enthält `#[Mapping\Column` und `#[Other\Config`.

**Bewusst deutsch geblieben:**
- `FieldEncryptionTest`: Klartext und Schlüssel des Legacy-Chiffretexts, der fest im Test steht.
  Beide zu ändern hiesse, den Chiffretext neu zu erzeugen, und dann prüfte der Test nicht mehr
  gegen das alte Format. Dazu die Umlaute im Rundlauf-Wert, um die es dem Test geht.
- `JwtAccessTokenTest`: `rolle` und `gruppe` stehen absichtlich auf der Liste verbotener
  Claim-Namen. Ein Kommentar sagt das jetzt.
- `MigrationGuideTest` und `RectorRuleTest`: Überschriften und Tabellenkopf, die gegen die deutschen
  Docs `migration.md` und `pim-annotationen-migration.md` abgeglichen werden.
- Verweise auf Integrationstests, die noch deutsch heissen (`AnmeldebremseApiTest`,
  `AnmeldeproviderApiTest`, `ConstraintApiTest::testKeineEntityNutztDieEncodedVerschluesselung`).
  Sie ziehen mit `014-005-0004` und `014-005-0005` nach.

**Zwei Werte bewusst anders als vorgeschlagen:** Das Test-Geheimnis in `TokenHandlerTest` heisst
`test-secret-only-for-this-test-run` (34 Bytes). Die kürzere Variante hätte die 32-Byte-Grenze
unterschritten, und der Test hätte nicht mehr den Zweig geprüft, den er prüfen soll. Der Kommentar
in `LoginThrottleTest` nannte eine Methode `wartezeit()`, die es nicht mehr gibt; er nennt jetzt
`retryAfter()`.

**Mitgezogene Verweise:** `FieldEncryption.php` nennt `testEveryTamperingIsDetected()`. Der alte
Kommentar nannte `testEineManipulationFaelltAuf()`, obwohl der Test `testJedeManipulationFaelltAuf`
hiess, und war damit schon vorher falsch. Die zugehörige Ausnahme des Sprachwächters ist entfernt.
`rector.php` und `pim-annotationen-migration.md` nennen `RectorRuleTest` und
`RectorMigration/before/`. `STRUCTURE.md` folgt mit `014-006`.

**Umbenannt** (gepaart über die Deklarationsreihenfolge vor und nach dem Task):

**`MigrationGuideTest`** (vorher `MigrationsleitfadenTest`) · 3 Tests, 15 Assertions

| alt | neu |
|---|---|
| `testJederRegisterAbschnittIstEinerPhaseZugeordnet` | `testEveryRegisterSectionIsAssignedToAPhase` |
| `testJedeZuordnungTrifftEinenAbschnitt` | `testEveryAssignmentHitsASection` |
| `testDieAngegebenenGroessenStimmenNoch` | `testTheStatedSizesAreStillCorrect` |

**`RectorRuleTest`** (vorher `RectorRegelTest`) · 19 Tests, 396 Assertions

| alt | neu |
|---|---|
| `testDerPruefsteinTraegtJedeGestricheneAnnotation` | `testTheReferenceFixtureCarriesEveryRemovedAnnotation` |
| `testDerPruefsteinTraegtJedesGestricheneUndJedesGebliebeneFeld` | `testTheReferenceFixtureCarriesEveryRemovedAndEveryRemainingField` |
| `testDerSollzustandTraegtNichtsGestrichenesMehr` | `testTheTargetStateCarriesNothingRemovedAnyMore` |
| `testDerSollzustandTraegtJedesGebliebeneFeldNoch` | `testTheTargetStateStillCarriesEveryRemainingField` |
| `testAltUndSollUnterscheidenSich` | `testBeforeAndAfterDiffer` |
| `testDerLaufGehtDurchUndHatEtwasZuTun` | `testTheRunGoesThroughAndHasSomethingToDo` |
| `testNachDemLaufStehtKeineOrmAnnotationMehrDa` | `testNoOrmAnnotationIsLeftAfterTheRun` |
| `testDerVerschachtelteFallWirdRichtigUmgesetzt` | `testTheNestedCaseIsConvertedCorrectly` |
| `testNachDemLaufBleibtKeineGestricheneAnnotationStehen` | `testNoRemovedAnnotationIsLeftAfterTheRun` |
| `testDieRegelGreiftAuchUnterEinemFremdenAlias` | `testTheRuleAlsoAppliesUnderAForeignAlias` |
| `testDieGebliebenenAnnotationenSindUnangetastet` | `testTheRemainingAnnotationsAreUntouched` |
| `testZweiLaeufeSindNoetigUndDerDritteFindetNichtsMehr` | `testTwoRunsAreNecessaryAndTheThirdFindsNothingMore` |
| `testNachDemFixpunktBleibtKeinGestrichenesFeld` | `testNoRemovedFieldIsLeftAtTheFixedPoint` |
| `testNachDemFixpunktStehenAlleGebliebenenFelderNoch` | `testAllRemainingFieldsArePresentAtTheFixedPoint` |
| `testDasErgebnisEntsprichtDemSollzustand` | `testTheResultMatchesTheTargetState` |
| `testJedesAttributDesErgebnissesLaesstSichInstanziieren` | `testEveryAttributeOfTheResultCanBeInstantiated` |
| `testGegenUmgestellteEntitiesTutDieRegelNichts` | `testTheRuleDoesNothingAgainstConvertedEntities` |
| `testDieListenStimmenNochMitDerDokumentationUeberein` | `testTheListsStillMatchTheDocumentation` |
| `testDerPruefsteinErfindetKeineFelder` | `testTheReferenceFixtureInventsNoFields` |

**`FieldEncryptionTest`** · 9 Tests, 11 Assertions

| alt | neu |
|---|---|
| `testEinWertKommtDurchDenRundlaufUnveraendertZurueck` | `testAValueSurvivesTheRoundTripUnchanged` |
| `testEinChiffretextAusDemAltenCodeBleibtLesbar` | `testACiphertextFromTheLegacyCodeStaysReadable` |
| `testZweiVerschluesselungenDesselbenWertsUnterscheidenSich` | `testTwoEncryptionsOfTheSameValueDiffer` |
| `testOhneSchluesselWirdNichtVerschluesselt` | `testWithoutAKeyNothingIsEncrypted` |
| `testOhneSchluesselWirdNichtEntschluesselt` | `testWithoutAKeyNothingIsDecrypted` |
| `testJedeManipulationFaelltAuf` | `testEveryTamperingIsDetected` |
| `testEinFremderChiffretextMitDemRichtigenPraefixWirdAbgewiesen` | `testAForeignCiphertextWithTheCorrectPrefixIsRejected` |
| `testDieAbleitungIstDeterministisch` | `testTheDerivationIsDeterministic` |
| `testNeueWerteTragenDasPraefixUndAlteNicht` | `testNewValuesCarryThePrefixAndLegacyValuesDoNot` |

**`GroupMappingTest`** · 9 Tests, 14 Assertions

| alt | neu |
|---|---|
| `testEineZugeordneteFremdgruppeSetztDieContentflyGruppe` | `testAMappedExternalGroupSetsTheContentflyGroup` |
| `testEineAdmingruppeSetztDasAdminflag` | `testAnAdminGroupSetsTheAdminFlag` |
| `testBeiMehrerenTreffernGewinntDerErsteEintrag` | `testWithSeveralMatchesTheFirstEntryWins` |
| `testOhneTrefferGibtEsKeineAdminrechte` | `testWithoutAMatchThereAreNoAdminRights` |
| `testOhneTrefferGreiftDieVorgabe` | `testWithoutAMatchTheDefaultApplies` |
| `testOhneTrefferUndOhneVorgabeWirdDieGruppeAbgeraeumt` | `testWithoutAMatchAndWithoutADefaultTheGroupIsCleared` |
| `testOhneEintragFuerDenAnbieterPassiertNichts` | `testWithoutAnEntryForTheProviderNothingHappens` |
| `testEineUnbekannteZielgruppeSchlaegtLautDurch` | `testAnUnknownTargetGroupFailsLoudly` |
| `testEineGeaenderteZuordnungWirktBeiDerNaechstenAnmeldung` | `testAChangedMappingTakesEffectOnTheNextLogin` |

**`JwtAccessTokenTest`** · 9 Tests, 17 Assertions

| alt | neu |
|---|---|
| `testEinTokenTraegtGenauDieFuenfFestgelegtenClaims` | `testATokenCarriesExactlyTheFiveDefinedClaims` |
| `testKeinRechtUndKeineRolleStehtImToken` | `testNoPermissionAndNoRoleIsInTheToken` |
| `testDieKennungStehtInSub` | `testTheIdentifierIsInSub` |
| `testDerAusgeberStehtInIss` | `testTheIssuerIsInIss` |
| `testJedesTokenTraegtEineEigeneJti` | `testEveryTokenCarriesItsOwnJti` |
| `testDieLebensdauerKommtAusDerKonfiguration` | `testTheLifetimeComesFromTheConfiguration` |
| `testEineUnsinnigeLebensdauerFaelltAufDieVorgabeZurueck` | `testANonsensicalLifetimeFallsBackToTheDefault` |
| `testOhneGeheimnisIstNichtsEingerichtet` | `testWithoutASecretNothingIsConfigured` |
| `testOhneGeheimnisWirdNichtAusgestellt` | `testWithoutASecretNoTokenIsIssued` |

**`LdapProviderTest`** · 13 Tests, 26 Assertions

| alt | neu |
|---|---|
| `testEineGelungeneAnmeldungLiefertKennungUndGruppen` | `testASuccessfulLoginReturnsIdentifierAndGroups` |
| `testDerProviderBindetZweimalUndSuchtDazwischen` | `testTheProviderBindsTwiceAndSearchesInBetween` |
| `testOhneDienstkontoWirdAnonymGesucht` | `testWithoutAServiceAccountTheSearchIsAnonymous` |
| `testEinLeeresPasswortWirdAbgewiesenOhneDasVerzeichnisZuFragen` | `testAnEmptyPasswordIsRejectedWithoutAskingTheDirectory` |
| `testOhneKennungWirdAbgewiesen` | `testWithoutAnIdentifierTheLoginIsRejected` |
| `testDieKennungWirdInDenFilterMaskiert` | `testTheIdentifierIsEscapedIntoTheFilter` |
| `testEineUnbekannteKennungWirdAbgewiesen` | `testAnUnknownIdentifierIsRejected` |
| `testZweiTrefferWerdenAbgewiesen` | `testTwoResultsAreRejected` |
| `testEinFalschesPasswortWirdAbgewiesen` | `testAWrongPasswordIsRejected` |
| `testEinNichtErreichbaresVerzeichnisWirdAbgewiesen` | `testAnUnreachableDirectoryIsRejected` |
| `testEinAbgelehntesDienstkontoWirdAbgewiesen` | `testARejectedServiceAccountIsRejected` |
| `testOhneGruppenattributKommenKeineGruppen` | `testWithoutAGroupAttributeNoGroupsArrive` |
| `testDieGruppenGehenUnveraendertWeiter` | `testTheGroupsArePassedOnUnchanged` |

**`LoginProviderRegistryTest`** · 9 Tests, 18 Assertions

| alt | neu |
|---|---|
| `testEinEingetragenerNameLiefertSeinenProvider` | `testARegisteredNameReturnsItsProvider` |
| `testEinNichtEingetragenerNameLiefertNichts` | `testAnUnregisteredNameReturnsNothing` |
| `testEinLeeresVerzeichnisLaesstNiemandenDurch` | `testAnEmptyRegistryLetsNobodyThrough` |
| `testGrossUndKleinschreibungEntscheidetNicht` | `testUpperAndLowerCaseDoNotMatter` |
| `testEinZweiterEintragUnterDemselbenNamenWirdAbgewiesen` | `testASecondEntryUnderTheSameNameIsRejected` |
| `testEinEintragWirdErstBeimAbrufenGebaut` | `testAnEntryIsOnlyBuiltWhenRetrieved` |
| `testEineClosureDieKeinenProviderLiefertWirdAbgewiesen` | `testAClosureThatReturnsNoProviderIsRejected` |
| `testEineFremdkennungOhneKennungGibtEsNicht` | `testAnExternalIdentityWithoutAnIdentifierDoesNotExist` |
| `testEineFremdkennungTraegtWasDasFremdsystemSagt` | `testAnExternalIdentityCarriesWhatTheExternalSystemSays` |

**`LoginThrottleTest`** · 11 Tests, 71 Assertions

| alt | neu |
|---|---|
| `testFrischIstNichtsGebremst` | `testNothingIsThrottledInitially` |
| `testNachDerGrenzeWirdGebremst` | `testAfterTheLimitTheLoginIsThrottled` |
| `testDieKennungWirdAuchVonEinerAnderenAdresseAusGebremst` | `testTheIdentifierIsAlsoThrottledFromAnotherAddress` |
| `testDieSchreibweiseDerKennungUmgehtNichts` | `testTheSpellingOfTheIdentifierBypassesNothing` |
| `testEineAndereKennungBleibtFrei` | `testAnotherIdentifierStaysFree` |
| `testWechselndeKennungenVonEinerAdresseWerdenGebremst` | `testChangingIdentifiersFromOneAddressAreThrottled` |
| `testEineGelungeneAnmeldungLoeschtDenZaehlerDerKennung` | `testASuccessfulLoginClearsTheIdentifierCounter` |
| `testDerZaehlerDerAdresseBleibtStehen` | `testTheAddressCounterStaysInPlace` |
| `testDiePruefungVerbrauchtNichts` | `testTheCheckConsumesNothing` |
| `testDieWartezeitWaechstMitDerHartnaeckigkeit` | `testTheWaitTimeGrowsWithPersistence` |
| `testDieAltenIntervallKonstantenGibtEsNichtMehr` | `testTheOldIntervalConstantsNoLongerExist` |

**`OidcProviderTest`** · 11 Tests, 17 Assertions

| alt | neu |
|---|---|
| `testEineGueltigeAntwortLiefertKennungUndGruppen` | `testAValidResponseReturnsIdentifierAndGroups` |
| `testDasTokenGehtAlsBearerAnDenEndpunkt` | `testTheTokenIsSentAsBearerToTheEndpoint` |
| `testDasTokenDarfAuchInPassStehen` | `testTheTokenMayAlsoBeInPass` |
| `testDerGruppenClaimIstKonfigurierbar` | `testTheGroupsClaimIsConfigurable` |
| `testEineAntwortOhneGruppenIstInOrdnung` | `testAResponseWithoutGroupsIsFine` |
| `testEinAbgelehntesTokenWirdAbgewiesen` | `testARejectedTokenIsRejected` |
| `testEineAntwortOhneKennungWirdAbgewiesen` | `testAResponseWithoutAnIdentifierIsRejected` |
| `testEineLeereKennungWirdAbgewiesen` | `testAnEmptyIdentifierIsRejected` |
| `testEinNichtErreichbarerProviderWirdAbgewiesen` | `testAnUnreachableProviderIsRejected` |
| `testOhneTokenWirdNichtGefragt` | `testWithoutATokenNothingIsAsked` |
| `testOhneEndpunktWirdNichtGefragt` | `testWithoutAnEndpointNothingIsAsked` |

**`KeyRotationTest`** (vorher `SchluesselwechselTest`) · 6 Tests, 13 Assertions

| alt | neu |
|---|---|
| `testEinWechselLaesstLaufendeSitzungenBestehen` | `testRotationKeepsRunningSessionsAlive` |
| `testDieKennungStehtImHeader` | `testKeyIdIsInTheHeader` |
| `testEineUnbekannteKennungWirdAbgewiesen` | `testUnknownKeyIdIsRejected` |
| `testZweiGleicheKennungenWerdenAbgewiesen` | `testTwoIdenticalKeyIdsAreRejected` |
| `testEinVorherigerSchluesselOhneKennungWirdAbgewiesen` | `testPreviousKeyWithoutKeyIdIsRejected` |
| `testEineFehlkonfigurationSchlaegtDurchStattAbzuweisen` | `testMisconfigurationFailsLoudlyInsteadOfRejecting` |

**`TokenAuthenticatorTest`** · 4 Tests, 7 Assertions

| alt | neu |
|---|---|
| `testEinGueltigesTokenLiefertDenBenutzer` | `testValidTokenReturnsTheUser` |
| `testOhneTokenGreiftDerTreiberNicht` | `testWithoutTokenTheDriverDoesNotApply` |
| `testEinVorhandenesTokenWirdNichtVorschnellAbgewiesen` | `testPresentTokenIsNotRejectedPrematurely` |
| `testJederFehlschlagSiehtGleichAus` | `testEveryFailureLooksTheSame` |

**`TokenHandlerTest`** · 27 Tests, 37 Assertions

| alt | neu |
|---|---|
| `testEinOpaquerTokenLiefertSeinenBenutzer` | `testOpaqueTokenReturnsItsUser` |
| `testEinUnbekannterOpaquerTokenWirdAbgewiesen` | `testUnknownOpaqueTokenIsRejected` |
| `testEinGesperrterBenutzerWirdAbgewiesen` | `testLockedUserIsRejected` |
| `testEinAbgelaufenerTokenWirdAbgewiesen` | `testExpiredTokenIsRejected` |
| `testEinReferrerTokenVerfaelltNicht` | `testReferrerTokenDoesNotExpire` |
| `testDerTimeoutDerGruppeSchlaegtDenAusDerKonfiguration` | `testGroupTimeoutOverridesConfiguredTimeout` |
| `testDerOpaqueZweigSchreibtModifiedZurueck` | `testOpaqueBranchWritesModifiedBack` |
| `testDerAufgeloesteTokenBleibtAbrufbar` | `testResolvedTokenRemainsRetrievable` |
| `testEinJwtLiefertSeinenBenutzer` | `testJwtReturnsItsUser` |
| `testDerJwtZweigFasstDieTokentabelleNichtAn` | `testJwtBranchDoesNotTouchTheTokenTable` |
| `testDasJwtBadgeUeberlaesstDasLadenDemBenutzerlader` | `testJwtBadgeLeavesLoadingToTheUserLoader` |
| `testEinAbgelaufenesJwtWirdAbgewiesen` | `testExpiredJwtIsRejected` |
| `testEinManipuliertesJwtWirdAbgewiesen` | `testTamperedJwtIsRejected` |
| `testEinJwtMitFremdemGeheimnisWirdAbgewiesen` | `testJwtWithForeignSecretIsRejected` |
| `testEinJwtOhneSubWirdAbgewiesen` | `testJwtWithoutSubIsRejected` |
| `testOhneGeheimnisWirdDerJwtZweigAbgewiesen` | `testWithoutSecretTheJwtBranchRejects` |
| `testEinZuKurzesGeheimnisWirdAbgewiesen` | `testTooShortSecretIsRejected` |
| `testEinTokenMitFremdemAusgeberWirdAbgewiesen` | `testTokenWithForeignIssuerIsRejected` |
| `testEinTokenOhneAusgeberWirdAbgewiesen` | `testTokenWithoutIssuerIsRejected` |
| `testEinRefreshTokenOeffnetDieApiNicht` | `testRefreshTokenDoesNotOpenTheApi` |
| `testEinGesperrtesTokenWirdAbgewiesenObwohlEsNochGilt` | `testRevokedTokenIsRejectedEvenThoughStillValid` |
| `testEineFremdeSperreTrifftDiesesTokenNicht` | `testUnrelatedRevocationDoesNotAffectThisToken` |
| `testEinTokenOhneJtiWirdAbgewiesen` | `testTokenWithoutJtiIsRejected` |
| `testDieClaimsBleibenFuerDasAbmeldenAbrufbar` | `testClaimsRemainRetrievableForLogout` |
| `testNachEinemOpaquenTokenGibtEsKeineClaims` | `testAfterOpaqueTokenThereAreNoClaims` |
| `testEinOpaquerTokenMitPunktenLandetImOpaquenZweig` | `testOpaqueTokenWithDotsEndsUpInOpaqueBranch` |
| `testBeideZweigeScheiternUnunterscheidbar` | `testBothBranchesFailIndistinguishably` |

**`TokenSourcesTest`** · 9 Tests, 13 Assertions

| alt | neu |
|---|---|
| `testTokenImQueryString` | `testTokenInQueryString` |
| `testTokenImRumpf` | `testTokenInBody` |
| `testOhneTokenLiefertDieKetteNull` | `testWithoutTokenTheChainReturnsNull` |
| `testDieReihenfolgeIstDieAusCheckToken` | `testOrderIsTheOneFromCheckToken` |
| `testEinAltquellenTokenDarfZeichenAusserhalbVonRfc6750Tragen` | `testLegacySourceTokenMayCarryCharactersOutsideRfc6750` |
| `testEinLeererWertZaehltAlsAbwesend` | `testEmptyValueCountsAsAbsent` |

**`TrustedProxiesTest`** · 9 Tests, 16 Assertions

| alt | neu |
|---|---|
| `testOhneAngabeWirdNichtsGesetzt` | `testWithoutSettingNothingIsApplied` |
| `testEinArrayWirdUebernommen` | `testArrayIsTakenOver` |
| `testEineZeichenketteMitKommasWirdZerlegt` | `testCommaSeparatedStringIsSplit` |
| `testLeereEintraegeFallenWeg` | `testEmptyEntriesAreDropped` |
| `testDieVorgabeIstDieEnge` | `testDefaultIsTheNarrowOne` |
| `testForwardedSchaltetUm` | `testForwardedSwitchesOver` |
| `testEinUnbekannterHeaderSatzWirdAbgewiesen` | `testUnknownHeaderSetIsRejected` |
| `testMitVertrautemProxyGiltDieWeitergereichteAdresse` | `testWithTrustedProxyTheForwardedAddressApplies` |
| `testEinNichtVertrauterAbsenderKannDieAdresseNichtSetzen` | `testUntrustedSenderCannotSetTheAddress` |

**`UserLoaderTest`** · 6 Tests, 8 Assertions

| alt | neu |
|---|---|
| `testEinBekannterBenutzerWirdGeladen` | `testKnownUserIsLoaded` |
| `testEinUnbekannterBenutzerWirdAbgewiesen` | `testUnknownUserIsRejected` |
| `testEinGesperrterBenutzerWirdWieEinUnbekannterAbgewiesen` | `testLockedUserIsRejectedLikeAnUnknownOne` |
| `testDerLaderIstFuerDieBenutzerEntityZustaendig` | `testLoaderIsResponsibleForTheUserEntity` |
| `testDieKennungIstDerAlias` | `testIdentifierIsTheAlias` |
| `testDieRollenBildenNurDenZugriffsschutzAb` | `testRolesOnlyMapAccessControl` |

**`UserProvisioningTest`** · 8 Tests, 20 Assertions

| alt | neu |
|---|---|
| `testEinNeuerBenutzerHatEinGesperrtesPasswort` | `testNewUserHasALockedPassword` |
| `testDerBenutzernameTaugtNichtAlsPasswort` | `testUserNameDoesNotWorkAsPassword` |
| `testKennungUndHerkunftStehenLesbarInEigenenFeldern` | `testIdentifierAndOriginAreStoredReadablyInSeparateFields` |
| `testZweiProviderMitDerselbenKennungErgebenZweiKonten` | `testTwoProvidersWithTheSameIdentifierYieldTwoAccounts` |
| `testEinNeuerBenutzerBekommtKeineAdminrechte` | `testNewUserGetsNoAdminRights` |
| `testEinVorhandenerBenutzerWirdWiedergefunden` | `testExistingUserIsFoundAgain` |
| `testEinVorhandenerBenutzerWirdNichtNachtraeglichGesperrt` | `testExistingUserIsNotLockedRetroactively` |
| `testEinGesperrtesPasswortWirdNichtUmgeschluesselt` | `testLockedPasswordIsNotRehashed` |

**Nachweis.** Je Datei sind Test- und Assertion-Zahl vor und nach dem Task identisch; der Vergleich
läuft über die JUnit-Logs der vollen Suite, mit den Umbenennungen als Paaren. Volle Suite
`OK (528 tests, 1703 assertions)`, PHPStan `[OK] No errors` (Result-Cache geleert),
Deprecation-Gate grün, `rector --dry-run` ohne Änderung. Der Detektor des Sprachwächters findet in
allen Unit-Tests und Fixtures 16 Stellen, alle oben eingeordnet oder als Zitat früherer Testnamen
in Kommentaren (`PluginManagerTest`, `FieldEncryptionTest`). Wie damit umzugehen ist, entscheidet
`014-005-0006`, wenn der Wächter `tests/` übernimmt.
