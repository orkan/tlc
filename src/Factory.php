<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC;

use Orkan\TLC\Transport\Cookies;
use Orkan\TLC\Transport\Curl;
use Orkan\TLC\Transport\CurlRequest;

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
	protected $Request;

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
		return $this->Cookies ?? $this->Cookies = new Cookies( $this );
	}

	/**
	 * @return Transport\Curl
	 */
	public function Transport()
	{
		return $this->Transport ?? $this->Transport = new Curl( $this );
	}

	/**
	 * @return Transport\CurlRequest
	 */
	public function Request()
	{
		return $this->Request ?? $this->Request = new CurlRequest();
	}

	// =================================================================================================================
	// HELPERS
	// =================================================================================================================

	/**
	 * Get decoded JSON.
	 *
	 * Save JSON errors to $json[errors][json]
	 * Save Filmweb error to cfg[last_error_filmweb]
	 *
	 * NOTE:
	 * It uses custom (less restrictive) throttle setting for API calls!
	 * It sends 'X-Requested-With' http header by default
	 * @see Application::defaults()
	 *
	 * @return mixed Decoded JSON
	 */
	public function getJson( string $url, array $options = [] )
	{
		$Utils = $this->Utils();
		$Cache = $this->Cache();
		$Logger = $this->Logger();

		/* @formatter:off */
		$options = array_replace_recursive([
			'throttle' => [
				'wait_min' => $this->get( 'json_throttle' ),
				'wait_max' => $this->get( 'json_throttle_max' ),
			],
			'curl' => [],
		], $options );

		// Merge default json http headers with user options or cfg value
		$options['curl'][CURLOPT_HTTPHEADER] = $Utils->arrayMergeValues(
			$this->get( 'json_headers', [] ),
			$options['curl'][CURLOPT_HTTPHEADER] ?? $this->get( 'net_curl' )[CURLOPT_HTTPHEADER] ?? [],
		);
		/* @formatter:on */

		$data = $this->getUrl( $url, $options );
		$json = json_decode( $data, true );

		if ( null === $json ) {
			/* @formatter:off */
			$json = [
				'url'    => $url,
				'data'   => $data,
				'errors' => [
					'json' => $Utils->errorJson(),
				],
			];
			/* @formatter:on */

			// Archive faulty response for later inspection
			$Cache->archive( $url, 'err' );

			$this->error( $json['errors'] );
		}

		DEBUG && $Logger->debug( $Utils->print_r( $json ) );

		return $json;
	}

	/**
	 * Load file from cache or download if not exist and cache it.
	 */
	public function getUrl( string $url, array $options = [] ): string
	{
		$Utils = $this->Utils();
		$Logger = $this->Logger();
		$Cache = $this->Cache();
		$Transport = $this->Transport();

		/* @formatter:off */
		$options = array_replace_recursive([
			'cache'     => [ 'reload' => false ],
			'transport' => [ 'method' => 'get' ],
		], $options );
		/* @formatter:on */

		$Logger->debug( $url );
		$Logger->debug( 'Options ' . $Utils->print_r( $options ) );

		if ( $options['cache']['reload'] ) {
			$Cache->del( $url );
		}

		$data = $Cache->get( $url );

		if ( false === $data ) {
			$data = $Transport->with( $options['transport']['method'], $url, $options );
			$Cache->put( $url, $data );
		}

		return (string) $data;
	}

	/**
	 * Append file contents to log file.
	 */
	public function logFile( string $file ): void
	{
		if ( DEBUG && is_file( $file ) ) {
			foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
				$this->Logger()->debug( $line, 1 );
			}
		}
	}

	/**
	 * Build net stats.
	 *
	 * @see \Orkan\TLC\Transport\TransportAbstract::$stats
	 */
	public function statsNet( string $key = '' )
	{
		$Utils = $this->Utils();
		$Transport = $this->Transport();

		$net = $Transport->stats();
		$stats = [];
		$stats['dl_time'] = $net['total_time'];
		$stats['dl_size'] = $net['total_size'];

		$sleep_time = $net['total_usleep'] / 1e+6; // microseconds to seconds

		$php_time = $Utils->exectime( null );
		$php_time -= $net['total_time'];
		$php_time -= $sleep_time;

		/* @formatter:off */
		$stats['extra'] = [
			'sizes' => [
				'sent: ' . $Utils->byteString( $net['total_sent'] ),
			],
			'times' => [
				'PHP: '      . $Utils->timeString( $php_time ),
				'NET: '      . $Utils->timeString( $net['total_time'] ),
				'Sleep: '    . $Utils->timeString( $sleep_time ),
				'Requests: ' . $net['total_calls'],
			],
		];
		/* @formatter:on */

		// Summary
		$bytes = $Utils->byteString( $stats['dl_size'] );
		$bytes .= ' (' . implode( ', ', $stats['extra']['sizes'] ) . ')';

		$times = $Utils->timeString( $Utils->exectime( null ) );
		$times .= ' (' . implode( ', ', $stats['extra']['times'] ) . ')';

		$stats['summary'] = "Recived $bytes in $times";

		return $stats[$key] ?? $stats;
	}
}
