<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use ApiTestCase;
use NullStatsdDataFactory;
use RequestContext;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\CheckConstraintParameters;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;

/**
 * @covers WikibaseQuality\ConstraintReport\Api\CheckConstraintParameters
 *
 * @group API
 * @group Database
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0-or-later
 */
class CheckConstraintParametersTest extends ApiTestCase {

	const P1 = 'P1';
	const P2 = 'P2';
	const P1_NONEXISTENT = 'P1$febf1ef9-9291-4599-8488-00dd5f6ac814';
	const P1_GOOD = 'P1$c76b6bf6-9c4f-4ef0-afb0-78cae372453d';
	const P1_BAD = 'P1$4f7462a4-4523-4371-a35c-d246fa7159ee';
	const P2_GOOD = 'P2$82648667-ca79-40e1-8839-4ed4abbb4c6d';

	private $oldModuleDeclaration;

	/**
	 * @var callable
	 */
	private $checkConstraintParametersOnPropertyId;

	/**
	 * @var callable
	 */
	private $checkConstraintParametersOnConstraintId;

	/**
	 * @var ViolationMessage
	 */
	private $testMessage;

	/**
	 * @var string HTML
	 */
	private $testMessageHtml;

	public function setUp() : void {
		global $wgAPIModules;

		$this->oldModuleDeclaration = $wgAPIModules['wbcheckconstraintparameters'];

		$this->checkConstraintParametersOnPropertyId = function( $propertyId ) {
			$this->assertTrue( false, 'checkConstraintParametersOnPropertyId method should not be called by this test.' );
		};
		$this->checkConstraintParametersOnConstraintId = function( $constraintId ) {
			$this->assertTrue( false, 'checkConstraintParametersOnConstraintId method should not be called by this test.' );
		};

		$this->testMessage = ( new ViolationMessage( 'wbqc-violation-message-parameter-value' ) )
			->withEntityId( new PropertyId( 'P1' ) );
		$this->testMessageHtml = $this->testMessage->getMessageKey();

		$wgAPIModules['wbcheckconstraintparameters']['factory'] = function ( $main, $name ) {
			$repo = WikibaseRepo::getDefaultInstance();
			$apiHelperFactory = $repo->getApiHelperFactory( RequestContext::getMain() );
			$statementGuidParser = $repo->getStatementGuidParser();

			$delegatingConstraintChecker = $this->createMock( DelegatingConstraintChecker::class );
			$delegatingConstraintChecker->method( 'checkConstraintParametersOnPropertyId' )
				->will( $this->returnCallback(
					function( $propertyId ) {
						$callable = $this->checkConstraintParametersOnPropertyId;
						return $callable( $propertyId );
					}
				) );
			$delegatingConstraintChecker->method( 'checkConstraintParametersOnConstraintId' )
				->will( $this->returnCallback(
					function( $constraintId ) {
						$callable = $this->checkConstraintParametersOnConstraintId;
						return $callable( $constraintId );
					}
				) );

			$violationMessageRenderer = $this->getMockBuilder( ViolationMessageRenderer::class )
				->disableOriginalConstructor()
				->setMethods( [ 'render' ] )
				->getMock();
			$violationMessageRenderer->method( 'render' )
				->willReturnCallback( function( ViolationMessage $violationMessage ) {
					return $violationMessage->getMessageKey();
				} );

			return new CheckConstraintParameters(
				$main,
				$name,
				$apiHelperFactory,
				$delegatingConstraintChecker,
				$violationMessageRenderer,
				$statementGuidParser,
				new NullStatsdDataFactory()
			);
		};

		parent::setUp();
	}

	public function tearDown() : void {
		global $wgAPIModules;

		$wgAPIModules['wbcheckconstraintparameters'] = $this->oldModuleDeclaration;

		parent::tearDown();
	}

	/**
	 * @param array $params
	 * @return array wbcheckconstraintparameters response, slightly normalized
	 */
	private function doRequest( array $params ) {
		$params['action'] = 'wbcheckconstraintparameters';

		$result = $this->doApiRequest( $params, [], false, null )[0];

		$this->assertSame( 1, $result['success'] );
		$this->assertArrayHasKey( 'wbcheckconstraintparameters', $result );

		ksort( $result['wbcheckconstraintparameters'] );
		return $result['wbcheckconstraintparameters'];
	}

	public function testReportForNonexistentProperty() {
		$this->checkConstraintParametersOnPropertyId = function( $propertyId ) {
			return [];
		};

		$result = $this->doRequest(
			[ CheckConstraintParameters::PARAM_PROPERTY_ID => self::P1 ]
		);

		$this->assertSame( [ self::P1 => [] ], $result );
	}

	public function testReportForNonexistentConstraint() {
		$this->checkConstraintParametersOnConstraintId = function( $constraintId ) {
			return null;
		};

		$result = $this->doRequest(
			[ CheckConstraintParameters::PARAM_CONSTRAINT_ID => self::P1_NONEXISTENT ]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_NONEXISTENT => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_FOUND
					]
				]
			],
			$result
		);
	}

	public function testReportForGoodConstraint() {
		$this->checkConstraintParametersOnConstraintId = function( $constraintId ) {
			return [];
		};

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_CONSTRAINT_ID => self::P1_GOOD
			]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					]
				]
			],
			$result
		);
	}

	public function testReportForBadConstraint() {
		$this->checkConstraintParametersOnConstraintId = function( $constraintId ) {
			return [ new ConstraintParameterException( $this->testMessage ) ];
		};

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_CONSTRAINT_ID => self::P1_BAD
			]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_BAD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => [
							[ CheckConstraintParameters::KEY_MESSAGE_HTML => $this->testMessageHtml ]
						]
					]
				]
			],
			$result
		);
	}

	public function testReportForMultipleConstraints() {
		$this->checkConstraintParametersOnConstraintId = function( $constraintId ) {
			switch ( $constraintId ) {
				case self::P1_NONEXISTENT:
					return null;
				case self::P1_GOOD:
					return [];
				case self::P1_BAD:
					return [ new ConstraintParameterException( $this->testMessage ) ];
			}
		};

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_CONSTRAINT_ID =>
					self::P1_NONEXISTENT . '|' . self::P1_GOOD . '|' . self::P1_BAD
			]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_NONEXISTENT => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_FOUND
					],
					self::P1_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					],
					self::P1_BAD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => [
							[ CheckConstraintParameters::KEY_MESSAGE_HTML => $this->testMessageHtml ]
						]
					]
				]
			],
			$result
		);
	}

	public function testReportForMultipleProperties() {
		$this->checkConstraintParametersOnPropertyId = function( $propertyId ) {
			switch ( $propertyId->getSerialization() ) {
				case self::P1:
					return [
						self::P1_GOOD => [],
						self::P1_BAD => [ new ConstraintParameterException( $this->testMessage ) ]
					];
				case self::P2:
					return [
						self::P2_GOOD => []
					];
			}
		};

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_PROPERTY_ID =>
					self::P1 . '|' . self::P2
			]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					],
					self::P1_BAD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => [
							[ CheckConstraintParameters::KEY_MESSAGE_HTML => $this->testMessageHtml ]
						]
					]
				],
				self::P2 => [
					self::P2_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					]
				]
			],
			$result
		);
	}

	public function testReportForConstraintAndProperty() {
		$this->checkConstraintParametersOnConstraintId = function( $constraintid ) {
			return [ new ConstraintParameterException( $this->testMessage ) ];
		};
		$this->checkConstraintParametersOnPropertyId = function( $propertyId ) {
			return [
				self::P2_GOOD => []
			];
		};

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_CONSTRAINT_ID => self::P1_BAD,
				CheckConstraintParameters::PARAM_PROPERTY_ID => self::P2
			]
		);

		$this->assertSame(
			[
				self::P1 => [
					self::P1_BAD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_NOT_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => [
							[ CheckConstraintParameters::KEY_MESSAGE_HTML => $this->testMessageHtml ]
						]
					]
				],
				self::P2 => [
					self::P2_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					]
				]
			],
			$result
		);
	}

	public function testReportForConstraintAndPropertyOverlapping() {
		$this->checkConstraintParametersOnPropertyId = function( $propertyId ) {
			return [
				self::P2_GOOD => []
			];
		};
		// $this->checkConstraintParametersOnConstraintId not set:
		// API should reuse result from property check

		$result = $this->doRequest(
			[
				CheckConstraintParameters::PARAM_CONSTRAINT_ID => self::P2_GOOD,
				CheckConstraintParameters::PARAM_PROPERTY_ID => self::P2
			]
		);

		$this->assertSame(
			[
				self::P2 => [
					self::P2_GOOD => [
						CheckConstraintParameters::KEY_STATUS => CheckConstraintParameters::STATUS_OKAY,
						CheckConstraintParameters::KEY_PROBLEMS => []
					]
				]
			],
			$result
		);
	}

}
