<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../config.php';

$PAGE->set_url($page_url = new moodle_url('/local/jwt_auth/auth.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Authorize oLab');

if ( ! ( $USER->id ?? null ) ) { // no user logged in, redirect to login screen
    $SESSION->wantsurl = new moodle_url(strval($page_url), $_GET);
    return redirect(new moodle_url('/login/index.php'));
}

if ( isset( $_POST['cancel'] ) ) // auth cancelled
    return redirect(new moodle_url('/'));

// where to redirect next, with the bearer appended
$next_url = 'http://' . OLAB_AUTH_REDIRECT_ALLOW_HOSTS[0];

// validate url against whitelisted hosts
if ( $redirect_to = urldecode($_GET['callback_url'] ?? '') ) {
    $url = parse_url($redirect_to);
    $whitelisted = false;

    foreach ( OLAB_AUTH_REDIRECT_ALLOW_HOSTS as $host ) {
        if ( strtolower($url['host']) == strtolower($host) ) {
            $whitelisted = true;
            break;
        } else if ( ($url['port'] ?? '') && strtolower(join(':', [$url['host'], $url['port']])) == strtolower($host) ) {
            $whitelisted = true;
            break;
        }
    }

    if ( $whitelisted ) {
        $next_url = $redirect_to;
    }
}

if ( ! isset( $_POST['authorize_olab'] ) ) { // consent screen
    ob_start(); ?>
    <?php echo $OUTPUT->header(); ?>

    <form method="post">
        <h1>Authorize oLab</h1>
        <p>oLab is requesting access to your user information:</p>
        <ul>
            <li>First and last name</li>
            <li>Email address</li>
            <li>Account role</li>
        </ul>
        <p class="mt-4">
            <button type="submit" class="btn btn-primary" name="authorize_olab">Continue to <?php echo htmlentities(parse_url($next_url, PHP_URL_HOST)); ?></button>
            &nbsp;
            <button type="submit" class="btn" name="cancel">Cancel</button>
        </p>
    </form>

    <?php echo $OUTPUT->footer(); ?>

    <?php echo ob_get_clean();

    return;
}

return redirect(new moodle_url($next_url, [
    'bearer' => jwt_auth_get_current_user_session_jwt(jwt_auth_get_current_user_jwt_payload())
]));
