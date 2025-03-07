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
		 * 1 request per X microseconds
		 * Use 0 to disable
		 *
		 * [net_throttle_max]
		 * Randomize pause between net requests: cfg[net_throttle]...cfg[net_throttle_max]
		 * Use value from cfg[net_throttle] to disable
		 *
		 * [net_useragent]
		 * Useragent string
		 *
		 * @formatter:off */
		return [
			'json_throttle'     => 6e+5,
			'json_throttle_max' => 1e+6,
			'json_headers'      => [
				'X-Requested-With: XMLHttpRequest',
			],
			'net_throttle'      => 2e+6,
			'net_throttle_max'  => 6e+6,
			'net_headers'       => [],
			'net_useragent'     => null,
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

		DEBUG && $this->Logger->debug( $url );
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
				'wait_min' => $this->Factory->get( 'json_throttle', 0 ),
				'wait_max' => $this->Factory->get( 'json_throttle_max', 0 ),
			],
			'curl'  => [
				CURLOPT_HTTPHEADER => $this->Factory->get( 'json_headers' ),
			],
			'tlc'   => [ 'log_errors' => true ],
			'cache' => [ 'key' => $url ],
		], $opt );
		/* @formatter:on */

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
	 * Throttle remote calls randomly: [wait_min] <-> [wait_max].
	 *
	 * Options:
	 * [host]     => Group requests by host
	 * [wait_min] => Throttle min time or throttle time if [max] is empty (usec)
	 * [wait_max] => Throttle max time (usec)

	 * @param array $opt Options
	 * @return int  Sleep time (usec)
	 */
	protected function throttle( array $opt = [] ): float
	{
		$this->Stats->calls++;
		$this->Utils->arrayInc( $this->hostCall, $opt['host'] );

		/* @formatter:off */
		$opt = array_merge([
			'host'     => 'default',
			'wait_min' => $this->Factory->get( 'net_throttle', 0 ),
			'wait_max' => $this->Factory->get( 'net_throttle_max', 0 ),
		], $opt );
		/* @formatter:on */

		$opt['wait_min'] = (int) min( $opt['wait_min'], $opt['wait_max'] );
		$opt['wait_max'] = (int) max( $opt['wait_min'], $opt['wait_max'] );

		DEBUG && $this->Logger->debug( 'Opt ' . $this->Utils->print_r( $opt ) );

		// Time passed from last call
		$last = $this->lastCall[$opt['host']] ?? 0;
		$this->lastCall[$opt['host']] = $this->Utils->exectime();
		$exec = $min = $max = $wait = 0;
		if ( $last ) {
			$exec = ( $this->Utils->exectime() - $last ) / 1e+3; // nano to usec
			$min = max( 0, $opt['wait_min'] - $exec );
			$max = max( 0, $opt['wait_max'] - $exec );
			$wait = rand( $min, $max );
		}

		/* @formatter:off */
		DEBUG && $this->Loggex->debug( 'Request #{total} | #{call}: {host}', [
			'{total}' => $this->Stats->calls,
			'{call}'  => $this->hostCall[$opt['host']],
			'{host}'  => $opt['host'],
		]);
		DEBUG && $this->Loggex->debug(
			'Sleep (min:{min} <-> max:{max}) pas:{pas} + rnd({rnd1}<->{rnd2}):{rnd} = tot:{tot}', [
			'{min}'   => sprintf( '%.1f', $opt['wait_min'] / 1e+6 ), // usec to sec
			'{max}'   => sprintf( '%.1f', $opt['wait_max'] / 1e+6 ),
			'{pas}'   => sprintf( '%.3f', $exec / 1e+6 ),
			'{rnd1}'  => $min / 1e+6,
			'{rnd2}'  => $max / 1e+6,
			'{rnd}'   => sprintf( '%.3f', $wait / 1e+6 ),
			'{tot}'   => sprintf( '%.3f', ( $exec + $wait ) / 1e+6 ),
		]);
		/* @formatter:on */

		// Sleep from 2nd call...
		$last && $this->sleep( $wait );

		return $wait;
	}

	/**
	 * Slow down.
	 */
	protected function sleep( int $usec ): float
	{
		$wait = max( 0, $usec );
		$last = $this->Stats->sleep;
		$this->Stats->sleep += $wait;

		/* @formatter:off */
		DEBUG && $this->Loggex->debug( 'Sleep ({sec} sec) old:{old} + now:{now} = tot:{tot}', [
			'{sec}' => sprintf( '%.6f', $usec / 1e+6 ), // usec to sec
			'{old}' => sprintf( '%.3f', $last / 1e+6 ),
			'{now}' => sprintf( '%.3f', $wait / 1e+6 ),
			'{tot}' => sprintf( '%.3f', $this->Stats->sleep / 1e+6 ),
		]);
		/* @formatter:on */

		$wait = defined( 'TESTING' ) ? 0 : $wait;
		usleep( $wait );

		DEBUG && $this->Loggex->debug( "usleep($wait) done!" );

		return $wait;
	}

	/**
	 * Extract host string from url.
	 */
	protected function host( string $url ): string
	{
		return parse_url( $url, PHP_URL_HOST );
	}
}
