<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Transport;

use Orkan\TLC\Factory;

/**
 * Transport implementation.
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
abstract class Transport
{
	/**
	 * Last call microtime groupped by [host].
	 * @var float[]
	 */
	protected $lastCall = [];

	/**
	 * Count calls groupped by [host].
	 * @var float[]
	 */
	protected $hostCall = [];

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;
	protected $Loggex;
	protected $Cache;
	protected $Stats;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		$this->Utils = $Factory->Utils();
		$this->Logger = $Factory->Logger();
		$this->Loggex = $Factory->Loggex();
		$this->Cache = $Factory->Cache();
		$this->Stats = $Factory->TransportStats();

		if ( null === $Factory->cfg( 'net_useragent' ) ) {
			/* @formatter:off */
			$Factory->cfg( 'net_useragent', $this->Utils->netUseragent([
				'brand'  => [ 'chrome', 'firefox' ],
				'os'     => 'windows',
				'device' => 'desktop',
				'last'   => 5,
			]));
			/* @formatter:on */
		}
	}

	/**
	 * Get default config.
	 */
	protected function defaults(): array
	{
		/**
		 * [net_throttle]
		 * [json_throttle]
		 * One request per X microseconds (usec)
		 * 0 to disable
		 *
		 * [net_throttle_rnd]
		 * Random interval to add to each request: 0...cfg[net_throttle_rnd] (usec)
		 *
		 * [net_headers]
		 * [json_headers]
		 * Request headers
		 *
		 * [net_useragent]
		 * Useragent string
		 *
		 * @formatter:off */
		return [
			'json_throttle'    => 5e+5, // 0.5 sec
			'json_headers'     => [
				'X-Requested-With: XMLHttpRequest',
			],
			'net_throttle'     => 2e+6, // 2 sec
			'net_throttle_rnd' => 3e+6, // 2 sec --> throttle(2...5)
			'net_headers'      => [],
			'net_useragent'    => null,
		];
		/* @formatter:on */
	}

	/**
	 * Choose the right method to send http request.
	 *
	 * @see \Orkan\TLC\Transport\Transport::get()
	 * @see \Orkan\TLC\Transport\Transport::post()
	 *
	 * @param string $method Request method: get|post
	 */
	public function with( string $method, string $url, array $opt = [] ): string
	{
		$method = strtolower( $method );
		return $this->$method( $url, $opt );
	}

	/**
	 * Do [get] http request.
	 *
	 * @param string  $url Target url (with query)
	 * @param array   $opt TLC options
	 * @return string Server response
	 */
	abstract public function get( string $url, array $opt = [] ): string;

	/**
	 * Do [post] http request.
	 *
	 * @param string  $url Target url
	 * @param array   $opt TLC options
	 * @return string Server response
	 */
	abstract public function post( string $url, array $opt = [] ): string;

	/**
	 * Load file from cache or download if not exist and cache it.
	 *
	 * TLC options:
	 * [cache][reload]     => Refresh cache?
	 * [transport][method] => HTTP method: [get]|post
	 *
	 * @return string Server response or previous cached results
	 */
	public function getUrl( string $url, array $opt = [] ): string
	{
		/* @formatter:off */
		$opt = array_replace_recursive([
			'transport' => [
				'method' => 'get',
			],
			'cache' => [
				'key'     => $url,
				'refresh' => false,
			],
		], $opt );
		/* @formatter:on */

		$this->Logger->debug( $url );
		DEBUG && $opt && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		if ( $opt['cache']['refresh'] ) {
			$this->Cache->del( $opt['cache']['key'] );
		}

		$data = $this->Cache->get( $opt['cache']['key'] );

		if ( false === $data ) {
			$data = $this->with( $opt['transport']['method'], $url, $opt );
			$this->Cache->put( $opt['cache']['key'], $data );
		}

		return $data;
	}

	/**
	 * Get decoded JSON.
	 * Save JSON errors to $json[errors][json]
	 *
	 * NOTE:
	 * It uses custom (less restrictive) throttle setting for API calls!
	 * It sends 'X-Requested-With' http header by default:
	 * @see \Orkan\TLC\Application::defaults()
	 *
	 * @return mixed Decoded JSON
	 */
	public function getJson( string $url, array $opt = [] )
	{
		/* @formatter:off */
		$opt = array_replace_recursive([
			'throttle' => [
				'wait' => $this->Factory->get( 'json_throttle', 0 ),
			],
			'curl'  => [
				CURLOPT_HTTPHEADER => $this->Factory->get( 'json_headers' ),
			],
			'tlc'   => [ 'log_errors' => true ],
			'cache' => [ 'key' => $url ],
		], $opt );
		/* @formatter:on */

		$this->Logger->debug( $url );
		DEBUG && $opt && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		$data = $this->getUrl( $url, $opt );
		$json = json_decode( $data, true );

		if ( null === $json ) {
			// Archive faulty response for later inspection
			$this->Cache->archive( $opt['cache']['key'], 'err' );

			/* @formatter:off */
			$json = [
				'url'    => $url,
				'data'   => $data,
				'errors' => [
					'json' => $this->Utils->errorJson(),
				],
			];
			/* @formatter:on */

			$opt['tlc']['log_errors'] && $this->Loggex->error( $json['errors'] );
		}

		DEBUG && $this->Logger->debug( $this->Utils->print_r( $json, [ 'trim' => 250 ] ) );

		return $json;
	}

	/**
	 * Throttle remote calls.
	 *
	 * Options:
	 * [host] => Separate throttles by host
	 * [wait] => Throttle min time (usec)
	 * [rand] => Randomize throttle time: [wait]...[wait+rand] (usec)

	 * @param array $opt Options
	 * @return int  Computed random time (usec)
	 *              Note: the sleep time is computed aftert decreasing this by already elapsed time!
	 */
	protected function throttle( array $opt = [] ): float
	{
		/* @formatter:off */
		$opt = array_merge([
			'host' => 'default',
			'wait' => $this->Factory->get( 'net_throttle' ),
			'rand' => $this->Factory->get( 'net_throttle_rnd' ),
		], $opt );
		/* @formatter:on */

		$this->Stats->calls++;
		$this->Utils->arrayInc( $this->hostCall, $opt['host'] );

		DEBUG && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		// Time passed from last call
		$last = $this->lastCall[$opt['host']] ?? 0;
		$pas = $min = $max = $rnd = $now = 0;
		if ( $last ) {
			$pas = ( $this->Utils->exectime() - $last ) / 1e+3; // nano to usec
			$min = max( 0, $opt['wait'] );
			$max = $min ? $min + $opt['rand'] : 0;
			$rnd = rand( $min, $max );
			$now = $rnd - $pas;
		}

		/* @formatter:off */
		$this->Loggex->debug( 'Request #{total} | #{call}: {host}', [
			'{total}' => $this->Stats->calls,
			'{call}'  => $this->hostCall[$opt['host']],
			'{host}'  => $opt['host'],
		]);
		DEBUG && $this->Loggex->debug(
			'Sleep(min:{min} <-> max:{max}) | rnd({rnd1}<->{rnd2}):{rnd} - pas:{pas} = now:{now}', [
			'{min}'   => sprintf( '%.1f', $opt['wait'] / 1e+6 ), // usec to sec
			'{max}'   => sprintf( '%.1f', ( $opt['wait'] + $opt['rand'] ) / 1e+6 ),
			'{rnd1}'  => $min / 1e+6,
			'{rnd2}'  => $max / 1e+6,
			'{pas}'   => sprintf( '%.3f', $pas / 1e+6 ),
			'{rnd}'   => sprintf( '%.3f', $rnd / 1e+6 ),
			'{now}'   => sprintf( '%.3f', $now / 1e+6 ),
		]);
		/* @formatter:on */

		// Sleep from 2nd call...
		$last && $this->usleep( $now );

		// Record current call time (after pause!)
		$this->lastCall[$opt['host']] = $this->Utils->exectime();

		return $rnd;
	}

	/**
	 * Sleep.
	 */
	protected function usleep( int $usec ): float
	{
		$now = max( 0, $usec );
		$old = $this->Stats->sleep;
		$tot = $old + $now;
		$this->Stats->sleep = $tot;

		/* @formatter:off */
		DEBUG && $this->Loggex->debug( 'Sleep({sec} sec) | old:{old} + now:{now} = tot:{tot}', [
			'{sec}' => sprintf( '%.6f', $usec / 1e+6 ), // usec to sec
			'{old}' => sprintf( '%.3f', $old / 1e+6 ),
			'{now}' => sprintf( '%.3f', $now / 1e+6 ),
			'{tot}' => sprintf( '%.3f', $tot / 1e+6 ),
		]);
		/* @formatter:on */

		$this->Logger->debug( "usleep( $now )" );
		$this->Utils->usleep( $now );

		return $now;
	}

	/**
	 * Extract host string from url.
	 */
	protected function host( string $url ): string
	{
		return parse_url( $url, PHP_URL_HOST );
	}
}
