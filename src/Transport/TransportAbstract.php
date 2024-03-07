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
abstract class TransportAbstract
{
	/**
	 * Last call microtime groupped by [host].
	 *
	 * @var float[]
	 */
	protected $lastCall = [];

	/**
	 * Last cURL results.
	 *
	 * @see \curl_getinfo()
	 * @see \Orkan\TLC\Transport\Curl::exec()
	 */
	protected $lastInfo = [];

	/**
	 * Last URL used.
	 *
	 * @see \Orkan\TLC\Transport\Curl::exec()
	 */
	protected $lastUrl = '';

	/* @formatter:off */

	/**
	 * Statistics.
	 */
	protected $stats = [
		'total_time'   => 0, // Total request time in fractional seconds
		'total_sent'   => 0, // Total data sent in bytes
		'total_size'   => 0, // Total data recived in bytes
		'total_usleep' => 0, // Throttle: Total sleep time in microseconds
		'total_calls'  => 0, // Throttle: Current call no.
	];
	/* @formatter:on */

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		$this->Utils = $this->Factory->Utils();
		$this->Logger = $this->Factory->Logger();
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
			'net_throttle'     => 2e+6,
			'net_throttle_max' => 6e+6,
			'net_useragent'    => Useragents::getUA(),
		];
		/* @formatter:on */
	}

	/**
	 * Throttle remote calls randomly: [wait_min] <-> [wait_max].
	 *
	 * @param array $options Array (
	 *   [wait_min] => usec,
	 *   [wait_max] => usec,
	 *   [host]     => Separate requests by group/host,
	 * )
	 * @return int Sleep time (usec)
	 */
	protected function throttle( array $options = [] ): float
	{
		$this->stats['total_calls']++;

		/* @formatter:off */
		$options = array_merge([
			'wait_min' => $this->Factory->get( 'net_throttle' ),
			'wait_max' => $this->Factory->get( 'net_throttle_max' ),
			'host'     => 'default',
		], $options );
		/* @formatter:on */

		$options['wait_min'] = min( $options['wait_min'], $options['wait_max'] );
		$options['wait_max'] = max( $options['wait_min'], $options['wait_max'] );

		DEBUG && $this->Logger->debug( 'Options ' . $this->Utils->print_r( $options ) );

		// Time passed from last call
		$last = $this->lastCall[$options['host']] ?? 0;
		$this->lastCall[$options['host']] = $this->Utils->exectime();
		$exec = $min = $max = $wait = 0;
		if ( $last ) {
			$exec = ( $this->Utils->exectime() - $last ) / 1e+3; // nano to usec
			$min = max( 0, $options['wait_min'] - $exec );
			$max = max( 0, $options['wait_max'] - $exec );
			$wait = rand( $min, $max );
		}

		/* @formatter:off */
		DEBUG && $this->Factory->debug( 'Request #%total% to "%host%"...', [
			'%total%' => $this->stats['total_calls'],
			'%host%'  => $options['host'],
		]);
		DEBUG && $this->Factory->debug(
			'Sleep (min:%min% <-> max:%max%) pas:%pas% + rnd(%rnd1%<->%rnd2%):%rnd% = tot:%tot%', [
			'%min%'   => sprintf( '%.1f', $options['wait_min'] / 1e+6 ), // usec to sec
			'%max%'   => sprintf( '%.1f', $options['wait_max'] / 1e+6 ),
			'%pas%'   => sprintf( '%.3f', $exec / 1e+6 ),
			'%rnd1%'  => $min / 1e+6,
			'%rnd2%'  => $max / 1e+6,
			'%rnd%'   => sprintf( '%.3f', $wait / 1e+6 ),
			'%tot%'   => sprintf( '%.3f', ( $exec + $wait ) / 1e+6 ),
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
		$last = $this->stats['total_usleep'];
		$this->stats['total_usleep'] += $wait;

		/* @formatter:off */
		DEBUG && $this->Factory->debug( 'Sleep (%usec% usec) old:%old% + now:%now% = tot:%tot%', [
			'%usec%' => sprintf( '%.6f', $usec / 1e+6 ), // usec to sec
			'%old%'  => sprintf( '%.3f', $last / 1e+6 ),
			'%now%'  => sprintf( '%.3f', $wait / 1e+6 ),
			'%tot%'  => sprintf( '%.3f', $this->stats['total_usleep'] / 1e+6 ),
		]);
		/* @formatter:on */

		$wait = defined( 'TESTING' ) ? 0 : $wait;
		usleep( $wait );

		return $wait;
	}

	/**
	 * Build statistics.
	 */
	public function stats( ?string $key = null )
	{
		$execTime = $this->Utils->exectime( null );
		$sleepTime = $this->stats['total_usleep'] / 1e+6; // microseconds to seconds
		$phpTime = $execTime - $this->stats['total_time'] - $sleepTime;

		/* @formatter:off */
		$this->stats['extra'] = [
			'sizes' => [
				'sent: ' . $this->Utils->byteString( $this->stats['total_sent'] ),
			],
			'times' => [
				'PHP: '      . $this->Utils->timeString( $phpTime ),
				'NET: '      . $this->Utils->timeString( $this->stats['total_time'] ),
				'Sleep: '    . $this->Utils->timeString( $sleepTime ),
				'Requests: ' . $this->stats['total_calls'],
			],
		];
		/* @formatter:on */

		// Summary
		$bytes = $this->Utils->byteString( $this->stats['total_size'] );
		$bytes .= ' (' . implode( ', ', $this->stats['extra']['sizes'] ) . ')';

		$times = $this->Utils->timeString( $execTime );
		$times .= ' (' . implode( ', ', $this->stats['extra']['times'] ) . ')';

		$this->stats['summary'] = "Recived $bytes in $times";

		return $this->stats[$key] ?? $this->stats;
	}

	/**
	 * Get last CURL results.
	 */
	public function lastInfo(): array
	{
		return $this->lastInfo;
	}

	/**
	 * Get last URL used.
	 */
	public function lastUrl(): string
	{
		return $this->lastUrl;
	}

	/**
	 * Choose the right method to send http request.
	 *
	 * @param  string $method Request method: get, post
	 * @param  string $extra  Extra options passed to child class
	 * @return string         Response from the server
	 */
	public function with( string $method, string $url, array $options = [] ): string
	{
		$method = strtolower( $method );
		return $this->$method( $url, $options );
	}

	/**
	 * Do [get] http request.
	 *
	 * @param  string $url     Full target url (could be with query)
	 * @param  array  $options Extra options passed to child class
	 * @return string          Response from the server
	 */
	abstract public function get( string $url, array $options = [] ): string;

	/**
	 * Do [post] http request.
	 *
	 * @param string  $url     Full target url (could be with query)
	 * @param array   $options Extra options passed to child class
	 *                         To pass FORM fields use: $options[fields] = Array( [field_name] => value, ... )
	 * @return string          Response from the server
	 */
	abstract public function post( string $url, array $options = [] ): string;
}
