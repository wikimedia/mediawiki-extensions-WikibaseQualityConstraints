<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use Job;
use JobQueueGroup;
use Serializers\Serializer;
use Title;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store;
use Wikimedia\Assert\Assert;

/**
 * A job that updates the constraints table
 * when changes were made on a property.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class UpdateConstraintsTableJob extends Job {

	const BATCH_SIZE = 10;

	public static function newFromGlobalState( Title $title, array $params ) {
		Assert::parameterType( 'string', $params['propertyId'], '$params["propertyId"]' );
		$repo = WikibaseRepo::getDefaultInstance();
		return new UpdateConstraintsTableJob(
			$title,
			$params,
			$params['propertyId'],
			$params['revisionId'] ?? null,
			MediaWikiServices::getInstance()->getMainConfig(),
			ConstraintsServices::getConstraintRepository(),
			$repo->getEntityRevisionLookup( Store::LOOKUP_CACHING_DISABLED ),
			$repo->getBaseDataModelSerializerFactory()->newSnakSerializer()
		);
	}

	/**
	 * @var string
	 */
	private $propertyId;

	/**
	 * @var int|null
	 */
	private $revisionId;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var ConstraintRepository
	 */
	private $constraintRepo;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var Serializer
	 */
	private $snakSerializer;

	/**
	 * @param Title $title
	 * @param string[] $params should contain 'propertyId' => 'P...'
	 * @param string $propertyId property ID of the property for this job (which has the constraint statements)
	 * @param int|null $revisionId revision ID that triggered this job, if any
	 * @param Config $config
	 * @param ConstraintRepository $constraintRepo
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param Serializer $snakSerializer
	 */
	public function __construct(
		Title $title,
		array $params,
		$propertyId,
		$revisionId,
		Config $config,
		ConstraintRepository $constraintRepo,
		EntityRevisionLookup $entityRevisionLookup,
		Serializer $snakSerializer
	) {
		parent::__construct( 'constraintsTableUpdate', $title, $params );

		$this->propertyId = $propertyId;
		$this->revisionId = $revisionId;
		$this->config = $config;
		$this->constraintRepo = $constraintRepo;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->snakSerializer = $snakSerializer;
	}

	public function extractParametersFromQualifiers( SnakList $qualifiers ) {
		$parameters = [];
		foreach ( $qualifiers as $qualifier ) {
			$qualifierId = $qualifier->getPropertyId()->getSerialization();
			$paramSerialization = $this->snakSerializer->serialize( $qualifier );
			$parameters[$qualifierId][] = $paramSerialization;
		}
		return $parameters;
	}

	public function extractConstraintFromStatement(
		PropertyId $propertyId,
		Statement $constraintStatement
	) {
		$constraintId = $constraintStatement->getGuid();
		$constraintTypeQid = $constraintStatement->getMainSnak()->getDataValue()->getEntityId()->getSerialization();
		$parameters = $this->extractParametersFromQualifiers( $constraintStatement->getQualifiers() );
		return new Constraint(
			$constraintId,
			$propertyId,
			$constraintTypeQid,
			$parameters
		);
	}

	public function importConstraintsForProperty(
		Property $property,
		ConstraintRepository $constraintRepo,
		PropertyId $propertyConstraintPropertyId
	) {
		$constraintsStatements = $property->getStatements()
			->getByPropertyId( $propertyConstraintPropertyId )
			->getByRank( [ Statement::RANK_PREFERRED, Statement::RANK_NORMAL ] );
		$constraints = [];
		foreach ( $constraintsStatements->getIterator() as $constraintStatement ) {
			$constraints[] = $this->extractConstraintFromStatement( $property->getId(), $constraintStatement );
			if ( count( $constraints ) >= self::BATCH_SIZE ) {
				$constraintRepo->insertBatch( $constraints );
				$constraints = [];
			}
		}
		$constraintRepo->insertBatch( $constraints );
	}

	/**
	 * @see Job::run
	 *
	 * @return bool
	 */
	public function run() {
		// TODO in the future: only touch constraints affected by the edit (requires T163465)

		$propertyId = new PropertyId( $this->propertyId );
		$propertyRevision = $this->entityRevisionLookup->getEntityRevision(
			$propertyId,
			0, // latest
			EntityRevisionLookup::LATEST_FROM_REPLICA
		);

		if ( $this->revisionId !== null && $propertyRevision->getRevisionId() < $this->revisionId ) {
			JobQueueGroup::singleton()->push( $this );
			return true;
		}

		$this->constraintRepo->deleteForProperty( $propertyId );

		/** @var Property $property */
		$property = $propertyRevision->getEntity();
		$this->importConstraintsForProperty(
			$property,
			$this->constraintRepo,
			new PropertyId( $this->config->get( 'WBQualityConstraintsPropertyConstraintId' ) )
		);

		return true;
	}

}
