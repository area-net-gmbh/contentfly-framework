---
id: 014-005-0002
title: Unit-Tests Kernel, Manager, Entity, Service und Ci auf Englisch
status: review
depends_on: [014-005-0001]
---

# Unit-Tests Kernel, Manager, Entity, Service und Ci auf Englisch

## Context
Umfang: `tests/Unit/AutoloaderUeberschneidungTest.php`, alle Dateien unter `tests/Unit/Kernel/`,
`tests/Unit/Manager/`, `tests/Unit/Entity/`, `tests/Unit/Service/` und `tests/Unit/Ci/`.

Deutsch benannte Klassen bekommen englische Namen, darunter `AutoloaderUeberschneidungTest`,
`KeineSilexTypenTest`, `PaketmanifestTest`, `HookReihenfolgeTest`, `RoutenNamenTest`,
`LoginProviderAufloesungTest` und `CiSchritteTest`. Die Verweise darauf in `lib/`, `custom/` und
den Ausnahmen des Sprachwächters ziehen mit.

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

**Die Unit-Tests für Kernel, Manager, Entity, Service und Ci sind englisch.** Das gilt für 16 Dateien
mit Klassen, Methoden, Helfern, Konstanten, Testdaten, Assertion-Meldungen und Kommentaren. Sieben
Testklassen sind samt Datei umbenannt (`git mv`). Die Übersetzung haben zwei Agenten gemacht;
nachgeprüft ist sie über die Zahlen je Datei, einen Scan auf deutsche Reste und das Durchsehen der
Grenzfälle.

**Bewusst deutsch geblieben** ist, was ein Test gegen Dateien ausserhalb von `tests/` abgleicht:
Skript- und Schrittnamen aus `tools/ci/` in `CiStepsTest` (etwa `audit-ausnahmen-pruefen.sh`) und
`providerKlasse` in `LoginProviderResolutionTest`. Letzteres ist der alte Methodenname, dessen
Abwesenheit der Test zusichert.

**Drei Anpassungen, die ein reines Übersetzen verfehlt hätte:**
- `NoSilexTypesTest` schliesst sich über `THIS_FILE` selbst aus der Suche aus. Mit dem alten Pfad
  hätte der Test seine eigene Datei gefunden und wäre rot geworden.
- Der Helfer `lauf()` in `HookOrderTest` heisst `runRequest()`, nicht `run()`. `run()` hätte
  `TestCase::run()` von PHPUnit überschrieben.
- Veraltete Framework-Namen in Kommentaren und Fehlermeldungen (`Start::keinZweiterBaum()`,
  `Paths::daten()`, `paket()`) nennen jetzt die heutigen Methoden. Jede davon ist in `lib/`
  nachgeschlagen.

**Mitgezogene Verweise:** `lib/contentfly/bootstrap.php` (`AutoloaderOverlapTest`,
`NoSilexTypesTest`), `lib/contentfly/composer.json` samt Metadaten in `composer.lock`
(`PackageManifestTest`; keine Versionsänderung), `custom/app.php` und `VorlageApiTest`
(`HookOrderTest`) sowie `architecture.md`, `technical.md` und `deployment.md`. Die vier Ausnahmen
des Sprachwächters für diese Namen sind entfernt; `testEveryExceptionIsStillNeeded()` hätte sie
sonst gemeldet. `STRUCTURE.md` nennt noch `CiSchritteTest`; die Datei wird mit `014-006`
überarbeitet.

**Umbenannt** (gepaart über die Deklarationsreihenfolge vor und nach dem Task):

**`AutoloaderOverlapTest`** (vorher `AutoloaderUeberschneidungTest`) · 4 Tests, 7 Assertions

| alt | neu |
|---|---|
| `testDerRootBaumExistiertUndIstNichtLeer` | `testTheRootTreeExistsAndIsNotEmpty` |
| `testEsGibtKeinenZweitenComposerBaum` | `testThereIsNoSecondComposerTree` |
| `testDasFrameworkLaedtKeinenAutoloader` | `testTheFrameworkLoadsNoAutoloader` |
| `testJedeAusnahmeWirdNochGebraucht` | `testEveryExceptionIsStillNeeded` |

**`CiStepsTest`** (vorher `CiSchritteTest`) · 4 Tests, 12 Assertions

| alt | neu |
|---|---|
| `testEinFehlgeschlagenerSchrittZeigtSeineAusgabe` | `testAFailedStepShowsItsOutput` |
| `testEinGelungenerSchrittBleibtStill` | `testASuccessfulStepStaysSilent` |
| `testKeinSchrittSchweigtAnDerFunktionVorbei` | `testNoStepStaysSilentBypassingTheFunction` |
| `testJedeAusnahmeWirdNochGebraucht` | `testEveryExceptionIsStillNeeded` |

**`GroupLanguagePermissionTest`** · 9 Tests, 14 Assertions

| alt | neu |
|---|---|
| `testOhneSprachrechteIstJedeSpracheSchreibbar` | `testWithoutLanguagePermissionsEveryLanguageIsWritable` |
| `testOhneSprachrechteIstJedeSpracheUebersetzbar` | `testWithoutLanguagePermissionsEveryLanguageIsTranslatable` |
| `testOhneSprachrechteIstKeineSpracheNurLesbar` | `testWithoutLanguagePermissionsNoLanguageIsOnlyReadable` |
| `testEineNichtAufgefuehrteSpracheIstSchreibbar` | `testALanguageThatIsNotListedIsWritable` |
| `testEineAufgefuehrteSpracheIstNichtSchreibbar` | `testAListedLanguageIsNotWritable` |
| `testNurLesbarBedeutetWederSchreibbarNochUebersetzbar` | `testReadOnlyMeansNeitherWritableNorTranslatable` |
| `testUebersetzbarIstNichtSchreibbarAberUebersetzbar` | `testTranslatableIsNotWritableButTranslatable` |
| `testSprachrechteWerdenAlsJsonGehaltenUndWiederGelesen` | `testLanguagePermissionsAreStoredAsJsonAndReadBack` |
| `testEinLeererWertLaesstDieSprachrechteUngesetzt` | `testAnEmptyValueLeavesTheLanguagePermissionsUnset` |

**`ContainerTest`** · 12 Tests, 16 Assertions

| alt | neu |
|---|---|
| `testEinWertKommtZurueckWieErAbgelegtWurde` | `testAValueComesBackAsItWasStored` |
| `testEineClosureIstEineFactoryUndLaeuftErstBeimZugriff` | `testAClosureIsAFactoryAndOnlyRunsOnAccess` |
| `testDieFactoryBekommtDenContainerAlsArgument` | `testTheFactoryReceivesTheContainerAsArgument` |
| `testEineFactoryLaeuftGenauEinmal` | `testAFactoryRunsExactlyOnce` |
| `testEinUnbekannterSchluesselWirftStattNullZuLiefern` | `testAnUnknownKeyThrowsInsteadOfReturningNull` |
| `testIssetUndUnsetWirkenWieErwartet` | `testIssetAndUnsetWorkAsExpected` |
| `testEinEintragMitNullGiltAlsVorhanden` | `testAnEntryWithNullCountsAsPresent` |
| `testExtendUmschliesstDieAlteFactory` | `testExtendWrapsTheOldFactory` |
| `testExtendNachDemErstenZugriffWirft` | `testExtendAfterTheFirstAccessThrows` |
| `testExtendAufEinemWertWirft` | `testExtendOnAValueThrows` |
| `testNeuSetzenHebtDasEinfrierenAuf` | `testSettingAgainLiftsTheFreeze` |
| `testKeysLiefertDieSchluesselInRegistrierungsreihenfolge` | `testKeysReturnsTheKeysInRegistrationOrder` |

**`HookOrderTest`** (vorher `HookReihenfolgeTest`) · 6 Tests, 9 Assertions

| alt | neu |
|---|---|
| `testEineHoeherePrioritaetLaeuftFrueherUnabhaengigVonDerRegistrierung` | `testAHigherPriorityRunsEarlierRegardlessOfRegistration` |
| `testGleichePrioritaetLaeuftInRegistrierungsreihenfolge` | `testEqualPriorityRunsInRegistrationOrder` |
| `testEinBeforeHookDerEineResponseZurueckgibtBrichtAb` | `testABeforeHookReturningAResponseAborts` |
| `testDerBeforeHookBekommtRequestUndAnwendung` | `testTheBeforeHookReceivesRequestAndApplication` |
| `testEinHookVerhindertKeineSpaetereCommandRegistrierung` | `testAHookDoesNotPreventLaterCommandRegistration` |
| `testAfterHooksLaufenNachPrioritaetUndSehenDieAntwort` | `testAfterHooksRunByPriorityAndSeeTheResponse` |

**`NoSilexTypesTest`** (vorher `KeineSilexTypenTest`) · 2 Tests, 2 Assertions

| alt | neu |
|---|---|
| `testKeineDateiAusserhalbDerListeNenntSilexPimpleOderKnp` | `testNoFileOutsideTheListNamesSilexPimpleOrKnp` |
| `testJedeErlaubteStelleWirdNochGebraucht` | `testEveryAllowedEntryIsStillNeeded` |

**`LoadMetadataTest`** · 3 Tests, 5 Assertions

| alt | neu |
|---|---|
| `testEineEntityMitModifiedBekommtDenIndex` | `testAnEntityWithModifiedGetsTheIndex` |
| `testEineEntityOhneModifiedWirdUebersprungen` | `testAnEntityWithoutModifiedIsSkipped` |
| `testBaeumeBleibenAusgenommen` | `testTreesRemainExcluded` |

**`LoginProviderResolutionTest`** (vorher `LoginProviderAufloesungTest`) · 2 Tests, 4 Assertions

| alt | neu |
|---|---|
| `testDieAufloesungUeberKlassennamenGibtEsNichtMehr` | `testResolutionByClassNameNoLongerExists` |
| `testKeinKlassennameWirdMehrAusEinemParameterGebaut` | `testNoClassNameIsBuiltFromAParameterAnyMore` |

**`PackageManifestTest`** (vorher `PaketmanifestTest`) · 5 Tests, 20 Assertions

| alt | neu |
|---|---|
| `testDieVersionImManifestStimmtMitVersionPhpUeberein` | `testTheVersionInTheManifestMatchesVersionPhp` |
| `testDasPaketTraegtNurDenNamensraumDesFrameworks` | `testThePackageCarriesOnlyTheFrameworkNamespace` |
| `testDasProjektFuehrtDenNamensraumDesFrameworksNicht` | `testTheProjectDoesNotCarryTheFrameworkNamespace` |
| `testDasPaketLiefertKeineWerkzeugeMit` | `testThePackageShipsNoTools` |
| `testDasManifestLiegtInDerPaketwurzel` | `testTheManifestIsInThePackageRoot` |

**`PathsTest`** · 6 Tests, 15 Assertions

| alt | neu |
|---|---|
| `testOhneGesetztesVerzeichnisWirftJederZugriff` | `testEveryAccessThrowsWithoutASetDirectory` |
| `testEinVerzeichnisDasEsNichtGibtWirdBeimSetzenAbgewiesen` | `testANonExistentDirectoryIsRejectedWhenSet` |
| `testDiePfadeHaengenAmUebergebenenVerzeichnis` | `testThePathsDependOnThePassedDirectory` |
| `testDasPaketverzeichnisBrauchtKeinProjekt` | `testThePackageDirectoryNeedsNoProject` |
| `testKeinSprungAusDemFrameworkHeraus` | `testNoJumpOutOfTheFramework` |
| `testJedeAusnahmeWirdNochGebraucht` | `testEveryExceptionIsStillNeeded` |

**`RouteNamesTest`** (vorher `RoutenNamenTest`) · 2 Tests, 2 Assertions

| alt | neu |
|---|---|
| `testZweiMountpunkteMitGleichemPfadVerlierenKeineRoute` | `testTwoMountPointsWithTheSamePathLoseNoRoute` |
| `testDerRoutennameTraegtDenMountpunkt` | `testTheRouteNameCarriesTheMountPoint` |

**`StartTest`** · 4 Tests, 8 Assertions

| alt | neu |
|---|---|
| `testEinVerzeichnisDasEsNichtGibtWirdAbgewiesen` | `testANonExistentDirectoryIsRejected` |
| `testOhneKonfigurationBrichtDerStartMitEinerMeldungDarueberAb` | `testWithoutConfigurationTheStartAbortsWithAMessageAboutIt` |
| `testEinZweiterComposerBaumWirdAbgewiesenStattUebergangen` | `testASecondComposerTreeIsRejectedInsteadOfIgnored` |
| `testOhneKonfigurationUndMitZweitemBaumGewinntDerZweiteBaum` | `testWithoutConfigurationAndWithASecondTreeTheSecondTreeWins` |

**`PluginManagerTest`** · 10 Tests, 17 Assertions

| alt | neu |
|---|---|
| `testEinPluginWirdUnterSeinemKeyAbgelegt` | `testAPluginIsStoredUnderItsKey` |
| `testDerKeyUndDerNamespaceKommenAusDemKlassennamen` | `testTheKeyAndTheNamespaceComeFromTheClassName` |
| `testEinUnbekanntesPluginWirdMitEinerAusnahmeAbgewiesen` | `testAnUnknownPluginIsRejectedWithAnException` |
| `testEineKlasseDieNichtVonPluginErbtWirdAbgewiesen` | `testAClassThatDoesNotExtendPluginIsRejected` |
| `testGetPluginNenntDenGesuchtenPluginNamen` | `testGetPluginNamesThePluginBeingLookedFor` |
| `testOhneUseOrmMeldetEinPluginKeineEntities` | `testWithoutUseOrmAPluginReportsNoEntities` |
| `testUseOrmRegistriertEinenAttributeDriverFuerDasPluginVerzeichnis` | `testUseOrmRegistersAnAttributeDriverForThePluginDirectory` |
| `testUseOrmLegtDasEntityVerzeichnisAnWennEsFehlt` | `testUseOrmCreatesTheEntityDirectoryIfItIsMissing` |
| `testMitUseOrmSammeltGetEntitiesDieKlassenAusDemVerzeichnis` | `testWithUseOrmGetEntitiesCollectsTheClassesFromTheDirectory` |
| `testEinPluginKannEinenEigenenFeldtypRegistrieren` | `testAPluginCanRegisterItsOwnFieldType` |

**`RouteAndConsoleManagerTest`** · 8 Tests, 10 Assertions

| alt | neu |
|---|---|
| `testMountLiefertDenProviderZumWeiterverketten` | `testMountReturnsTheProviderForChaining` |
| `testZweiMountsAufDenselbenPfadUeberschreibenSich` | `testTwoMountsOnTheSamePathOverwriteEachOther` |
| `testBindRoutesReichtJedenGesammeltenMountWeiter` | `testBindRoutesPassesOnEveryCollectedMount` |
| `testOhneMountBindetBindRoutesNichts` | `testWithoutMountBindRoutesBindsNothing` |
| `testAddCommandMussVorDemErstenZugriffAufDenDispatcherLaufen` | `testAddCommandMustRunBeforeTheFirstAccessToTheDispatcher` |
| `testAddCommandHaengtEinenListenerAnDenDispatcher` | `testAddCommandAttachesAListenerToTheDispatcher` |
| `testMehrereCommandsErgebenMehrereListener` | `testSeveralCommandsResultInSeveralListeners` |
| `testCustomCommandPraefigiertDenNamenMitCustom` | `testCustomCommandPrefixesTheNameWithCustom` |

**`TypeManagerTest`** · 6 Tests, 9 Assertions

| alt | neu |
|---|---|
| `testEinTypWirdUnterSeinemAliasAbgelegt` | `testATypeIsStoredUnderItsAlias` |
| `testEinUnbekannterAliasLiefertNullStattZuWerfen` | `testAnUnknownAliasReturnsNullInsteadOfThrowing` |
| `testGetTypesLiefertAlleRegistriertenTypenNachAliasGeschluesselt` | `testGetTypesReturnsAllRegisteredTypesKeyedByAlias` |
| `testEinZweiterTypMitDemselbenAliasErsetztDenErsten` | `testASecondTypeWithTheSameAliasReplacesTheFirst` |
| `testEinPluginTypeWirdMitEinerAusnahmeAbgewiesen` | `testAPluginTypeIsRejectedWithAnException` |
| `testEinTypBrauchtKeineAnnotationsdateiMehr` | `testATypeNoLongerNeedsAnAnnotationFile` |

**`ApiDateTimeFormatterTest`** · 6 Tests, 6 Assertions

| alt | neu |
|---|---|
| `testFormatiertInIso8601MitMillisekundenUndOffset` | `testFormatsAsIso8601WithMillisecondsAndOffset` |
| `testRechnetJedeZeitzoneNachUtcUm` | `testConvertsEveryTimezoneToUtc` |
| `testAkzeptiertAuchEinVeraenderlichesDateTime` | `testAlsoAcceptsAMutableDateTime` |
| `testVeraendertDenUebergebenenZeitpunktNicht` | `testDoesNotModifyThePassedPointInTime` |
| `testNullBleibtNull` | `testNullStaysNull` |
| `testNowLiefertDasVereinbarteFormat` | `testNowReturnsTheAgreedFormat` |

**Nachweis.** Je Datei sind Test- und Assertion-Zahl vor und nach dem Task identisch; der Vergleich
läuft über die JUnit-Logs der vollen Suite, mit den Umbenennungen als Paaren. Volle Suite
`OK (528 tests, 1703 assertions)`, PHPStan `[OK] No errors` (Result-Cache geleert),
Deprecation-Gate grün, Template-Config unverändert.
