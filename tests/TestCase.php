<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests;

/**
 * TestCase: Orkan\TLC.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class TestCase extends \Orkan\Tests\TestCase
{
	const DIR_SELF = __DIR__;
	const USE_FIXTURE = true;

	/**
	 * App config.
	 */
	protected function defaults(): array
	{
		/* @formatter:off */
		return [
			'cookies_db'    => 'sqlite:' . ( getenv( 'COOKIES_DB_USE_FILE' ) ? self::sandboxPath( 'cookies.db' ) : ':memory:' ),
			'cookies_query' => 'SELECT * FROM ' . MozillaDB::TABLE,
		];
		/* @formatter:on */
	}
}
