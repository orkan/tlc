<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2024 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests;

/**
 * Test: Orkan\TLC\Factory.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class FactoryTest extends TestCase
{
	const USE_SANDBOX = false;
	const USE_FIXTURE = false;

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * getJson(): skip log errors.
	 */
	public function testCanGetJsonSkipLogError()
	{
		$Factory = new FactoryMock( $this );

		$url = 'url/api';
		$err = ' ERROR! ';

		$Factory->Cache()->expects( $this->exactly( 2 ) )->method( 'get' )->with( $url )->willReturn( $err );

		// Log error first time only!
		$Factory->Logger()->expects( $this->once() )->method( 'error' )->with( $this->stringContains( 'JSON_ERROR_SYNTAX' ) );

		$json1 = $Factory->getJson( $url ); // Error: log
		$json2 = $Factory->getJson( $url, [ 'tlc' => [ 'log_errors' => false ] ] ); // Error: skip logging

		$this->assertSame( $err, $json1['data'] );
		$this->assertSame( $err, $json2['data'] );

		$this->assertTrue( isset( $json1['errors']['json'] ) );
		$this->assertTrue( isset( $json2['errors']['json'] ) );
	}
}
