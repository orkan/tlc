# TLC - Transport, Logging, Cache `v2.2.0`
Simple PHP/cURL/FlareSolverr framework with Logger, Cache and more!

## Installation
`$ composer require orkan/tlc`

## Usage
```php
// Setup
use Orkan\TLC\Application;
use Orkan\TLC\Factory;
use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';
$Factory = new Factory( require __DIR__ . "/cfg.php" );
$Application = new Application( $Factory );
$Application->run();

// GET page
$html = $Factory->Transport()->get( $url = 'http://example.com/welcome.php' );
$Crawler = new Crawler( $html );

// Fill & POST "Log in" FORM
$fields = $Crawler->filter( '#form-login' )->form()->getValues();
$fields['user'] = 'Me';
$fields['pass'] = 'secret';
$Factory->Transport()->post( $url, [ 'fields' => $fields ] );

// Log some info...
$Factory->Logger()->info( 'Form fields: ' . print_r( fields, true ) );
```

For more examples see [`/demo`](/demo) folder.

## About
### Requirements
PHP  ^7.4

## Third Party Packages
* [Seldaek/Monolog](https://github.com/Seldaek/monolog)
* [Symfony/DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html)

### Author
[Orkan](https://github.com/orkan)

### License
MIT

### Updated
Sat, 15 Mar 2025 04:25:54 +01:00
