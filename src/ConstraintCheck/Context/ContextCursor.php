<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * A context cursor encapsulates the location
 * where a check result serialization for a certain Context should be stored.
 *
 * @license GPL-2.0-or-later
 */
interface ContextCursor {

	/**
	 * The type of the associated context.
	 *
	 * @return string one of the Context::TYPE_* constants
	 */
	public function getType(): string;

	/**
	 * The ID of the entity of the associated context.
	 *
	 * @return string {@link EntityId::getSerialization() entity ID serialization}
	 */
	public function getEntityId(): string;

	/**
	 * The property ID of the main statement of the associated context.
	 *
	 * @return string {@link PropertyId::getSerialization() property ID serialization}
	 */
	public function getStatementPropertyId(): string;

	/**
	 * The GUID of the main statement of the associated context.
	 */
	public function getStatementGuid(): string;

	/**
	 * The property ID serialization of the snak of the associated context.
	 */
	public function getSnakPropertyId(): string;

	/**
	 * The hash of the snak of the associated context.
	 */
	public function getSnakHash(): string;

	/**
	 * Store the check result serialization $result
	 * at the appropriate location for this context in $container.
	 *
	 * Mainly used in the API, where $container is part of the API response.
	 *
	 * If $result is null, don’t actually store it,
	 * but still populate the appropriate location for the context in $container
	 * (by creating all intermediate path elements of the location where $result would be stored).
	 *
	 * @param ?array $result
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( ?array $result, array &$container ): void;

}
