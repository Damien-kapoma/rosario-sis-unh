<?php
/**
 * Demo / bulk account data helpers (UNH RosarioSIS).
 *
 * @package RosarioSIS
 */

define( 'DEMO_MAX_STUDENTS', 1500 );

/**
 * Ensure schema for email login and public_login_codes.
 */
function DemoDataEnsureSchema()
{
	global $DatabaseType;

	DBQuery( "CREATE TABLE IF NOT EXISTS public_login_codes (
		email VARCHAR(255) PRIMARY KEY,
		code VARCHAR(10),
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	)" );

	if ( $DatabaseType === 'mysql' )
	{
		$has_role = DBGetOne( "SELECT 1 FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA=DATABASE()
			AND TABLE_NAME='public_login_codes'
			AND COLUMN_NAME='role'" );

		if ( ! $has_role )
		{
			DBQuery( "ALTER TABLE public_login_codes ADD COLUMN role VARCHAR(20) DEFAULT NULL AFTER code" );
		}
	}

	if ( $DatabaseType === 'mysql' )
	{
		$has_email = DBGetOne( "SELECT 1 FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA=DATABASE()
			AND TABLE_NAME='students'
			AND COLUMN_NAME='email'" );

		if ( ! $has_email )
		{
			DBQuery( "ALTER TABLE students ADD COLUMN email VARCHAR(255) NULL AFTER username" );
		}
	}

	DBQuery( "UPDATE config SET config_value='USERNAME' WHERE title='STUDENTS_EMAIL_FIELD'" );
}

/**
 * Generate a unique 4-digit login code.
 *
 * @param array $used_codes Reference to used codes map.
 */
function DemoDataUniqueCode( &$used_codes )
{
	do
	{
		$code = str_pad( (string) random_int( 0, 9999 ), 4, '0', STR_PAD_LEFT );
	}
	while ( isset( $used_codes[ $code ] ) );

	$used_codes[ $code ] = true;

	return $code;
}

/**
 * Get default school id for current year.
 */
function DemoDataDefaultSchoolId()
{
	$school_id = DBGetOne( "SELECT ID FROM schools
		WHERE SYEAR='" . Config( 'SYEAR' ) . "'
		ORDER BY ID LIMIT 1" );

	if ( ! $school_id )
	{
		DBInsert( 'schools', [
			'SYEAR' => Config( 'SYEAR' ),
			'TITLE' => DBEscapeString( 'Université Nouveaux Horizons (UNH)' ),
		] );

		$school_id = DBGetOne( "SELECT ID FROM schools WHERE SYEAR='" . Config( 'SYEAR' ) . "' ORDER BY ID LIMIT 1" );
	}

	return (int) $school_id;
}

/**
 * Restrict teacher profile: view/edit students but never delete accounts.
 */
function DemoDataApplyRolePermissions()
{
	// Teachers: no edit on user management (prevents privilege escalation).
	DBQuery( "UPDATE profile_exceptions SET can_edit=NULL
		WHERE profile_id=2
		AND modname LIKE 'Users/User%'" );

	// Teachers: no edit on student record deletion workflows.
	DBQuery( "UPDATE profile_exceptions SET can_edit=NULL
		WHERE profile_id=2
		AND modname='Students/Student.php'" );

	// Teachers keep read-only use.
	DBQuery( "UPDATE profile_exceptions SET can_use='Y'
		WHERE profile_id=2
		AND modname='Students/Student.php'" );
}

/**
 * Save login code mapping.
 */
function DemoDataSaveLoginCode( $email, $code, $role = '' )
{
	global $DatabaseType;

	static $has_role_col = null;

	if ( $has_role_col === null )
	{
		$has_role_col = false;

		if ( $DatabaseType === 'mysql' )
		{
			$has_role_col = (bool) DBGetOne( "SELECT 1 FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA=DATABASE()
				AND TABLE_NAME='public_login_codes'
				AND COLUMN_NAME='role'" );
		}
	}

	if ( $has_role_col )
	{
		DBQuery( "REPLACE INTO public_login_codes (email,code,role)
			VALUES ('" . DBEscapeString( $email ) . "','" . DBEscapeString( $code ) . "','" . DBEscapeString( $role ) . "')" );
	}
	else
	{
		DBQuery( "REPLACE INTO public_login_codes (email,code)
			VALUES ('" . DBEscapeString( $email ) . "','" . DBEscapeString( $code ) . "')" );
	}
}

/**
 * Whether students table has email column.
 */
function DemoDataHasStudentEmailColumn()
{
	global $DatabaseType;

	if ( $DatabaseType !== 'mysql' )
	{
		return false;
	}

	return (bool) DBGetOne( "SELECT 1 FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA=DATABASE()
		AND TABLE_NAME='students'
		AND COLUMN_NAME='email'" );
}

/**
 * Check if email is already used.
 */
function DemoDataAccountExists( $email )
{
	$esc = DBEscapeString( $email );

	return (bool) DBGetOne( "SELECT 1 FROM staff
		WHERE UPPER(username)=UPPER('" . $esc . "')
		OR UPPER(email)=UPPER('" . $esc . "')
		UNION SELECT 1 FROM students
		WHERE UPPER(username)=UPPER('" . $esc . "')" .
		( DemoDataHasStudentEmailColumn() ? " OR UPPER(email)=UPPER('" . $esc . "')" : '' ) );
}

/**
 * Get saved login code for an email.
 */
function DemoDataGetLoginCode( $email )
{
	return DBGetOne( "SELECT code FROM public_login_codes
		WHERE UPPER(email)=UPPER('" . DBEscapeString( $email ) . "')
		LIMIT 1" );
}

/**
 * Public registration: create account + save 4-digit code linked to email.
 *
 * @param string $role       student|teacher.
 * @param string $first_name Prénom.
 * @param string $last_name  Nom.
 * @param string $email      E-mail.
 * @param array  $extra      Champs UNH (téléphone, faculté, LMD, etc.).
 *
 * @return array|false
 */
function DemoDataRegisterAccount( $role, $first_name, $last_name, $email, $extra = [] )
{
	global $DatabaseType, $error, $note;

	require_once 'modules/misc/includes/UNHData.fnc.php';

	DemoDataEnsureSchema();
	UNHEnsureSchema();

	$role = strtolower( trim( $role ) );
	$first_name = trim( $first_name );
	$last_name = trim( $last_name );
	$email = trim( $email );
	$extra = (array) $extra;

	if ( ! in_array( $role, [ 'student', 'teacher' ], true ) )
	{
		$error[] = _( 'Please choose a valid registration type.' );
	}

	if ( $first_name === '' )
	{
		$error[] = _( 'Please enter your first name.' );
	}

	if ( $last_name === '' )
	{
		$error[] = _( 'Please enter your last name.' );
	}

	if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) )
	{
		$error[] = _( 'Please enter a valid email address.' );
	}

	if ( $role === 'student' )
	{
		UNHValidateRegistration( $extra, $error );
	}

	if ( ! empty( $error ) )
	{
		return false;
	}

	if ( DemoDataAccountExists( $email ) )
	{
		$error[] = _( 'A user with that email address already exists. Use "Se connecter" with this email and your 4-digit code.' );

		return false;
	}

	// DEMO_MAX_STUDENTS applies to bulk seed only (SeedTestData), not public registration.

	$username = mb_substr( $email, 0, 100 );
	$used_codes = [];
	$login_code = DemoDataUniqueCode( $used_codes );
	$password_hash = encrypt_password( $login_code );
	$school_id = DemoDataDefaultSchoolId();

	if ( $role === 'student' )
	{
		$student_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'students' : 'students_student_id_seq' );

		$columns = [
			'STUDENT_ID' => (int) $student_id,
			'FIRST_NAME' => $first_name,
			'LAST_NAME' => $last_name,
			'USERNAME' => $username,
			'PASSWORD' => $password_hash,
		];

		if ( DemoDataHasStudentEmailColumn() )
		{
			$columns['EMAIL'] = $email;
		}

		DBInsert( 'students', $columns );

		DBInsert( 'student_enrollment', [
			'SYEAR' => Config( 'SYEAR' ),
			'SCHOOL_ID' => $school_id,
			'STUDENT_ID' => (int) $student_id,
			'START_DATE' => DBDate(),
		] );

		UNHSaveStudentInfo( $student_id, $extra );

		$account_id = $student_id;
	}
	else
	{
		$staff_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'staff' : 'staff_staff_id_seq' );

		DBInsert( 'staff', [
			'SYEAR' => Config( 'SYEAR' ),
			'STAFF_ID' => (int) $staff_id,
			'FIRST_NAME' => $first_name,
			'LAST_NAME' => $last_name,
			'USERNAME' => $username,
			'PASSWORD' => $password_hash,
			'EMAIL' => $email,
			'PROFILE' => 'teacher',
			'PROFILE_ID' => 2,
			'CURRENT_SCHOOL_ID' => $school_id,
			'SCHOOLS' => ',' . $school_id . ',',
		] );

		$account_id = $staff_id;
		$role = 'teacher';
	}

	DemoDataSaveLoginCode( $email, $login_code, $role );

	$email_sent = false;
	$login_url = 'http' . ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 's' : '' ) . '://' .
		$_SERVER['HTTP_HOST'] . dirname( $_SERVER['REQUEST_URI'] ) . '/index.php';

	$message = sprintf(
		"Bonjour %s %s,\n\nVotre compte a été créé.\nE-mail : %s\nCode de connexion : %s\n\nConnexion : %s",
		$first_name,
		$last_name,
		$email,
		$login_code,
		$login_url
	);

	if ( function_exists( 'SendEmail' ) )
	{
		$email_sent = SendEmail(
			$email,
			_( 'Votre code de connexion RosarioSIS' ),
			$message,
			Config( 'NAME' ) . ' <no-reply@' . preg_replace( '/^www\./', '', $_SERVER['SERVER_NAME'] ) . '>'
		);
	}

	return [
		'role' => $role,
		'email' => $email,
		'login_code' => $login_code,
		'username' => $username,
		'account_id' => $account_id,
		'email_sent' => $email_sent,
		'first_name' => $first_name,
		'last_name' => $last_name,
	];
}

/**
 * Delete demo users and codes; keep default admin and school structure.
 *
 * @return array{students:int,staff:int,codes:int}
 */
function DemoDataResetDatabase()
{
	global $DatabaseType;

	$deleted_students = (int) DBGetOne( 'SELECT COUNT(*) FROM students' );

	if ( $DatabaseType === 'mysql' )
	{
		DBQuery( 'SET FOREIGN_KEY_CHECKS=0' );

		$tables = DBGet( "SELECT DISTINCT c.TABLE_NAME FROM information_schema.COLUMNS c
			INNER JOIN information_schema.TABLES t
			ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
			WHERE c.TABLE_SCHEMA=DATABASE()
			AND c.COLUMN_NAME='student_id'
			AND c.TABLE_NAME!='students'
			AND t.TABLE_TYPE='BASE TABLE'
			ORDER BY c.TABLE_NAME" );

		foreach ( $tables as $row )
		{
			$table = $row['TABLE_NAME'];

			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) )
			{
				continue;
			}

			DBQuery( 'TRUNCATE TABLE `' . $table . '`' );
		}

		DBQuery( 'TRUNCATE TABLE students' );

		$deleted_staff = (int) DBGetOne( "SELECT COUNT(*) FROM staff
			WHERE NOT (LOWER(username)='admin' AND profile='admin')" );

		DBQuery( "DELETE FROM staff
			WHERE NOT (LOWER(username)='admin' AND profile='admin')" );

		DBQuery( 'DELETE FROM public_login_codes' );

		DBQuery( 'SET FOREIGN_KEY_CHECKS=1' );

		return [
			'students' => $deleted_students,
			'staff' => $deleted_staff,
			'codes' => 1,
		];
	}
	else
	{
		require_once 'modules/Students/includes/Student.fnc.php';

		$student_ids = DBGet( 'SELECT STUDENT_ID FROM students ORDER BY STUDENT_ID DESC' );

		foreach ( $student_ids as $row )
		{
			DBQuery( StudentDeleteSQL( (int) $row['STUDENT_ID'] ) );
		}

		$deleted_staff = (int) DBGetOne( "SELECT COUNT(*) FROM staff
			WHERE NOT (LOWER(username)='admin' AND profile='admin')" );

		DBQuery( "DELETE FROM staff
			WHERE NOT (LOWER(username)='admin' AND profile='admin')" );

		DBQuery( 'DELETE FROM public_login_codes' );

		return [
			'students' => $deleted_students,
			'staff' => $deleted_staff,
			'codes' => 1,
		];
	}
}

/**
 * Seed N demo students (batch).
 *
 * @param int    $count   Total to create in this run.
 * @param int    $offset  Start index (1-based numbering in emails).
 * @param string $domain  Email domain.
 *
 * @return array{created:int,credentials:array,last_index:int}
 */
function DemoDataSeedStudents( $count = 1500, $offset = 0, $domain = 'rosario.unh' )
{
	global $DatabaseType;

	DemoDataEnsureSchema();

	$school_id = DemoDataDefaultSchoolId();
	$syear = Config( 'SYEAR' );
	$used_codes = [];
	$credentials = [];
	$created = 0;
	$has_email_col = DemoDataHasStudentEmailColumn();

	for ( $i = 1; $i <= $count; $i++ )
	{
		$num = $offset + $i;
		$email = 'etudiant' . str_pad( (string) $num, 4, '0', STR_PAD_LEFT ) . '@' . $domain;

		$exists = DBGetOne( "SELECT 1 FROM students WHERE UPPER(username)=UPPER('" . DBEscapeString( $email ) . "')" );

		if ( $exists )
		{
			continue;
		}

		$code = DemoDataUniqueCode( $used_codes );
		$hash = encrypt_password( $code );

		$student_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'students' : 'students_student_id_seq' );

		$columns = [
			'STUDENT_ID' => (int) $student_id,
			'FIRST_NAME' => 'Étudiant',
			'LAST_NAME' => str_pad( (string) $num, 4, '0', STR_PAD_LEFT ),
			'USERNAME' => $email,
			'PASSWORD' => $hash,
		];

		if ( $has_email_col )
		{
			$columns['EMAIL'] = $email;
		}

		DBInsert( 'students', $columns );

		DBInsert( 'student_enrollment', [
			'SYEAR' => $syear,
			'SCHOOL_ID' => $school_id,
			'STUDENT_ID' => (int) $student_id,
			'START_DATE' => DBDate(),
		] );

		DemoDataSaveLoginCode( $email, $code, 'student' );

		$credentials[] = [
			'role' => 'student',
			'email' => $email,
			'code' => $code,
			'name' => 'Étudiant ' . str_pad( (string) $num, 4, '0', STR_PAD_LEFT ),
		];

		$created++;
	}

	return [
		'created' => $created,
		'credentials' => $credentials,
		'last_index' => $offset + $count,
	];
}

/**
 * Seed staff accounts (teachers or admins).
 *
 * @param string $profile teacher|admin.
 * @param int    $count   Number of accounts.
 * @param string $prefix  Email prefix.
 * @param string $domain  Email domain.
 */
function DemoDataSeedStaff( $profile, $count, $prefix, $domain = 'rosario.unh' )
{
	global $DatabaseType;

	DemoDataEnsureSchema();

	$school_id = DemoDataDefaultSchoolId();
	$syear = Config( 'SYEAR' );
	$profile_id = $profile === 'admin' ? 1 : 2;
	$used_codes = [];
	$credentials = [];
	$created = 0;

	for ( $i = 1; $i <= $count; $i++ )
	{
		$email = $prefix . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) . '@' . $domain;

		$exists = DBGetOne( "SELECT 1 FROM staff
			WHERE UPPER(username)=UPPER('" . DBEscapeString( $email ) . "')
			AND syear='" . $syear . "'" );

		if ( $exists )
		{
			continue;
		}

		$code = DemoDataUniqueCode( $used_codes );
		$hash = encrypt_password( $code );
		$staff_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'staff' : 'staff_staff_id_seq' );

		DBInsert( 'staff', [
			'SYEAR' => $syear,
			'STAFF_ID' => (int) $staff_id,
			'FIRST_NAME' => $profile === 'admin' ? 'Admin' : 'Enseignant',
			'LAST_NAME' => str_pad( (string) $i, 2, '0', STR_PAD_LEFT ),
			'USERNAME' => $email,
			'PASSWORD' => $hash,
			'EMAIL' => $email,
			'PROFILE' => $profile,
			'PROFILE_ID' => $profile_id,
			'CURRENT_SCHOOL_ID' => $school_id,
			'SCHOOLS' => ',' . $school_id . ',',
		] );

		DemoDataSaveLoginCode( $email, $code, $profile );

		$credentials[] = [
			'role' => $profile,
			'email' => $email,
			'code' => $code,
			'name' => ( $profile === 'admin' ? 'Admin ' : 'Enseignant ' ) . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ),
		];

		$created++;
	}

	return [
		'created' => $created,
		'credentials' => $credentials,
	];
}

/**
 * Export credentials to CSV file under FileUploads.
 *
 * @param array  $rows       Credential rows.
 * @param string $filename   Base filename.
 */
function DemoDataExportCsv( $rows, $filename = 'unh_demo_credentials.csv' )
{
	global $FileUploadsPath;

	$dir = $FileUploadsPath . 'Demo/';

	if ( ! is_dir( $dir ) )
	{
		mkdir( $dir, 0755, true );
	}

	$path = $dir . $filename;
	$fp = fopen( $path, 'w' );

	if ( ! $fp )
	{
		return false;
	}

	fputcsv( $fp, [ 'role', 'nom', 'email', 'code_connexion' ], ';' );

	foreach ( $rows as $row )
	{
		fputcsv( $fp, [
			$row['role'],
			$row['name'],
			$row['email'],
			$row['code'],
		], ';' );
	}

	fclose( $fp );

	return $path;
}

/**
 * Stream CSV download to browser.
 *
 * @param array $rows Credential rows.
 */
function DemoDataDownloadCsv( $rows )
{
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="unh_comptes_' . date( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'role', 'nom', 'email', 'code_connexion' ], ';' );

	foreach ( $rows as $row )
	{
		fputcsv( $out, [
			$row['role'],
			$row['name'],
			$row['email'],
			$row['code'],
		], ';' );
	}

	fclose( $out );
	exit;
}

/**
 * Load all demo credentials from public_login_codes.
 */
function DemoDataListCredentials( $limit = 5000 )
{
	return DBGet( "SELECT email AS EMAIL, code AS CODE, role AS ROLE
		FROM public_login_codes
		ORDER BY role,email
		LIMIT " . (int) $limit, [], [ 'EMAIL' ] );
}
