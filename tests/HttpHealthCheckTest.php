<?php declare(strict_types=1);

/**
 * This file is part of the http-health-check package.
 *
 * (c) Jesús Gómez <hola@jesusgomez.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HttpHealthCheck;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class HttpHealthCheckTest extends TestCase
{
    /**
     * @dataProvider validParametersProvider
     */
    public function testCanBeCreatedFromValidParameters(string $endpointUrl, array $conditions, array $options): void
    {
        $this->assertInstanceOf(
            HttpHealthCheck::class,
            new HttpHealthCheck($endpointUrl, $conditions, $options)
        );
    }

    /**
     * @dataProvider invalidEndpointUrlProvider
     */
    public function testCannotBeCreatedWithInvalidEndpointUrl($invalidEndpointUrl): void
    {
        $this->expectException(\TypeError::class);
        $check = new HttpHealthCheck($invalidEndpointUrl);
    }

    /**
     * @dataProvider invalidConditionsProvider
     */
    public function testCannotBeCreatedWithInvalidConditions($invalidConditions): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $check = new HttpHealthCheck('http://dummy-url', $invalidConditions);
    }

    public function testCanBeCreatedGrantingFiniteTimeout(): void
    {
        $class = new \ReflectionClass(HttpHealthCheck::class);
        $options = $class->getProperty('options');
        $options->setAccessible(true);

        $check = $class->newInstanceArgs(['http://dummy-url']);

        $this->assertEquals([
            'connect_timeout' => HttpHealthCheck::DEFAULT_TIMEOUT,
            'read_timeout' => HttpHealthCheck::DEFAULT_TIMEOUT,
            'timeout' => HttpHealthCheck::DEFAULT_TIMEOUT,
        ], $options->getValue($check));

        $timeout = 30;
        $check = $class->newInstanceArgs(['http://dummy-url', [], ['timeout' => $timeout]]);

        $this->assertEquals([
            'connect_timeout' => HttpHealthCheck::DEFAULT_TIMEOUT,
            'read_timeout' => HttpHealthCheck::DEFAULT_TIMEOUT,
            'timeout' => $timeout,
        ], $options->getValue($check));
    }

    public function testIsHealthyReturnsTrueWithNoChecks()
    {
        $check = $this->mockCheckInstance(new Response, 'http://dummy-url');
        $this->assertTrue($check->isHealthy());
    }

    public function testIsHealthyReturnsTrueWithExpectedStatusCode()
    {
        $statusCode = 200;

        $check = $this->mockCheckInstance(
            new Response($statusCode),
            'http://dummy-url',
            ['status_code_equals_to' => $statusCode]
        );

        $this->assertTrue($check->isHealthy());
    }

    public function testIsHealthyReturnsFalseWithUnexpectedStatusCode()
    {
        $check = $this->mockCheckInstance(
            new Response(404),
            'http://dummy-url',
            ['status_code_equals_to' => 200]
        );

        $this->assertFalse($check->isHealthy());
    }

    public function testIsHealthyReturnsTrueWithExpectedPlainTextBodyContent()
    {
        $bodyContent = 'sample';

        $check = $this->mockCheckInstance(
            new Response(200, [], $bodyContent),
            'http://dummy-url',
            ['body_contains' => $bodyContent]
        );

        $this->assertTrue($check->isHealthy());
    }

    public function testIsHealthyReturnsTrueWithExpectedPartialBodyContent()
    {
        $check = $this->mockCheckInstance(
            new Response(200, [], 'Lorem ipsum dolor $it amet'),
            'http://dummy-url',
            ['body_contains' => '$it']
        );

        $this->assertTrue($check->isHealthy());
    }

    public function testIsHealthyReturnsFalseWithUnexpectedBodyContent()
    {
        $check = $this->mockCheckInstance(
            new Response(200, [], 'foo'),
            'http://dummy-url',
            ['body_contains' => 'bar']
        );

        $this->assertFalse($check->isHealthy());
    }

    public function validParametersProvider()
    {
        return [
            [
                '',
                array(),
                array(),
            ],
            [
                'http://dummy-url',
                array('status_code_equals_to' => 200),
                array(),
            ],
            [
                'http://dummy-url',
                array(),
                array('timeout' => 3)
            ],
            [
                'http://dummy-url',
                array(
                    'status_code_equals_to' => 200,
                    'body_contains' => 'It works',
                ),
                array('headers' => [
                    'User-Agent' => 'Testing Agent',
                    'Accept' => 'application/json',
                ])
            ],
        ];
    }

    public function invalidEndpointUrlProvider(): array
    {
        return [
            [null],
            [false],
            [true],
            [0],
            [rand(1, PHP_INT_MAX)],
            [0.],
            [array()],
            [new \stdClass()],
            [function(){}],
        ];
    }

    public function invalidConditionsProvider(): array
    {
        return [
            [array('wrong' => 'condition')],
            [array('body_contains')], // no associative key defined
            [array(1)],
        ];
    }

    private function mockCheckInstance(Response $response, ...$arguments)
    {
        $client = $this->createMock(Client::class);
        $client->method('request')->willReturn($response);

        $reflectionClass = new \ReflectionClass(HttpHealthCheck::class);
        $check = $reflectionClass->newInstance(...$arguments);

        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($check, $client);

        return $check;
    }
}
