<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Tests;

use Orkan\TLC\Cache;
use Orkan\TLC\Transport\Cookies;
use Orkan\TLC\Transport\Curl;
use Orkan\TLC\Transport\CurlRequest;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Mock trait: Orkan\TLC\Factory.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
trait FactoryMockTrait
{
	use \Orkan\Tests\FactoryMockTrait;

	// =================================================================================================================
	// SERVICES - MOCK
	// =================================================================================================================

	/**
	 * @return MockObject
	 */
	public function Transport()
	{
		return $this->Transport ?? $this->Transport = $this->TestCase->createMock( Curl::class );
	}

	/**
	 * @return MockObject
	 */
	public function Request()
	{
		return $this->Request ?? $this->Request = $this->TestCase->createMock( CurlRequest::class );
	}

	/**
	 * @return MockObject
	 */
	public function Cache()
	{
		return $this->Cache ?? $this->Cache = $this->TestCase->createMock( Cache::class );
	}

	/**
	 * @return MockObject
	 */
	public function Cookies()
	{
		return $this->Cookies ?? $this->Cookies = $this->TestCase->createMock( Cookies::class );
	}
}
