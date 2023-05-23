<?php

/**
  * @package local_jwt_auth
  * @author oLab Inc
  * @license https://www.gnu.org/licenses/gpl-3.0.en.html
  */

if ( ! defined('MOODLE_INTERNAL') )
    exit; // prevent direct access

require_once __DIR__ . '/config.php';

function jwt_auth_get_current_user_jwt_payload() : array
{
    global $USER;

    $user_roles = [];

    foreach ( $all_roles=get_all_roles() as $role ) {
        if ( user_has_role_assignment($USER->id, $role->id) ) {
            $user_roles []= $role->shortname;
        }
    }

    if ( 0 == count($user_roles) && 1 == $USER->id ) { // guest user
        foreach ( $all_roles as $role ) {
            if ( 'guest' == strtolower($role->shortname ?? '') ) {
                $user_roles []= $role->shortname;
                break;
            }
        }
    }

    return [
        'unique_name' => trim($USER->username ?? ''), // user login
        'id' => strval($USER->id), // moodle user id
        'email' => $USER->email ?? '', // user email address
        'role' => join(',', $user_roles), // user roles (csv), if any (@see https://moodle.olab.ca/user/index.php?id=1)
        'iat' => time(), // issuance time (epoch, UTC)
        'exp' => time() + 60*60*24*10, // 10 days (epoch, UTC)
        'iss' => 'moodle',
        'aud' => 'https://www.olab.ca',
    ];
}

function jwt_auth_get_current_user_session_jwt( array $claims ) : string
{
    // generate jwt
    $jwt_header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $b64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwt_header));
    $b64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($claims)));
    $token_signature = hash_hmac('sha256', "{$b64_header}.{$b64_payload}", OLAB_JWT_PRIVATE_KEY, true);
    $b64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($token_signature));
    return join('.', [$b64_header, $b64_payload, $b64_signature]);
}

function local_jwt_auth_after_config() : void
{
    global $USER;

    parse_str($url=parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY), $qs);
    $courseid = intval($qs['id'] ?? null);
    $course = $courseid > 0 ? get_course($courseid) : new \stdClass;

    if ( ! isloggedin() ) { // no session attached
        // user just signed out, delete their token cookie if any
        if ( trim($_COOKIE[ OLAB_AUTH_JWT_COOKIE_NAME ] ?? '') ) {
            jwt_auth_set_token_cookie( ' ', time() - 31556926 ); // -1 year to expire the cookie
            unset($_COOKIE[ OLAB_AUTH_JWT_COOKIE_NAME ]);
        }
    } else { // moodle user logged in
        $user_payload = jwt_auth_get_current_user_jwt_payload();
        $user_payload['course'] = intval(jwt_auth_get_active_course_parent($course, true) ?: ($course->id ?? null));

        if ( $saved_jwt = ($_COOKIE[ OLAB_AUTH_JWT_COOKIE_NAME ] ?? '') ) {
            $saved_jwt = json_decode(base64_decode(explode('.', $saved_jwt)[1] ?? ''), 1) ?: [];

            // check if user session has changed (logins and logouts)
            $user_session_changed = ($saved_jwt['unique_name'] ?? null) != ($USER->username ?? null);

            // check if course context has changed
            $course_changed = ($saved_jwt['course'] ?? null) != $user_payload['course'];

            // attached cookie jwt reflects the current user, no need to keep updating the cookie and issuing a new token
            if ( ! $user_session_changed && ! $course_changed )
                return;
        }

        $jwt = jwt_auth_get_current_user_session_jwt( $user_payload );

        jwt_auth_set_token_cookie( $jwt, $user_payload['exp'] ); // expire on token expiration
    }
}

function jwt_auth_set_token_cookie( string $value, int $expires )
{
    return setcookie(
        OLAB_AUTH_JWT_COOKIE_NAME,
        $value,
        $expires,
        OLAB_AUTH_JWT_COOKIE_PARAMS['path'],
        OLAB_AUTH_JWT_COOKIE_PARAMS['domain'],
        OLAB_AUTH_JWT_COOKIE_PARAMS['secure'],
        OLAB_AUTH_JWT_COOKIE_PARAMS['httponly']
    );
}

function jwt_auth_get_active_course_parent( stdClass $refcourse, bool $return_id=false )
{
    global $PAGE, $CFG;
    require_once($CFG->dirroot.'/course/lib.php');

    if ( ! ( ($refcourse->id ?? 0) > 0 ) )
        return $return_id ? 0 : null;

    $courseformat = course_get_format($refcourse);
    $coursenode = $PAGE->navigation->find_active_node() ?: $refcourse;
    $targettype = navigation_node::TYPE_COURSE;

    // Single activity format has no course node - the course node is swapped for the activity node.
    if (!$courseformat->has_view_page()) {
        $targettype = navigation_node::TYPE_ACTIVITY;
    }

    while ( $coursenode->parent ?? null ) {
        $coursenode = $coursenode->parent;
    }

    return $return_id ? ($coursenode->id ?? 0) : $coursenode;
}

function local_jwt_auth_extend_navigation( global_navigation $nav )
{
    global $PAGE;

    // polyfill for URL API, see https://caniuse.com/url
    $PAGE->requires->js(new moodle_url('https://unpkg.com/url-polyfill@1.1.12/url-polyfill.min.js'), true);

    // script for syncing token with select iframes
    $PAGE->requires->js(new moodle_url('/local/jwt_auth/tokenize_iframes.js', [
        'hosts' => join(',', TOKENIZE_IFRAMES_WITH_HOST),
        'regexpr' => base64_encode('(^|\s)' . preg_quote(OLAB_AUTH_JWT_COOKIE_NAME) . '=([^;|$]+)'),
    ]), true);
}

function olab_local_course_section_updated_event_listener( $event )
{
    $data = $event->get_data();

    if ( 'course_sections' != ($data['objecttable'] ?? '') )
        return;

    if ( ! $record_id = intval($data['objectid'] ?? 0) )
        return;

    $html = $_POST['summary_editor']['text'] ?? '';

    if ( ! $doc = olab_local_remove_token_info_from_db_input($html) )
        return;

    olab_local_remove_token_info_from_db_input_apply(
        $data['objecttable'], $record_id, $doc, 'summary'
    );
}

function olab_local_course_module_updated_event_listener( $event )
{
    $data = $event->get_data();

    if ( 'course_modules' != ($data['objecttable'] ?? '') )
        return;

    if ( 'page' != ($data['other']['modulename'] ?? '') )
        return;

    if ( ! $instanceid = intval($data['other']['instanceid'] ?? 0) )
        return;

    foreach ( ['introeditor' => 'intro', 'page' => 'content'] as $location => $field ) {
        if ( ! $doc = olab_local_remove_token_info_from_db_input($_POST[$location]['text'] ?? '') )
            continue;

        olab_local_remove_token_info_from_db_input_apply(
            $data['other']['modulename'], $instanceid, $doc, $field
        );
    }
}

function olab_local_remove_token_info_from_db_input(string $input) : ?\DOMDocument
{
    if ( ! trim($input) )
        return null;

    libxml_use_internal_errors(true);
    $doc = new \DOMDocument;
    $doc->loadHTML($input, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new \DOMXPath($doc);
    $modified = false;

    foreach ( $xpath->evaluate('//iframe[@src]') as $frame ) {
        if ( ! $src = $frame->getAttribute('src') )
            continue;

        $url = parse_url($src);

        // check if it's one of our frame domains as we query all iframes with a src attribute
        if ( ! in_array(strtolower($url['host']), array_map('strtolower', TOKENIZE_IFRAMES_WITH_HOST)) )
            continue;

        if ( $class = $frame->getAttribute('class') ) {
            if ( $new_class = trim(str_replace('__tokenized', '', $class)) ) {
                $frame->setAttribute('class', $new_class);
            } else {
                $frame->removeAttribute('class');
            }

            $modified = true;
        }

        parse_str($url['query'] ?? '', $query);

        if ( $query['token'] ?? null ) {
            unset($query['token']);
            $qs = http_build_query($query);
            $new_src = join('', [$url['scheme'], '://', $url['host'], $url['path'], $qs ? "?{$qs}" : '']);
            $frame->setAttribute('src', $new_src);
            $modified = true;
        }
    }

    return $modified ? $doc : null;
}

function olab_local_remove_token_info_from_db_input_apply(string $table, int $record_id, \DOMDocument $doc, string $field) {
    global $DB;

    // load record object
    $record = $DB->get_record($table, ['id' => $record_id]);

    // save cleaned-up summary
    $record->$field = trim($doc->saveHtml());
    $record->timemodified = time();

    // update record
    $updated = $DB->update_record($table, $record);

    // refresh caches
    $updated && rebuild_course_cache($record->course, true);
}