# HTTP Health Check

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg?style=flat-square)](https://php.net/)
[![CircleCI](https://circleci.com/gh/jesuGMZ/http-health-check.svg?style=svg)](https://circleci.com/gh/jesuGMZ/http-health-check)

HTTP Health Check is a simple HTTP health check written in PHP built on top of [Guzzle](https://github.com/guzzle/guzzle).

-   Easy way to check HTTP services status by status code and body content from your PHP application
-   Grant finite HTTP request timeout (Guzzle does not)
-   Positive behavior by default. If no conditions are provided, the health will be consider _healthy_ as soon as the request can be made successfully - no connectivity issues

## Installation

Install the latest version through [Composer](https://getcomposer.org/):

```bash
$ composer require jesugmz/http-health-check
```

## Usage

HTTP Health Check will do a GET request to the given endpoint URL and can check the following conditions in the response:

-   HTTP status code: expressed as `status_code_equals_to`
-   Body content: plain text that appears in the body response and is expressed as `body_contains`

```php
use HttpHealthCheck\HttpHealthCheck;

$endpointUrl = 'https://github.com/jesuGMZ/';
$conditions = [
    'status_code_equals_to' => 200,
    'body_contains'         => 'jesuGMZ',
];

$check = new HttpHealthCheck($endpointUrl, $conditions);

var_dump($check->isHealthy());
```

It allows also [Guzzle Request Options](http://docs.guzzlephp.org/en/stable/request-options.html) parameters:

```php
use HttpHealthCheck\HttpHealthCheck;

$endpointUrl = 'https://mdn.github.io/learning-area/javascript/oojs/json/superheroes.json';
$conditions = [
    'status_code_equals_to' => 200,
    'body_contains'         => 'Super hero squad',
];
$options = [
    'headers' => [
        'User-Agent' => 'My custom user agent',
        'Accept'     => 'application/json',
    ]
];

$check = new HttpHealthCheck($endpointUrl, $conditions, $options);

var_dump($check->isHealthy());
```
