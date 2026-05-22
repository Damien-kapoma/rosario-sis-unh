<?php
/**
 * Index
 *
 * Login screen
 *
 * @package RosarioSIS
 */

// FJ bugfix check accept cookies.
$default_session_name = session_name();

require_once 'Warehouse.php';
require_once 'ProgramFunctions/FirstLogin.fnc.php';
require_once 'modules/misc/includes/DemoData.fnc.php';
require_once 'modules/misc/includes/UNHData.fnc.php';

// AJAX: return saved login code for a given email (used for autofill)
if ( $_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset( $_POST['ajax_action'] )
	&& $_POST['ajax_action'] === 'get_login_code'
	&& ! empty( $_POST['email'] ) )
{
	header( 'Content-Type: application/json; charset=utf-8' );
	$email = trim( $_POST['email'] );

	DemoDataEnsureSchema();

	$code = DemoDataGetLoginCode( $email );

	echo json_encode( [ 'code' => $code ? $code : '' ] );
	exit;
}

// AJAX: return saved login code for the current logged-in user (option 1)
if ( $_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset( $_POST['ajax_action'] )
	&& $_POST['ajax_action'] === 'get_my_login_code' )
{
	header( 'Content-Type: application/json; charset=utf-8' );

	$email = '';
	if ( ! empty( $_SESSION['STAFF_ID'] ) )
	{
		$row = DBGet( "SELECT EMAIL FROM staff WHERE STAFF_ID='" . (int) $_SESSION['STAFF_ID'] . "' LIMIT 1" );
		if ( $row ) $email = $row[1]['EMAIL'];
	}
	elseif ( ! empty( $_SESSION['STUDENT_ID'] ) )
	{
		$row = DBGet( "SELECT EMAIL FROM students WHERE STUDENT_ID='" . (int) $_SESSION['STUDENT_ID'] . "' LIMIT 1" );
		if ( $row ) $email = $row[1]['EMAIL'];
	}

	$code = '';
	if ( $email )
	{
		DemoDataEnsureSchema();

		$code = DemoDataGetLoginCode( $email );
	}

	echo json_encode( [ 'code' => $code ? $code : '' ] );
	exit;
}

// Logout.
if ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'logout' )
{
	// Redirect to index.php with same locale as old session & eventual reason & redirect to URL.
	header( 'Location: ' . URLEscape( 'index.php?locale=' . $_SESSION['locale'] .
		( isset( $_REQUEST['reason'] ) ? '&reason=' . $_REQUEST['reason'] : '' ) .
		( isset( $_REQUEST['redirect_to'] ) ?
			'&redirect_to=' . urlencode( $_REQUEST['redirect_to'] ) :
			'' ) ) );

	if ( ! empty( $_REQUEST['token'] )
		&& $_SESSION['token'] === $_REQUEST['token'] )
	{
		session_unset();

		session_destroy();
	}

	exit;
}

// First login.
elseif ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'first-login' )
{
	// @since 7.3 Before First Login form action hook.
	// @example Parent Agreement plugin: Add a form before first login form without interfering with logic.
	do_action( 'index.php|before_first_login_form' );

	/**
	 * First Login Form
	 *
	 * Password Change & Poll after install.
	 *
	 * @since 5.3 Force password change on first login
	 */
	if ( HasFirstLoginForm() )
	{
		$first_login_done = false;

		if ( ! empty( $_POST['first_login'] ) )
		{
			// Save Password and set LAST_LOGIN.
			$first_login_done = DoFirstLoginForm( $_REQUEST['first_login'] );
		}

		if ( ! $first_login_done )
		{
			$_ROSARIO['page'] = 'first-login';

			Warehouse( 'header' );

			echo FirstLoginForm();

			Warehouse( 'footer' );

			exit;
		}
	}

	$_REQUEST['modfunc'] = false;
}

/**
 * Register a public account by email and name.
 *
 * @return array|false Registration summary or false on error.
 */
function RegisterNewAccount()
{
	global $error, $note;

	return DemoDataRegisterAccount(
		isset( $_REQUEST['ROLE'] ) ? $_REQUEST['ROLE'] : '',
		isset( $_REQUEST['FIRST_NAME'] ) ? $_REQUEST['FIRST_NAME'] : '',
		isset( $_REQUEST['LAST_NAME'] ) ? $_REQUEST['LAST_NAME'] : '',
		isset( $_REQUEST['EMAIL'] ) ? $_REQUEST['EMAIL'] : '',
		[
			'phone' => isset( $_REQUEST['PHONE'] ) ? $_REQUEST['PHONE'] : '',
			'birthdate' => isset( $_REQUEST['BIRTHDATE'] ) ? $_REQUEST['BIRTHDATE'] : '',
			'gender' => isset( $_REQUEST['GENDER'] ) ? $_REQUEST['GENDER'] : '',
			'address' => isset( $_REQUEST['ADDRESS'] ) ? $_REQUEST['ADDRESS'] : '',
			'city' => isset( $_REQUEST['CITY'] ) ? $_REQUEST['CITY'] : '',
			'faculty_code' => isset( $_REQUEST['FACULTY_CODE'] ) ? $_REQUEST['FACULTY_CODE'] : '',
			'lmd_level' => isset( $_REQUEST['LMD_LEVEL'] ) ? $_REQUEST['LMD_LEVEL'] : '',
			'nationality' => isset( $_REQUEST['NATIONALITY'] ) ? $_REQUEST['NATIONALITY'] : '',
			'emergency_name' => isset( $_REQUEST['EMERGENCY_NAME'] ) ? $_REQUEST['EMERGENCY_NAME'] : '',
			'emergency_phone' => isset( $_REQUEST['EMERGENCY_PHONE'] ) ? $_REQUEST['EMERGENCY_PHONE'] : '',
			'id_number' => isset( $_REQUEST['ID_NUMBER'] ) ? $_REQUEST['ID_NUMBER'] : '',
		]
	);
}

// Register.
if ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'register' )
{
	$registration_result = RegisterNewAccount();

	// Show code on login page (saved in DB + session flash).
	if ( $registration_result )
	{
		$_SESSION['registration_flash'] = $registration_result;

		header( 'Location: ' . URLEscape( 'index.php?registered=1' ) );
		exit;
	}

	$_REQUEST['modfunc'] = false;
}

// Login.
elseif ( isset( $_POST['USERNAME'] )
	&& $_REQUEST['USERNAME'] !== ''
	&& isset( $_POST['PASSWORD'] )
	&& $_REQUEST['PASSWORD'] !== '' )
{
	// FJ check accept cookies.
	if ( ! isset( $_COOKIE['RosarioSIS'] )
		&& ! isset( $_COOKIE[ $default_session_name ] ) )
	{
		header( 'Location: index.php?modfunc=logout&reason=cookie&token=' . $_SESSION['token'] );

		exit;
	}

	// Only regenerate session ID if session.auto_start == 0.
	elseif ( isset( $_COOKIE['RosarioSIS'] ) )
	{
		session_regenerate_id( true ); // And invalidate old session.

		/**
		 * Add CSRF token to protect unauthenticated requests
		 *
		 * @since 9.0
		 * @since 11.0 Fix PHP fatal error if openssl PHP extension is missing
		 * @link https://stackoverflow.com/questions/5207160/what-is-a-csrf-token-what-is-its-importance-and-how-does-it-work
		 */
		$_SESSION['token'] = bin2hex( function_exists( 'openssl_random_pseudo_bytes' ) ?
			openssl_random_pseudo_bytes( 16 ) :
			( function_exists( 'random_bytes' ) ? random_bytes( 16 ) :
				mb_substr( sha1( rand( 999999999, 9999999999 ), true ), 0, 16 ) ) );
	}

	$username = DBEscapeString( (string) $_REQUEST['USERNAME'] );

	unset( $_REQUEST['USERNAME'], $_POST['USERNAME'] );

	// Lookup staff by email or username.
	$login_RET = DBGet( "SELECT USERNAME,PROFILE,STAFF_ID,LAST_LOGIN,FAILED_LOGIN,PASSWORD
	FROM staff
	WHERE SYEAR='" . Config( 'SYEAR' ) . "'
	AND (UPPER(USERNAME)=UPPER('" . $username . "')
		OR UPPER(EMAIL)=UPPER('" . $username . "'))" );

	if ( $login_RET
		&& match_password( $login_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
	{
		unset( $_REQUEST['PASSWORD'], $_POST['PASSWORD'] );
	}
	else
		$login_RET = false;

	if ( ! $login_RET )
	{
		// Lookup for student $username in DB.
		$student_email_sql = DemoDataHasStudentEmailColumn() ?
			" OR UPPER(s.EMAIL)=UPPER('" . $username . "')" : '';

		$student_RET = DBGet( "SELECT s.USERNAME,s.STUDENT_ID,s.LAST_LOGIN,
			s.FAILED_LOGIN,s.PASSWORD,se.START_DATE
			FROM students s,student_enrollment se
			WHERE se.STUDENT_ID=s.STUDENT_ID
			AND se.SYEAR='" . Config( 'SYEAR' ) . "'
			AND CURRENT_DATE>=se.START_DATE
			AND (CURRENT_DATE<=se.END_DATE OR se.END_DATE IS NULL)
			AND (UPPER(s.USERNAME)=UPPER('" . $username . "')" . $student_email_sql . ')' );

		if ( $student_RET
			&& match_password( $student_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
		{
			unset( $_REQUEST['PASSWORD'], $_POST['PASSWORD'] );
		}
		else
		{
			// Student may be inactive or not verified, see below for corresponding errors.
			$student_RET = DBGet( "SELECT s.USERNAME,s.STUDENT_ID,
				s.LAST_LOGIN,s.FAILED_LOGIN,se.START_DATE,s.PASSWORD
			FROM students s,student_enrollment se
			WHERE se.STUDENT_ID=s.STUDENT_ID
			AND se.SYEAR='" . Config( 'SYEAR' ) . "'
			AND (CURRENT_DATE<=se.END_DATE OR se.END_DATE IS NULL)
			AND (UPPER(s.USERNAME)=UPPER('" . $username . "')" . $student_email_sql . ')' );

			if ( ! $student_RET
				|| ! match_password( $student_RET[1]['PASSWORD'], $_POST['PASSWORD'] ) )
			{
				$student_RET = false;
			}
		}
	}

	$login_status = '';

	$is_banned = false;

	$ip = ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
		// Filter IP, HTTP_* headers can be forged.
		&& filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP ) ?
		$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'] );

	if ( Config( 'FAILED_LOGIN_LIMIT' ) )
	{
		// Failed login ban if >= X failed attempts within 10 minutes.
		$failed_login_RET = DBGet( "SELECT
			COUNT(CASE WHEN STATUS IS NULL OR STATUS='B' THEN 1 END) AS FAILED_COUNT,
			COUNT(CASE WHEN STATUS='B' THEN 1 END) AS BANNED_COUNT
			FROM access_log
			WHERE CREATED_AT > (CURRENT_TIMESTAMP - INTERVAL " . ( $DatabaseType === 'mysql' ? '10 minute' : "'10 minute'" ) . ")
			AND USER_AGENT='" . DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ) . "'
			AND IP_ADDRESS='" . $ip . "'" );

		if ( $failed_login_RET[1]['BANNED_COUNT']
			|| $failed_login_RET[1]['FAILED_COUNT'] >= Config( 'FAILED_LOGIN_LIMIT' ) )
		{
			// Ban in every case.
			$is_banned = true;

			$login_RET = $student_RET = false;

			// Banned status code: B.
			$login_status = 'B';
		}
	}

	// Admin, teacher or parent: initiate session.
	if ( $login_RET
		&& ( $login_RET[1]['PROFILE'] === 'admin'
			|| $login_RET[1]['PROFILE'] === 'teacher'
			|| $login_RET[1]['PROFILE'] === 'parent' ) )
	{
		$_SESSION['STAFF_ID'] = $login_RET[1]['STAFF_ID'];

		// Invalidate any active Student session.
		unset( $_SESSION['STUDENT_ID'] );

		unset( $_SESSION['UserSchool'] );

		$_SESSION['LAST_LOGIN'] = $login_RET[1]['LAST_LOGIN'];

		$failed_login = $login_RET[1]['FAILED_LOGIN'];

		$login_status = 'Y';
	}

	// User with No access profile.
	elseif ( $login_RET
			&& $login_RET[1]['PROFILE'] == 'none' )
	{
		$error[] = _( 'Your account has not yet been activated.' ) . ' '
			. _( 'You will be notified when it has been verified by a school administrator.' );
	}

	// Student account inactive (today < Attendance start date).
	elseif ( $student_RET
			&& DBDate() < $student_RET[1]['START_DATE'] )
	{
		$error[] = _( 'Your account has not yet been activated.' );
	}

	// Student account not verified (enrollment school + start date + last login are NULL).
	elseif ( $student_RET
			&& ! $student_RET[1]['START_DATE']
			&& ! $student_RET[1]['LAST_LOGIN'] )
	{
		$error[] = _( 'Your account has not yet been activated.' ) . ' '
			. _( 'You will be notified when it has been verified by a school administrator.' );
	}

	// Student: initiate session.
	elseif ( $student_RET )
	{
		$_SESSION['STUDENT_ID'] = $student_RET[1]['STUDENT_ID'];

		// Invalidate any active User session.
		unset( $_SESSION['STAFF_ID'] );

		unset( $_SESSION['UserSchool'] );

		$_SESSION['LAST_LOGIN'] = $student_RET[1]['LAST_LOGIN'];

		$failed_login = $student_RET[1]['FAILED_LOGIN'];

		$login_status = 'Y';
	}

	// Failed login.
	else
	{
		DBQuery( "UPDATE staff
			SET FAILED_LOGIN=" . db_case( [ 'FAILED_LOGIN', "''", '1', 'FAILED_LOGIN+1' ] ) . "
			WHERE UPPER(USERNAME)=UPPER('" . $username . "')
			AND SYEAR='" . Config( 'SYEAR' ) . "';
			UPDATE students
			SET FAILED_LOGIN=" . db_case( [ 'FAILED_LOGIN', "''", '1', 'FAILED_LOGIN+1' ] ) . "
			WHERE UPPER(USERNAME)=UPPER('" . $username . "')" );

		if ( $is_banned )
		{
			// Failed login ban if >= X failed attempts within 10 minutes.
			$error[] = _( 'Too many Failed Login Attempts.' ) . '&nbsp;'
				. _( 'Please try logging in later.' );
		}
		else
		{
			$error[] = _( 'Incorrect username or password.' ) . '&nbsp;'
				. _( 'Please try logging in again.' );
		}
	}

	// Access Log.
	if ( ! function_exists( 'AccessLogRecord' ) )
	{
		DBInsert(
			'access_log',
			[
				'SYEAR' => Config( 'SYEAR' ),
				'USERNAME' => mb_substr( $username, 0, 100 ),
				'PROFILE' => User( 'PROFILE' ),
				'IP_ADDRESS' => $ip,
				'USER_AGENT' => DBEscapeString( $_SERVER['HTTP_USER_AGENT'] ),
				'STATUS' => $login_status,
			]
		);
	}

	// Set current SchoolYear on login.
	if ( $login_status === 'Y'
		&& ! UserSyear() )
	{
		$_SESSION['UserSyear'] = Config( 'SYEAR' );
	}

	// @since 2.9.8 Login check action hook.
	do_action( 'index.php|login_check', $username );

	if ( HasFirstLoginForm() )
	{
		// First Login.
		header( 'Location: index.php?locale=' . $_SESSION['locale'] . '&modfunc=first-login' );

		exit;
	}

	// Set LAST_LOGIN, reset FAILED_LOGIN.
	if ( $login_status === 'Y'
		&& User( 'STAFF_ID' ) )
	{
		DBQuery( "UPDATE staff
			SET LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=NULL
			WHERE STAFF_ID='" . User( 'STAFF_ID' ) . "'" );
	}
	elseif ( $login_status === 'Y' )
	{
		DBQuery( "UPDATE students
			SET LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=NULL
			WHERE STUDENT_ID='" . (int) $_SESSION['STUDENT_ID'] . "'" );
	}
}

// FJ create account.
elseif ( isset( $_REQUEST['create_account'] ) )
{
	$include = false;

	unset( $_SESSION['STAFF_ID'], $_SESSION['STUDENT_ID'] );

	if ( $_REQUEST['create_account'] === 'user'
		&& Config( 'CREATE_USER_ACCOUNT' ) )
	{
		$include = 'Users/User.php';

		if ( UserStaffID() )
		{
			unset( $_SESSION['staff_id'] );
		}
	}

	elseif ( $_REQUEST['create_account'] === 'student'
		&& Config( 'CREATE_STUDENT_ACCOUNT' ) )
	{
		$include = 'Students/Student.php';

		// @since 6.0 Create Student Account: add school_id param to URL.
		if ( ! empty( $_REQUEST['school_id'] )
			&& ! Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL_FORCE' ) )
		{
			$sql_order_by = "ID='" . (int) $_REQUEST['school_id'] . "' DESC,ID";
		}
		else
		{
			// @since 6.3 Create Student Account Default School.
			// @link https://stackoverflow.com/questions/1250156/how-do-i-return-rows-with-a-specific-value-first#comment-67097263
			$sql_order_by = Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ) ?
				// Prevent SQL injection, cast to integer.
				"ID='" . (int) Config( 'CREATE_STUDENT_ACCOUNT_DEFAULT_SCHOOL' ) . "' DESC,ID" : "ID";
		}

		$_SESSION['UserSchool'] = DBGetOne( "SELECT ID FROM schools
			WHERE SYEAR='" . Config( 'SYEAR' ) . "'
			ORDER BY " . $sql_order_by );

		if ( UserStudentID() )
		{
			unset( $_SESSION['student_id'] );
		}
	}

	if ( ! $include )
	{
		// Do not use RedirectURL() here (no JS loaded).
		header( 'Location: index.php' );
	}
	else
	{
		if ( ! isset( $_REQUEST['modfunc'] ) )
		{
			$_REQUEST['modfunc'] = false;
		}

		$_REQUEST['modname'] = false;

		$_ROSARIO['page'] = 'create-account';

		Warehouse( 'header' );

		$_ROSARIO['allow_edit'] = true;

		// FJ security fix, cf http://www.securiteam.com/securitynews/6S02U1P6BI.html.
		if ( mb_substr( $include, -4, 4 ) !== '.php'
			|| mb_strpos( $include, '..' ) !== false
			|| ! is_file( 'modules/' . $include ) )
		{
			require_once 'ProgramFunctions/HackingLog.fnc.php';
			HackingLog();
		}
		else
			require_once 'modules/' . $include;

		Warehouse( 'footer' );

		if ( UserSchool() )
		{
			// Unset UserSchool() so we get correct Config values if next request changes school.
			unset( $_SESSION['UserSchool'] );
		}
	}
}


// Login screen.
if ( empty( $_SESSION['STAFF_ID'] )
	&& empty( $_SESSION['STUDENT_ID'] )
	&& ! isset( $_REQUEST['create_account'] ) )
{
	$_ROSARIO['page'] = 'register';

	$registration_result = null;

	if ( isset( $_REQUEST['registered'] )
		&& ! empty( $_SESSION['registration_flash'] ) )
	{
		$registration_result = $_SESSION['registration_flash'];
		unset( $_SESSION['registration_flash'] );
	}

	Warehouse( 'header' );

	PopTable(
		'header',
		sprintf( 'Inscription à %s', Config( 'NAME' ) )
	);
	?>

	<section class="register-page">
		<div class="register-panel">
			<!-- Quick-login toggle bar -->
			<div class="global-login-bar">
				<button id="globalShowLogin" class="button-primary">Se connecter</button>
			</div>
			<!-- Quick-login form (hidden by default) -->
			<div id="globalLoginBox" class="global-login-form" style="display: none;">
				<form id="globalLoginForm" method="post" action="index.php">
					<div class="form-row">
						<input type="email" name="USERNAME" id="GLOBAL_USERNAME" placeholder="Votre adresse e-mail" required />
						<input type="password" name="PASSWORD" id="GLOBAL_PASSWORD" placeholder="Votre code à 4 chiffres" required />
						<input type="submit" value="Se connecter" class="button-primary" />
					</div>
				</form>
			</div>
			<div class="register-hero">
				<img src="assets/themes/<?php echo URLEscape( Config( 'THEME' ) ); ?>/logo.png" class="logo" alt="Logo" />
				<h4><?php echo ParseMLField( Config( 'TITLE' ) ); ?></h4>
			</div>
			<div class="register-card">
				<p class="intro">Remplissez ce formulaire pour créer votre compte. Vous recevrez un code à 4 chiffres pour vous connecter.</p>
	<?php

	echo ErrorMessage( $error );

	echo ErrorMessage( $note, 'note' );

	if ( ! empty( $registration_result ) ) : ?>
		<div class="box success">
			<p><strong>Inscription réussie !</strong> Votre code a été enregistré pour l'adresse
				<strong><?php echo AttrEscape( $registration_result['email'] ); ?></strong>.
				Utilisez-le pour vous connecter (il sera reconnu automatiquement quand vous saisissez votre e-mail).</p>
			<p class="register-code-display"><?php echo AttrEscape( $registration_result['login_code'] ); ?></p>
			<?php if ( empty( $registration_result['email_sent'] ) ) : ?>
			<p><em>L'e-mail n'a pas pu être envoyé (serveur local). Notez bien le code ci-dessus.</em></p>
			<?php else : ?>
			<p>Un e-mail contenant ce code vous a également été envoyé.</p>
			<?php endif; ?>
		</div>
		<div class="dashboard-preview">
			<h5 class="card-title">Connexion rapide</h5>
			<form name="loginform" id="loginform" method="post" action="index.php">
				<table class="cellspacing-0 width-100p">
					<tr>
						<td>
							<label>
								<input type="email" name="USERNAME" id="USERNAME" autocomplete="email" required placeholder="Votre adresse e-mail" value="<?php echo AttrEscape( $registration_result['email'] ); ?>" />
								Adresse e-mail de connexion
							</label>
						</td>
					</tr>
					<tr>
						<td>
							<label>
								<input type="password" name="PASSWORD" id="PASSWORD" autocomplete="one-time-code" required placeholder="Entrez votre code à 4 chiffres" value="<?php echo AttrEscape( $registration_result['login_code'] ); ?>" />
								Code de connexion
							</label>
						</td>
					</tr>
				</table>
				<p class="center">
					<input type="submit" value="Se connecter" class="button-primary" />
				</p>
			</form>
		</div>
<?php endif; ?>

		<div class="section-split">
			<div class="form-card">
				<h5 class="card-title">Créer un compte</h5>
				<form name="registerform" id="registerform" method="post" action="index.php?modfunc=register">
	<table class="cellspacing-0 width-100p">

		<tr>
			<td>
				<label>
					<input type="text" name="FIRST_NAME" id="FIRST_NAME" maxlength="50" required autofocus autocomplete="given-name" placeholder="Entrez votre prénom" value="<?php echo isset( $_REQUEST['FIRST_NAME'] ) ? AttrEscape( $_REQUEST['FIRST_NAME'] ) : ''; ?>" />
					Prénom
					<span class="field-hint">Votre prénom tel qu’il apparaîtra dans votre compte.</span>
				</label>
			</td>
		</tr>
		<tr>
			<td>
				<label>
					<input type="text" name="LAST_NAME" id="LAST_NAME" maxlength="50" required autocomplete="family-name" placeholder="Entrez votre nom de famille" value="<?php echo isset( $_REQUEST['LAST_NAME'] ) ? AttrEscape( $_REQUEST['LAST_NAME'] ) : ''; ?>" />
					Nom de famille
					<span class="field-hint">Votre nom de famille tel qu’il apparaîtra dans votre compte.</span>
				</label>
			</td>
		</tr>
		<tr>
			<td>
				<label>
					<input type="email" name="EMAIL" id="EMAIL" maxlength="255" required autocomplete="email" placeholder="Entrez votre adresse e-mail" value="<?php echo isset( $_REQUEST['EMAIL'] ) ? AttrEscape( $_REQUEST['EMAIL'] ) : ''; ?>" />
					Adresse e-mail
					<span class="field-hint">Vous utiliserez cette adresse pour vous connecter.</span>
				</label>
			</td>
		</tr>
		<tr>
			<td>
				<label>
					<select name="ROLE" id="ROLE" required>
						<option value="student"<?php echo ( isset( $_REQUEST['ROLE'] ) && $_REQUEST['ROLE'] === 'student' ) ? ' selected' : ''; ?>>Étudiant</option>
						<option value="teacher"<?php echo ( isset( $_REQUEST['ROLE'] ) && $_REQUEST['ROLE'] === 'teacher' ) ? ' selected' : ''; ?>>Enseignant</option>
					</select>
					Type de compte
					<span class="field-hint">Choisissez le type de profil que vous souhaitez créer.</span>
				</label>
			</td>
		</tr>
	</table>

	<fieldset id="unh-student-fields" class="unh-student-fields">
		<legend>Informations académiques et administratives (UNH)</legend>
		<table class="cellspacing-0 width-100p">
			<tr>
				<td>
					<label>
						<input type="tel" name="PHONE" id="PHONE" maxlength="30" autocomplete="tel" placeholder="+243 …" value="<?php echo isset( $_REQUEST['PHONE'] ) ? AttrEscape( $_REQUEST['PHONE'] ) : ''; ?>" />
						Téléphone / WhatsApp
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="date" name="BIRTHDATE" id="BIRTHDATE" value="<?php echo isset( $_REQUEST['BIRTHDATE'] ) ? AttrEscape( $_REQUEST['BIRTHDATE'] ) : ''; ?>" />
						Date de naissance
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<select name="GENDER" id="GENDER">
							<option value="">— Genre —</option>
							<option value="M"<?php echo ( isset( $_REQUEST['GENDER'] ) && $_REQUEST['GENDER'] === 'M' ) ? ' selected' : ''; ?>>Masculin</option>
							<option value="F"<?php echo ( isset( $_REQUEST['GENDER'] ) && $_REQUEST['GENDER'] === 'F' ) ? ' selected' : ''; ?>>Féminin</option>
						</select>
						Genre
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="text" name="NATIONALITY" id="NATIONALITY" maxlength="80" placeholder="Ex. Congolaise" value="<?php echo isset( $_REQUEST['NATIONALITY'] ) ? AttrEscape( $_REQUEST['NATIONALITY'] ) : ''; ?>" />
						Nationalité
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="text" name="ID_NUMBER" id="ID_NUMBER" maxlength="50" placeholder="Carte d'étudiant / pièce d'identité" value="<?php echo isset( $_REQUEST['ID_NUMBER'] ) ? AttrEscape( $_REQUEST['ID_NUMBER'] ) : ''; ?>" />
						N° pièce d'identité (optionnel)
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="text" name="ADDRESS" id="ADDRESS" maxlength="255" placeholder="Avenue, quartier, commune" value="<?php echo isset( $_REQUEST['ADDRESS'] ) ? AttrEscape( $_REQUEST['ADDRESS'] ) : ''; ?>" />
						Adresse complète
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="text" name="CITY" id="CITY" maxlength="100" placeholder="Ex. Lubumbashi" value="<?php echo isset( $_REQUEST['CITY'] ) ? AttrEscape( $_REQUEST['CITY'] ) : ''; ?>" />
						Ville
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<select name="FACULTY_CODE" id="FACULTY_CODE">
							<option value="">— Faculté —</option>
							<?php foreach ( UNHFacultiesCatalog() as $code => $faculty ) : ?>
							<option value="<?php echo AttrEscape( $code ); ?>"<?php echo ( isset( $_REQUEST['FACULTY_CODE'] ) && $_REQUEST['FACULTY_CODE'] === $code ) ? ' selected' : ''; ?>><?php echo AttrEscape( $code . ' — ' . $faculty['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
						Faculté (Nouveaux Horizons)
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<select name="LMD_LEVEL" id="LMD_LEVEL">
							<option value="">— Niveau LMD —</option>
							<?php foreach ( UNHLmdLevels() as $code => $label ) : ?>
							<option value="<?php echo AttrEscape( $code ); ?>"<?php echo ( isset( $_REQUEST['LMD_LEVEL'] ) && $_REQUEST['LMD_LEVEL'] === $code ) ? ' selected' : ''; ?>><?php echo AttrEscape( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						Niveau d'études
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="text" name="EMERGENCY_NAME" id="EMERGENCY_NAME" maxlength="120" placeholder="Nom du contact" value="<?php echo isset( $_REQUEST['EMERGENCY_NAME'] ) ? AttrEscape( $_REQUEST['EMERGENCY_NAME'] ) : ''; ?>" />
						Personne à contacter en urgence
					</label>
				</td>
			</tr>
			<tr>
				<td>
					<label>
						<input type="tel" name="EMERGENCY_PHONE" id="EMERGENCY_PHONE" maxlength="30" placeholder="Téléphone du contact" value="<?php echo isset( $_REQUEST['EMERGENCY_PHONE'] ) ? AttrEscape( $_REQUEST['EMERGENCY_PHONE'] ) : ''; ?>" />
						Téléphone d'urgence
					</label>
				</td>
			</tr>
		</table>
		<p class="field-hint unh-fields-note">Ces informations sont requises pour la gestion académique et administrative de l'université.</p>
	</fieldset>

	<p class="center">
		<input type="submit" name="REGISTER_SUBMIT" value="S'inscrire" class="button-primary" />
	</p>
	</form>
	</div>
	<div class="feature-card">
		<h5 class="card-title">Un espace complet</h5>
		<ul class="feature-list">
			<li>Tableau de bord web pour suivre vos cours et activités</li>
			<li>Emploi du temps hebdomadaire et salles attribuées</li>
			<li>Suivi des notes TP, TD, interro et examen</li>
			<li>Pointage en cours pour étudiant et enseignant</li>
			<li>Validation des heures réalisées et reste à donner</li>
		</ul>
	</div>
</div>
	<details class="about-rosariosis">
		<summary>À propos</summary>
		<?php // System disclaimer. ?>
		<p class="size-3">
			<?php
				echo sprintf(
					'Il s’agit d’un réseau restreint. L’utilisation de ce réseau, de ses équipements et de ses ressources est surveillée en permanence et nécessite l’autorisation explicite de l’administrateur réseau et de %s. Si vous ne disposez pas de cette autorisation écrite, vous enfreignez les règles de ce réseau et pouvez faire l’objet de poursuites dans toute la mesure permise par la loi. En continuant dans ce système, vous reconnaissez en être informé et accepter ces conditions.',
					ParseMLField( Config( 'TITLE' ) )
				);
			?>
		</p>
		<p class="center size-1">
			&copy; 2004-2009 The Miller Group &amp; Learners Circle
			<br />&copy; 2012-2026 <a href="https://www.rosariosis.org" rel="noreferrer">RosarioSIS</a>
		</p>
	</details>
		</div>
		</div>
	</section>

	<script>
	(function(){
		function fetchCode(email, cb){
			if(!email) return cb('');
			var body = 'ajax_action=get_login_code&email=' + encodeURIComponent(email);
			fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
				.then(function(r){ return r.json(); })
				.then(function(data){ cb(data.code || ''); })
				.catch(function(){ cb(''); });
		}

		// Autofill quick-login form if code exists
		var qEmail = document.querySelector('#loginform input[name="USERNAME"], #loginform #USERNAME');
		var qPass = document.querySelector('#loginform input[name="PASSWORD"], #loginform #PASSWORD');
		if(qEmail){
			qEmail.addEventListener('change', function(){
				fetchCode(this.value, function(code){ if(code && qPass) qPass.value = code; });
			});
			if(qEmail.value) fetchCode(qEmail.value, function(code){ if(code && qPass) qPass.value = code; });
		}

		// Generic autofill on any email input on the page (useful for login forms)
		var anyEmailInputs = document.querySelectorAll('input[type="email"][name]');
		Array.prototype.forEach.call(anyEmailInputs, function(el){
			el.addEventListener('change', function(){
				var targetPass = document.querySelector('input[type="password"]');
				fetchCode(this.value, function(code){ if(code && targetPass) targetPass.value = code; });
			});
		});

		// Simple toggle between register and quick-login to avoid losing users
		var formCard = document.querySelector('.form-card');
		var dashPreview = document.querySelector('.dashboard-preview');
		if(formCard && dashPreview){
			var switchBar = document.createElement('div');
			switchBar.style.textAlign='center'; switchBar.style.margin='10px 0';
			switchBar.innerHTML = '<p style="margin:8px 0;color:#475569;">Choisissez de vous connecter si vous avez déjà un compte.</p>'+
								  '<button id="show-login" class="button-primary" style="width:48%;margin-right:4%;">Se connecter</button>'+
								  '<button id="show-register" class="button-primary" style="width:48%;background:#fff;color:#2563eb;border:1px solid #2563eb;">S\'inscrire</button>';
			formCard.parentNode.insertBefore(switchBar, formCard);
			document.getElementById('show-login').addEventListener('click', function(){ dashPreview.style.display='block'; formCard.style.display='none'; });
			document.getElementById('show-register').addEventListener('click', function(){ dashPreview.style.display='none'; formCard.style.display='block'; });
			dashPreview.style.display='none';
		}

		// Champs étudiant UNH : affichés et requis uniquement pour les étudiants
		var roleSelect = document.getElementById('ROLE');
		var unhFields = document.getElementById('unh-student-fields');
		var studentInputs = unhFields ? unhFields.querySelectorAll('input, select') : [];

		function setStudentFieldsRequired(isStudent){
			if(!unhFields) return;
			unhFields.style.display = isStudent ? 'block' : 'none';
			Array.prototype.forEach.call(studentInputs, function(el){
				if(el.name === 'ID_NUMBER') return;
				el.required = isStudent;
			});
		}

		if(roleSelect){
			setStudentFieldsRequired(roleSelect.value === 'student');
			roleSelect.addEventListener('change', function(){
				setStudentFieldsRequired(this.value === 'student');
			});
		}

		// Global quick-login toggle for existing users (persistent button)
		var globalShow = document.getElementById('globalShowLogin');
		var globalBox = document.getElementById('globalLoginBox');
		if(globalShow && globalBox){
			globalShow.addEventListener('click', function(){ globalBox.style.display = (globalBox.style.display === 'none' ? 'block' : 'none'); });

			var gEmail = document.getElementById('GLOBAL_USERNAME');
			var gPass = document.getElementById('GLOBAL_PASSWORD');
			if(gEmail){
				gEmail.addEventListener('change', function(){ fetchCode(this.value, function(code){ if(code && gPass) gPass.value = code; }); });
				if(gEmail.value) fetchCode(gEmail.value, function(code){ if(code && gPass) gPass.value = code; });
			}
		}
	})();
	</script>

	<?php PopTable( 'footer' );

	Warehouse( 'footer' );
}

// Successfully logged in, display Portal.
elseif ( ! isset( $_REQUEST['create_account'] ) )
{
	$redirect_to = empty( $_REQUEST['redirect_to'] )
		|| mb_strpos( $_REQUEST['redirect_to'], 'modname=misc/' ) === 0 ?
		'modname=misc/DemoPortal.php' :
		str_replace(
			[ '&_ROSARIO_PDF=true', '&_ROSARIO_PDF', '&LO_save=1', '&bottomfunc=print', '&delete_ok=1' ],
			'',
			$_REQUEST['redirect_to']
		);

	header( 'Location: ' . URLEscape( 'Modules.php?' . $redirect_to ) );

	exit;
}
