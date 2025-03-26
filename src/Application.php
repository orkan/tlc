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
	const APP_VERSION = '3.0.0';
	const APP_DATE = 'Wed, 26 Mar 2025 17:04:39 +01:00';

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
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		parent::__construct( $Factory );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function defaults()
	{
		/**
		 * [app_php_ext]
		 * Append to parent's list!
		 *
		 * @formatter:off */
		return [
			'app_php_ext' => [
				'curl'    => true,
				'openssl' => true,
			],
		];
		/* @formatter:on */
	}
}
