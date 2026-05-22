<?php
/**
 * Simple Profile module
 * Shows basic account info after registration/login
 */

if ( empty( $_SESSION['STAFF_ID'] ) && empty( $_SESSION['STUDENT_ID'] ) )
{
    header( 'Location: index.php' );
    exit;
}

PopTable( 'header', 'Mon compte' );
if ( isset( $_REQUEST['sent'] ) )
{
    if ( $_REQUEST['sent'] === '1' )
    {
        echo '<div class="box success" style="margin-bottom:16px;">Un e-mail contenant votre code de connexion a été envoyé à votre adresse.</div>';
    }
    else
    {
        echo '<div class="box" style="margin-bottom:16px;">L\'e-mail n\'a pas pu être envoyé automatiquement. Votre code est affiché ci-dessus.</div>';
    }
}

// Handle show/regenerate actions
$note = [];
$email_for_action = '';
if ( ! empty( $_POST['action'] ) && ! empty( $_POST['email_for_code'] ) )
{
    $email_for_action = trim( $_POST['email_for_code'] );

    // Determine current user email(s)
    $current_email = '';
    if ( ! empty( $_SESSION['STAFF_ID'] ) )
    {
        $row = DBGet( "SELECT EMAIL,PROFILE FROM staff WHERE STAFF_ID='" . (int) $_SESSION['STAFF_ID'] . "' LIMIT 1" );
        if ( $row ) { $current_email = $row[1]['EMAIL']; $current_profile = $row[1]['PROFILE']; }
    }
    elseif ( ! empty( $_SESSION['STUDENT_ID'] ) )
    {
        $row = DBGet( "SELECT EMAIL FROM students WHERE STUDENT_ID='" . (int) $_SESSION['STUDENT_ID'] . "' LIMIT 1" );
        if ( $row ) { $current_email = $row[1]['EMAIL']; $current_profile = 'student'; }
    }

    $allowed = false;
    if ( strcasecmp( $current_email, $email_for_action ) === 0 ) { $allowed = true; }
    if ( ! empty( $current_profile ) && strcasecmp( $current_profile, 'admin' ) === 0 ) { $allowed = true; }

    if ( ! $allowed )
    {
        $note[] = 'Action non autorisée pour cet e-mail.';
    }
    else
    {
        if ( $_POST['action'] === 'show' )
        {
            // nothing server-side, client will fetch via AJAX; but we can preload
        }
        elseif ( $_POST['action'] === 'regen' )
        {
            // Generate new 4-digit code, update users and public_login_codes, send email
            $new_code = str_pad( rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
            $hash = encrypt_password( $new_code );

            // Update staff and students if present
            DBQuery( "UPDATE staff SET PASSWORD='" . DBEscapeString( $hash ) . "' WHERE UPPER(EMAIL)=UPPER('" . DBEscapeString( $email_for_action ) . "')" );
            DBQuery( "UPDATE students SET PASSWORD='" . DBEscapeString( $hash ) . "' WHERE UPPER(EMAIL)=UPPER('" . DBEscapeString( $email_for_action ) . "')" );

            // Save code mapping
            DBQuery( "REPLACE INTO public_login_codes (email,code) VALUES ('" . DBEscapeString( $email_for_action ) . "','" . DBEscapeString( $new_code ) . "')" );

            // Send email notification
            $sent = false;
            if ( function_exists( 'SendEmail' ) )
            {
                $login_url = 'index.php';
                $message = sprintf( "Bonjour,\n\nVotre code de connexion a été régénéré. Nouveau code : %s\n\nConnectez-vous : %s", $new_code, $login_url );
                $sent = SendEmail( $email_for_action, _( 'Nouveau code de connexion RosarioSIS' ), $message, Config( 'NAME' ) . ' <no-reply@' . preg_replace( '/^www\./', '', $_SERVER['SERVER_NAME'] ) . '>' );
            }

            $note[] = $sent ? 'Nouveau code envoyé par e-mail.' : 'Nouveau code généré et enregistré.';
        }
    }
}

echo ErrorMessage( $note, 'note' );

if ( ! empty( $_SESSION['STAFF_ID'] ) )
{
    $staff_id = (int) $_SESSION['STAFF_ID'];
    $row = DBGet( "SELECT FIRST_NAME,LAST_NAME,EMAIL,PROFILE FROM staff WHERE STAFF_ID='" . $staff_id . "' LIMIT 1" );
    if ( $row )
    {
        $u = $row[1];
        echo '<h3>' . AttrEscape( $u['FIRST_NAME'] . ' ' . $u['LAST_NAME'] ) . '</h3>';
        echo '<p><strong>Rôle:</strong> ' . AttrEscape( $u['PROFILE'] ) . '</p>';
        echo '<p><strong>Email:</strong> ' . AttrEscape( $u['EMAIL'] ) . '</p>';
    }
}
else
{
    $student_id = (int) $_SESSION['STUDENT_ID'];
    $row = DBGet( "SELECT FIRST_NAME,LAST_NAME,EMAIL FROM students WHERE STUDENT_ID='" . $student_id . "' LIMIT 1" );
    if ( $row )
    {
        $u = $row[1];
        echo '<h3>' . AttrEscape( $u['FIRST_NAME'] . ' ' . $u['LAST_NAME'] ) . '</h3>';
        echo '<p><strong>Rôle:</strong> Étudiant</p>';
        echo '<p><strong>Email:</strong> ' . AttrEscape( $u['EMAIL'] ) . '</p>';
    }
}

// Code actions form
echo '<div class="unh-profile-actions" style="margin-top:18px;border-top:1px solid #e6eef8;padding-top:12px;">';
echo '<h4>Afficher / régénérer le code de connexion</h4>';
echo '<form id="codeForm" method="post" action="Modules.php?modname=misc/Profile.php">';
echo '<label>Email: <input type="email" name="email_for_code" id="email_for_code" value="' . AttrEscape( isset( $u['EMAIL'] ) ? $u['EMAIL'] : '' ) . '" required /></label> ';
echo '<button type="button" id="btnShow" class="button">Afficher le code</button> ';
// Option 1: show code for current logged-in user without re-supplying email
echo '<button type="button" id="btnShowSelf" class="button" style="margin-left:8px;">Afficher mon code</button> ';
echo '<button type="submit" name="action" value="regen" class="button-primary">Régénérer le code</button>';
echo '</form>';
echo '<div id="codeResult" style="margin-top:12px;color:#0f172a;font-weight:700;"></div>';
echo '</div>';

echo '<p><a href="index.php?modfunc=logout">Se déconnecter</a></p>';

PopTable( 'footer' );

?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('btnShow');
    if(!btn) return;
    btn.addEventListener('click', function(){
        var email = document.getElementById('email_for_code').value;
        if(!email) return alert('Veuillez saisir un e-mail.');
        var body = 'ajax_action=get_login_code&email=' + encodeURIComponent(email);
        fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
            .then(function(r){ return r.json(); })
            .then(function(data){
                var out = document.getElementById('codeResult');
                if(data.code) out.textContent = 'Code pour ' + email + ' : ' + data.code;
                else out.textContent = 'Aucun code trouvé pour cet e-mail.';
            }).catch(function(){ alert('Erreur réseau'); });
    });
    var btnSelf = document.getElementById('btnShowSelf');
    if(btnSelf){
        btnSelf.addEventListener('click', function(){
            var out = document.getElementById('codeResult');
            out.textContent = '';
            fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'ajax_action=get_my_login_code' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data.code) out.textContent = 'Votre code : ' + data.code;
                    else out.textContent = 'Aucun code trouvé pour votre compte.';
                }).catch(function(){ alert('Erreur réseau'); });
        });
    }
});
</script>