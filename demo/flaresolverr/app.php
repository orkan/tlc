<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Demo: Flaresolverr
 * @author Orkan <orkans+tlc@gmail.com>
 */
use Orkan\Logger;
use Orkan\TLC\Application;
use Orkan\TLC\Factory;

// =====================================================================================================================
// Setup
require dirname( __DIR__, 4 ) . '/autoload.php';

/* @formatter:off */
$Factory = new Factory([
	// TLC/Transport
	'net_retry'         => 1,
	'net_timeout'       => 2,
	'net_throttle'      => 2e+6,
	'net_throttle_max'  => 6e+6,
	'json_throttle'     => 6e+5,
	'json_throttle_max' => 1e+6,
	// Utils/Logger
	'log_file'          => __FILE__ . '.log',
	'log_keep'          => 1,
	'log_reset'         => true,
	'log_extras'        => true,
	'log_level'         => 'DEBUG',
	'log_verbose'       => 'NOTICE',
	// TLC/Cache
	'cache_dir'         => __DIR__,
	'cache_name'        => 'cache',
	'cache_keep'        => 3600,
]);
/* @formatter:on */

$App = new Application( $Factory );
$App->run();
$Flaresolverr = $Factory->Proxy();
$Logger = $Factory->Logger();
$Loggex = $Factory->Loggex();

// =====================================================================================================================
// DEMO:
$Logger->notice( 'DEMO: START' );

/* @formatter:off */
$urls = [
// 	'http://localhost:8000/_index.php',
// 	'http://localhost:8000/_debug.php?server_info=1',
// 	'http://localhost:8000/_debug.php',
	'https://httpbin.io/user-agent',
	'https://httpbin.io/delay/3',
	'https://telemagazyn.pl',
	'https://google.com',
	'https://telemagazyn.pl/stacje/polsat',
];
/* @formatter:on */

foreach ( $urls as $url ) {
	$Loggex->notice( [ '-', $url, '-' ] );
	$text = $Flaresolverr->get( $url );
	$text = fixText( $text, 1000 );
	$Logger->notice( $text );
}

$Logger->notice( 'DEMO: END' );

// =====================================================================================================================
// Functions:
function fixText( $text, $len = 200 )
{
	/* @formatter:off */
	$preg = [
		'~[^[:print:]]~u' => ' ', // Non-printable chars
		'~ ~'             => ' ', // Non-breaking space (U+C2A0)
		'~[ ]{2,}~'       => ' ', // Double spaces
	];
	/* @formatter:on */

	$text = preg_replace( array_keys( $preg ), $preg, $text );
	$text = trim( $text );
	$text = substr( $text, 0, $len );

	return $text;
}
