<?php
/**
 * Interactive site dashboard — browse all modules and programs.
 *
 * @package RosarioSIS
 */

if ( empty( $_SESSION['STAFF_ID'] ) && empty( $_SESSION['STUDENT_ID'] ) )
{
	header( 'Location: index.php' );
	exit;
}

require_once 'Menu.php';

$profile = User( 'PROFILE' );
$display_name = User( 'NAME' );
$is_admin = ( $profile === 'admin' );

$stats = [
	'students' => DBGetOne( "SELECT COUNT(*) FROM students" ),
	'staff' => DBGetOne( "SELECT COUNT(*) FROM staff WHERE syear='" . Config( 'SYEAR' ) . "'" ),
];

if ( $is_admin )
{
	$stats['codes'] = DBGetOne( "SELECT COUNT(*) FROM public_login_codes" );
}

$quick_links = [
	[
		'title' => 'Mon compte',
		'url' => 'Modules.php?modname=misc/Profile.php',
		'icon' => '👤',
		'roles' => [ 'admin', 'teacher', 'parent', 'student' ],
	],
	[
		'title' => 'Étudiants',
		'url' => 'Modules.php?modname=Students/Student.php',
		'icon' => '🎓',
		'roles' => [ 'admin', 'teacher' ],
	],
	[
		'title' => 'Utilisateurs',
		'url' => 'Modules.php?modname=Users/User.php',
		'icon' => '👥',
		'roles' => [ 'admin' ],
	],
	[
		'title' => 'Présences',
		'url' => 'Modules.php?modname=Attendance/TakeAttendance.php',
		'icon' => '✓',
		'roles' => [ 'admin', 'teacher' ],
	],
	[
		'title' => 'Notes',
		'url' => 'Modules.php?modname=Grades/Grades.php',
		'icon' => '📊',
		'roles' => [ 'admin', 'teacher' ],
	],
	[
		'title' => 'Emploi du temps',
		'url' => 'Modules.php?modname=Scheduling/Schedule.php',
		'icon' => '📅',
		'roles' => [ 'admin', 'teacher', 'parent', 'student' ],
	],
];

if ( $is_admin )
{
	$quick_links[] = [
		'title' => 'Générer comptes',
		'url' => 'Modules.php?modname=misc/SeedTestData.php',
		'icon' => '⚙',
		'roles' => [ 'admin' ],
	];
}

$role_labels = [
	'admin' => 'Administrateur',
	'teacher' => 'Enseignant',
	'parent' => 'Parent',
	'student' => 'Étudiant',
];

$role_label = isset( $role_labels[ $profile ] ) ? $role_labels[ $profile ] : $profile;

PopTable( 'header', _( 'Tableau de bord UNH' ) );

?>
<div id="demo-portal" class="demo-portal">
	<header class="demo-portal-hero">
		<div>
			<p class="demo-portal-welcome">Bienvenue,</p>
			<h2 class="demo-portal-name"><?php echo AttrEscape( $display_name ); ?></h2>
			<span class="demo-portal-badge"><?php echo AttrEscape( $role_label ); ?></span>
		</div>
		<div class="demo-portal-hero-stats">
			<?php if ( $profile !== 'student' ) : ?>
			<div><strong><?php echo (int) $stats['students']; ?></strong><span>étudiants</span></div>
			<?php endif; ?>
			<?php if ( $is_admin ) : ?>
			<div><strong><?php echo (int) $stats['staff']; ?></strong><span>personnel</span></div>
			<div><strong><?php echo (int) $stats['codes']; ?></strong><span>codes actifs</span></div>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $profile === 'student' || $profile === 'parent' ) : ?>
	<div class="demo-portal-hint box">
		Votre code de connexion est disponible dans
		<a href="Modules.php?modname=misc/Profile.php">Mon compte</a>.
	</div>
	<?php elseif ( $profile === 'teacher' ) : ?>
	<div class="demo-portal-hint box">
		En tant qu'enseignant, vous pouvez consulter et modifier les dossiers, mais
		<strong>seuls les administrateurs</strong> peuvent supprimer un étudiant ou un compte personnel.
	</div>
	<?php endif; ?>

	<section class="demo-portal-quick">
		<h3>Accès rapide</h3>
		<div class="demo-portal-quick-grid">
			<?php
			foreach ( $quick_links as $link ) :
				if ( ! in_array( $profile, $link['roles'], true ) )
				{
					continue;
				}
				?>
			<a href="<?php echo URLEscape( $link['url'] ); ?>" class="demo-quick-card">
				<span class="demo-quick-icon"><?php echo $link['icon']; ?></span>
				<span class="demo-quick-title"><?php echo AttrEscape( $link['title'] ); ?></span>
			</a>
				<?php
			endforeach;
			?>
		</div>
	</section>

	<section class="demo-portal-explore">
		<h3>Parcourir tout le site</h3>
		<div class="demo-portal-search-wrap">
			<input type="search" id="demo-portal-search" placeholder="Rechercher un module ou un programme…" autocomplete="off" />
			<span id="demo-portal-search-count"></span>
		</div>

		<div class="demo-portal-modules" id="demo-portal-modules">
			<?php
			foreach ( (array) $_ROSARIO['Menu'] as $modcat => $programs ) :
				$modcat_title = ! empty( $programs['title'] ) ? $programs['title'] : str_replace( '_', ' ', $modcat );
				$program_items = [];

				foreach ( (array) $programs as $program => $title ) :
					if ( $program === 'title' || $program === 'default' || is_numeric( $program ) )
					{
						continue;
					}

					$program_items[] = [
						'url' => 'Modules.php?modname=' . $program,
						'title' => $title,
						'program' => $program,
					];
				endforeach;

				if ( ! $program_items )
				{
					continue;
				}
				?>
			<article class="demo-module-card" data-module="<?php echo AttrEscape( strtolower( $modcat . ' ' . $modcat_title ) ); ?>">
				<header class="demo-module-header">
					<span class="module-icon <?php echo AttrEscape( $modcat ); ?>"></span>
					<h4><?php echo AttrEscape( $modcat_title ); ?></h4>
					<span class="demo-module-count"><?php echo count( $program_items ); ?></span>
				</header>
				<ul class="demo-program-list">
					<?php foreach ( $program_items as $item ) : ?>
					<li data-search="<?php echo AttrEscape( strtolower( $item['title'] . ' ' . $item['program'] ) ); ?>">
						<a href="<?php echo URLEscape( $item['url'] ); ?>"><?php echo AttrEscape( $item['title'] ); ?></a>
					</li>
					<?php endforeach; ?>
				</ul>
			</article>
				<?php
			endforeach;
			?>
		</div>
	</section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
	var input = document.getElementById('demo-portal-search');
	var countEl = document.getElementById('demo-portal-search-count');
	if(!input) return;

	function filter(){
		var q = input.value.trim().toLowerCase();
		var visible = 0;
		document.querySelectorAll('.demo-module-card').forEach(function(card){
			var showCard = !q;
			card.querySelectorAll('.demo-program-list li').forEach(function(li){
				var match = !q || (li.getAttribute('data-search')||'').indexOf(q) !== -1;
				li.style.display = match ? '' : 'none';
				if(match) showCard = true;
			});
			card.style.display = showCard ? '' : 'none';
			if(showCard) visible++;
		});
		if(countEl) countEl.textContent = q ? visible + ' module(s)' : '';
	}

	input.addEventListener('input', filter);
});
</script>
<?php

PopTable( 'footer' );
