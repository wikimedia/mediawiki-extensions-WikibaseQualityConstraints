<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;

/**
 * @license GPL-2.0-or-later
 */
class ConstraintsServices {

	const LOGGING_HELPER = 'WBQC_LoggingHelper';
	const CONSTRAINT_REPOSITORY = 'WBQC_ConstraintRepository';
	const CONSTRAINT_LOOKUP = 'WBQC_ConstraintLookup';
	const CHECK_RESULT_SERIALIZER = 'WBQC_CheckResultSerializer';
	const CHECK_RESULT_DESERIALIZER = 'WBQC_CheckResultDeserializer';
	const VIOLATION_MESSAGE_SERIALIZER = 'WBQC_ViolationMessageSerializer';
	const VIOLATION_MESSAGE_DESERIALIZER = 'WBQC_ViolationMessageDeserializer';
	const CONSTRAINT_PARAMETER_PARSER = 'WBQC_ConstraintParameterParser';

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
		return self::getService( $services, self::LOGGING_HELPER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintRepository
	 */
	public static function getConstraintRepository( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONSTRAINT_REPOSITORY );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintLookup
	 */
	public static function getConstraintLookup( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONSTRAINT_LOOKUP );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return CheckResultSerializer
	 */
	public static function getCheckResultSerializer( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CHECK_RESULT_SERIALIZER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return CheckResultDeserializer
	 */
	public static function getCheckResultDeserializer( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CHECK_RESULT_DESERIALIZER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ViolationMessageSerializer
	 */
	public static function getViolationMessageSerializer( MediaWikiServices $services = null ) {
		return self::getService( $services, self::VIOLATION_MESSAGE_SERIALIZER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ViolationMessageDeserializer
	 */
	public static function getViolationMessageDeserializer( MediaWikiServices $services = null ) {
		return self::getService( $services, self::VIOLATION_MESSAGE_DESERIALIZER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintParameterParser
	 */
	public static function getConstraintParameterParser( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONSTRAINT_PARAMETER_PARSER );
	}

}
