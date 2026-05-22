<?php
/**
 * Seed demo accounts — 1000 students, teachers, admins (admin only).
 *
 * @package RosarioSIS
 */

require_once 'modules/misc/includes/DemoData.fnc.php';
require_once 'modules/misc/includes/UNHData.fnc.php';

if ( User( 'PROFILE' ) !== 'admin' )
{
	die( ErrorMessage( [ _( 'Access denied.' ) ], 'fatal' ) );
}

$note = [];
$error = [];
$all_credentials = [];

if ( isset( $_REQUEST['modfunc'] ) && $_REQUEST['modfunc'] === 'download_csv' )
{
	$rows = DBGet( "SELECT role AS ROLE, email AS EMAIL, code AS CODE FROM public_login_codes ORDER BY role,email" );

	$credentials = [];

	foreach ( $rows as $row )
	{
		$credentials[] = [
			'role' => $row['ROLE'],
			'email' => $row['EMAIL'],
			'code' => $row['CODE'],
			'name' => $row['EMAIL'],
		];
	}

	DemoDataDownloadCsv( $credentials );
}

if ( isset( $_POST['seed_action'] ) )
{
	set_time_limit( 600 );

	$domain = ! empty( $_POST['email_domain'] ) ? preg_replace( '/[^a-z0-9.\-]/i', '', $_POST['email_domain'] ) : 'rosario.unh';

	if ( $_POST['seed_action'] === 'reset' )
	{
		$result = DemoDataResetDatabase();
		$note[] = sprintf(
			_( 'Database cleared: %d student(s), %d staff account(s) removed. Codes reset.' ),
			$result['students'],
			$result['staff']
		);
	}
	elseif ( $_POST['seed_action'] === 'students' )
	{
		$total = min( DEMO_MAX_STUDENTS, max( 1, (int) $_POST['student_count'] ) );
		$batch = 100;
		$offset = 0;
		$all_rows = [];

		while ( $offset < $total )
		{
			$chunk = min( $batch, $total - $offset );
			$result = DemoDataSeedStudents( $chunk, $offset, $domain );
			$all_rows = array_merge( $all_rows, $result['credentials'] );
			$offset += $chunk;
		}

		DemoDataExportCsv( $all_rows, 'etudiants_' . $total . '.csv' );
		$note[] = sprintf( _( '%d student account(s) created.' ), count( $all_rows ) );
	}
	elseif ( $_POST['seed_action'] === 'teachers' )
	{
		$count = min( 200, max( 1, (int) $_POST['teacher_count'] ) );
		$result = DemoDataSeedStaff( 'teacher', $count, 'enseignant', $domain );
		DemoDataExportCsv( $result['credentials'], 'enseignants.csv' );
		$note[] = sprintf( _( '%d teacher account(s) created.' ), $result['created'] );
	}
	elseif ( $_POST['seed_action'] === 'admins' )
	{
		$count = min( 50, max( 1, (int) $_POST['admin_count'] ) );
		$result = DemoDataSeedStaff( 'admin', $count, 'admin.demo', $domain );
		DemoDataExportCsv( $result['credentials'], 'admins.csv' );
		$note[] = sprintf( _( '%d admin account(s) created.' ), $result['created'] );
	}
	elseif ( $_POST['seed_action'] === 'permissions' )
	{
		DemoDataApplyRolePermissions();
		$note[] = _( 'Teacher and admin permissions updated. Only administrators can delete students and staff.' );
	}
	elseif ( $_POST['seed_action'] === 'unh_courses' )
	{
		$stats = UNHSeedCourses();
		$note[] = sprintf(
			_( 'UNH faculties seeded: %d subject(s), %d course(s), %d class period(s).' ),
			$stats['subjects'],
			$stats['courses'],
			$stats['periods']
		);
	}
	elseif ( $_POST['seed_action'] === 'full' )
	{
		DemoDataResetDatabase();
		DemoDataApplyRolePermissions();

		$student_total = min( DEMO_MAX_STUDENTS, max( 1, (int) $_POST['student_count'] ) );
		$batch = 100;
		$offset = 0;
		$all_rows = [];

		while ( $offset < $student_total )
		{
			$chunk = min( $batch, $student_total - $offset );
			$result = DemoDataSeedStudents( $chunk, $offset, $domain );
			$all_rows = array_merge( $all_rows, $result['credentials'] );
			$offset += $chunk;
		}

		$student_created = count( $all_rows );

		$teachers = DemoDataSeedStaff( 'teacher', min( 30, (int) $_POST['teacher_count'] ), 'enseignant', $domain );
		$admins = DemoDataSeedStaff( 'admin', min( 5, (int) $_POST['admin_count'] ), 'admin.demo', $domain );

		$course_stats = UNHSeedCourses();

		$all_rows = array_merge( $all_rows, $teachers['credentials'], $admins['credentials'] );
		DemoDataExportCsv( $all_rows, 'unh_comptes_complet.csv' );

		$note[] = sprintf(
			_( 'Full seed done: %d students, %d teachers, %d admins.' ),
			$student_created,
			$teachers['created'],
			$admins['created']
		);
		$note[] = sprintf(
			_( 'UNH courses: %d subject(s), %d course(s), %d period(s).' ),
			$course_stats['subjects'],
			$course_stats['courses'],
			$course_stats['periods']
		);
	}
}

$stats = [
	'students' => DBGetOne( "SELECT COUNT(*) FROM students" ),
	'staff' => DBGetOne( "SELECT COUNT(*) FROM staff WHERE syear='" . Config( 'SYEAR' ) . "'" ),
	'codes' => DBGetOne( "SELECT COUNT(*) FROM public_login_codes" ),
];

PopTable( 'header', _( 'Génération des comptes démo UNH' ) );

echo ErrorMessage( $error );
echo ErrorMessage( $note, 'note' );

?>
<div class="demo-seed-panel">
	<p class="demo-seed-intro">
		Génération en masse : jusqu'à <strong>1500 étudiants</strong> démo.
		L'<strong>inscription publique</strong> sur la page d'accueil reste ouverte (nouveaux e-mails).
		Les CSV sont dans <code>assets/FileUploads/Demo/</code>.
	</p>

	<div class="demo-seed-stats">
		<div class="demo-stat-card"><span class="demo-stat-num"><?php echo (int) $stats['students']; ?></span><span class="demo-stat-label">Étudiants</span></div>
		<div class="demo-stat-card"><span class="demo-stat-num"><?php echo (int) $stats['staff']; ?></span><span class="demo-stat-label">Personnel</span></div>
		<div class="demo-stat-card"><span class="demo-stat-num"><?php echo (int) $stats['codes']; ?></span><span class="demo-stat-label">Codes enregistrés</span></div>
	</div>

	<form method="post" action="<?php echo URLEscape( 'Modules.php?modname=misc/SeedTestData.php' ); ?>" class="demo-seed-form">
		<fieldset>
			<legend>Paramètres</legend>
			<label>Domaine e-mail :
				<input type="text" name="email_domain" value="rosario.unh" maxlength="80" />
			</label>
			<label>Étudiants :
				<input type="number" name="student_count" value="1500" min="1" max="1500" />
			</label>
			<label>Enseignants :
				<input type="number" name="teacher_count" value="25" min="1" max="200" />
			</label>
			<label>Admins :
				<input type="number" name="admin_count" value="5" min="1" max="50" />
			</label>
		</fieldset>

		<div class="demo-seed-actions">
			<button type="submit" name="seed_action" value="reset" class="button" onclick="return confirm('Supprimer tous les étudiants, enseignants et codes ? Le compte admin par défaut est conservé.');">Vider la base</button>
			<button type="submit" name="seed_action" value="full" class="button-primary" onclick="return confirm('Vider puis créer 1500 étudiants + personnel ?');">Réinitialiser + 1500 étudiants</button>
			<button type="submit" name="seed_action" value="students" class="button">Étudiants seulement</button>
			<button type="submit" name="seed_action" value="teachers" class="button">Enseignants</button>
			<button type="submit" name="seed_action" value="admins" class="button">Administrateurs</button>
			<button type="submit" name="seed_action" value="permissions" class="button">Appliquer permissions</button>
			<button type="submit" name="seed_action" value="unh_courses" class="button-primary">Facultés &amp; cours UNH</button>
		</div>
	</form>

	<p style="margin-top:1.5em;">
		<a class="button" href="<?php echo URLEscape( 'Modules.php?modname=misc/SeedTestData.php&modfunc=download_csv' ); ?>">Télécharger tous les codes (CSV)</a>
		<a class="button" href="<?php echo URLEscape( 'Modules.php?modname=misc/SetDemoPasswords.php' ); ?>">Synchroniser mots de passe</a>
		<a class="button" href="<?php echo URLEscape( 'Modules.php?modname=misc/DemoPortal.php' ); ?>">Retour au tableau de bord</a>
	</p>

	<h4>Format des comptes</h4>
	<ul>
		<li>Étudiants : <code>etudiant0001@rosario.unh</code> … <code>etudiant1500@…</code></li>
		<li>Enseignants : <code>enseignant01@rosario.unh</code> …</li>
		<li>Admins : <code>admin.demo01@rosario.unh</code> …</li>
		<li>Connexion : e-mail + code à 4 chiffres (mot de passe)</li>
	</ul>
</div>
<?php

PopTable( 'footer' );
