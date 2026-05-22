<?php

/**
 * Exam Eligibility based on Attendance
 *
 * @package RosarioSIS
 * @subpackage Attendance
 */

/**
 * Calculate Student Absence Percentage
 *
 * Calculates the percentage of absences (A and H = half-day)
 * for a student during the current marking period
 *
 * @param int    $student_id   Student ID
 * @param string $syear        School year (optional, defaults to current)
 * @param string $start_date   Start date for absence calculation (optional)
 * @param string $end_date     End date for absence calculation (optional)
 *
 * @return float Absence percentage (0-100)
 */
function AttendanceStudentAbsencePercentage( $student_id, $syear = '', $start_date = '', $end_date = '' )
{
	if ( ! $syear )
	{
		$syear = UserSyear();
	}

	// If date range not provided, use current quarter
	if ( ! $start_date || ! $end_date )
	{
		$qtr_id = GetCurrentMP( 'QTR', DBDate(), false );

		if ( ! $qtr_id )
		{
			return 0;
		}

		$mp_info = DBGetOne( "SELECT START_DATE,END_DATE
			FROM school_marking_periods
			WHERE MARKING_PERIOD_ID='" . (int) $qtr_id . "'
			AND SYEAR='" . (int) $syear . "'" );

		if ( ! $mp_info )
		{
			return 0;
		}

		$start_date = $mp_info['START_DATE'];
		$end_date = $mp_info['END_DATE'];
	}

	// Count total possible days (attendance calendar entries)
	$total_days_sql = "SELECT COUNT(DISTINCT ad.SCHOOL_DATE) AS TOTAL
		FROM attendance_day ad
		WHERE ad.STUDENT_ID='" . (int) $student_id . "'
		AND ad.SYEAR='" . (int) $syear . "'
		AND ad.SCHOOL_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'";

	$total_days = (int) DBGetOne( $total_days_sql );

	if ( $total_days === 0 )
	{
		return 0;
	}

	// Count absent days (STATE_VALUE = 0 is present, -1 is absent, -0.5 is half-day)
	$absent_days_sql = "SELECT COUNT(DISTINCT ad.SCHOOL_DATE) AS ABSENCES
		FROM attendance_day ad
		WHERE ad.STUDENT_ID='" . (int) $student_id . "'
		AND ad.SYEAR='" . (int) $syear . "'
		AND ad.SCHOOL_DATE BETWEEN '" . $start_date . "' AND '" . $end_date . "'
		AND (ad.STATE_VALUE < 0)";

	$absent_days = (int) DBGetOne( $absent_days_sql );

	// Calculate percentage
	$absence_percentage = ( $absent_days / $total_days ) * 100;

	return round( $absence_percentage, 2 );
}

/**
 * Check Student Exam Eligibility
 *
 * Determines if a student is eligible for exams based on
 * their absence percentage vs the configured threshold
 *
 * @param int    $student_id Student ID
 * @param string $syear      School year (optional)
 *
 * @return array ['eligible' => bool, 'absence_percent' => float, 'threshold' => float]
 */
function AttendanceStudentIsExamEligible( $student_id, $syear = '' )
{
	if ( ! $syear )
	{
		$syear = UserSyear();
	}

	// Get configured threshold (default 25%)
	$threshold = (float) ( ProgramConfig( 'attendance', 'ATTENDANCE_EXAM_ELIGIBILITY_THRESHOLD' ) ?: 25 );

	// Calculate student absence percentage
	$absence_percent = AttendanceStudentAbsencePercentage( $student_id, $syear );

	// Eligible if absence <= threshold
	$eligible = ( $absence_percent <= $threshold );

	return [
		'eligible' => $eligible,
		'absence_percent' => $absence_percent,
		'threshold' => $threshold,
	];
}

/**
 * Get Student Exam Eligibility Message
 *
 * Returns a formatted message about student exam eligibility status
 *
 * @param int    $student_id Student ID
 * @param string $syear      School year (optional)
 *
 * @return string Warning or info message
 */
function AttendanceStudentExamEligibilityMessage( $student_id, $syear = '' )
{
	$eligibility = AttendanceStudentIsExamEligible( $student_id, $syear );

	if ( ! $eligibility['eligible'] )
	{
		return sprintf(
			_( '<span class="warning">Student is INELIGIBLE for exams (Absence: %s%% > Threshold: %s%%)</span>' ),
			$eligibility['absence_percent'],
			$eligibility['threshold']
		);
	}

	return sprintf(
		_( '<span class="info">Student is eligible for exams (Absence: %s%% ≤ Threshold: %s%%)</span>' ),
		$eligibility['absence_percent'],
		$eligibility['threshold']
	);
}
