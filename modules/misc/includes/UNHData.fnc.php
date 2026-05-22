<?php
/**
 * Université Nouveaux Horizons (UNH) — facultés, cours LMD, inscription étendue.
 *
 * @package RosarioSIS
 */

/**
 * Facultés et cours de référence UNH (système LMD, Lubumbashi — RDC).
 *
 * @return array
 */
function UNHFacultiesCatalog()
{
	return [
		'FSGC' => [
			'title' => 'Faculté des Sciences de Gestion et du Commerce',
			'courses' => [
				[ 'code' => 'MGT101', 'title' => 'Introduction à la gestion', 'credits' => 4 ],
				[ 'code' => 'MGT201', 'title' => 'Comptabilité générale', 'credits' => 5 ],
				[ 'code' => 'MGT301', 'title' => 'Marketing stratégique', 'credits' => 4 ],
				[ 'code' => 'MGT401', 'title' => 'Entrepreneuriat et innovation', 'credits' => 5 ],
				[ 'code' => 'MGT501', 'title' => 'Gestion des ressources humaines', 'credits' => 4 ],
			],
		],
		'FSI' => [
			'title' => 'Faculté des Sciences Informatiques',
			'courses' => [
				[ 'code' => 'INF101', 'title' => 'Algorithmique et programmation', 'credits' => 5 ],
				[ 'code' => 'INF201', 'title' => 'Bases de données', 'credits' => 5 ],
				[ 'code' => 'INF301', 'title' => 'Réseaux et télécommunications', 'credits' => 4 ],
				[ 'code' => 'INF401', 'title' => 'Développement web et mobile', 'credits' => 5 ],
				[ 'code' => 'INF501', 'title' => 'Cybersécurité', 'credits' => 4 ],
			],
		],
		'FDSP' => [
			'title' => 'Faculté de Droit et Sciences Politiques',
			'courses' => [
				[ 'code' => 'DRT101', 'title' => 'Introduction au droit', 'credits' => 4 ],
				[ 'code' => 'DRT201', 'title' => 'Droit constitutionnel', 'credits' => 5 ],
				[ 'code' => 'DRT301', 'title' => 'Droit des affaires OHADA', 'credits' => 5 ],
				[ 'code' => 'DRT401', 'title' => 'Sciences politiques', 'credits' => 4 ],
			],
		],
		'FASE' => [
			'title' => 'Faculté des Sciences de l\'Éducation',
			'courses' => [
				[ 'code' => 'EDU101', 'title' => 'Psychologie de l\'éducation', 'credits' => 4 ],
				[ 'code' => 'EDU201', 'title' => 'Didactique générale', 'credits' => 5 ],
				[ 'code' => 'EDU301', 'title' => 'Évaluation des apprentissages', 'credits' => 4 ],
				[ 'code' => 'EDU401', 'title' => 'Gestion d\'établissement scolaire', 'credits' => 4 ],
			],
		],
		'FASI' => [
			'title' => 'Faculté des Sciences Infirmières',
			'courses' => [
				[ 'code' => 'INFIR101', 'title' => 'Anatomie et physiologie', 'credits' => 5 ],
				[ 'code' => 'INFIR201', 'title' => 'Soins infirmiers fondamentaux', 'credits' => 6 ],
				[ 'code' => 'INFIR301', 'title' => 'Santé communautaire', 'credits' => 4 ],
				[ 'code' => 'INFIR401', 'title' => 'Éthique et déontologie médicale', 'credits' => 3 ],
			],
		],
		'FASCOM' => [
			'title' => 'Faculté des Sciences de la Communication',
			'courses' => [
				[ 'code' => 'COM101', 'title' => 'Communication interpersonnelle', 'credits' => 3 ],
				[ 'code' => 'COM201', 'title' => 'Journalisme et médias numériques', 'credits' => 4 ],
				[ 'code' => 'COM301', 'title' => 'Relations publiques', 'credits' => 4 ],
				[ 'code' => 'COM401', 'title' => 'Production audiovisuelle', 'credits' => 5 ],
			],
		],
		'FSE' => [
			'title' => 'Faculté des Sciences Économiques',
			'courses' => [
				[ 'code' => 'ECO101', 'title' => 'Microéconomie', 'credits' => 4 ],
				[ 'code' => 'ECO201', 'title' => 'Macroéconomie', 'credits' => 5 ],
				[ 'code' => 'ECO301', 'title' => 'Économie du développement', 'credits' => 4 ],
				[ 'code' => 'ECO401', 'title' => 'Économétrie', 'credits' => 5 ],
			],
		],
		'FTL' => [
			'title' => 'Faculté de Théologie et Leadership',
			'courses' => [
				[ 'code' => 'THE101', 'title' => 'Introduction à la théologie', 'credits' => 3 ],
				[ 'code' => 'THE201', 'title' => 'Éthique chrétienne et leadership', 'credits' => 4 ],
				[ 'code' => 'THE301', 'title' => 'Philosophie et culture africaine', 'credits' => 4 ],
			],
		],
	];
}

/**
 * Niveaux LMD proposés à l'inscription.
 *
 * @return array code => label
 */
function UNHLmdLevels()
{
	return [
		'L1' => 'Licence 1',
		'L2' => 'Licence 2',
		'L3' => 'Licence 3',
		'M1' => 'Master 1',
		'M2' => 'Master 2',
		'D1' => 'Doctorat',
	];
}

/**
 * Crée la table d'informations étudiant UNH.
 */
function UNHEnsureSchema()
{
	DBQuery( "CREATE TABLE IF NOT EXISTS unh_student_info (
		student_id INT PRIMARY KEY,
		phone VARCHAR(30) DEFAULT NULL,
		birthdate DATE DEFAULT NULL,
		gender VARCHAR(20) DEFAULT NULL,
		address VARCHAR(255) DEFAULT NULL,
		city VARCHAR(100) DEFAULT NULL,
		faculty_code VARCHAR(20) DEFAULT NULL,
		faculty_name VARCHAR(200) DEFAULT NULL,
		lmd_level VARCHAR(10) DEFAULT NULL,
		nationality VARCHAR(80) DEFAULT NULL,
		emergency_name VARCHAR(120) DEFAULT NULL,
		emergency_phone VARCHAR(30) DEFAULT NULL,
		id_number VARCHAR(50) DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	)" );

	DBQuery( "UPDATE schools SET title='Université Nouveaux Horizons (UNH)'
		WHERE syear='" . Config( 'SYEAR' ) . "' LIMIT 1" );
}

/**
 * Crée périodes de notation et créneaux horaires si absents (année / école courantes).
 */
function UNHEnsureSchedulingData( $school_id )
{
	global $DatabaseType;

	$syear = Config( 'SYEAR' );
	$school_id = (int) $school_id;

	$has_mp = DBGetOne( "SELECT 1 FROM school_marking_periods
		WHERE school_id='" . $school_id . "'
		AND syear='" . $syear . "'" );

	if ( $has_mp )
	{
		return;
	}

	$start = $syear . '-09-01';
	$end = ( $syear + 1 ) . '-06-30';

	$fy_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'school_marking_periods' : 'school_marking_periods_marking_period_id_seq' );

	DBInsert( 'school_marking_periods', [
		'MARKING_PERIOD_ID' => (int) $fy_id,
		'SYEAR' => $syear,
		'MP' => 'FY',
		'SCHOOL_ID' => $school_id,
		'PARENT_ID' => null,
		'TITLE' => DBEscapeString( 'Année universitaire UNH' ),
		'SHORT_NAME' => DBEscapeString( 'AU' ),
		'SORT_ORDER' => 1,
		'START_DATE' => $start,
		'END_DATE' => $end,
	] );

	$qtr_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'school_marking_periods' : 'school_marking_periods_marking_period_id_seq' );

	DBInsert( 'school_marking_periods', [
		'MARKING_PERIOD_ID' => (int) $qtr_id,
		'SYEAR' => $syear,
		'MP' => 'QTR',
		'SCHOOL_ID' => $school_id,
		'PARENT_ID' => (int) $fy_id,
		'TITLE' => DBEscapeString( 'Trimestre 1' ),
		'SHORT_NAME' => DBEscapeString( 'T1' ),
		'SORT_ORDER' => 1,
		'START_DATE' => $start,
		'END_DATE' => $end,
		'DOES_GRADES' => 'Y',
		'DOES_COMMENTS' => 'Y',
	] );

	$period_exists = DBGetOne( "SELECT 1 FROM school_periods
		WHERE syear='" . $syear . "'
		AND school_id='" . $school_id . "'" );

	if ( ! $period_exists )
	{
		$period_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'school_periods' : 'school_periods_period_id_seq' );

		DBInsert( 'school_periods', [
			'PERIOD_ID' => (int) $period_id,
			'SYEAR' => $syear,
			'SCHOOL_ID' => $school_id,
			'SORT_ORDER' => 1,
			'TITLE' => DBEscapeString( 'Period 1' ),
			'SHORT_NAME' => DBEscapeString( 'P1' ),
			'LENGTH' => 60,
			'ATTENDANCE' => 'Y',
		] );
	}
}

/**
 * Insère les niveaux LMD comme grade levels si absents.
 */
function UNHSeedGradeLevels( $school_id )
{
	$syear = Config( 'SYEAR' );
	$levels = [
		[ 'L1', 'Licence 1', 1 ],
		[ 'L2', 'Licence 2', 2 ],
		[ 'L3', 'Licence 3', 3 ],
		[ 'M1', 'Master 1', 4 ],
		[ 'M2', 'Master 2', 5 ],
		[ 'D1', 'Doctorat', 6 ],
	];

	$created = 0;

	foreach ( $levels as $level )
	{
		$exists = DBGetOne( "SELECT 1 FROM school_gradelevels
			WHERE school_id='" . (int) $school_id . "'
			AND short_name='" . DBEscapeString( $level[0] ) . "'" );

		if ( $exists )
		{
			continue;
		}

		DBInsert( 'school_gradelevels', [
			'SCHOOL_ID' => (int) $school_id,
			'SHORT_NAME' => DBEscapeString( $level[0] ),
			'TITLE' => DBEscapeString( $level[1] ),
			'SORT_ORDER' => $level[2],
			'NEXT_GRADE_ID' => null,
		] );

		$created++;
	}

	return $created;
}

/**
 * Seed course subjects, courses and course periods for UNH.
 *
 * @return array{subjects:int,courses:int,periods:int}
 */
function UNHSeedCourses()
{
	global $DatabaseType;

	UNHEnsureSchema();

	$syear = Config( 'SYEAR' );
	$school_id = DemoDataDefaultSchoolId();

	UNHEnsureSchedulingData( $school_id );
	UNHSeedGradeLevels( $school_id );

	$mp_id = DBGetOne( "SELECT MARKING_PERIOD_ID FROM school_marking_periods
		WHERE SYEAR='" . $syear . "'
		AND SCHOOL_ID='" . (int) $school_id . "'
		AND MP='QTR'
		ORDER BY SORT_ORDER LIMIT 1" );

	if ( ! $mp_id )
	{
		$mp_id = DBGetOne( "SELECT MARKING_PERIOD_ID FROM school_marking_periods
			WHERE SYEAR='" . $syear . "'
			AND SCHOOL_ID='" . (int) $school_id . "'
			LIMIT 1" );
	}

	$period_id = DBGetOne( "SELECT PERIOD_ID FROM school_periods
		WHERE SYEAR='" . $syear . "'
		AND SCHOOL_ID='" . (int) $school_id . "'
		AND TITLE LIKE '%Period 1%'
		LIMIT 1" );

	if ( ! $period_id )
	{
		$period_id = DBGetOne( "SELECT PERIOD_ID FROM school_periods
			WHERE SYEAR='" . $syear . "'
			AND SCHOOL_ID='" . (int) $school_id . "'
			LIMIT 1" );
	}

	$teachers = DBGet( "SELECT STAFF_ID FROM staff
		WHERE SYEAR='" . $syear . "'
		AND PROFILE='teacher'
		ORDER BY STAFF_ID" );

	$teacher_ids = [];

	foreach ( $teachers as $t )
	{
		$teacher_ids[] = (int) $t['STAFF_ID'];
	}

	if ( ! $teacher_ids )
	{
		$admin_id = DBGetOne( "SELECT STAFF_ID FROM staff WHERE PROFILE='admin' AND SYEAR='" . $syear . "' LIMIT 1" );
		$teacher_ids = [ (int) $admin_id ];
	}

	$teacher_i = 0;
	$stats = [ 'subjects' => 0, 'courses' => 0, 'periods' => 0 ];
	$catalog = UNHFacultiesCatalog();

	foreach ( $catalog as $faculty_code => $faculty )
	{
		$subject_title = $faculty_code . ' — ' . $faculty['title'];

		$subject_id = DBGetOne( "SELECT SUBJECT_ID FROM course_subjects
			WHERE SYEAR='" . $syear . "'
			AND SCHOOL_ID='" . (int) $school_id . "'
			AND TITLE='" . DBEscapeString( $subject_title ) . "'" );

		if ( ! $subject_id )
		{
			$subject_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'course_subjects' : 'course_subjects_subject_id_seq' );

			DBInsert( 'course_subjects', [
				'SYEAR' => $syear,
				'SCHOOL_ID' => (int) $school_id,
				'SUBJECT_ID' => (int) $subject_id,
				'TITLE' => DBEscapeString( $subject_title ),
			] );

			$stats['subjects']++;
		}

		foreach ( $faculty['courses'] as $course )
		{
			$course_title = $course['code'] . ' — ' . $course['title'];

			$course_id = DBGetOne( "SELECT COURSE_ID FROM courses
				WHERE SYEAR='" . $syear . "'
				AND SCHOOL_ID='" . (int) $school_id . "'
				AND SUBJECT_ID='" . (int) $subject_id . "'
				AND SHORT_NAME='" . DBEscapeString( $course['code'] ) . "'" );

			if ( ! $course_id )
			{
				$course_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'courses' : 'courses_course_id_seq' );

				DBInsert( 'courses', [
					'SYEAR' => $syear,
					'COURSE_ID' => (int) $course_id,
					'SUBJECT_ID' => (int) $subject_id,
					'SCHOOL_ID' => (int) $school_id,
					'TITLE' => DBEscapeString( $course_title ),
					'SHORT_NAME' => DBEscapeString( $course['code'] ),
					'CREDIT_HOURS' => $course['credits'],
					'DESCRIPTION' => DBEscapeString( 'Cours UNH — ' . $faculty['title'] ),
				] );

				$stats['courses']++;
			}

			$cp_exists = DBGetOne( "SELECT 1 FROM course_periods
				WHERE COURSE_ID='" . (int) $course_id . "'
				AND SYEAR='" . $syear . "'" );

			if ( $cp_exists )
			{
				continue;
			}

			$teacher_id = $teacher_ids[ $teacher_i % count( $teacher_ids ) ];
			$teacher_i++;

			$cp_id = DBSeqNextID( $DatabaseType === 'mysql' ? 'course_periods' : 'course_periods_course_period_id_seq' );

			DBInsert( 'course_periods', [
				'SYEAR' => $syear,
				'SCHOOL_ID' => (int) $school_id,
				'COURSE_PERIOD_ID' => (int) $cp_id,
				'COURSE_ID' => (int) $course_id,
				'TITLE' => DBEscapeString( $course['title'] ),
				'SHORT_NAME' => DBEscapeString( $course['code'] . '-A' ),
				'MP' => 'QTR',
				'MARKING_PERIOD_ID' => (int) $mp_id,
				'TEACHER_ID' => $teacher_id,
				'TOTAL_SEATS' => 60,
				'FILLED_SEATS' => 0,
				'DOES_ATTENDANCE' => 'Y',
				'CREDITS' => $course['credits'],
			] );

			DBInsert( 'course_period_school_periods', [
				'COURSE_PERIOD_ID' => (int) $cp_id,
				'PERIOD_ID' => (int) $period_id,
				'DAYS' => 'UMTWHF',
			] );

			$stats['periods']++;
		}
	}

	return $stats;
}

/**
 * Enregistre les informations complémentaires d'un étudiant UNH.
 */
function UNHSaveStudentInfo( $student_id, $data )
{
	UNHEnsureSchema();

	$faculty_code = isset( $data['faculty_code'] ) ? $data['faculty_code'] : '';
	$catalog = UNHFacultiesCatalog();
	$faculty_name = isset( $catalog[ $faculty_code ] ) ? $catalog[ $faculty_code ]['title'] : '';

	$birthdate = ! empty( $data['birthdate'] ) ? $data['birthdate'] : null;

	DBQuery( "REPLACE INTO unh_student_info (
		student_id, phone, birthdate, gender, address, city,
		faculty_code, faculty_name, lmd_level, nationality,
		emergency_name, emergency_phone, id_number
	) VALUES (
		'" . (int) $student_id . "',
		'" . DBEscapeString( isset( $data['phone'] ) ? $data['phone'] : '' ) . "',
		" . ( $birthdate ? "'" . DBEscapeString( $birthdate ) . "'" : 'NULL' ) . ",
		'" . DBEscapeString( isset( $data['gender'] ) ? $data['gender'] : '' ) . "',
		'" . DBEscapeString( isset( $data['address'] ) ? $data['address'] : '' ) . "',
		'" . DBEscapeString( isset( $data['city'] ) ? $data['city'] : '' ) . "',
		'" . DBEscapeString( $faculty_code ) . "',
		'" . DBEscapeString( $faculty_name ) . "',
		'" . DBEscapeString( isset( $data['lmd_level'] ) ? $data['lmd_level'] : '' ) . "',
		'" . DBEscapeString( isset( $data['nationality'] ) ? $data['nationality'] : '' ) . "',
		'" . DBEscapeString( isset( $data['emergency_name'] ) ? $data['emergency_name'] : '' ) . "',
		'" . DBEscapeString( isset( $data['emergency_phone'] ) ? $data['emergency_phone'] : '' ) . "',
		'" . DBEscapeString( isset( $data['id_number'] ) ? $data['id_number'] : '' ) . "'
	)" );

	// Lier le niveau LMD à l'inscription.
	if ( ! empty( $data['lmd_level'] ) )
	{
		$grade_id = DBGetOne( "SELECT ID FROM school_gradelevels
			WHERE school_id='" . (int) DemoDataDefaultSchoolId() . "'
			AND short_name='" . DBEscapeString( $data['lmd_level'] ) . "'
			LIMIT 1" );

		if ( $grade_id )
		{
			DBQuery( "UPDATE student_enrollment SET grade_id='" . (int) $grade_id . "'
				WHERE student_id='" . (int) $student_id . "'
				AND syear='" . Config( 'SYEAR' ) . "'" );
		}
	}
}

/**
 * Valide les champs d'inscription étendue.
 *
 * @param array $data  Données POST.
 * @param array $error Tableau d'erreurs (par référence).
 */
function UNHValidateRegistration( $data, &$error )
{
	if ( empty( $data['phone'] ) )
	{
		$error[] = _( 'Please enter your phone number.' );
	}

	if ( empty( $data['birthdate'] ) )
	{
		$error[] = _( 'Please enter your date of birth.' );
	}

	if ( empty( $data['gender'] ) )
	{
		$error[] = _( 'Please select your gender.' );
	}

	if ( empty( $data['address'] ) )
	{
		$error[] = _( 'Please enter your address.' );
	}

	if ( empty( $data['city'] ) )
	{
		$error[] = _( 'Please enter your city.' );
	}

	$catalog = UNHFacultiesCatalog();

	if ( empty( $data['faculty_code'] ) || ! isset( $catalog[ $data['faculty_code'] ] ) )
	{
		$error[] = _( 'Please select a valid faculty.' );
	}

	$levels = UNHLmdLevels();

	if ( empty( $data['lmd_level'] ) || ! isset( $levels[ $data['lmd_level'] ] ) )
	{
		$error[] = _( 'Please select your LMD level.' );
	}

	if ( empty( $data['nationality'] ) )
	{
		$error[] = _( 'Please enter your nationality.' );
	}

	if ( empty( $data['emergency_name'] ) || empty( $data['emergency_phone'] ) )
	{
		$error[] = _( 'Please provide emergency contact information.' );
	}
}
