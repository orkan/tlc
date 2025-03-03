<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC;

/**
 * Factory: Orkan\TLC.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class Factory extends \Orkan\Factory
{
	/*
	 * Services:
	 */
	protected $Cache;
	protected $Cookies;
	protected $Transport;
	protected $TransportStats;
	protected $Request;
	protected $Proxy;

	// =================================================================================================================
	// SERVICES
	// =================================================================================================================

	/**
	 * @return Cache
	 */
	public function Cache()
	{
		return $this->Cache ?? $this->Cache = new Cache( $this );
	}

	/**
	 * @return Transport\Cookies
	 */
	public function Cookies()
	{
		return $this->Cookies ?? $this->Cookies = new Transport\Cookies( $this );
	}

	/**
	 * @return Transport\Curl
	 */
	public function Transport()
	{
		return $this->Transport ?? $this->Transport = new Transport\Curl( $this );
	}

	/**
	 * @return Transport\TransportStats
	 */
	public function TransportStats()
	{
		return $this->TransportStats ?? $this->TransportStats = new Transport\TransportStats( $this );
	}

	/**
	 * @return Transport\CurlRequest
	 */
	public function Request()
	{
		return $this->Request ?? $this->Request = new Transport\CurlRequest();
	}

	/**
	 * @return Transport\Flaresolverr
	 */
	public function Proxy()
	{
		return $this->Proxy ?? $this->Proxy = new Transport\Flaresolverr( $this );
	}
}
