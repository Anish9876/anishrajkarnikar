<?php
/**
 * contact.php
 * Handles submissions from the "Get in touch" form and emails them to the owner.
 *
 * SETUP:
 * 1. Upload this file to the SAME server/folder as your index.html
 *    (e.g. https://yourdomain.com/contact.php)
 * 2. In index.html, point the form's fetch() call at this file's URL.
 * 3. Most shared hosting (cPanel, etc.) has PHP's mail() working out of the box.
 *    If emails don't arrive, check your spam folder first, then ask your host
 *    whether mail() is enabled / whether you should use SMTP instead (see note
 *    at the bottom of this file).
 */

// ----- Basic config -----
$recipient_email = "anish.rajkarnikar4@gmail.com";
$site_name        = "Anish Rajkarnikar Portfolio";

// ----- CORS (only needed if the form is hosted on a different domain than this script) -----
header("Access-Control-Allow-Origin: *"); // for tighter security, replace * with your exact site URL
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight request, nothing else to do
    http_response_code(204);
    exit;
}

header("Content-Type: application/json");

// ----- Only allow POST -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit;
}

// ----- Collect + sanitize input -----
function clean($value) {
    $value = trim($value ?? "");
    // Strip anything that could be used for email header injection
    $value = str_replace(["\r", "\n", "%0a", "%0d"], "", $value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$from_email = clean($_POST['email'] ?? '');
$subject    = clean($_POST['subject'] ?? '');
$message    = clean($_POST['message'] ?? '');
$honeypot   = trim($_POST['company'] ?? ''); // hidden field — real users never fill this in

// ----- Spam trap: if the honeypot field has anything in it, silently reject -----
if ($honeypot !== '') {
    http_response_code(200); // pretend success so bots don't learn anything
    echo json_encode(["success" => true]);
    exit;
}

// ----- Validate -----
$errors = [];

if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
}
if ($subject === '') {
    $errors[] = "Subject is required.";
}
if (strlen($subject) > 200) {
    $errors[] = "Subject is too long.";
}
if (strlen($message) > 5000) {
    $errors[] = "Message is too long.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
    exit;
}

// ----- Build the email -----
$mail_subject = "[$site_name] " . $subject;

$mail_body  = "You received a new message from your portfolio site.\n\n";
$mail_body .= "From: $from_email\n";
$mail_body .= "Subject: $subject\n\n";
$mail_body .= "Message:\n";
$mail_body .= ($message !== '' ? $message : "(no message provided)") . "\n";

// Headers — From/Reply-To use a no-reply address on your own domain when possible,
// since some mail servers reject mail claiming to be "From" an address they don't own.
// Replace no-reply@yourdomain.com with a real address on your hosting domain.
$headers  = "From: no-reply@yourdomain.com\r\n";
$headers .= "Reply-To: $from_email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// ----- Send -----
$sent = mail($recipient_email, $mail_subject, $mail_body, $headers);

if ($sent) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Message sent successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "The server could not send the email. Please try again later."]);
}

/**
 * NOTE ON RELIABILITY:
 * PHP's built-in mail() function depends on your hosting server being configured
 * to send mail, and many providers (or spam filters on the receiving end) treat
 * mail() output as low-trust. If messages aren't arriving reliably, switch to
 * SMTP via PHPMailer (https://github.com/PHPMailer/PHPMailer) using your own
 * email provider's SMTP credentials (Gmail, Zoho, your hosting's SMTP, etc.) —
 * this is the more reliable option for production sites.
 */
