<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests\Transport;

use Orkan\TLC\Tests\FactoryMock;
use Orkan\TLC\Transport\Curl;

/**
 * Test: Orkan\TLC\Transport\Curl.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class CurlTest extends \Orkan\TLC\Tests\TestCase
{

	/**
	 * Merge with parent defaults.
	 */
	public function testCanMergeDefaults()
	{
		$Factory = new FactoryMock( $this ); // empty config!
		new Curl( $Factory );

		// Does TransportAbstract cfg passed through?
		$this->assertNotEmpty( $Factory->get( 'net_throttle' ), 'Parent abstract class config' );

		// Does Curl cfg passed through?
		$this->assertNotEmpty( $Factory->get( 'net_retry' ), 'Curl child class config' );
	}
}
