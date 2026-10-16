<?php
// Email settings. Fill these in. Don't commit real credentials to a public repo.
if (!defined("MAIL_FROM"))       define("MAIL_FROM", "no-reply@carepulse.local");
if (!defined("MAIL_FROM_NAME"))  define("MAIL_FROM_NAME", "CarePulse — Malabon District II Health Centers");
if (!defined("MAIL_SMTP_HOST"))  define("MAIL_SMTP_HOST", "smtp.gmail.com");
if (!defined("MAIL_SMTP_PORT"))  define("MAIL_SMTP_PORT", 587);
if (!defined("MAIL_SMTP_USER"))  define("MAIL_SMTP_USER", "");   // e.g. your Gmail address
if (!defined("MAIL_SMTP_PASS"))  define("MAIL_SMTP_PASS", "");   // Gmail app password
if (!defined("APP_BASE_URL"))    define("APP_BASE_URL", "http://localhost/your-project-folder"); // used in email links

// Uses PHPMailer + SMTP when installed (composer require phpmailer/phpmailer) and
// MAIL_SMTP_USER is set; otherwise falls back to PHP's mail().
if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    require_once __DIR__ . "/vendor/autoload.php";
}

function build_email_html($message, $buttonUrl = null, $buttonLabel = null) {
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#131b2e">'
          . '<h2 style="color:#00685f;margin:0 0 12px">CarePulse</h2>'
          . '<p style="font-size:15px;line-height:1.5">' . htmlspecialchars($message) . '</p>';
    if ($buttonUrl) {
        $html .= '<p><a href="' . htmlspecialchars($buttonUrl) . '" style="display:inline-block;background:#00685f;'
               . 'color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold">'
               . htmlspecialchars($buttonLabel ?: "Open") . '</a></p>';
    }
    $html .= '<p style="font-size:12px;color:#6d7a77">Malabon District II Health Centers. This is an automated message.</p></div>';
    return $html;
}

function send_email($to, $subject, $htmlBody) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    try {
        if (class_exists("PHPMailer\\PHPMailer\\PHPMailer") && MAIL_SMTP_USER !== "") {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_SMTP_USER;
            $mail->Password = MAIL_SMTP_PASS;
            $mail->SMTPSecure = "tls";
            $mail->Port = MAIL_SMTP_PORT;
            $mail->setFrom(MAIL_FROM_NAME ? MAIL_SMTP_USER : MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            return $mail->send();
        }
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        return mail($to, $subject, $htmlBody, $headers);
    } catch (Throwable $e) {
        error_log("send_email failed: " . $e->getMessage());
        return false; // a mail failure should never break booking
    }
}

// Same as create_notification(), but also emails the same message so the
// website notification and the email always say the same thing.
function notify_with_email($conn, $username, $message, $apptId = null, $buttonUrl = null, $buttonLabel = null) {
    create_notification($conn, $username, $message, $apptId);

    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row["email"])) {
        send_email($row["email"], "CarePulse notification", build_email_html($message, $buttonUrl, $buttonLabel));
    }
}
