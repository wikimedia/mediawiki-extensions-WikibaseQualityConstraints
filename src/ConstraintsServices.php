<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;

/**
 * @license GPL-2.0-or-later
 */
class ConstraintsServices {

	private static function getService( MediaWikiServices $services = null, $name ) {
		if ( $services === null ) {
			$services = MediaWikiServices::getInstance();
		}
		return $services->getService( $name );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return LoggingHelper
	 */
	public static function getLoggingHelper( MediaWikiServices $services = null ) {
		return self::getService( $services, 'WBQC_LoggingHelper' );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintRepository
	 */
	public static function getConstraintRepository( MediaWikiServices $services = null ) {
		return self::getService( $services, 'WBQC_ConstraintRepository' );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintLookup
	 */
	public static function getConstraintLookup( MediaWikiServices $services = null ) {
		return self::getService( $services, 'WBQC_ConstraintLookup' );
	}

}
