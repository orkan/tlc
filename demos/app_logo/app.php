<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/**
 * Show Application logo
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

$Factory = new Factory();
$Application = new Application( $Factory );
$Application->run();

/*
 * =====================================================================================================================
 * Run
 */
echo $Application->getHelp();
