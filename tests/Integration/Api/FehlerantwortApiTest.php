<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Die Fehlerantwort selbst — Gegenstand von `000-000-0006`.
 *
 * Diese Tests halten nicht fest, welchen Statuscode ein bestimmter Endpunkt liefert; das tun
 * die Charakterisierungstests der Endpunkte. Sie halten fest, dass **die Anwendung antwortet**
 * und nicht der Nothelfer darunter.
 *
 * Der Unterschied ist keine Kosmetik. Bis `000-000-0006` liess ein PHP-Fehler — ein TypeError
 * etwa — die Fehlerkette ausfallen: Der `$app->error()`-Handler starb an `$app['request']`,
 * das zu diesem Zeitpunkt `null` war, und die Closure in `bootstrap-web.php`, die ihn retten
 * sollte, starb an demselben `null`. Übrig blieb Symfonys „Whoops"-Seite mit HTTP 500 — HTML,
 * ohne `message`, ohne `type`, ohne `status`. Ein Client, der JSON erwartet, bekam damit an
 * jeder Stelle, an der irgendetwas schiefging, etwas, das er nicht lesen kann.
 */
class FehlerantwortApiTest extends IntegrationTestCase
{
    /**
     * Ein echter PHP-Fehler, absichtlich ausgelöst: `data` als Zeichenkette statt als Objekt.
     * `Api::doUpdate()` nimmt `array $data` typisiert entgegen — das ist ein TypeError, und
     * ein TypeError ist kein `Exception`.
     */
    public function testEinPhpFehlerKommtAlsJsonUndNichtAlsHtmlSeite(): void
    {
        [$status, $body, ] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => 'egal', 'data' => 'keinArray'),
            $this->token()
        );

        $this->assertSame(500, $status, 'Ein PHP-Fehler ist ein Serverfehler');
        $this->assertArrayHasKey('message', $body, 'Die Antwort ist JSON — vorher HTML');
        $this->assertArrayHasKey('type', $body);
        $this->assertSame(500, $body['status'],
            'Der Statuscode steht unter "status" — vorher als schluesselloser Eintrag unter "0"');
    }

    /**
     * Die Gegenprobe zum Rumpf: Auch der rohe Text darf die „Whoops"-Seite nicht enthalten.
     * Ein leerer json_decode wäre sonst schwer von einem leeren Rumpf zu unterscheiden.
     */
    public function testDieWhoopsSeiteErscheintNicht(): void
    {
        $ch = curl_init(self::$baseUrl.'/api/update');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode(array('entity' => 'PIM\\Tag', 'id' => 'egal', 'data' => 'keinArray')),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'appcms-token: '.$this->token()),
        ));
        $roh = (string) curl_exec($ch);
        curl_close($ch);

        $this->assertStringNotContainsString('Whoops', $roh);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $roh);
        $this->assertNotNull(json_decode($roh, true), 'Der Rumpf ist gueltiges JSON');
    }
}
