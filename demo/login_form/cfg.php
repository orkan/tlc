<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */

/* @formatter:off */
return [
	/*
	 * Demo:
	 * -----------------------------------------------------------------------------------------------------------------
	 */
	'url_form' => 'http://localhost:8000/orkan/ork-tlc/vendor/orkan/tlc/demos/login_form/form.php',
	'app_user' => [
		'user' => 'Demo',
		'pass' => 'Password',
	],
	/*
	 * Orkan\TLC
	 * -----------------------------------------------------------------------------------------------------------------
	 */
	'log_file'       => __DIR__ . "/app.log",
	'cache_keep'     => Orkan\TLC\Cache::DISABLED,
	'net_retry'      => 1,
	'net_timeout'    => 2,
	'net_cookiefile' => __DIR__ . "/cookies.txt",
	'net_throttle'   => 0,
];
/* @formatter:on */
