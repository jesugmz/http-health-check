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
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class HttpHealthCheck
{
    /**
     * Default HTTP request timeout
     */
    const DEFAULT_TIMEOUT = 10;

    /**
     * Health of the target endpoint - by default considered healthy
     *
     * @var bool $health
     */
    protected $health = true;

    /**
     * URL of the target endpoint to be checked
     *
     * @var string $endpointUrl
     */
    protected $endpointUrl;

    /**
     * Conditions to check the success
     *
     * Example:
     * $conditions = [
     *     'body_contains' => 'It works',
     *     'status_code_equals_to' => 200,
     * ];
     *
     * @var array $conditions
     */
    protected $conditions = [
        'body_contains' => null,
        'status_code_equals_to' => null,
    ];

    /**
     * Optional Guzzle request options
     *
     * @var array $options
     *
     * @see http://docs.guzzlephp.org/en/stable/request-options.html
     */
    protected $options = [];

    protected $client;

    /**
     * @param string $endpointUrl
     * @param array $conditions
     * @param array $options
     */
    public function __construct(string $endpointUrl, array $conditions = [], array $options = [])
    {
        if (!$this->validateEndpointUrl($endpointUrl)) {
            throw new \InvalidArgumentException('An endpoint URL must be provided.');
        }

        if (!$this->validateConditions($conditions)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Wrong conditions were provided. The accepted ones are: %s',
                    implode(', ', array_keys($this->conditions))
                )
            );
        }

        $this->endpointUrl = $endpointUrl;
        $this->conditions = $conditions;
        $this->options = $this->grantFiniteTimeout($options);
        $this->client = new Client;
    }

    /**
     * Determines if a service is alive.
     *
     * @return bool
     */
    public function isAlive()
    {
        try {
            /** @var Response $response */
            $response = $this->client->request('GET', $this->endpointUrl, $this->options);
        }
        catch (\Exception $exception) {
            return false;
        }

        return $this->checkResponse($response);
    }

    /**
     * Performs all the checks over the response.
     *
     * If no conditions were provided, as soon as the request is done
     * successfully - a response was received from the endpoint -, it will be
     * consider healthy.
     *
     * @param Response $response
     *
     * @return bool
     */
    protected function checkResponse(Response $response)
    {
        if (!empty($this->conditions['body_contains'])) {
            $this->health = $this->health && $this->checkContentBody($response);
        }

        if (!empty($this->conditions['status_code_equals_to'])) {
            $this->health = $this->health && $this->checkStatusCode($response);
        }

        return $this->health;
    }

    /**
     * Checks if the body content of the response contains the user-provided
     * plain text.
     *
     * @param Response $response
     *
     * @return bool
     */
    protected function checkContentBody(Response $response): bool
    {
        return (bool) preg_match(
            '/' . preg_quote($this->conditions['body_contains']) . '/',
            (string) $response->getBody()
        );
    }

    /**
     * Checks if the HTTP status code of the response match with the
     * user-provided one.
     *
     * @param Response $response
     *
     * @return bool
     */
    protected function checkStatusCode(Response $response): bool
    {
        return $response->getStatusCode() == $this->conditions['status_code_equals_to'];
    }

    /**
     * Validates the user-provided endpoint URL.
     *
     * @param string $endpointUrl
     *
     * @return bool
     */
    private function validateEndpointUrl(string $endpointUrl): bool
    {
        return is_string($endpointUrl) ? true : false;
    }

    /**
     * Validates that the user-provided conditions are empty or defined by this
     * package.
     *
     * @param array $conditions
     *
     * @return bool
     */
    private function validateConditions(array $conditions): bool
    {
        return empty(array_diff_key($conditions, $this->conditions)) ? true : false;
    }

    /**
     * Grants that the HTTP requests will not wait indefinitely (default
     * behavior in Guzzle).
     *
     * @param array $options
     *
     * @return array
     *
     * @see http://docs.guzzlephp.org/en/stable/request-options.html#timeout
     */
    private function grantFiniteTimeout(array $options): array
    {
        if (!isset($options['connect_timeout'])) {
            $options += ['connect_timeout' => self::DEFAULT_TIMEOUT];
        }

        if (!isset($options['read_timeout'])) {
            $options += ['read_timeout' => self::DEFAULT_TIMEOUT];
        }

        if (!isset($options['timeout'])) {
            $options += ['timeout' => self::DEFAULT_TIMEOUT];
        }

        return $options;
    }
}
