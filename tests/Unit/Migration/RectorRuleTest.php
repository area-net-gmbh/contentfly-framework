<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * The migration rule for the `Entity/` directory (story `007-002`).
 *
 * ## Why the rule has a test and not just instructions
 * A Rector rule cannot be measured by its run going through — only by what is in the code
 * afterwards. And a rule without a test decays like instructions nobody follows: it silently
 * becomes wrong with the next Rector update, and it would only be noticed by the project that
 * uses it.
 *
 * ## The structure
 * `tests/Fixtures/RectorMigration/before/` carries sample entities in the legacy state,
 * `…/after/` the **hand-written** target state. By hand is the point: generated from a Rector
 * run, the comparison would check the rule against itself.
 *
 * ## What this test checks with `007-002-0001`
 * The frame, not the rule — that does not exist yet. That the reference fixture really is the
 * legacy state, that the target state really is the target state, that the two differ, and
 * that the run goes through. **The piece-by-piece comparisons come with `0002` to `0004`**, and
 * the equality of actual and target state with `0004`.
 */
class RectorRuleTest extends TestCase
{
    /**
     * The seven annotations removed with epic `012`.
     *
     * Source: `an_project/docs/pim-annotationen-migration.md`, section 1. The list appears here
     * a second time — and `testTheListsStillMatchTheDocumentation()` ensures that the two do
     * not drift apart.
     */
    private const REMOVED_ANNOTATIONS = array(
        'Rte', 'Textarea', 'Datetime', 'Time', 'Password', 'MatrixChooser', 'EntitySelector',
    );

    /** The 14 removed fields of `@PIM\Config`. Source: the same file, section 3. */
    private const REMOVED_FIELDS = array(
        'viewMode', 'showInList', 'listShorten', 'hide', 'label', 'tab', 'tabs', 'sort',
        'isDatalist', 'isSidebar', 'lines', 'accept', 'readonly', 'filter',
    );

    /**
     * The fields that `@PIM\Checkbox` and `@PIM\Radio` lost.
     *
     * Source: `pim-annotationen-migration.md`, section 2. They are kept separate from the
     * `Config` fields because they belonged to other annotations — and because the classes
     * themselves are the evidence: both have exactly one constructor parameter today, `group`.
     */
    private const REMOVED_CHOICE_FIELDS = array(
        'horizontalAlignment', 'columns', 'select',
    );

    /**
     * The annotations that remain. Source: the same file, section 2.
     *
     * `Checkbox` and `Radio` are included — they remain, but lose fields.
     */
    private const REMAINING_ANNOTATIONS = array(
        'Config', 'Select', 'Virtualjoin', 'Permissions', 'I18nPermissions', 'Checkbox', 'Radio',
    );

    /** The 10 remaining fields. Source: the same file, section 4. */
    private const REMAINING_FIELDS = array(
        'excludeFromSync', 'encoded', 'isFilterable', 'unique', 'type', 'i18n_universal',
        'sortRestrictTo', 'sortBy', 'sortOrder', 'labelProperty',
    );

    /**
     * The reference fixture really is the legacy state.
     *
     * Without this guarantee all following comparisons would be vacuous: a rule that runs
     * against already migrated code always succeeds.
     */
    public function testTheReferenceFixtureCarriesEveryRemovedAnnotation(): void
    {
        $before = $this->content('before');

        foreach (self::REMOVED_ANNOTATIONS as $annotation) {
            $this->assertStringContainsString(
                '@PIM\\' . $annotation,
                $before,
                sprintf('The reference fixture must carry @PIM\\%s — otherwise the rule checks it against nothing.', $annotation)
            );
        }

        $this->assertStringContainsString('@ORM\\Column', $before);
        $this->assertStringContainsString('@ORM\\JoinTable', $before, 'The nested case is missing.');
    }

    public function testTheReferenceFixtureCarriesEveryRemovedAndEveryRemainingField(): void
    {
        $before = $this->content('before');

        foreach (array_merge(self::REMOVED_FIELDS, self::REMAINING_FIELDS) as $field) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($field, '/') . '\s*=/',
                $before,
                sprintf('The field %s is missing from the reference fixture.', $field)
            );
        }
    }

    /**
     * And the target state really is the target state.
     *
     * What is checked is the **code**, not the comments: the names of the removed annotations
     * appear there on purpose, because the comment explains what happened to them.
     */
    public function testTheTargetStateCarriesNothingRemovedAnyMore(): void
    {
        $after = $this->codeOnly($this->content('after'));

        foreach (self::REMOVED_ANNOTATIONS as $annotation) {
            $this->assertStringNotContainsString('PIM\\' . $annotation, $after);
        }

        foreach (self::REMOVED_FIELDS as $field) {
            $this->assertDoesNotMatchRegularExpression('/\b' . preg_quote($field, '/') . '\s*:/', $after);
        }

        $this->assertStringNotContainsString('@ORM\\', $after, 'The target state contains no ORM annotation any more.');
        $this->assertStringContainsString('#[ORM\\Column', $after);
    }

    /**
     * The remaining fields are still present in the target state — all ten.
     *
     * This is the counter-check to the test above. A target state in which the remaining
     * fields are missing too would be easy to reach and wrong.
     */
    public function testTheTargetStateStillCarriesEveryRemainingField(): void
    {
        $after = $this->codeOnly($this->content('after'));

        foreach (self::REMAINING_FIELDS as $field) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($field, '/') . '\s*:/',
                $after,
                sprintf('The field %s must survive the run, but is not in the target state.', $field)
            );
        }
    }

    public function testBeforeAndAfterDiffer(): void
    {
        $this->assertNotSame(
            $this->content('before'),
            $this->content('after'),
            'If both were identical, there would be nothing to migrate and the comparison would say nothing.'
        );
    }

    /**
     * The run goes through and has something to do.
     *
     * **This test has been inverted (007-002-0002).** With `0001` no rule was registered yet,
     * and it asserted that Rector warns about exactly that ("Register rules or sets"). That
     * guarantee became moot with the first registered rule — and a guarantee that no longer
     * holds is a defect, not legacy.
     *
     * Now the reverse applies: Rector **must** propose something. A run that finds nothing
     * would mean that the rule does not apply or the reference fixture is no longer the legacy
     * state.
     */
    public function testTheRunGoesThroughAndHasSomethingToDo(): void
    {
        $result = $this->runRector();

        /*
         * A TRAP, AND IT POINTS EXACTLY THE WRONG WAY.
         *
         * Rector ends a `--dry-run` with **exit 2** when it has found changes, and with 0 when
         * there was nothing to do. A test that writes `assertSame(0, …)` here — and that was my
         * first draft — is therefore green exactly when the rule does NOT apply.
         *
         * With the applying run (without --dry-run) it is the other way round: 0 means done.
         */
        $this->assertSame(
            2,
            $result['code'],
            "A dry run with findings ends with 2. If it returned 0, the rule found nothing:\n"
            . $result['output']
        );

        $this->assertStringNotContainsString(
            'Register rules or sets',
            $result['output'],
            'A rule is registered — the warning must not appear any more.'
        );

        $this->assertStringContainsString(
            'would have been changed',
            $result['output'],
            'Rector finds nothing to do. Either the rule does not apply, or the reference fixture '
            .'is no longer the legacy state.'
        );
    }

    /**
     * After the run no ORM annotation is left (007-002-0002).
     *
     * This is the first half of the rule, and the only one for which ready-made rules exist:
     * `DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES`.
     */
    public function testNoOrmAnnotationIsLeftAfterTheRun(): void
    {
        $result = $this->runRector(true);

        $this->assertSame(0, $result['code'], $result['output']);

        foreach ($result['files'] as $name => $content) {
            $this->assertDoesNotMatchRegularExpression(
                '/@\\w+\\\\(Entity|Table|Column|ManyToOne|OneToOne|OneToMany|JoinColumn|JoinTable)\\b/',
                $this->effectiveLines($content),
                sprintf('In %s an ORM annotation is still present after the run.', $name)
            );

            /*
             * Alias-independent: the alias probe writes `#[Mapping\Column`, and Rector does not
             * rearrange imports. A check for `ORM` would miss it.
             */
            $this->assertMatchesRegularExpression(
                '/#\\[\\w+\\\\Column/',
                $content,
                sprintf('In %s no ORM attribute was created.', $name)
            );
        }
    }

    /**
     * The nested case — the one where a conversion typically fails, in our experience.
     *
     * `@ORM\JoinTable(joinColumns={@ORM\JoinColumn(…)}, inverseJoinColumns={…})` must become
     * three independent attributes. The task required this as a measurement and not as an
     * assumption — here it is.
     */
    public function testTheNestedCaseIsConvertedCorrectly(): void
    {
        $result   = $this->runRector(true);
        $category = $result['files']['Category.php'] ?? '';

        $this->assertNotSame('', $category, 'Category.php is missing from the result.');

        $this->assertStringContainsString("#[ORM\\JoinTable(name: 'fixture_category_article')]", $category);
        $this->assertStringContainsString("#[ORM\\JoinColumn(name: 'category_id', referencedColumnName: 'id')]", $category);
        $this->assertStringContainsString("#[ORM\\InverseJoinColumn(name: 'article_id', referencedColumnName: 'id')]", $category);

        $this->assertStringNotContainsString('joinColumns=', $category, 'The nested form is still present.');
        $this->assertStringNotContainsString('inverseJoinColumns=', $category);
    }

    /**
     * None of the seven removed annotations is left (007-002-0003).
     */
    public function testNoRemovedAnnotationIsLeftAfterTheRun(): void
    {
        $result = $this->runRector(true);

        $remaining = array();

        foreach ($result['files'] as $name => $content) {
            $effective = $this->effectiveLines($content);

            foreach (self::REMOVED_ANNOTATIONS as $annotation) {
                if (preg_match('/@\\w+\\\\' . $annotation . '\\b/', $effective) === 1) {
                    $remaining[] = $name . ': ' . $annotation;
                }
            }
        }

        $this->assertSame(array(), $remaining, implode("\n", array_merge(
            array(
                'These removed annotations are still present after the run. A',
                'leftover field is not a tolerated relic: the AnnotationReader',
                'already aborts while reading, and the project does not start (007-002-0003).',
                '',
            ),
            $remaining
        )));
    }

    /**
     * Also under a foreign alias — and that is the reason for configuring the fully qualified
     * name.
     *
     * `OtherAlias.php` imports `Areanet\PIM\Classes\Annotations as Other`. If the rule were
     * configured on `PIM\Rte`, it would miss this file — and a project with its own alias would
     * consider the run complete.
     */
    public function testTheRuleAlsoAppliesUnderAForeignAlias(): void
    {
        $result = $this->runRector(true);
        $file   = $result['files']['OtherAlias.php'] ?? '';

        $this->assertNotSame('', $file, 'The alias probe is missing from the result.');

        $effective = $this->effectiveLines($file);

        $this->assertStringNotContainsString('@Other\\Rte', $effective);
        $this->assertStringNotContainsString('@Other\\Password', $effective);

        // And the project's alias remains: the rule does not rearrange imports.
        $this->assertStringContainsString('#[Mapping\\Column', $file);
        $this->assertStringContainsString('#[Other\\Config', $file);
    }

    /**
     * The remaining annotations are untouched — **character by character**.
     *
     * This is the more important half of this task. A rule measured only by what it is
     * supposed to remove can clear away everything else as well without anyone noticing.
     *
     * Their removed FIELDS are still present here — they are dropped only with `007-002-0004`.
     */
    public function testTheRemainingAnnotationsAreUntouched(): void
    {
        $result = $this->runRector(true);

        $beforeRun = $this->pimDeclarations($this->content('before'));
        $afterRun  = array();

        /*
         * NO array_merge: with string keys it REPLACES instead of appending — my first draft
         * thereby compared only the last file and was blind to the others.
         */
        foreach ($result['files'] as $content) {
            foreach ($this->pimDeclarations($content) as $annotation => $declarations) {
                foreach ($declarations as $declaration) {
                    $afterRun[$annotation][] = $declaration;
                }
            }
        }

        foreach (self::REMAINING_ANNOTATIONS as $annotation) {
            $this->assertSame(
                $beforeRun[$annotation] ?? array(),
                $afterRun[$annotation] ?? array(),
                sprintf(
                    '@PIM\%s has changed. This annotation remains, and its fields '
                    .'are dropped only with 007-002-0004.',
                    $annotation
                )
            );
        }
    }

    /**
     * **Two runs are necessary, and the second is not a convenience** (007-002-0004).
     *
     * The rule that removes fields from attributes sees attributes — and those only come into
     * existence once `AnnotationToAttributeRector` has rewritten the annotation in the same run.
     * A Rector pass applies the rules to the tree it found; what one rule newly creates only
     * reaches another rule in the next pass.
     *
     * **Whoever runs it only once has a broken tree, not a half-migrated one:** the code then
     * reads `#[PIM\Config(label: 'Article')]`, and `Config::__construct()` has no `$label` —
     * "Unknown named parameter $label", a fatal error when loading the entity.
     *
     * This test pins down the fixed point: after the second run there is nothing left to do.
     * The stop condition thereby stands as a checked guarantee and not as advice —
     * **run until Rector reports nothing more.**
     */
    public function testTwoRunsAreNecessaryAndTheThirdFindsNothingMore(): void
    {
        $target = $this->createCopy();

        $first = $this->invokeRector($target, true);
        $this->assertSame(0, $first['code'], $first['output']);

        $second = $this->invokeRector($target, true);
        $this->assertSame(0, $second['code'], $second['output']);
        $this->assertStringContainsString(
            'have been changed',
            $second['output'],
            'The second run must still have something to do — otherwise the field rule never applies.'
        );

        $third = $this->invokeRector($target, false);
        $this->assertSame(
            0,
            $third['code'],
            "After two runs the fixed point is reached; a third must find nothing more:\n"
            . $third['output']
        );

        $this->cleanUp($target);
    }

    /**
     * No removed field is left — the core of this task.
     */
    public function testNoRemovedFieldIsLeftAtTheFixedPoint(): void
    {
        foreach ($this->untilFixedPoint() as $name => $content) {
            foreach ($this->pimDeclarations($content) as $annotation => $declarations) {
                foreach ($declarations as $declaration) {
                    foreach (array_merge(self::REMOVED_FIELDS, self::REMOVED_CHOICE_FIELDS) as $field) {
                        $this->assertDoesNotMatchRegularExpression(
                            '/\b' . preg_quote($field, '/') . '=/',
                            $declaration,
                            sprintf(
                                'In %s, @PIM\%s still carries the removed field %s. That is not a '
                                .'relic: the constructor does not know it, and loading the entity '
                                .'aborts with "Unknown named parameter".',
                                $name,
                                $annotation,
                                $field
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * And no remaining field has been lost — the counter-check.
     *
     * It is the more important half: a rule that removes too much is not noticed by the test
     * for removal.
     */
    public function testAllRemainingFieldsArePresentAtTheFixedPoint(): void
    {
        $all = implode("\n", $this->untilFixedPoint());

        foreach (self::REMAINING_FIELDS as $field) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($field, '/') . '\s*:/',
                $all,
                sprintf('The field %s was lost during the run.', $field)
            );
        }

        // `group` remains on Checkbox and Radio — the only field both keep.
        $this->assertSame(
            2,
            preg_match_all('/group:/', $all),
            'group must remain on Checkbox AND Radio.'
        );
    }

    /**
     * The result matches the hand-written target state.
     *
     * **The comparison is per element, not per line.** The order of the attributes on a class
     * or property carries no meaning — Doctrine reads them as a list, and Rector sets them in
     * its own order. A comparison that insists on that too would be red because of a
     * non-statement.
     *
     * That the attribute is attached to the **right** element is still checked by the
     * comparison: the key is the name of the class or the property.
     */
    public function testTheResultMatchesTheTargetState(): void
    {
        $result = $this->untilFixedPoint();

        foreach ($result as $name => $content) {
            $after = (string) file_get_contents($this->fixtures('after') . '/' . $name);

            $this->assertSame(
                $this->attributesPerElement($after),
                $this->attributesPerElement($content),
                sprintf(
                    '%s deviates from the target state. The target state is written by hand — '
                    .'if the rule deviates, the rule needs checking, not the template.',
                    $name
                )
            );
        }
    }

    /**
     * **Every attribute of the result can really be instantiated.**
     *
     * This is the verification of this task in executable form, and it checks something no
     * text comparison can check: an attribute with a field the constructor does not know is
     * syntactically flawless and only throws when loaded — "Unknown named parameter". Exactly
     * the state a project would have after only one run.
     *
     * PHPStan covers the other side: it checks the **target state** against the constructors.
     * This test checks what the **rule** actually produces.
     *
     * For this, the files are rewritten into a namespace of their own — `before/` and `after/`
     * carry the same class name, and without the renaming they would collide in the process.
     */
    public function testEveryAttributeOfTheResultCanBeInstantiated(): void
    {
        $counted = 0;

        foreach ($this->untilFixedPoint() as $name => $content) {
            $namespace = 'RectorProbe' . bin2hex(random_bytes(4));
            $source    = (string) preg_replace('/namespace [^;]+;/', 'namespace ' . $namespace . ';', $content, 1);

            $tmp = sys_get_temp_dir() . '/' . $namespace . '-' . $name;
            file_put_contents($tmp, $source);
            require $tmp;
            unlink($tmp);

            $class      = $namespace . '\\' . basename($name, '.php');
            $reflection = new \ReflectionClass($class);

            $attributes = $reflection->getAttributes();

            foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $attributes = array_merge($attributes, $property->getAttributes());
            }

            foreach ($attributes as $attribute) {
                $attribute->newInstance();
                ++$counted;
            }
        }

        $this->assertGreaterThan(
            30,
            $counted,
            'Hardly any attributes were checked — then this test says nothing.'
        );
    }

    /**
     * And the counter-check: against already converted entities the rule must do **nothing**.
     *
     * `lib/contentfly/Entity/` has been on attributes since epic `010`. A proposal there would
     * be the sign that the rule does more than it should — and a project that ran it twice
     * would take damage the second time.
     */
    public function testTheRuleDoesNothingAgainstConvertedEntities(): void
    {
        $result = $this->runRector(false, $this->root() . '/lib/contentfly/Entity');

        // Here 0 is the right result: a dry run WITHOUT findings ends with 0.
        $this->assertSame(
            0,
            $result['code'],
            "The rule proposes something for already converted entities:\n" . $result['output']
        );

        $this->assertStringContainsString('Rector is done!', $result['output']);

        $this->assertStringNotContainsString(
            'would have been changed',
            $result['output'],
            'The rule does something to already converted entities — a project that ran it '
            .'twice would take damage the second time.'
        );
    }

    /**
     * The lists here still match the documentation.
     *
     * They live in two places, and two places drift apart. The file states its counts in the
     * section titles — this check hinges on that. If someone changes the list without touching
     * this test, the run turns red instead of silently wrong.
     *
     * The same rule as for the gates from `006-005`.
     */
    public function testTheListsStillMatchTheDocumentation(): void
    {
        $docs = (string) file_get_contents($this->root() . '/an_project/docs/pim-annotationen-migration.md');

        $this->assertStringContainsString('## 1. Entfallene Annotationen (7)', $docs);
        $this->assertStringContainsString('## 3. Entfallene Felder von `@PIM\\Config` (14)', $docs);
        $this->assertStringContainsString('## 4. Gebliebene Felder von `@PIM\\Config` (10)', $docs);

        $this->assertCount(7, self::REMOVED_ANNOTATIONS);
        $this->assertCount(14, self::REMOVED_FIELDS);
        $this->assertCount(10, self::REMAINING_FIELDS);

        foreach (self::REMOVED_ANNOTATIONS as $annotation) {
            $this->assertStringContainsString('`@PIM\\' . $annotation, $docs,
                sprintf('%s is no longer in the documentation.', $annotation));
        }
    }

    /**
     * The reference fixture must not invent fields.
     *
     * **This came out of a mistake of my own.** My first draft wrote
     * `@PIM\Radio(options=…)`, `@PIM\Virtualjoin(entity=…, mappedBy=…)` and
     * `@PIM\Permissions(mode=…)` — fields that none of these classes ever had. I had derived
     * them from the removal list, and that only says what is DROPPED. What REMAINS is stated by
     * the constructor of the annotation class.
     *
     * PHPStan caught it, but only in the target state: attributes are there, and it checks
     * those. The legacy state has docblocks, and it does not look at them. A reference fixture
     * whose legacy state invents fields would have the rule measured against fiction.
     *
     * Therefore **both** states are checked against the real constructors.
     */
    public function testTheReferenceFixtureInventsNoFields(): void
    {
        $invented = array();

        foreach (array('before', 'after') as $state) {
            foreach ($this->declarations($this->effectiveLines($this->content($state))) as $location => $fields) {
                [$annotation, $line] = explode('|', $location, 2);

                $allowed = $this->constructorFields($annotation);

                if ($allowed === null) {
                    // A removed annotation — its class no longer exists, and whatever stood in
                    // its parentheses is irrelevant: it disappears completely.
                    continue;
                }

                foreach ($fields as $field) {
                    if (in_array($field, $allowed, true)
                        || in_array($field, self::REMOVED_FIELDS, true)
                        || in_array($field, self::REMOVED_CHOICE_FIELDS, true)) {
                        continue;
                    }

                    $invented[] = sprintf('%s: %s(%s) — %s', $state, $annotation, $field, $line);
                }
            }
        }

        $this->assertSame(array(), $invented, implode("\n", array_merge(
            array(
                'These fields do not exist on the annotation classes, and they are not on the',
                'removal list either. So the reference fixture invents them — and the rule would be',
                'measured against fiction (007-002-0001):',
                '',
            ),
            $invented,
            array(
                '',
                'What an annotation carries is stated by its constructor in',
                'lib/contentfly/Classes/Annotations/ — not by the removal list.',
            )
        )));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────────────

    /**
     * The PIM declarations of a file, per annotation name and normalized.
     *
     * Normalized means: line breaks and indentation removed, so that a multi-line annotation
     * is comparable with its single-line form. The names of the ANNOTATION count without
     * alias — `@Other\Config` and `#[PIM\Config]` are the same declaration.
     *
     * @return array<string,array<int,string>>
     */
    private function pimDeclarations(string $content): array
    {
        $hits = array();

        preg_match_all(
            '/(?:@|#\\[)\\w+\\\\(\\w+)\\s*(\\(([^)]*)\\))?/s',
            $this->effectiveLines($content),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            if (!in_array($match[1], self::REMAINING_ANNOTATIONS, true)) {
                continue;
            }

            $hits[$match[1]][] = $this->normalize($match[3] ?? '');
        }

        return $hits;
    }

    /**
     * The constructor parameters of an annotation class, or `null` if it does not exist.
     *
     * A class without a constructor accepts nothing — that is the case for `Permissions` and
     * `I18nPermissions` and results in an empty list, not `null`.
     *
     * @return array<int,string>|null
     */
    private function constructorFields(string $annotation): ?array
    {
        $class = 'Areanet\\PIM\\Classes\\Annotations\\' . $annotation;

        if (!class_exists($class)) {
            return null;
        }

        $reflection  = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return array();
        }

        return array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $constructor->getParameters()
        );
    }

    /**
     * Bring an argument list into `field=value` pairs, independent of the notation.
     *
     * `label="Article"` (annotation) and `label: 'Article'` (attribute) both yield
     * `label=Article`. Only this way can we compare whether the run lost fields without the
     * change of notation itself counting as a loss.
     *
     * Sorted, because the order of the arguments carries no meaning.
     */
    private function normalize(string $arguments): string
    {
        $flat = (string) preg_replace('/\\s*\\*\\s*|\\s+/', ' ', $arguments);

        preg_match_all('/(\\w+)\\s*[:=]\\s*("[^"]*"|\\x27[^\\x27]*\\x27|[^,]+)/', $flat, $matches, PREG_SET_ORDER);

        $pairs = array();

        foreach ($matches as $match) {
            $pairs[] = $match[1] . '=' . trim($match[2], " \x22\x27");
        }

        sort($pairs);

        return implode(', ', $pairs);
    }

    /**
     * All `@PIM\X(...)` and `#[PIM\X(...)]` declarations with their named fields.
     *
     * @return array<string,array<int,string>> "Annotation|Line" => field names
     */
    private function declarations(string $content): array
    {
        $hits = array();

        preg_match_all(
            /*
             * FOUR BACKSLASHES, and that is not a typo. In single quotes PHP turns `\\\\` into
             * `\\`, and the regex thereby sees a literal backslash. My first draft wrote two —
             * that became `PIM\\(`, i.e. an ESCAPED PARENTHESIS, and the expression never
             * matched anything. The test was green because it found nothing (007-002-0001).
             */
            '/(?:@|#\\[)\\w+\\\\(\\w+)\\s*\\(([^)]*)\\)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $i => $match) {
            $fields = array();

            /*
             * String values out first: `options="one=One,two=Two"` carries equals signs IN THE
             * VALUE, and without this step the expression read `one` and `two` as field names.
             */
            $withoutValues = (string) preg_replace('/"[^"]*"|\'[^\']*\'/', '""', $match[2]);

            /*
             * `(?::(?!:)|=)` instead of `[:=]`: a double colon is not a named argument. Without
             * the restriction the expression read the word `Article` from
             * `targetEntity: \Tests\…\Article::class` as a field name.
             */
            preg_match_all('/(\w+)\s*(?::(?!:)|=)/', $withoutValues, $names);

            foreach ($names[1] as $name) {
                $fields[] = $name;
            }

            if ($fields !== array()) {
                $hits[$match[1] . '|' . $i] = $fields;
            }
        }

        return $hits;
    }


    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function fixtures(string $state): string
    {
        return $this->root() . '/tests/Fixtures/RectorMigration/' . $state;
    }

    /** All files of a state, concatenated in a stable order. */
    private function content(string $state): string
    {
        $files = glob($this->fixtures($state) . '/*.php');

        $this->assertNotEmpty($files, sprintf('There is no file under %s.', $this->fixtures($state)));

        sort($files);

        return implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            $files
        ));
    }

    /**
     * The lines on which an annotation or an attribute **takes effect**.
     *
     * **The distinction here is not comment versus code, and that is where my first attempt
     * failed.** In the legacy state the annotations sit IN docblocks — filtering out comments
     * took exactly them along, and the test was green because it found nothing any more.
     *
     * An annotation only takes effect at the start of a line: `* @PIM\Config(…)` in the
     * docblock, `#[PIM\Config(…)]` in the code. My prose examples sit in the middle of a
     * sentence, behind a backtick — they are thereby left out without an exception list being
     * necessary.
     *
     * Multi-line annotations are taken along as long as the parentheses are open; otherwise
     * the check would lose exactly the cases that matter.
     */
    private function effectiveLines(string $content): string
    {
        $kept = array();
        $open = 0;

        foreach (explode("\n", $content) as $line) {
            $trimmed = ltrim($line, " \t*");

            /*
             * ALIAS-INDEPENDENT (007-002-0003). `@PIM\\` and `#[PIM\\` used to be hard-wired
             * here — and `OtherAlias.php` imports the annotations as `Other`.
             * The checks missed it, and silently so.
             */
            if ($open === 0
                && preg_match('/^@\\w+\\\\\\w/', $trimmed) !== 1
                && preg_match('/^#\\[\\w+\\\\\\w/', $trimmed) !== 1) {
                continue;
            }

            $kept[] = $line;
            $open  += substr_count($line, '(') - substr_count($line, ')');

            if ($open < 0) {
                $open = 0;
            }
        }

        return implode("\n", $kept);
    }

    /** Comment lines out — the old names appear there on purpose. */
    private function codeOnly(string $content): string
    {
        $lines = array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $t = ltrim($line);

                return !str_starts_with($t, '*') && !str_starts_with($t, '/*') && !str_starts_with($t, '//');
            }
        );

        return implode("\n", $lines);
    }

    /**
     * A copy of the reference fixture in a throwaway directory.
     *
     * Never against the reference fixture itself: a run without `--dry-run` would rewrite the
     * legacy state, and the test would be green on the second invocation because there is
     * nothing left to do.
     */
    private function createCopy(): string
    {
        $target = sys_get_temp_dir() . '/contentfly-rector-' . bin2hex(random_bytes(6));
        mkdir($target, 0777, true);

        foreach ((array) glob($this->fixtures('before') . '/*.php') as $path) {
            copy((string) $path, $target . '/' . basename((string) $path));
        }

        return $target;
    }

    private function cleanUp(string $target): void
    {
        foreach ((array) glob($target . '/*.php') as $path) {
            unlink((string) $path);
        }

        rmdir($target);
    }

    /**
     * One Rector invocation against a directory.
     *
     * @return array{code:int,output:string}
     */
    private function invokeRector(string $target, bool $apply): array
    {
        $command = sprintf(
            '%s %s process %s%s --no-progress-bar --clear-cache 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->root() . '/vendor/bin/rector'),
            escapeshellarg($target),
            $apply ? '' : ' --dry-run'
        );

        $output = array();
        $code   = 0;
        exec($command, $output, $code);

        return array('code' => $code, 'output' => implode("\n", $output));
    }

    /**
     * Two runs — until there is nothing left to do — and the files afterwards.
     *
     * Two and not "until it is stable" in a loop: the number is the statement. If a third were
     * necessary, that would have to be noticed and not silently run along; exactly that is
     * checked by `testTwoRunsAreNecessaryAndTheThirdFindsNothingMore()`.
     *
     * @return array<string,string> file name => content after the fixed point
     */
    private function untilFixedPoint(): array
    {
        $target = $this->createCopy();

        $this->invokeRector($target, true);
        $this->invokeRector($target, true);

        $files = array();

        foreach ((array) glob($target . '/*.php') as $path) {
            $files[basename((string) $path)] = (string) file_get_contents((string) $path);
        }

        $this->cleanUp($target);

        return $files;
    }

    /**
     * The attributes per element — class or property —, sorted.
     *
     * The key is the name of the element, so that an attribute that ends up on the wrong
     * property is noticed. Sorted, because the order of the attributes on an element carries
     * no meaning.
     *
     * @return array<string,array<int,string>>
     */
    private function attributesPerElement(string $content): array
    {
        $result = array();
        $buffer = array();
        $open   = 0;

        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);

            if ($open > 0) {
                $buffer[count($buffer) - 1] .= ' ' . $trimmed;
                $open += substr_count($trimmed, '(') - substr_count($trimmed, ')');
                continue;
            }

            if (str_starts_with($trimmed, '#[')) {
                $buffer[] = $trimmed;
                $open     = substr_count($trimmed, '(') - substr_count($trimmed, ')');
                continue;
            }

            if (preg_match('/^(?:final\s+)?class\s+(\w+)/', $trimmed, $match) === 1
                || preg_match('/^(?:protected|public|private)\s+\$(\w+)\s*;/', $trimmed, $match) === 1) {
                sort($buffer);
                $result[$match[1]] = $buffer;
                $buffer            = array();
            }
        }

        return $result;
    }

    /**
     * Rector against a **copy** of the reference fixture, never against the fixture itself.
     *
     * Without the copy, a run without `--dry-run` would rewrite the legacy state, and the test
     * would be green on the second invocation because there is nothing left to do.
     *
     * @param bool        $apply  Run without `--dry-run` and return the files.
     * @param string|null $source A different directory; it is then **not** copied and always
     *                            run with `--dry-run`.
     *
     * @return array{code:int,output:string,files:array<string,string>}
     */
    private function runRector(bool $apply = false, ?string $source = null): array
    {
        if ($source !== null) {
            // A foreign directory is never modified — only queried.
            $target = $source;
            $apply  = false;
        } else {
            $target = sys_get_temp_dir() . '/contentfly-rector-' . bin2hex(random_bytes(6));
            mkdir($target, 0777, true);

            foreach ((array) glob($this->fixtures('before') . '/*.php') as $path) {
                copy((string) $path, $target . '/' . basename((string) $path));
            }
        }

        $command = sprintf(
            '%s %s process %s%s --no-progress-bar --clear-cache 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->root() . '/vendor/bin/rector'),
            escapeshellarg($target),
            $apply ? '' : ' --dry-run'
        );

        $output = array();
        $code   = 0;
        exec($command, $output, $code);

        $files = array();

        if ($source === null) {
            foreach ((array) glob($target . '/*.php') as $path) {
                $files[basename((string) $path)] = (string) file_get_contents((string) $path);
                unlink((string) $path);
            }

            rmdir($target);
        }

        return array('code' => $code, 'output' => implode("\n", $output), 'files' => $files);
    }
}
