<?php

/* @formatter:off */
return [
	'01_15mins' => [
		'header' => 'Set-Cookie: cookie1=15mins; expires=Sun, 28-Aug-2022 14:59:45 GMT; Max-Age=900; path=/; domain=example.1.com',
		'file'   => '.example.1.com	TRUE	/	FALSE	1661698785	cookie1	15mins',
		'cookie' => [
			'name'     => 'cookie1',
			'value'    => '15mins',
			'expires'  => 1661698785,
			'max-age'  => 900,
			'domain'   => 'example.1.com',
			'path'     => '/',
		],
	],
	'02_24h_httponly_lax' => [
		'header' => 'Set-CookIe: cookie2=24h_httponly_lax; expires=Mon, 29-Aug-2022 00:00:00 GMT; Max-Age=33315; path=/; domain=www.example-2.com; HttpOnly; SameSite=Lax',
		'file'   => '#HttpOnly_.www.example-2.com	TRUE	/	FALSE	1661731200	cookie2	24h_httponly_lax',
		'cookie' => [
			'name'     => 'cookie2',
			'value'    => '24h_httponly_lax',
			'expires'  => 1661731200,
			'max-age'  => 33315,
			'domain'   => 'www.example-2.com',
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'lax',
		],
	],
	'03_session' => [
		'header' => 'set-cookie: cookie3=session; path=/abc; domain=example-3.com',
		'file'   => '.example-3.com	TRUE	/abc	FALSE	0	cookie3	session',
		'cookie' => [
			'name'     => 'cookie3',
			'value'    => 'session',
			'domain'   => 'example-3.com',
			'path'     => '/abc',
		],
	],
	'04_https_path' => [
		'header' => 'set-Cookie: cookie4=https; path=/~rasmus/; domain=example4.com; Secure',
		'file'   => '.example4.com	TRUE	/~rasmus/	TRUE	0	cookie4	https',
		'cookie' => [
			'name'     => 'cookie4',
			'value'    => 'https',
			'domain'   => 'example4.com',
			'path'     => '/~rasmus/',
			'secure'   => true,
		],
	],
	'05_nopath' => [
		'header' => 'set-cookiE: cookie_5=nopath; domain=example5.com',
		'file'   => '.example5.com	TRUE	/	FALSE	0	cookie_5	nopath',
		'cookie' => [
			'name'     => 'cookie_5',
			'value'    => 'nopath',
			'domain'   => 'example5.com',
		],
	],
	'06_nopath_nodomain' => [
		'header' => 'Set-Cookie: cookie-6=nopath_nodomain',
		'file'   => '	FALSE	/	FALSE	0	cookie-6	nopath_nodomain',
		'cookie' => [
			'name'     => 'cookie-6',
			'value'    => 'nopath_nodomain',
		],
	],
	'07_encode' => [
		'header' => 'set-cookie: cookie7=' . urlencode( 'cookie [7] + value' ) . '; path=/; domain=example7.com',
		'file'   => '.example7.com	TRUE	/	FALSE	0	cookie7	' . urlencode( 'cookie [7] + value' ),
		'cookie' => [
			'name'     => 'cookie7',
			'value'    => 'cookie [7] + value',
			'domain'   => 'example7.com',
			'path'     => '/',
		],
	],
	'08_https_path_strict' => [
		'header' => 'set-Cookie: cookie8=https; path=/abc/def; domain=example8.com; secure; SameSite=Strict',
		'file'   => '.example8.com	TRUE	/abc/def	TRUE	0	cookie8	https',
		'cookie' => [
			'name'     => 'cookie8',
			'value'    => 'https',
			'domain'   => 'example8.com',
			'path'     => '/abc/def',
			'secure'   => true,
			'samesite' => 'strict',
		],
	],
	'09_httponly_none' => [
		'header' => 'set-Cookie: cookie9=https; path=/; domain=example9.com; HttpOnly; samesitE=noNe',
		'file'   => '#HttpOnly_.example9.com	TRUE	/	FALSE	0	cookie9	https',
		'cookie' => [
			'name'     => 'cookie9',
			'value'    => 'https',
			'domain'   => 'example9.com',
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'none',
		],
	],
];
/* @formatter:on */
