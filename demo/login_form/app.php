<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Demo2: Log in with cookie
 *
 * Requirements:
 * symfony/css-selector
 * symfony/dom-crawler
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
use Orkan\TLC\Application;
use Orkan\TLC\Factory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\UriResolver;

/*
 * =====================================================================================================================
 * Functions
 */
function getBody( $html )
{
	$Crawler = new Crawler( $html );
	$text = $Crawler->filter( 'body' )->html();
	$text = trim( preg_replace( '/(?:\s{2,}+|[\t ])/', ' ', $text ) );
	return $text;
}

/*
 * =====================================================================================================================
 * Setup
 */
require dirname( __DIR__, 4 ) . '/autoload.php';
define( 'DEBUG', getenv( 'APP_DEBUG' ) ? true : false );

$Factory = new Factory( require __DIR__ . '/cfg.php' );
$Application = new Application( $Factory );
$Application->run();

/*
 * =====================================================================================================================
 * Run
 */
$Factory->Utils()->writeln( 'CMD:START', 1 );
$Factory->Logger()->info( 'LOG:START' );

printf( "--------- GET1: %s\n", $Factory->get( 'url_form' ) );
$html = $Factory->Transport()->get( $Factory->get( 'url_form' ) );
var_dump( getBody( $html ) );

$Crawler = new Crawler( $html );
$Node = $Crawler->filter( '#form-login' );

if ( $Node->count() ) {
	$Factory->Logger()->info( 'Loging in...' );

	// Create FORM object - action uri must be resolved to absolute url.
	// Use cfg[url_form] for scheme & host when relative.
	$uri = UriResolver::resolve( $Node->attr( 'action' ), $Factory->get( 'url_form' ) );
	$Form = new Form( $Node->getNode( 0 ), $uri );

	// Merge current FORM fields with Login credentials from config file
	$fields = array_merge( $Form->getValues(), $Factory->get( 'app_user' ) );
	//$fields['nonce'] = 'invalid'; // Uncomment to raise errors with invalid nonce

	printf( "--------- POST: %s\n", $Form->getUri() );
	$post = $Factory->Transport()->post( $Form->getUri(), [ 'fields' => $fields ] );
	var_dump( getBody( $post ) );

	printf( "--------- GET2: %s\n", $Factory->get( 'url_form' ) );
	$html = $Factory->Transport()->get( $Factory->get( 'url_form' ) );
	var_dump( getBody( $html ) );

	$Crawler = new Crawler( $html );
	$Node = $Crawler->filter( '#form-login' );
	if ( $Node->count() ) {
		echo "\n----------\n";
		throw new Exception( "Loging in failed!" );
	}
}

$Factory->Utils()->writeln( 'CMD:END' );
$Factory->Logger()->info( 'LOG:END' );

if ( getenv( 'APP_CLEAN' ) ) {
	// Remove cookie for next run
	if ( is_file( $cookie = $Factory->get( 'net_cookiefile' ) ) ) {
		rename( $cookie, "{$cookie}.last" );
	}
}
