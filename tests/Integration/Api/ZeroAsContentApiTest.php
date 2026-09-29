<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * A `"0"` written through the API comes back as `"0"` (`000-000-0102`).
 *
 * THE FINDING. `StringType::toDatabase()` opened with `if(empty($value))` and stored `''` for
 * whatever it caught. `empty()` says yes to `'0'` — so an article number, a house number, a
 * floor, a meter reading or a sort value of `0` was silently replaced by the empty string.
 * `TextareaType` carried the same three lines.
 *
 * WHAT IS MEASURED IS THE ROUND TRIP through the real API, and additionally the column itself:
 * a fix that returned `'0'` from a cache while writing `''` would pass a read assertion.
 *
 * The encrypted half is measured in `Tests\Unit\Types\EmptyValueTest` — no entity in this tree
 * sets `encoded: true`, so that branch cannot be reached over HTTP at all.
 */
class ZeroAsContentApiTest extends IntegrationTestCase
{
    public function testZeroSurvivesInsertAndRead(): void
    {
        $id = $this->insert(array('name' => '0'));

        $this->assertSame('0', $this->readBack($id, 'name'), 'the API hands "0" back');
        $this->assertSame('0', $this->column($id, 'name'), 'and the column really holds it');
    }

    public function testZeroSurvivesAnUpdate(): void
    {
        $id = $this->insert(array('name' => 'something'));

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'Core\\Example',
            'id'     => $id,
            'data'   => array('name' => '0'),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame('0', $this->column($id, 'name'));
    }

    /** Two fields at once — the type is chosen per property, not per request. */
    public function testZeroSurvivesInASecondStringField(): void
    {
        $id = $this->insert(array('name' => '0', 'slug' => '0'));

        $this->assertSame('0', $this->column($id, 'name'));
        $this->assertSame('0', $this->column($id, 'slug'));
    }

    /** And what is genuinely empty stays empty — the fix must not turn `''` into something. */
    public function testAnEmptyStringStaysEmpty(): void
    {
        $id = $this->insert(array('name' => ''));

        $this->assertSame('', $this->column($id, 'name'));
    }

    public function testNullStaysEmpty(): void
    {
        $id = $this->insert(array('name' => null));

        $this->assertSame('', $this->column($id, 'name'));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $data @return string the new id */
    private function insert(array $data): string
    {
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'Core\\Example',
            'data'   => $data,
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));

        $id = $body['data']['id'] ?? null;
        $this->assertNotEmpty($id, 'the insert returned an id: '.json_encode($body));

        $this->deleteAfterTest('example_entity', (string) $id);

        return (string) $id;
    }

    private function readBack(string $id, string $field): ?string
    {
        [$status, $body] = $this->postJson('/api/single', array(
            'entity' => 'Core\\Example',
            'id'     => $id,
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));

        $value = $body['data'][$field] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function column(string $id, string $field): ?string
    {
        $statement = $this->pdo()->prepare(sprintf('SELECT `%s` FROM example_entity WHERE id = ?', $field));
        $statement->execute(array($id));

        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }
}
