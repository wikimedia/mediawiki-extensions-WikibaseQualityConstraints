<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\Api\ExpiryLock;
use WikibaseQuality\ConstraintReport\Api\ResultsSource;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageSerializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResultSerializer;

/**
 * @license GPL-2.0-or-later
 */
class ConstraintsServices {

	const LOGGING_HELPER = 'WBQC_LoggingHelper';
	const CONSTRAINT_STORE = 'WBQC_ConstraintStore';
	const CONSTRAINT_LOOKUP = 'WBQC_ConstraintLookup';
	const CHECK_RESULT_SERIALIZER = 'WBQC_CheckResultSerializer';
	const CHECK_RESULT_DESERIALIZER = 'WBQC_CheckResultDeserializer';
	const VIOLATION_MESSAGE_SERIALIZER = 'WBQC_ViolationMessageSerializer';
	const VIOLATION_MESSAGE_DESERIALIZER = 'WBQC_ViolationMessageDeserializer';
	const CONSTRAINT_PARAMETER_PARSER = 'WBQC_ConstraintParameterParser';
	const CONNECTION_CHECKER_HELPER = 'WBQC_ConnectionCheckerHelper';
	const RANGE_CHECKER_HELPER = 'WBQC_RangeCheckerHelper';
	const SPARQL_HELPER = 'WBQC_SparqlHelper';
	const TYPE_CHECKER_HELPER = 'WBQC_TypeCheckerHelper';
	const DELEGATING_CONSTRAINT_CHECKER = 'WBQC_DelegatingConstraintChecker';
	const RESULTS_SOURCE = 'WBQC_ResultsSource';
	const EXPIRY_LOCK = 'WBQC_ExpiryLock';

	private static function getService( ?MediaWikiServices $services, $name ) {
		if ( $services === null ) {
			$services = MediaWikiServices::getInstance();
		}
		return $services->getService( $name );
	}

	public static function getLoggingHelper( MediaWikiServices $services = null ): LoggingHelper {
		return self::getService( $services, self::LOGGING_HELPER );
	}

	public static function getConstraintStore(
		MediaWikiServices $services = null
	): ConstraintStore {
		return self::getService( $services, self::CONSTRAINT_STORE );
	}

	public static function getConstraintLookup( MediaWikiServices $services = null ): ConstraintLookup {
		return self::getService( $services, self::CONSTRAINT_LOOKUP );
	}

	public static function getCheckResultSerializer(
		MediaWikiServices $services = null
	): CheckResultSerializer {
		return self::getService( $services, self::CHECK_RESULT_SERIALIZER );
	}

	public static function getCheckResultDeserializer(
		MediaWikiServices $services = null
	): CheckResultDeserializer {
		return self::getService( $services, self::CHECK_RESULT_DESERIALIZER );
	}

	public static function getViolationMessageSerializer(
		MediaWikiServices $services = null
	): ViolationMessageSerializer {
		return self::getService( $services, self::VIOLATION_MESSAGE_SERIALIZER );
	}

	public static function getViolationMessageDeserializer(
		MediaWikiServices $services = null
	): ViolationMessageDeserializer {
		return self::getService( $services, self::VIOLATION_MESSAGE_DESERIALIZER );
	}

	public static function getConstraintParameterParser(
		MediaWikiServices $services = null
	): ConstraintParameterParser {
		return self::getService( $services, self::CONSTRAINT_PARAMETER_PARSER );
	}

	public static function getConnectionCheckerHelper(
		MediaWikiServices $services = null
	): ConnectionCheckerHelper {
		return self::getService( $services, self::CONNECTION_CHECKER_HELPER );
	}

	public static function getRangeCheckerHelper( MediaWikiServices $services = null ): RangeCheckerHelper {
		return self::getService( $services, self::RANGE_CHECKER_HELPER );
	}

	public static function getSparqlHelper( MediaWikiServices $services = null ): SparqlHelper {
		return self::getService( $services, self::SPARQL_HELPER );
	}

	public static function getTypeCheckerHelper( MediaWikiServices $services = null ): TypeCheckerHelper {
		return self::getService( $services, self::TYPE_CHECKER_HELPER );
	}

	public static function getDelegatingConstraintChecker(
		MediaWikiServices $services = null
	): DelegatingConstraintChecker {
		return self::getService( $services, self::DELEGATING_CONSTRAINT_CHECKER );
	}

	public static function getResultsSource( MediaWikiServices $services = null ): ResultsSource {
		return self::getService( $services, self::RESULTS_SOURCE );
	}

	public static function getExpiryLock( MediaWikiServices $services = null ): ExpiryLock {
		return self::getService( $services, self::EXPIRY_LOCK );
	}

}
