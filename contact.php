<?php
/**
 * Nate Hall Insurance contact form handler (Hostinger / any PHP host).
 *
 * Emails each quote request to $TO. The visitor's email is set as Reply-To,
 * so hitting "Reply" in Gmail answers them directly.
 *
 * Responds with JSON when called from js/main.js, or redirects to
 * thanks.html when the browser posts the form without JavaScript.
 */

// ---- Settings -------------------------------------------------------------
$TO   = 'natehallinsurance@gmail.com';
// Must be an address on your own domain, or many mail servers will treat the
// message as spam. It doesn't need to be a real inbox you check.
$FROM = 'no-reply@natehallinsurance.com';
$SITE = 'Nate Hall Insurance website';
// Max submissions per visitor (by IP address) per hour, to slow down spam.
$RATE_LIMIT = 3;
// Fastest a person can fill out the form, in seconds.
$MIN_SECONDS = 3;
// Private folder next to public_html (not reachable from the web). Holds
// config.php with the Turnstile secret key, and the spam rejection log.
$PRIVATE_DIR = dirname(__DIR__) . '/nhi-private';
// Turnstile: the action set in js/main.js, and the site addresses a token may come from.
$TURNSTILE_ACTION = 'contact';
$TURNSTILE_HOSTNAMES = ['www.natehallinsurance.com', 'natehallinsurance.com'];
// Time zone used for the "Sent" time in each email (Hartford, SD is Central).
date_default_timezone_set('America/Chicago');
// ---------------------------------------------------------------------------

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $message, $status = 200)
{
    global $wantsJson;
    http_response_code($status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } elseif ($ok) {
        header('Location: thanks.html', true, 303);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Message not sent</title><body style="font-family:system-ui,sans-serif;max-width:40em;margin:3em auto;padding:0 16px">'
           . '<h1>Sorry, your message wasn\'t sent</h1><p>' . htmlspecialchars($message) . '</p>'
           . '<p><a href="index.html#contact">Go back to the form</a> or call <a href="tel:+16053215367">605-321-5367</a>.</p></body>';
    }
    exit;
}

// Removes line breaks so a value can't add extra email headers.
function one_line($value, $max)
{
    $value = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function field($name, $max = 200)
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? one_line($_POST[$name], $max) : '';
}

// Spam is answered exactly like a real submission so bots learn nothing.
// The reason goes to a private log file, never to the browser.
function reject_spam($reason)
{
    global $PRIVATE_DIR, $ip;
    $line = date('Y-m-d H:i:s T') . "\t" . $ip . "\t" . one_line($reason, 200) . "\n";
    if (!is_dir($PRIVATE_DIR)) @mkdir($PRIVATE_DIR, 0700, true);
    if (!@file_put_contents($PRIVATE_DIR . '/form-rejections.log', $line, FILE_APPEND | LOCK_EX)) {
        error_log('Contact form spam rejected: ' . trim($line));
    }
    respond(true, 'Thanks! Your request was sent.');
}

function turnstile_config()
{
    global $PRIVATE_DIR, $TURNSTILE_HOSTNAMES;
    $config = [];
    $error = '';
    $file = $PRIVATE_DIR . '/config.php';
    if (is_readable($file)) {
        ob_start();
        try {
            $loaded = include $file;
        } catch (Throwable $e) {
            $loaded = null;
            error_log('Contact form: could not read config.php: ' . $e->getMessage());
        }
        ob_end_clean();
        if (is_array($loaded)) $config = $loaded;
        else $error = 'nhi-private/config.php has a typo, so the Turnstile secret key could not be read';
    }
    $secret = getenv('TURNSTILE_SECRET_KEY');
    if (!is_string($secret) || $secret === '') {
        $secret = isset($config['turnstile_secret']) ? (string) $config['turnstile_secret'] : '';
    }
    $hosts = getenv('TURNSTILE_HOSTNAMES');
    if (is_string($hosts) && trim($hosts) !== '') {
        $hosts = explode(',', $hosts);
    } elseif (isset($config['turnstile_hostnames']) && is_array($config['turnstile_hostnames'])) {
        $hosts = $config['turnstile_hostnames'];
    } else {
        $hosts = $TURNSTILE_HOSTNAMES;
    }
    $hosts = array_values(array_filter(array_map(function ($h) { return strtolower(trim((string) $h)); }, $hosts)));
    return ['secret' => trim($secret), 'hostnames' => $hosts, 'error' => $error];
}

// Returns [status, detail]. Status is 'pass', 'fail', or 'unknown' (Cloudflare couldn't be reached).
function turnstile_verify($secret, $token, $ip, $hostnames, $action)
{
    $data = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $data,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]));
    }
    $result = is_string($raw) ? json_decode($raw, true) : null;
    return turnstile_judge($result, $hostnames, $action);
}

function turnstile_judge($result, $hostnames, $action)
{
    if (!is_array($result)) return ['unknown', 'Cloudflare could not be reached to check it'];
    if (empty($result['success'])) {
        $codes = isset($result['error-codes']) ? (array) $result['error-codes'] : [];
        if (in_array('internal-error', $codes, true)) return ['unknown', 'Cloudflare could not be reached to check it'];
        if (array_intersect(['invalid-input-secret', 'missing-input-secret'], $codes)) {
            return ['unknown', 'the Turnstile secret key in nhi-private/config.php is wrong'];
        }
        return ['fail', 'Turnstile token rejected (' . implode(', ', $codes) . ')'];
    }
    $gotAction = isset($result['action']) ? (string) $result['action'] : '';
    if ($gotAction !== $action) return ['fail', "Turnstile action was \"$gotAction\", expected \"$action\""];
    $gotHost = isset($result['hostname']) ? strtolower((string) $result['hostname']) : '';
    if (!in_array($gotHost, $hostnames, true)) return ['fail', "Turnstile hostname \"$gotHost\" is not allowed"];
    return ['pass', ''];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(false, 'This address only accepts form submissions.', 405);
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

if (isset($_POST['company_website']) && $_POST['company_website'] !== '') {
    reject_spam('honeypot filled');
}

// form_loaded and form_sent are set by js/main.js from the visitor's own clock.
$loaded = isset($_POST['form_loaded']) && is_string($_POST['form_loaded']) ? $_POST['form_loaded'] : '';
$sentAt = isset($_POST['form_sent']) && is_string($_POST['form_sent']) ? $_POST['form_sent'] : '';
if (!ctype_digit($loaded) || !ctype_digit($sentAt)) {
    reject_spam('missing page-load time');
}
$elapsed = ((int) $sentAt - (int) $loaded) / 1000;
if ($elapsed < $MIN_SECONDS) {
    reject_spam(sprintf('submitted %.1f seconds after page load', $elapsed));
}

$token = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response'])
    ? trim($_POST['cf-turnstile-response']) : '';
$widgetFailed = isset($_POST['turnstile_status']) && $_POST['turnstile_status'] === 'unavailable';
if ($token === '' && !$widgetFailed) {
    reject_spam('no Turnstile token');
}
if (strlen($token) > 2048) {
    reject_spam('Turnstile token too long');
}

// Simple per-IP rate limit stored in the server's temp folder.
$rateFile = rtrim(sys_get_temp_dir(), '/') . '/nhi_form_' . md5($ip);
$now = time();
$recent = [];
if (is_readable($rateFile)) {
    foreach ((array) json_decode((string) @file_get_contents($rateFile), true) as $t) {
        if (is_int($t) && $t > $now - 3600) $recent[] = $t;
    }
}
if (count($recent) >= $RATE_LIMIT) {
    reject_spam("hourly limit of $RATE_LIMIT submissions reached");
}

$first    = field('first-name', 100);
$last     = field('last-name', 100);
$email    = field('email', 254);
$phone    = field('phone', 40);
$zip      = field('zip', 10);
$meeting  = field('meeting', 40);
$comments = isset($_POST['comments']) && is_string($_POST['comments'])
    ? trim(substr(str_replace("\r", '', $_POST['comments']), 0, 5000)) : '';

$allowed = ['Life insurance', 'Annuities & retirement', 'Medicare', 'Health insurance', 'Cancer & critical illness', 'Long-term care'];
$interests = [];
if (isset($_POST['interests']) && is_array($_POST['interests'])) {
    foreach ($_POST['interests'] as $i) {
        if (is_string($i) && in_array($i, $allowed, true)) $interests[] = $i;
    }
}
if (!in_array($meeting, ['In person', 'Phone', 'Virtual'], true)) $meeting = '';

if ($first === '' || $last === '') {
    respond(false, 'Please enter your first and last name.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}
if ($zip !== '' && !preg_match('/^\d{5}$/', $zip)) {
    respond(false, 'Please enter a 5-digit ZIP code.', 422);
}

if (preg_match('~https?://|www\.|\[url|<a\s~i', $comments)) {
    reject_spam('link in comments');
}
$text = "$first $last $comments";
$letters = preg_match_all('/\p{L}/u', $text);
$latin = preg_match_all('/\p{Latin}/u', $text);
if ($letters > 0 && $latin / $letters < 0.5) {
    reject_spam('mostly non-Latin text');
}
$sameName = function_exists('mb_strtolower')
    ? mb_strtolower($first, 'UTF-8') === mb_strtolower($last, 'UTF-8')
    : strtolower($first) === strtolower($last);
if ($sameName) {
    reject_spam('first name equals last name');
}

$verified = false;
$unverifiedWhy = 'the spam check did not load in the visitor\'s browser';
if ($token !== '') {
    $turnstile = turnstile_config();
    if ($turnstile['secret'] === '') {
        $unverifiedWhy = $turnstile['error'] !== '' ? $turnstile['error'] : 'the Turnstile secret key is not set up on the server';
    } else {
        try {
            list($check, $detail) = turnstile_verify($turnstile['secret'], $token, $ip, $turnstile['hostnames'], $TURNSTILE_ACTION);
        } catch (Throwable $e) {
            error_log('Contact form: Turnstile check failed: ' . $e->getMessage());
            list($check, $detail) = ['unknown', 'Cloudflare could not be reached to check it'];
        }
        if ($check === 'fail') reject_spam($detail);
        if ($check === 'pass') $verified = true;
        else $unverifiedWhy = $detail;
    }
}

$name = "$first $last";
$body = "New quote request from the $SITE\n"
      . str_repeat('-', 40) . "\n"
      . "Name:       $name\n"
      . "Email:      $email\n"
      . "Phone:      " . ($phone !== '' ? $phone : '(not given)') . "\n"
      . "ZIP:        " . ($zip !== '' ? $zip : '(not given)') . "\n"
      . "Interested: " . ($interests ? implode(', ', $interests) : '(none checked)') . "\n"
      . "Meet by:    " . ($meeting !== '' ? $meeting : '(no preference)') . "\n"
      . "\nComments:\n" . ($comments !== '' ? $comments : '(none)') . "\n"
      . str_repeat('-', 40) . "\n"
      . "Sent " . date('M j, Y g:i A T') . ". Reply to this email to answer $first directly.\n"
      . ($verified ? '' : "\nUNVERIFIED: $unverifiedWhy. It may still be a real person, but look it over before replying.\n");

// Keep only normal name characters in the email header (letters, spaces, . ' -).
$replyName = trim(preg_replace('/[^\p{L}\p{M} .\'-]+/u', '', $name));
if ($replyName === '') $replyName = 'Website visitor';
$subject = '=?UTF-8?B?' . base64_encode(($verified ? '' : '[UNVERIFIED] ') . "New quote request: $name") . '?=';
$headers = [
    'From: ' . $SITE . ' <' . $FROM . '>',
    'Reply-To: ' . (preg_match('/[^\x20-\x7E]/', $replyName)
        ? '=?UTF-8?B?' . base64_encode($replyName) . '?='
        : '"' . $replyName . '"') . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP',
];

$sent = @mail($TO, $subject, $body, implode("\r\n", $headers), '-f' . $FROM);
if (!$sent) {
    respond(false, 'The server could not send your message right now. Please try again or give me a call.', 500);
}

$recent[] = $now;
@file_put_contents($rateFile, json_encode($recent), LOCK_EX);
respond(true, 'Thanks! Your request was sent.');
