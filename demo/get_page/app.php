<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Demo1
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
use Orkan\TLC\Application;
use Orkan\TLC\Factory;

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

$data = $Factory->getUrl( $url = $Factory->get( 'url_page' ) );
file_put_contents( $file = $Factory->Utils()->strSlug( $url ) . '.html', $data );
$Factory->Utils()->writeln( "Saved [$url] to [$file]" );

$Factory->Utils()->writeln( 'CMD:END', 1 );
$Factory->Logger()->info( 'LOG:END' );
