<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * Abstract superclass of all contexts for the second version of the API output format.
 *
 * This output format is modeled after the Wikibase entity JSON format,
 * where an object with the members 'hash' and a list of 'reports' can appear
 * wherever the entity JSON format contains snaks.
 *
 * That is, the container is keyed by entity ID and contains objects with a 'claims' member,
 * which holds an object that is keyed by property ID and contains lists of statement objects,
 * which have an 'id' member, a 'mainsnak' snak,
 * 'qualifiers' keyed by property ID holding a list of snaks,
 * and a list of 'references' each having a 'hash' and 'snaks'
 * which are keyed by property ID and then hold a list of snaks.
 */
abstract class ApiV2Context extends AbstractContext {

	/**
	 * Returns the statement subcontainer.
	 *
	 * @param array &$container
	 * @param string $entityId entity ID serialization
	 * @param string $propertyId property ID serialization
	 * @param string $statementId statement GUID
	 * @return array
	 */
	protected function &getStatementArray(
		array &$container,
		$entityId,
		$propertyId,
		$statementId
	) {
		if ( !array_key_exists( $entityId, $container ) ) {
			$container[$entityId] = [];
		}
		$entityContainer = &$container[$entityId];

		if ( !array_key_exists( 'claims', $entityContainer ) ) {
			$entityContainer['claims'] = [];
		}
		$claimsContainer = &$entityContainer['claims'];

		if ( !array_key_exists( $propertyId, $claimsContainer ) ) {
			$claimsContainer[$propertyId] = [];
		}
		$propertyContainer = &$claimsContainer[$propertyId];

		foreach ( $propertyContainer as &$statement ) {
			if ( $statement['id'] === $statementId ) {
				$statementArray = &$statement;
				break;
			}
		}
		if ( !isset( $statementArray ) ) {
			$statementArray = [ 'id' => $statementId ];
			$propertyContainer[] = &$statementArray;
		}

		return $statementArray;
	}

	/**
	 * This method returns the array with the 'hash' and 'reports' member.
	 * It should locate the array in $container or,
	 * if the array doesn’t exist yet, create it and emplace it there.
	 *
	 * @param array &$container
	 * @return array
	 */
	abstract protected function &getMainArray( array &$container );

	public function storeCheckResultInArray( array $result, array &$container ) {
		$this->getMainArray( $container )['results'][] = $result;
	}

}
