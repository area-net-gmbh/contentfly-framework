<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * The guard over the completeness of the migration guide (007-004-0003).
 *
 * ## What the story requires
 * "Every one of the breaks is reachable in the guide — either as a step of its own or as a
 * reference into the register, and **none falls through**."
 *
 * ## Why this needs a check
 * **It only remains a matter of diligence until it goes wrong once.** The register grows with
 * every story — in epic `007` alone, four entries and one section were added.
 * Without a check, a new section falls through exactly when nobody thinks of it any more.
 *
 * ## Section by section, and the price is stated in the guide
 * What is checked is that every **section** of the register is assigned to a phase — not every
 * entry. Decided on 2026-09-11: 14 assignments instead of 100 markers. A new entry in an
 * already assigned section therefore counts as covered; that is a decision and not a matter of
 * course, and it is stated in the guide.
 *
 * ## Both directions
 * - **Every section is assigned** — otherwise the guide leads past it.
 * - **Every assignment hits a section** — otherwise it leads nowhere once the section has been
 *   renamed or dissolved. The same rule as for the gates from `006-005`.
 */
class MigrationGuideTest extends TestCase
{
    public function testEveryRegisterSectionIsAssignedToAPhase(): void
    {
        $assigned = $this->assignments();

        $withoutPhase = array_values(array_diff($this->registerSections(), $assigned));

        sort($withoutPhase);

        $this->assertSame(array(), $withoutPhase, implode("\n", array_merge(
            array(
                'These sections of breaking-changes.md are not assigned to any phase in the',
                'migration guide — the guide leads past them (007-004-0003):',
                '',
            ),
            $withoutPhase,
            array(
                '',
                'Add them in an_project/docs/migration.md to the table "Welcher Register-Abschnitt in',
                'welcher Phase". If the section fits none of the phases, the list of phases needs',
                'to be extended — rather than forcing the entry in.',
            )
        )));
    }

    public function testEveryAssignmentHitsASection(): void
    {
        $sections = $this->registerSections();

        $dangling = array_values(array_diff($this->assignments(), $sections));

        sort($dangling);

        $this->assertSame(array(), $dangling, implode("\n", array_merge(
            array(
                'These assignments in the migration guide point to sections that do not exist',
                '(any more) in breaking-changes.md:',
                '',
            ),
            $dangling,
            array(
                '',
                'A dangling assignment is worse than none: it looks like a path.',
                'Either the section has a different name — then update it here — or it is gone,',
                'then the row goes too.',
            )
        )));
    }

    /**
     * And the number in the header of the guide is still correct.
     *
     * It is the only place where the guide claims a size of the register — and a number that
     * is no longer correct is the same defect as a reference that points nowhere. It carries
     * its date so that it stays visible what it refers to.
     */
    public function testTheStatedSizesAreStillCorrect(): void
    {
        $guide    = $this->read('an_project/docs/migration.md');
        $register = $this->read('an_project/docs/breaking-changes.md');

        $entries  = substr_count($register, "\n### ");
        $sections = count($this->registerSections());

        $this->assertStringContainsString(
            sprintf('**%d Einträge in %d Abschnitten**', $entries, $sections),
            $guide,
            sprintf(
                "The guide states a different size than the register has.\n"
                ."Counted: %d entries in %d sections.\n"
                .'The number in the header of migration.md needs to be updated, along with its date.',
                $entries,
                $sections
            )
        );
    }

    /**
     * The sections of the register — the `##` headings.
     *
     * @return array<int,string>
     */
    private function registerSections(): array
    {
        $found = array();

        foreach (explode("\n", $this->read('an_project/docs/breaking-changes.md')) as $line) {
            if (str_starts_with($line, '## ')) {
                $found[] = trim(substr($line, 3));
            }
        }

        $this->assertNotEmpty($found, 'The register has no sections — then this test checks nothing.');

        return $found;
    }

    /**
     * The sections the guide assigns to a phase.
     *
     * Read is the table *Welcher Register-Abschnitt in welcher Phase*: rows of the form
     * `| 3 | A · B |`. Several sections in one cell are separated by the middle dot; the
     * italic note for a phase without a section is skipped.
     *
     * @return array<int,string>
     */
    private function assignments(): array
    {
        $lines   = explode("\n", $this->read('an_project/docs/migration.md'));
        $inTable = false;
        $found   = array();

        foreach ($lines as $line) {
            if (str_starts_with($line, '| Phase | Register-Abschnitt |')) {
                $inTable = true;
                continue;
            }

            if (!$inTable) {
                continue;
            }

            if (!str_starts_with($line, '|')) {
                break;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));

            if (count($cells) < 2 || !ctype_digit($cells[0])) {
                continue;
            }

            foreach (explode('·', $cells[1]) as $entry) {
                $entry = trim($entry);

                // The italic note of a phase without a section.
                if ($entry === '' || str_starts_with($entry, '*(')) {
                    continue;
                }

                $found[] = $entry;
            }
        }

        $this->assertNotEmpty($found, 'No assignment was found in the guide — then this test checks nothing.');

        return $found;
    }

    private function read(string $relative): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
