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
	 * Last CURL results.
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

	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( $this->defaults() );
		$this->Utils = $this->Factory->Utils();
		$this->Logger = $this->Factory->Logger();

		$Factory->cfg( 'net_throttle_max', max( $Factory->get( 'net_throttle' ), $Factory->get( 'net_throttle_max' ) ) );

		if ( 'shuffle' === $this->Factory->get( 'net_useragent' ) ) {
			$this->Factory->cfg( 'net_useragent', require __DIR__ . '/useragents.php' );
		}
	}

	/**
	 * Get default config.
	 */
	protected function defaults(): array
	{
		/**
		 * [net_throttle]
		 * 1 request per X microseconds. Use 0 to disable
		 *
		 * [net_throttle_max]
		 * Randomize pause between net requests: cfg[net_throttle]...cfg[net_throttle_max]
		 *
		 * [net_useragent]
		 * Rotate UA from src/Transport/useragents.php
		 *
		 * @formatter:off */
		return [
			'net_throttle'     => 2e+6,
			'net_throttle_max' => 6e+6,
			'net_useragent'    => 'shuffle',
		];
		/* @formatter:on */
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

	/**
	 * Convert options array to string with numeric keys replaced by string constants.
	 */
	abstract public function printOptions( array $options ): string;

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
		/* @formatter:off */
		$options = array_merge([
			'wait_min' => $this->Factory->get( 'net_throttle' ),
			'wait_max' => $this->Factory->get( 'net_throttle_max' ),
			'host'     => 'default',
		], $options );
		/* @formatter:on */

		$options['wait_min'] = min( $options['wait_min'], $options['wait_max'] );
		$options['wait_max'] = max( $options['wait_min'], $options['wait_max'] );

		// Don't wait on first call!
		$wait = 0;
		if ( $lastCall = $this->lastCall[$options['host']] ?? 0) {
			// Time passed since last call
			$exec = ( $this->Utils->exectime() - $lastCall ) / 1e+3; // nano to usec
			$min = $options['wait_min'] - $exec;
			$max = $options['wait_max'] - $exec;

			// Time passed below [wait_min] ?
			if ( 0 < $min ) {
				$wait = rand( $min, $max ); // add some randomness
			}
		}

		if ( $wait ) {

			if ( DEBUG ) {
				$this->Logger->debug( 'Options ' . $this->Utils->print_r( $options ) );
				/* @formatter:off */
				$this->Factory->debug(
					'Request #%total% to "%host%"... ' .
					'Sleep (min:%min% <-> max:%max%) pas:%pas% + rnd:%rnd% = tot:%tot%', [
					'%total%' => $this->stats['total_calls'],
					'%host%'  => $options['host'],
					'%min%'   => sprintf( '%.1f', $options['wait_min'] / 1e+6 ), // usec to sec
					'%max%'   => sprintf( '%.1f', $options['wait_max'] / 1e+6 ),
					'%pas%'   => sprintf( '%.3f', $exec / 1e+6 ),
					'%rnd%'   => sprintf( '%.3f', $wait / 1e+6 ),
					'%tot%'   => sprintf( '%.3f', ( $exec + $wait ) / 1e+6 ),
				]);
				/* @formatter:on */
			}

			$this->sleep( $wait );
			$this->stats['total_usleep'] += $wait;
		}

		$this->lastCall[$options['host']] = $this->Utils->exectime();

		return $wait;
	}

	/**
	 * Slow down.
	 */
	protected function sleep( int $usec ): void
	{
		!defined( 'TESTING' ) && usleep( $usec );
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
}
