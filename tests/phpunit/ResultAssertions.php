<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Assertions on the status of a {@link CheckResult}.
 *
 * {@link assertTodoCompliance} and {@link assertTodoViolation}
 * are functionally identical to {@link assertTodo},
 * but allow the test author to record the intended outcome even before it is implemented;
 * once the feature is implemented, one only needs to replace ‘assertTodo’ with ‘assert’
 * in order to use the existing test cases.
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
trait ResultAssertions {

	/**
	 * Assert that $result indicates compliance with a constraint.
	 *
	 * @param CheckResult $result
	 */
	public function assertCompliance( CheckResult $result ) {
		$this->assertEquals(
			CheckResult::STATUS_COMPLIANCE,
			$result->getStatus(),
			'Check should comply; message: ' . $result->getMessage()
		);
	}

	/**
	 * Assert that $result indicates violation of a constraint.
	 *
	 * @param CheckResult $result
	 * @param string? $messageKey If present, additionally assert that the violation message
	 *                            matches the message given by this key.
	 */
	public function assertViolation( CheckResult $result, $messageKey = null ) {
		$this->assertEquals(
			CheckResult::STATUS_VIOLATION,
			$result->getStatus(),
			'Check should not comply'
		);
		if ( $messageKey !== null ) {
			$message = wfMessage( $messageKey );
			$messagePlain = $message->plain();
			$pos = stripos( $messagePlain, '{{PLURAL:' );
			if ( $pos === false ) {
				// turn parameters into “match all” and match full message
				$pattern = preg_replace( '/\$[0-9]+/', '%a', $message->escaped() );
			} else {
				// no chance to match after {{PLURAL}} processing, take pattern up to that and append “match all”
				$pattern = substr( $messagePlain, 0, $pos );
				$pattern = preg_replace( '/\$[0-9]+/', '%a', $pattern );
				$pattern = htmlspecialchars( $pattern, ENT_QUOTES, 'UTF-8', false ); // simulate ->escaped()
				$pattern .= '%a';
			}
			$this->assertStringMatchesFormat(
				$pattern,
				$result->getMessage(),
				"Violation message should be ⧼${messageKey}⧽."
			);
		}
	}

	/**
	 * Assert that $result indicates an unimplemented constraint.
	 *
	 * @param CheckResult $result
	 */
	public function assertTodo( CheckResult $result ) {
		$this->assertEquals(
			CheckResult::STATUS_TODO,
			$result->getStatus(),
			'Check is not implemented.'
		);
	}

	/**
	 * Assert that $result indicates an unimplemented constraint,
	 * and also record that $result should indicate compliance once the constraint is implemented.
	 *
	 * @param CheckResult $result
	 * @see assertTodo
	 * @see assertCompliance
	 */
	public function assertTodoCompliance( CheckResult $result ) {
		$this->assertTodo( $result );
	}

	/**
	 * Assert that $result indicates an unimplemented constraint,
	 * and also record that $result should indicate violation once the constraint is implemented.
	 *
	 * @param CheckResult $result
	 * @param string? $messageKey
	 * @see assertTodo
	 * @see assertViolation
	 */
	public function assertTodoViolation( CheckResult $result, $messageKey = null ) {
		$this->assertTodo( $result );
	}

}
