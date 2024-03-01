<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests\Transport;

use Orkan\TLC\Tests\FactoryMock;
use Orkan\TLC\Tests\MozillaDB;
use Orkan\TLC\Transport\Cookies;

/**
 * Test: Orkan\TLC\Transport\Cookies.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class CookiesTest extends \Orkan\TLC\Tests\TestCase
{
	const USE_SANDBOX = true;
	const USE_FIXTURE = true;

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Parse 'Set-Cookie: ' HTTP header to array.
	 */
	public function testCanBuildCookie()
	{
		$cookies = require self::fixturePath( 'cookies.php' );
		$Factory = new FactoryMock( $this );
		$Cookies = new Cookies( $Factory );

		foreach ( $cookies as $v ) {
			$header = substr( $v['header'], 12 ); // remove 'Set-Cookie: ' prefix
			$this->assertSame( $v['cookie'], $Cookies->buildCookie( $header ) );
		}
	}

	/**
	 * Build 'Cookie: ' HTTP header from array.
	 */
	public function testCanBuildCookieHeader()
	{
		$cookies = require self::fixturePath( 'cookies.php' );
		$Factory = new FactoryMock( $this );

		$cookies = $pairs = [];

		foreach ( $cookies as $v ) {
			$pairs[] = $v['cookie']['name'] . '=' . urlencode( $v['cookie']['value'] );
			$cookies[] = $v['cookie'];
		}

		$expect = implode( '; ', $pairs );

		$Cookies = new Cookies( $Factory );
		$actual = $Cookies->buildCookieHeader( $cookies );

		$this->assertSame( $expect, $actual );
	}

	/**
	 * Parse all Set-Cookie HTTP headers from url.
	 */
	public function testCanGetCookiesFromUrl()
	{
		$cookies = require self::fixturePath( 'cookies.php' );
		$Factory = new FactoryMock( $this );

		$url = 'http://test.com/page.html';
		$body = '<!doctype html><html lang="pl"><head><title>Test</title><meta charset="utf-8"></head><body>Test body</body></html>';

		$headers = $expect = [];
		$headers[] = 'HTTP/1.0 200 OK';
		$headers[] = 'X-Powered-By: PHP/7.4.0';
		foreach ( $cookies as $v ) {
			$headers[] = $v['header'];
			$expect[$v['cookie']['name']] = $v['cookie'];
		}
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$headers = implode( "\n", $headers );

		/*
		 * Return full response: headers + html page
		 * @formatter:off */
		$Factory->Transport()
			->expects( $this->exactly( 1 ) )
			->method( 'with' )
			->with( 'get', $url, $this->anything() )
			->willReturn( $headers . "\n" . $body );

		$Factory->Transport()
			->expects( $this->exactly( 1 ) )
			->method( 'lastInfo' )
			->willReturn( [ 'header_size' => strlen( $headers ) ] );
		/* @formatter:on */

		$Cookies = new Cookies( $Factory );
		$actual = $Cookies->getCookiesFromUrl( $url );

		$this->assertSame( $expect, $actual );
	}

	/**
	 * Build a cookie in Netscape format.
	 */
	public function testCanBuildNetscapeCookie()
	{
		$cookies = require self::fixturePath( 'cookies.php' );
		$Factory = new FactoryMock( $this );
		$Cookies = new Cookies( $Factory );

		foreach ( $cookies as $v ) {
			$expect = $v['file'];
			$actual = $Cookies->buildNetscapeCookie( $v['cookie']['name'], $v['cookie']['value'], $v['cookie'], false );

			$this->assertSame( $expect, $actual );
		}
	}

	/**
	 * Translate Firefox moz_cookies DB row to PHP format.
	 */
	public function testCanTranslateMozillaDbCookie()
	{
		$Factory = new FactoryMock( $this, self::defaults() );
		$Cookies = new Cookies( $Factory );
		$Database = new MozillaDB( $Factory->get( 'cookies_db' ) );

		// Insert cookies array to DB
		$cookies = require self::fixturePath( 'cookies.php' );
		$cookies = array_column( $cookies, 'cookie' );
		array_walk( $cookies, [ $Database, 'add' ] );

		// Translate DB cookies back to array
		$Database->query( $Factory->get( 'cookies_query' ) );
		$rows = $Database->fetchAll();

		foreach ( $rows as $k => $row ) {
			$expect = $cookies[$k];
			$actual = $Cookies->translateMozillaCookie( $row );

			// Reduce cookie attributes to DB fields
			$expect = array_intersect_key( $expect, array_flip( Cookies::MAP_MOZ_FIELD ) );

			// Remove DB default values
			$actual = array_intersect_key( $actual, $expect );

			// lowercase sameSite field!
			if ( isset( $expect['samesite'] ) && isset( $actual['samesite'] ) ) {
				$expect['samesite'] = strtolower( $expect['samesite'] );
				$actual['samesite'] = strtolower( $actual['samesite'] );
			}

			$this->assertSame( $expect, $actual );
		}
	}

	/**
	 * Parse all Netscape formatted cookies from JAR file.
	 */
	public function testCanParseCookieFile()
	{
		$cookies = require self::fixturePath( 'cookies.php' );
		$Factory = new FactoryMock( $this );
		$Cookies = new Cookies( $Factory );

		$expect = [];
		$data = '# Test: ' . __METHOD__ . PHP_EOL . PHP_EOL;

		foreach ( $cookies as $v ) {
			$data .= $v['file'] . PHP_EOL;

			// Set required Netscape fields / unset unsupported
			$v['cookie'] = array_merge( Cookies::DEFAULTS, $v['cookie'] );
			unset( $v['cookie']['max-age'] );
			unset( $v['cookie']['samesite'] );

			$expect[$v['cookie']['name']] = $v['cookie'];
		}

		$file = self::sandboxPath( '%s-cookies.txt', __FUNCTION__ );
		file_put_contents( $file, $data );

		$this->assertSame( $expect, $Cookies->getCookiesFromFile( $file ) );
	}

	/**
	 * Log changes in cookie JAR file.
	 */
	public function testCanLogCookiesFromFile()
	{
		$cookies = require self::fixturePath( 'cookies.php' );

		$file1 = self::sandboxPath( '%s-cookies1.txt', __FUNCTION__ );
		$file2 = self::sandboxPath( '%s-cookies2.txt', __FUNCTION__ );

		$Factory = new FactoryMock( $this );
		$Cookies = new Cookies( $Factory );

		/* @formatter:off */
		$Factory->Logger()
			->expects( $this->exactly( 3 ) )
			->method( 'debug' )
			->withConsecutive(
				[ $this->logicalAnd( $this->stringContains( 'Add:', true ), $this->stringContains( '15mins', true ) ), 1 ],
				[ $this->logicalAnd( $this->stringContains( 'Mod:', true ), $this->stringContains( '15mins', true ) ), 1 ],
				[ $this->logicalAnd( $this->stringContains( ' >> ', true ), $this->stringContains( '16mins', true ) ), 1 ],
			);
		/* @formatter:on */

		$cookie = $cookies['01_15mins']['file'] . PHP_EOL;
		file_put_contents( $file1, $cookie );
		$Cookies->logCookiesFromFile( $file1, 'debug' );

		$cookie = str_replace( '15mins', '16mins', $cookie );
		file_put_contents( $file1, $cookie );
		$Cookies->logCookiesFromFile( $file1, 'debug' );

		/*
		 * Test support for multiple cookie files!
		 *
		 * Note:
		 * We can NOT make another expectation on same SUT method -> Logger::debug() (within one test case)
		 * so instead we use different method here -> Logger::info()
		 */
		/* @formatter:off */
		$Factory->Logger()
			->expects( $this->exactly( 3 ) )
			->method( 'info' )
			->withConsecutive(
				[ $this->logicalAnd( $this->stringContains( 'Add:', true ), $this->stringContains( 'cookie3', true ) ), 1 ],
				[ $this->logicalAnd( $this->stringContains( 'Add:', true ), $this->stringContains( 'cookie7', true ) ), 1 ],
				[ $this->logicalAnd( $this->stringContains( 'Del:', true ), $this->stringContains( 'cookie7', true ) ), 1 ],
			);
		/* @formatter:on */

		$cookie = $cookies['03_session']['file'] . PHP_EOL . $cookies['07_encode']['file'] . PHP_EOL;
		file_put_contents( $file2, $cookie );
		$Cookies->logCookiesFromFile( $file2, 'info' );

		$cookie = $cookies['03_session']['file'] . PHP_EOL;
		file_put_contents( $file2, $cookie );
		$Cookies->logCookiesFromFile( $file2, 'info' );
	}

	/**
	 * Simply skip if cookie file is missing.
	 */
	public function testCanLogCookiesFromMissingFile()
	{
		$Factory = new FactoryMock( $this );
		$Factory->Logger()->expects( $this->never() )->method( 'debug' );

		$Cookies = new Cookies( $Factory );
		$Cookies->logCookiesFromFile( 'X:/wrong/path', 'debug' );
	}
}
