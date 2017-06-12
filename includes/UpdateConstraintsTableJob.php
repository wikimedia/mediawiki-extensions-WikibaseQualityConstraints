<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use Job;
use Title;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Serializers\SnakSerializer;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Assert\Assert;

/**
 * A job that updates the constraints table
 * when changes were made on a property.
 *
 * @package WikibaseQuality\ConstraintReport;
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
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
			MediaWikiServices::getInstance()->getMainConfig(),
			ConstraintReportFactory::getDefaultInstance()->getConstraintRepository(),
			$repo->getEntityLookup(),
			$repo->getSerializerFactory()
		);
	}

	/**
	 * @var string
	 */
	private $propertyId;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var ConstraintRepository
	 */
	private $constraintRepo;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var SnakSerializer
	 */
	private $snakSerializer;

	/**
	 * @param Title $title
	 * @param string[] $params should contain 'propertyId' => 'P...'
	 * @param string $propertyId property ID of the property for this job (which has the constraint statements)
	 * @param Config $config
	 * @param ConstraintRepository $constraintRepo
	 * @param EntityLookup $entityLookup
	 * @param SerializerFactory $serializerFactory
	 */
	public function __construct(
		Title $title,
		array $params,
		$propertyId,
		Config $config,
		ConstraintRepository $constraintRepo,
		EntityLookup $entityLookup,
		SerializerFactory $serializerFactory
	) {
		parent::__construct( 'constraintsTableUpdate', $title, $params );
		$this->propertyId = $propertyId;
		$this->config = $config;
		$this->constraintRepo = $constraintRepo;
		$this->entityLookup = $entityLookup;
		$this->snakSerializer = $serializerFactory->newSnakSerializer();
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

	public function extractConstraintFromStatement( PropertyId $propertyId, Statement $constraintStatement ) {
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

	public function importConstraintsForProperty( Property $property, ConstraintRepository $constraintRepo, PropertyId $propertyConstraintPropertyId ) {
		$constraintsStatements = $property->getStatements()->getByPropertyId( $propertyConstraintPropertyId );
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

	public function run() {
		// TODO in the future: only touch constraints affected by the edit (requires T163465)

		$propertyId = new PropertyId( $this->propertyId );
		$this->constraintRepo->deleteForPropertyWhereConstraintIdIsStatementId( $propertyId );

		$property = $this->entityLookup->getEntity( $propertyId );
		$this->importConstraintsForProperty( $property, $this->constraintRepo, new PropertyId( $this->config->get( 'WBQualityConstraintsPropertyConstraintId' ) ) );
	}

}
