<?php

declare( strict_types = 1 );

// @phan-file-suppress PhanPluginNeverReturnMethod

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use LogicException;

/**
 * A context cursor that is only associated with an entity,
 * not with any statement or something else within it.
 * It can only be used to partially populate a results container,
 * not to actually store a full check result.
 * This is used by {@link CheckingResultsSource} to ensure
 * that even entities with no statements are present in the results container.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class EntityContextCursor extends ApiV2ContextCursor {

	private string $entityId;

	public function __construct(
		string $entityId
	) {
		$this->entityId = $entityId;
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getType(): string {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	public function getEntityId(): string {
		return $this->entityId;
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getStatementPropertyId(): string {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getStatementGuid(): string {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getSnakPropertyId(): string {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getSnakHash(): string {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function &getMainArray( array &$container ): array {
		throw new LogicException( 'EntityContextCursor cannot store check results' );
	}

	/**
	 * Populate the results container up to the 'claims' level.
	 *
	 * @param ?array $result must be null
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( ?array $result, array &$container ): void {
		if ( $result !== null ) {
			throw new LogicException( 'EntityContextCursor cannot store check results' );
		}

		// this ensures that the claims array is present in the $container,
		// populating it if necessary, even though we ignore the return value
		$this->getClaimsArray( $container );
	}

}
