<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC;

/**
 * Console app.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Application extends \Orkan\Application
{
	const APP_NAME = 'TLC';
	const APP_VERSION = '1.3.0';
	const APP_DATE = 'Fri, 03 Jan 2025 09:00:57 +01:00';

	/**
	 * @link https://patorjk.com/software/taag/#p=display&v=0&f=Lean&t=TLC
	 * @link Utils\usr\php\logo\logo.php
	 */
	const LOGO = '
_/_/_/_/_/  _/          _/_/_/
   _/      _/        _/
  _/      _/        _/
 _/      _/        _/
_/      _/_/_/_/    _/_/_/';

	/**
	 * Create TLC App.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		parent::__construct( $Factory );
	}

	/**
	 * {@inheritdoc}
	 */
	private function defaults()
	{
		/**
		 * [json_throttle]
		 * [json_throttle_max]
		 * [json_headers]
		 * @see Factory::getJson()
		 *
		 * [app_php_ext]
		 * Append to parent's list!
		 *
		 * @formatter:off */
		return [
			'json_throttle'     => 6e+5,
			'json_throttle_max' => 1e+6,
			'json_headers'      => [
				'X-Requested-With: XMLHttpRequest',
			],
			'app_php_ext' => [
				'curl'    => true,
				'openssl' => true,
			],
		];
		/* @formatter:on */
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Application::run()
	 */
	public function run()
	{
		if ( !in_array( PHP_SAPI, [ 'cli', 'phpdbg', 'embed' ], true ) ) {
			// Stop here since parent class uses PHP CLI functions not available in apache2handler SAPI!
			die( sprintf(
				/**/ "This application should be invoked via the CLI version of PHP, not the %s SAPI.",
				/**/ PHP_SAPI ) );
		}

		parent::run();
	}
}
