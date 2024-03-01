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
$baseName = basename( __FILE__, '.php' );

$Factory = new Factory( require __DIR__ . "/$baseName.cfg.php" );
$Application = new Application( $Factory );
$Application->run();

/*
 * =====================================================================================================================
 * Run
 */
$Factory->Utils()->writeln( 'CMD:START', 1 );
$Factory->Logger()->info( 'LOG:START' );

//$out = $Application->get( $url = 'http://localhost:1888' ); // Error #7 CURLE_COULDNT_CONNECT
$data = $Factory->getUrl( $url = 'http://localhost' );
file_put_contents( $file = $Factory->Logger()->getFilename() . '-out.txt', $data );
$Factory->Utils()->writeln( sprintf( 'Saved [%s] to "%s"', $url, $file ), 2 );

$Factory->Utils()->writeln( 'CMD:END', 1 );
$Factory->Logger()->info( 'LOG:END' );
