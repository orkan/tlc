<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
use Orkan\TLC\Cache;

global $baseName;

/* @formatter:off */
return [
	/*
	 * demo2.php
	 * -----------------------------------------------------------------------------------------------------------------
	 */
	'app_title' => "$baseName: Orkan/TLC",
	'app_home'  => 'http://htdocs.wamp/orkan/ork-tlc/vendor/orkan/tlc/demos/demo2/home.php',
	'app_user'  => [
		'user' => 'Demo',
		'pass' => 'Password',
	],

	/*
	 * Orkan\TLC
	 * -----------------------------------------------------------------------------------------------------------------
	 */
	'log_file'       => __DIR__ . "/$baseName.log",
	'cache_name'     => $baseName,
	'cache_keep'     => Cache::DISABLED,
	'net_retry'      => 1,
	'net_timeout'    => 2,
	'net_cookiefile' => __DIR__ . "/$baseName-cookie.txt",
	'net_throttle'   => 0,
];
/* @formatter:on */
