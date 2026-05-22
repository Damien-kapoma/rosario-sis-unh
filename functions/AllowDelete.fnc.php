<?php
/**
 * Delete permission check — administrators only.
 *
 * @package RosarioSIS
 * @subpackage functions
 */

/**
 * Can delete students or staff accounts?
 *
 * @param string $type Optional context: student|user.
 *
 * @return bool
 */
function AllowDelete( $type = '' )
{
	if ( User( 'PROFILE' ) !== 'admin' )
	{
		return false;
	}

	return AllowEdit();
}
