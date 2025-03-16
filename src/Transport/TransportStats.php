<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2025 Orkan <orkans+tlc@gmail.com>
 */
namespace Orkan\TLC\Transport;

use Orkan\TLC\Factory;

/**
 * Statistics: Orkan\TLC\Transport.
 *
 * @property int      $calls    Total requests.
 * @property float    $time     Total request time (sec).
 * @property int      $sleep    Total sleep time (usec).
 * @property int      $sent     Total data sent (bytes).
 * @property int      $size     Total data recived (bytes).
 * @property string   $lastUrl  Last request URL.
 * @property array    $lastInfo Last request info.
 * @property string[] $sizes    Formated all bytes but [size], eg. Array ( "sent: 2kB" ).
 * @property string[] $times    Formated all times, eg. Array ( "PHP: 1s", "NET: 2s", "Sleep: 3s" ).
 * $summarystring   $summary  Formated string eg. "Recived 20MB in 4m21s".
 *
 * @author Orkan <orkans+tlc@gmail.com>
 */
class TransportStats extends \Orkan\Dataset
{
	/**
	 * Read-only fields.
	 */
	protected $read = [ 'sizes', 'times', 'summary' ];

	/**
	 * Summary status.
	 */
	protected $dirtySummary = true;

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory;
		$this->Utils = $Factory->Utils();

		/* @formatter:off */
		parent::__construct([
			'calls'   => 0,
			'time'    => 0,
			'sleep'   => 0,
			'sent'    => 0,
			'size'    => 0,
			'sizes'   => [],
			'times'   => [],
			'summary' => '',
		]);
		/* @formatter:on */

		// Force first rebuild
		$this->dirty = true;
	}

	protected function getSizes()
	{
		return $this->summary( 'sizes' );
	}

	protected function getTimes()
	{
		return $this->summary( 'times' );
	}

	protected function getSummary()
	{
		return $this->summary( 'summary' );
	}

	/**
	 * Build summary.
	 */
	protected function summary( string $key = '' )
	{
		if ( $this->dirtySummary ) {
			$timeExe = $this->Utils->exectime( null );
			$timeSlp = $this->data['sleep'] / 1e+6; // usec to sec
			$timePhp = $timeExe - $this->data['time'] - $timeSlp;

			/* @formatter:off */
			$this->data['sizes'] = [
				'sent: ' . $this->Utils->byteString( $this->data['sent'] ),
			];
			$this->data['times'] = [
				'PHP: '      . $this->Utils->timeString( $timePhp ),
				'NET: '      . $this->Utils->timeString( $this->data['time'] ),
				'Sleep: '    . $this->Utils->timeString( $timeSlp ),
				'Requests: ' . $this->data['calls'],
			];
			/* @formatter:on */

			// Summary
			$bytes = $this->Utils->byteString( $this->data['size'] );
			$bytes .= ' (' . implode( ', ', $this->data['sizes'] ) . ')';

			$times = $this->Utils->timeString( $timeExe );
			$times .= ' (' . implode( ', ', $this->data['times'] ) . ')';

			$this->data['summary'] = "Recived $bytes in $times";
			$this->dirtySummary = false;
		}

		if ( !isset( $this->data[$key] ) ) {
			throw new \RuntimeException( "Unknown data[$key]" );
		}

		return $this->data[$key];
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Dataset::rebuild()
	 */
	protected function rebuild(): void
	{
		parent::rebuild();
		$this->dirtySummary = true;
	}
}
