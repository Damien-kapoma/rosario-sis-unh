<?php
/**
 * CLI: reset demo data and seed 1500 students.
 * Usage: php tools/reset_and_seed.php
 */

chdir( dirname( __DIR__ ) );

require_once 'Warehouse.php';
require_once 'modules/misc/includes/DemoData.fnc.php';
require_once 'modules/misc/includes/UNHData.fnc.php';

set_time_limit( 600 );

echo "Reset database...\n";
$reset = DemoDataResetDatabase();
echo "Removed {$reset['students']} students, {$reset['staff']} staff rows.\n";

DemoDataApplyRolePermissions();

echo "Seeding 1500 students...\n";
$offset = 0;
$total = DEMO_MAX_STUDENTS;
$batch = 100;
$all = 0;

while ( $offset < $total )
{
	$chunk = min( $batch, $total - $offset );
	$result = DemoDataSeedStudents( $chunk, $offset, 'rosario.unh' );
	$all += $result['created'];
	$offset += $chunk;
	echo "  ... {$offset}/{$total}\n";
}

$teachers = DemoDataSeedStaff( 'teacher', 25, 'enseignant', 'rosario.unh' );

echo "Seeding UNH faculties and courses...\n";
$course_stats = UNHSeedCourses();
echo "  {$course_stats['subjects']} subjects, {$course_stats['courses']} courses, {$course_stats['periods']} periods.\n";
$rows = DBGet( 'SELECT email AS EMAIL, code AS CODE, role AS ROLE FROM public_login_codes ORDER BY role,email' );
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

DemoDataExportCsv( $credentials, 'unh_comptes_complet.csv' );

echo "Done. {$all} students + {$teachers['created']} teachers.\n";
echo "Login admin: admin / admin\n";
echo "Example student: etudiant0001@rosario.unh + code in public_login_codes\n";
