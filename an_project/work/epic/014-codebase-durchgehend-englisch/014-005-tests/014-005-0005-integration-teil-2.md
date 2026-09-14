---
id: 014-005-0005
title: Integrationstests Teil 2 auf Englisch
status: done
depends_on: [014-005-0004]
---

# Integrationstests Teil 2 auf Englisch

## Context
Umfang: die übrigen Integrationstests unter `tests/Integration/Api/`:
`ConstraintApiTest`, `FileApiTest`, `LogSideEffectApiTest`, `ManyToManyApiTest`,
`MultiupdateApiTest`, `QueryApiTest`, `ReadApiTest`, `ReadPermissionApiTest`,
`RouteSecurityApiTest`, `SyncApiTest`, `TreeApiTest`, `UnenforcedPermissionApiTest`,
`UpdateReplaceApiTest`, `WriteApiTest` und `WritePermissionApiTest`.

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

**Die übrigen 15 Integrationstests unter `tests/Integration/Api/` sind englisch.** Keine der Klassen
hatte einen deutschen Namen; umbenannt sind Methoden, Helfer, Variablen, Testdaten, Meldungen und
Kommentare. Drei Agenten haben übersetzt. Die Assertion-Zahl je Datei ist mit der vollen Suite
gegen den Testserver nachgewiesen.

**Zwei Methodennamen waren schon auf Deutsch falsch.** Die wörtliche Übersetzung hätte den Fehler
übernommen:

| alt | wörtlich | tatsächlich geprüft, neuer Name |
|---|---|---|
| `testUnbekannteEntityLiefert500` | `…Returns500` | 404, `testUnknownEntityReturns404` |
| `testSingleOhneTokenLiefert500` | `…Returns500` | 401, `testSingleWithoutTokenReturns401` |

Die Namen stammen aus der Charakterisierung, als beide Fälle mit 500 antworteten. Mit `006-002-0006`
wurden die Assertions an den neuen Stack angepasst (404 und 401), die Namen nicht. Das Verhalten ist
unverändert.

**SQL-Platzhalter mitgezogen:** `:titel` und `:typ` waren Namen gebundener Parameter, keine Spalten.
Sie heissen in fünf Dateien `:title` und `:type`; Spalten- und Tabellennamen sind unverändert.

**Bewusst deutsch geblieben:** Zitate früherer Testnamen in Kommentaren („The test was called …"),
etwa in `FileApiTest`, `MultiupdateApiTest`, `SyncApiTest` und `UnenforcedPermissionApiTest`. Wie
damit umzugehen ist, entscheidet `014-005-0006`. Die Log-Modi `INS`/`UPT`/`DEL`/`USERDEL`,
Meldungen des Frameworks, JSON-Schlüssel und Routen sind ohnehin englisch und unverändert.

**Nachgezogen:** `FieldEncryptionTest` verweist auf `ConstraintApiTest::testNoEntityUsesEncodedEncryption`,
`ManyToManyApiTest` auf `WritePermissionApiTest::testTheWriteCheckInMultijoinTypeCannotBeTriggered()`.

**Umbenannt** (gepaart über die Deklarationsreihenfolge vor und nach dem Task):

**`ConstraintApiTest`** · 7 Tests, 17 Assertions

| alt | neu |
|---|---|
| `testEineUniqueVerletzungWirdAbgewiesen` | `testUniqueViolationIsRejected` |
| `testEineUniqueVerletzungLaesstKeineHalbeZeileZurueck` | `testUniqueViolationLeavesNoHalfWrittenRow` |
| `testDasSchemaWeistDieUniqueEigenschaftAus` | `testSchemaExposesTheUniqueProperty` |
| `testBaseSortableEntitiesWerdenAlsSortierbarGefuehrt` | `testBaseSortableEntitiesAreMarkedAsSortable` |
| `testSortRestrictToWirdAusDerAnnotationUebernommen` | `testSortRestrictToIsTakenFromTheAnnotation` |
| `testKeineEntityNutztDieEncodedVerschluesselung` | `testNoEntityUsesEncodedEncryption` |
| `testEsGibtKeineOnejoinEigenschaftFuerDieLoeschKaskade` | `testThereIsNoOnejoinPropertyForTheDeleteCascade` |

**`FileApiTest`** · 11 Tests, 21 Assertions

| alt | neu |
|---|---|
| `testAnmeldungLiefertEinToken` | `testLoginReturnsAToken` |
| `testUploadLegtEineDateiAnUndLiefertIhreId` | `testUploadCreatesAFileAndReturnsItsId` |
| `testHochgeladeneDateiLiegtByteGleichAufDerPlatte` | `testUploadedFileIsStoredByteIdenticalOnDisk` |
| `testAuslieferungAntwortetMitRedirectAufDieDatei` | `testDeliveryRespondsWithRedirectToTheFile` |
| `testAuslieferungLiefertDenInhalt` | `testDeliveryReturnsTheContent` |
| `testAuslieferungBrauchtKeinenToken` | `testDeliveryRequiresNoToken` |
| `testUnbekannteIdLiefertKeineDatei` | `testUnknownIdReturnsNoFile` |
| `testUploadOhneTokenWirdAbgewiesen` | `testUploadWithoutTokenIsRejected` |
| `testUeberschreibenErsetztDenInhaltDesZiels` | `testOverwriteReplacesTheContentOfTheTarget` |
| `testUeberschreibenVerlangtGleicheDateinamen` | `testOverwriteRequiresIdenticalFileNames` |
| `testUploadUebernimmtDieGemeldeteDateigroesse` | `testUploadTakesOverTheReportedFileSize` |

**`LogSideEffectApiTest`** · 9 Tests, 22 Assertions

| alt | neu |
|---|---|
| `testInsertSchreibtEineLogZeileMitModusINS` | `testInsertWritesALogRowWithModeINS` |
| `testUpdateSchreibtEineLogZeileMitModusUPT` | `testUpdateWritesALogRowWithModeUPT` |
| `testDeleteSchreibtEineLogZeileMitModusDEL` | `testDeleteWritesALogRowWithModeDEL` |
| `testDerGanzeLebenszyklusHinterlaesstDreiZeilen` | `testTheWholeLifecycleLeavesThreeRows` |
| `testDerZeitstempelDerLogZeilenHatNurSekundenaufloesung` | `testLogRowTimestampHasOnlySecondResolution` |
| `testModelLabelWirdAusDerLabelPropertyGefuellt` | `testModelLabelIsFilledFromTheLabelProperty` |
| `testModelLabelHaeltDenWertZumZeitpunktDerOperation` | `testModelLabelKeepsTheValueAtTheTimeOfTheOperation` |
| `testEntziehenEinesBenutzersSchreibtEineUSERDELZeile` | `testRevokingAUserWritesAUSERDELRow` |
| `testEinAbgewiesenerSchreibversuchHinterlaesstKeineLogZeile` | `testARejectedWriteAttemptLeavesNoLogRow` |

**`ManyToManyApiTest`** · 9 Tests, 28 Assertions

| alt | neu |
|---|---|
| `testEineVerknuepfteDateiLiefertIhreTagsAlsVolleObjekte` | `testLinkedFileReturnsItsTagsAsFullObjects` |
| `testEineDateiOhneTagsLiefertEineLeereListe` | `testFileWithoutTagsReturnsAnEmptyList` |
| `testDasSchemaBeschreibtDieVerknuepfungstabelle` | `testSchemaDescribesTheJoinTable` |
| `testTagsLassenSichUeberUpdateSetzen` | `testTagsCanBeSetViaUpdate` |
| `testEineNeueMengeErsetztDieAlteVollstaendig` | `testNewSetReplacesTheOldOneCompletely` |
| `testEineLeereMengeLoestAlleVerknuepfungen` | `testEmptySetRemovesAllLinks` |
| `testMitDerDateiVerschwindenAuchIhreVerknuepfungen` | `testLinksDisappearTogetherWithTheFile` |
| `testAuchDasLoeschenDesTagsRaeumtDieVerknuepfungAuf` | `testDeletingTheTagAlsoCleansUpTheLink` |
| `testUeberTagsLaesstSichFiltern` | `testFilteringByTagsWorks` |

**`MultiupdateApiTest`** · 8 Tests, 18 Assertions

| alt | neu |
|---|---|
| `testMehrereObjekteWerdenInEinemAufrufGeaendert` | `testMultipleObjectsAreUpdatedInOneCall` |
| `testDieAntwortNenntZeitstempelUndDieGeaendertenObjekte` | `testResponseListsTimestampAndUpdatedObjects` |
| `testTeilfehlerRolltDenGanzenStapelZurueck` | `testPartialFailureRollsBackTheWholeBatch` |
| `testEinFehlerHinterlaesstAuchKeineProtokollzeile` | `testFailureAlsoLeavesNoLogRow` |
| `testDerFehlerfallMeldetKeineGeaendertenObjekte` | `testFailureResponseReportsNoUpdatedObjects` |
| `testMultiupdateOhneTokenAendertNichts` | `testMultiupdateWithoutTokenChangesNothing` |
| `testLeererStapelIstKeinFehler` | `testEmptyBatchIsNotAnError` |
| `testEinFehlendesObjectsIstEinFehlerUndKeinLeerlauf` | `testMissingObjectsIsAnErrorNotANoOp` |

**`QueryApiTest`** · 9 Tests, 16 Assertions

| alt | neu |
|---|---|
| `testQueryAlsAdminIstErlaubtUndEchotDieParameter` | `testQueryAsAdminIsAllowedAndEchoesTheParameters` |
| `testQueryFuerNichtAdminMitFreigegebenerGruppeIstErlaubt` | `testQueryForNonAdminWithEnabledGroupIsAllowed` |
| `testQueryFuerNichtAdminOhneFreigabeIstVerboten` | `testQueryForNonAdminWithoutEnablementIsForbidden` |
| `testQueryOhneSelectWirdAbgewiesen` | `testQueryWithoutSelectIsRejected` |
| `testQueryOhneFromWirdAbgewiesen` | `testQueryWithoutFromIsRejected` |
| `testQueryOhneTokenLiefertKeineDaten` | `testQueryWithoutTokenReturnsNoData` |
| `testTranslationsFuerEineEntityOhneI18nWirft` | `testTranslationsForEntityWithoutI18nThrows` |
| `testTranslationsOhneTokenLiefertKeineDaten` | `testTranslationsWithoutTokenReturnsNoData` |
| `testAppLanguagesIstInDerVorlageLeer` | `testAppLanguagesIsEmptyInTheTemplate` |

**`ReadApiTest`** · 13 Tests, 39 Assertions

| alt | neu |
|---|---|
| `testSingleLiefertDasObjektImStandardEnvelope` | `testSingleReturnsTheObjectInTheStandardEnvelope` |
| `testDatumsfelderKommenAlsViererGruppe` | `testDateFieldsComeAsGroupOfFour` |
| `testVerschachteltesObjektTraegtAlleEigenschaften` | `testNestedObjectCarriesAllProperties` |
| `testUnbekannteIdLiefert404` | `testUnknownIdReturns404` |
| `testUnbekannteEntityLiefert500` | `testUnknownEntityReturns404` |
| `testSingleOhneTokenLiefert500` | `testSingleWithoutTokenReturns401` |
| `testListLiefertEinenAnderenEnvelopeAlsSingle` | `testListReturnsADifferentEnvelopeThanSingle` |
| `testListSortiertOhneOrderParameterNachIdAbsteigend` | `testListWithoutOrderParameterSortsByIdDescending` |
| `testSortByUndSortOrderStehenImSchemaWirkenAberNichtAufDieAntwort` | `testSortByAndSortOrderAreInTheSchemaButDoNotAffectTheResponse` |
| `testOrderParameterBestimmtDieReihenfolge` | `testOrderParameterDeterminesTheOrder` |
| `testPropertiesSchraenktDieFeldmengeEin` | `testPropertiesRestrictsTheFieldSet` |
| `testPartialSelectLiefertDieLabelPropertyDesVerjointenZiels` | `testPartialSelectReturnsTheLabelPropertyOfTheJoinedTarget` |
| `testListOhneTokenLiefertKeineDaten` | `testListWithoutTokenReturnsNoData` |

**`ReadPermissionApiTest`** · 10 Tests, 24 Assertions

| alt | neu |
|---|---|
| `testMitStufeAllSindAlleObjekteSichtbar` | `testWithLevelAllAllObjectsAreVisible` |
| `testMitStufeOwnIstNurEigenesSichtbar` | `testWithLevelOwnOnlyOwnObjectsAreVisible` |
| `testMitStufeOwnMachtDieUsersListeEinObjektSichtbar` | `testWithLevelOwnTheUsersListMakesAnObjectVisible` |
| `testMitStufeGroupIstEigenesUndGruppenGeteiltesSichtbar` | `testWithLevelGroupOwnAndGroupSharedObjectsAreVisible` |
| `testOhneLeserechtWirftDieListeStattEineLeereMengeZuLiefern` | `testWithoutReadPermissionListThrowsInsteadOfReturningAnEmptySet` |
| `testOhneLeserechtWirftAuchSingle` | `testWithoutReadPermissionSingleAlsoThrows` |
| `testEinBenutzerOhneGruppeSiehtNichts` | `testUserWithoutGroupSeesNothing` |
| `testEinAdminUebergehtAlleStufen` | `testAdminBypassesAllLevels` |
| `testEinNichtLesbaresVerjointesObjektKommtAlsPimBlocked` | `testUnreadableJoinedObjectComesAsPimBlocked` |
| `testMitLeserechtAufDerZielentityKommtDasVerjointeObjektGanz` | `testWithReadPermissionOnTargetEntityJoinedObjectComesInFull` |

**`RouteSecurityApiTest`** · 10 Tests, 20 Assertions

| alt | neu |
|---|---|
| `testEineGesicherteRouteWeistOhneTokenAb` | `testSecuredRouteRejectsWithoutToken` |
| `testEineGesicherteRouteAntwortetMitToken` | `testSecuredRouteRespondsWithToken` |
| `testEineLeereListeKommtAls200` | `testEmptyListComesAs200` |
| `testEineUnbekannteEntityBleibtEin404MitBegruendung` | `testUnknownEntityRemainsA404WithReason` |
| `testEineUngesicherteRouteAntwortetOhneToken` | `testUnsecuredRouteRespondsWithoutToken` |
| `testDieUngesicherteRouteAntwortetAuchMitToken` | `testUnsecuredRouteAlsoRespondsWithToken` |
| `testApiConfigIstOhneTokenErreichbar` | `testApiConfigIsReachableWithoutToken` |
| `testDerAfterHookAusCustomAppSetztDenReferrerPolicyHeader` | `testAfterHookFromCustomAppSetsTheReferrerPolicyHeader` |
| `testDerAfterHookGreiftAuchAufEinerGesichertenRoute` | `testAfterHookAlsoAppliesOnASecuredRoute` |
| `testDieSprachrechtePruefungIstUeberDieApiNichtAusloesbar` | `testLanguagePermissionCheckCannotBeTriggeredViaTheApi` |

**`SyncApiTest`** · 13 Tests, 36 Assertions

| alt | neu |
|---|---|
| `testAllLiefertDieDatenAllerSynchronisierbarenEntities` | `testAllReturnsTheDataOfAllSyncableEntities` |
| `testAllSchliesstDieselbenEntitiesAusWieDeleted` | `testAllExcludesTheSameEntitiesAsDeleted` |
| `testAllOhneTokenLiefertKeineDaten` | `testAllWithoutTokenReturnsNoData` |
| `testDeletedLiefertEineListeImStandardEnvelope` | `testDeletedReturnsAListInTheStandardEnvelope` |
| `testDeletedLiefertEineFlacheListeRoherLogZeilen` | `testDeletedReturnsAFlatListOfRawLogRows` |
| `testDeletedSchliesstAusWasExcludeFromSyncSetzt` | `testDeletedExcludesWhatExcludeFromSyncSets` |
| `testZweiLoeschungenInDerselbenSekundeKommenBeide` | `testTwoDeletionsInTheSameSecondAreBothReturned` |
| `testDeletedOhneTokenLiefertKeineDaten` | `testDeletedWithoutTokenReturnsNoData` |
| `testCountIstEineGlobaleStatistikKeinGefilterterZaehler` | `testCountIsAGlobalStatisticNotAFilteredCounter` |
| `testCountWeistDieAnzahlJeEntityInDetailsAus` | `testCountReportsTheNumberPerEntityInDetails` |
| `testCountOhneTokenLiefertKeineDaten` | `testCountWithoutTokenReturnsNoData` |
| `testSiebenEntitiesSetzenExcludeFromSync` | `testSevenEntitiesSetExcludeFromSync` |
| `testDieAusschlussliegtNichtMehrImCode` | `testTheExclusionIsNoLongerInTheCode` |

**`TreeApiTest`** · 9 Tests, 34 Assertions

| alt | neu |
|---|---|
| `testTreeHaengtKinderAlsTreeChildsAn` | `testTreeAttachesChildrenAsTreeChilds` |
| `testTreeSerialisiertWieDerRestDerApi` | `testTreeSerialisesLikeTheRestOfTheApi` |
| `testTreePropertiesSchraenktDieFeldmengeEin` | `testTreePropertiesRestrictsTheFieldSet` |
| `testTreeOhneTokenLiefertKeineDaten` | `testTreeWithoutTokenReturnsNoData` |
| `testTree2HaengtKinderAlsChildsAnUndTraegtParent` | `testTree2AttachesChildrenAsChildsAndCarriesParent` |
| `testTree2ReichtDieRohwerteDerDatenbankDurch` | `testTree2PassesThroughTheRawDatabaseValues` |
| `testTree2LiefertAlleSkalarenFelder` | `testTree2ReturnsAllScalarFields` |
| `testTree2QuotetSpaltennamenUndVertraegtDasReservierteWortGroups` | `testTree2QuotesColumnNamesAndHandlesTheReservedWordGroups` |
| `testTree2OhneTokenLiefertKeineDaten` | `testTree2WithoutTokenReturnsNoData` |

**`UnenforcedPermissionApiTest`** · 7 Tests, 17 Assertions

| alt | neu |
|---|---|
| `testDasMasterPasswortGibtEsNichtMehr` | `testTheMasterPasswordNoLongerExists` |
| `testEineFalscheAnmeldungScheitert` | `testAWrongLoginFails` |
| `testDerPermissionsBlockFuehrtNurNochDieDreiDurchgesetztenRechte` | `testThePermissionsBlockListsOnlyTheThreeEnforcedRights` |
| `testAuchFuerEinenNichtAdminKommenDieBeidenFelderNichtMehr` | `testEvenForANonAdminTheTwoFieldsNoLongerAppear` |
| `testDieSpaltenBleibenErhaltenUndLesbar` | `testTheColumnsRemainAndAreReadable` |
| `testOhneExportRechtLaesstSichTrotzdemAllesTunWasDieApiAnbietet` | `testWithoutExportRightEverythingTheApiOffersIsStillPossible` |
| `testEinExtendedEintragAendertDieAntwortNicht` | `testAnExtendedEntryDoesNotChangeTheResponse` |

**`UpdateReplaceApiTest`** · 7 Tests, 21 Assertions

| alt | neu |
|---|---|
| `testUpdateLaesstNichtGesendeteFelderUnberuehrt` | `testUpdateLeavesFieldsNotSentUntouched` |
| `testReplaceLaesstNichtGesendeteFelderEbenfallsUnberuehrt` | `testReplaceAlsoLeavesFieldsNotSentUntouched` |
| `testReplaceLegtEinNichtVorhandenesObjektMitDerVorgegebenenIdAn` | `testReplaceCreatesANonExistingObjectWithTheGivenId` |
| `testUpdateAufEineUnbekannteIdScheitert` | `testUpdateOnAnUnknownIdFails` |
| `testBeideAktualisierenModified` | `testBothUpdateModified` |
| `testUpdateOhneTokenAendertNichts` | `testUpdateWithoutTokenChangesNothing` |
| `testReplaceOhneTokenLegtNichtsAn` | `testReplaceWithoutTokenCreatesNothing` |

**`WriteApiTest`** · 12 Tests, 33 Assertions

| alt | neu |
|---|---|
| `testInsertLiefertDieErzeugteIdAufOberSterEbene` | `testInsertReturnsTheGeneratedIdOnTheTopLevel` |
| `testInsertErzeugtEineGuid` | `testInsertGeneratesAGuid` |
| `testInsertSetztCreatedModifiedUndUserCreated` | `testInsertSetsCreatedModifiedAndUserCreated` |
| `testDasAngelegteObjektIstUeberSingleAbrufbar` | `testTheCreatedObjectCanBeFetchedViaSingle` |
| `testInsertUndSingleStellenBoolescheWerteUnterschiedlichDar` | `testInsertAndSingleRepresentBooleanValuesDifferently` |
| `testInsertOhneDatenWirdAbgewiesen` | `testInsertWithoutDataIsRejected` |
| `testInsertMitUnbekannterEntityWirdAbgewiesen` | `testInsertWithUnknownEntityIsRejected` |
| `testInsertOhneTokenLegtNichtsAn` | `testInsertWithoutTokenCreatesNothing` |
| `testDeleteEntferntDasObjekt` | `testDeleteRemovesTheObject` |
| `testNachDemLoeschenLiefertSingleEinen404` | `testAfterDeletingSingleReturnsA404` |
| `testDeleteMitUnbekannterIdWirdAbgewiesen` | `testDeleteWithUnknownIdIsRejected` |
| `testDeleteOhneTokenLoeschtNichts` | `testDeleteWithoutTokenDeletesNothing` |

**`WritePermissionApiTest`** · 10 Tests, 23 Assertions

| alt | neu |
|---|---|
| `testInsertPruefetNurDasRechtAufDieEntity` | `testInsertChecksOnlyTheRightOnTheEntity` |
| `testOhneSchreibrechtEntstehtNichts` | `testWithoutWriteRightNothingIsCreated` |
| `testMitStufeOwnLaesstSichDasEigeneObjektAendern` | `testWithLevelOwnTheOwnObjectCanBeChanged` |
| `testMitStufeOwnBleibtEinFremdesObjektUnveraendert` | `testWithLevelOwnAForeignObjectStaysUnchanged` |
| `testMitStufeGroupZaehltDieGruppeDesObjekts` | `testWithLevelGroupTheGroupOfTheObjectCounts` |
| `testOhneLoeschrechtBleibtDasObjektBestehen` | `testWithoutDeleteRightTheObjectRemains` |
| `testMitLoeschrechtOwnBleibtEinFremdesObjektBestehen` | `testWithDeleteRightOwnAForeignObjectRemains` |
| `testMitStufeOwnDarfSichEinBenutzerImmerSelbstAendern` | `testWithLevelOwnAUserMayAlwaysChangeThemselves` |
| `testDieSchreibpruefungInMultijoinTypeIstNichtAusloesbar` | `testTheWriteCheckInMultijoinTypeCannotBeTriggered` |
| `testEinAdminSchreibtUndLoeschtOhneBerechtigungszeile` | `testAnAdminWritesAndDeletesWithoutAPermissionRow` |

**Nachweis.** Je Datei sind Test- und Assertion-Zahl vor und nach dem Task identisch; der Vergleich
läuft über die JUnit-Logs der vollen Suite gegen den Testserver. Volle Suite
`OK (528 tests, 1703 assertions)`, PHPStan `[OK] No errors` (Result-Cache geleert),
Deprecation-Gate grün.
