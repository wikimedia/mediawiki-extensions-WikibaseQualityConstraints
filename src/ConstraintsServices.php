<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
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
	const CONSTRAINT_REPOSITORY = 'WBQC_ConstraintRepository';
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

	// TODO these services really belong in WikibaseRepo
	const ENTITY_LOOKUP = 'WBQC_EntityLookup';
	const PROPERTY_DATA_TYPE_LOOKUP = 'WBQC_PropertyDataTypeLookup';

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

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConnectionCheckerHelper
	 */
	public static function getConnectionCheckerHelper( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONNECTION_CHECKER_HELPER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return RangeCheckerHelper
	 */
	public static function getRangeCheckerHelper( MediaWikiServices $services = null ) {
		return self::getService( $services, self::RANGE_CHECKER_HELPER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return SparqlHelper
	 */
	public static function getSparqlHelper( MediaWikiServices $services = null ) {
		return self::getService( $services, self::SPARQL_HELPER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return TypeCheckerHelper
	 */
	public static function getTypeCheckerHelper( MediaWikiServices $services = null ) {
		return self::getService( $services, self::TYPE_CHECKER_HELPER );
	}

	// the below services really belong in WikibaseRepo, but we need them wired up here for now

	/**
	 * @param MediaWikiServices|null $services
	 * @return EntityLookup
	 */
	public static function getEntityLookup( MediaWikiServices $services = null ) {
		return self::getService( $services, self::ENTITY_LOOKUP );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return PropertyDataTypeLookup
	 */
	public static function getPropertyDataTypeLookup( MediaWikiServices $services = null ) {
		return self::getService( $services, self::PROPERTY_DATA_TYPE_LOOKUP );
	}

}
