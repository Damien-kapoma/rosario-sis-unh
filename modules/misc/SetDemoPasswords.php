<?php
/**
 * Sync passwords from public_login_codes (admin only).
 *
 * @package RosarioSIS
 */

if ( User( 'PROFILE' ) !== 'admin' )
{
	die( ErrorMessage( [ _( 'Access denied.' ) ], 'fatal' ) );
}

$note = [];

if ( isset( $_POST['sync'] ) )
{
	$rows = DBGet( "SELECT email AS EMAIL, code AS CODE FROM public_login_codes" );
	$updated = 0;

	foreach ( $rows as $row )
	{
		$hash = encrypt_password( $row['CODE'] );

		DBQuery( "UPDATE staff SET PASSWORD='" . DBEscapeString( $hash ) . "'
			WHERE UPPER(EMAIL)=UPPER('" . DBEscapeString( $row['EMAIL'] ) . "')" );

		DBQuery( "UPDATE students SET PASSWORD='" . DBEscapeString( $hash ) . "'
			WHERE UPPER(username)=UPPER('" . DBEscapeString( $row['EMAIL'] ) . "')" );

		$updated++;
	}

	$note[] = sprintf( _( '%d account(s) synchronized.' ), $updated );
}

PopTable( 'header', _( 'Synchroniser les mots de passe' ) );
echo ErrorMessage( $note, 'note' );
?>
<p>Recopie chaque code de <code>public_login_codes</code> vers les comptes staff et students.</p>
<form method="post" action="<?php echo URLEscape( 'Modules.php?modname=misc/SetDemoPasswords.php' ); ?>">
	<button type="submit" name="sync" value="1" class="button-primary">Synchroniser</button>
	<a class="button" href="<?php echo URLEscape( 'Modules.php?modname=misc/SeedTestData.php' ); ?>">Retour</a>
</form>
<?php
PopTable( 'footer' );
