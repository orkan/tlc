# TLC - Transport, Logging, Cache `v2.0.0`
Simple PHP/cURL framework with Logger, Cache and more!

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

## Third Party Packages
* [Seldaek/Monolog](https://github.com/Seldaek/monolog)
* [Symfony/DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html)

## About
### Requirements
PHP  ^7.4

## Installation
`$ composer require orkan/tlc`

### Author
[Orkan](https://github.com/orkan)

### License
MIT

### Updated
Thu, 13 Feb 2025 04:17:01 +01:00
