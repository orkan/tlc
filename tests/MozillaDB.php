<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2024 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests;

use Orkan\TLC\Transport\Cookies;

/**
 * Mozilla cookies DB.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class MozillaDB extends \Orkan\Database
{
	/**
	 * Mozilla test table name.
	 * Harcoded here by purpose!
	 */
	const TABLE = 'tmp_cookies';

	/**
	 * @param string $dsn        PDO database file
	 * @param string $tblCookies Table name
	 */
	public function __construct( string $dsn )
	{
		parent::__construct( $dsn );

		$this->query( "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
			id         INTEGER PRIMARY KEY,
			name       TEXT,
			value      TEXT NOT NULL DEFAULT '',
			host       TEXT NOT NULL DEFAULT '',
			path       TEXT NOT NULL DEFAULT '/',
			expiry     INTEGER NOT NULL DEFAULT 0,
			isSecure   INTEGER NOT NULL DEFAULT 0,
			isHttpOnly INTEGER NOT NULL DEFAULT 0,
			sameSite   INTEGER NOT NULL DEFAULT 1 )
		" );
	}

	/**
	 * Add cookie.
	 */
	public function add( array $cookie )
	{
		foreach ( $cookie as $attr => $val ) {
			if ( false !== $field = array_search( $attr, Cookies::MAP_MOZ_FIELD ) ) {
				$fields[$attr] = $field;
				$places[$attr] = '?';

				if ( 'value' === $attr ) {
					// Firefox doesnt decode Set-Cookie headers so keep it encoded!
					$val = urlencode( $val );
				}
				elseif ( 'samesite' === $attr ) {
					// Replace DB::sameSite value bcos is indexed by ID!
					$val = array_search( ucfirst( $val ), Cookies::MAP_MOZ_SAMESITE );
				}

				$values[] = $val;
			}
		}

		$query = sprintf( "INSERT INTO " . self::TABLE . " (%s) VALUES (%s)", implode( ',', $fields ), implode( ',', $places ) );
		$this->prepare( $query );
		$this->execute( $values );
	}
}
