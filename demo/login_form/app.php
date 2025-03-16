<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Demo: Log in with cookie.
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
function writeln( $s ) {
	echo $s . "\n";
}

/*
 * =====================================================================================================================
 * Setup
 */
require dirname( getcwd(), 4 ) . '/autoload.php';
define( 'DEBUG', getenv( 'APP_DEBUG' ) ? true : false );
getenv( 'APP_TESTING' ) && define( 'TESTING', true );

/* @formatter:off */
$Factory = new Factory([
	// Demo
	'url_form' => 'http://localhost:8000/form.php',
	'app_user' => [
		'user' => 'User',
		'pass' => 'Password',
	],
	// TLC
	'net_retry'      => 1,
	'net_timeout'    => 2,
	'net_cookiefile' => __DIR__ . "/cookies.txt",
	// Application
	'app_opts'  => [
		'reset' => [ 'short' => 'r', 'long' =>  'reset', 'desc' => 'Reset login form'  ],
		'nonce' => [ 'short' => 'n', 'long' =>  'nonce', 'desc' => 'Use invalid nonce'  ],
	],
]);
/* @formatter:on */
$App = new Application( $Factory );
$App->run();

$Logger = $Factory->Logger();
$Transport = $Factory->Transport();

/*
 * =====================================================================================================================
 * Run
 */
$Logger->info( 'CMD: ' . implode( ' ', $GLOBALS['argv'] ) );

// Reset cookie before next run?
if ( $App->getArg( 'reset' ) || $App->getArg( 'nonce' ) ) {
	if ( is_file( $cookie = $Factory->get( 'net_cookiefile' ) ) ) {
		$Logger->info( 'Signing out...' );
		rename( $cookie, "{$cookie}.last" );
	}
}

writeln( "--------- GET1: " . $Factory->get( 'url_form' ) );
$html = $Transport->get( $Factory->get( 'url_form' ) );
var_dump( getBody( $html ) );

$Crawler = new Crawler( $html );
$Node = $Crawler->filter( '#form-login' );

if ( $Node->count() ) {
	$Logger->info( 'Signing in...' );

	// Create FORM object - action uri must be resolved to absolute url.
	// Use cfg[url_form] for scheme & host when relative.
	$uri = UriResolver::resolve( $Node->attr( 'action' ), $Factory->get( 'url_form' ) );
	$Form = new Form( $Node->getNode( 0 ), $uri );

	// Merge current FORM fields with Login credentials from config file
	$fields = array_merge( $Form->getValues(), $Factory->get( 'app_user' ) );

	if ( $App->getArg( 'nonce' ) ) {
		$Logger->info( '- with invalid nonce!' );
		$fields['nonce'] = 'invalid';
	}

	writeln( "--------- POST: " . $Form->getUri() );
	$html = $Transport->post( $Form->getUri(), [ 'fields' => $fields ] );
	$Crawler = new Crawler( $html );
	$errors = $Crawler->filter( '#error' )->text( '' );
	var_dump( getBody( $html ) );

	// POST errors?
	if ( $errors ) {
		throw new Exception( $errors );
	}

	writeln( "--------- GET2: " . $Factory->get( 'url_form' ) );
	$html = $Transport->get( $Factory->get( 'url_form' ) );
	var_dump( getBody( $html ) );
}
