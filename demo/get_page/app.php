<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Demo: Get html.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
use Orkan\TLC\Application;
use Orkan\TLC\Factory;

/*
 * =====================================================================================================================
 * Setup
 */
require dirname( getcwd(), 4 ) . '/autoload.php';
define( 'DEBUG', getenv( 'APP_DEBUG' ) ? true : false );

/* @formatter:off */
$Factory = new Factory([
	// Demo
	'url_page' => 'http://localhost:8000/page.php',
	// Orkan\TLC
	'net_retry'   => 1,
	'net_timeout' => 2,
	'cache_name'  => basename( __FILE__ ),
	'cache_keep'  => 20,
]);
/* @formatter:on */
$App = new Application( $Factory );
$App->run();

$Utils = $Factory->Utils();
$Loggex = $Factory->Loggex();
$Transport = $Factory->Transport();

/*
 * =====================================================================================================================
 * Run
 */
$url = $Factory->get( 'url_page' );
$file = $Utils->strSlug( $url ) . '.html';
$data = $Transport->getUrl( $url );
file_put_contents( $file, $data );
$Loggex->info( 'Saved: "{url}" > "{file}"', [ '{url}' => $url, '{file}' => $file ] );

$Utils->writeln( 'HTML contents:' );
$Utils->writeln( strip_tags($data) );
