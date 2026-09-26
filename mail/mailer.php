<?php
/**
 * RideFlow Mail System
 * Uses PHPMailer for SMTP when configured, falls back to PHP mail().
 * PHPMailer installed via Composer.
 */
if (!defined('RF_ROOT')) define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/vendor/autoload.php';
require_once RF_ROOT.'/config/config.php';

/**
 * Send an email using the configured method.
 * Falls back to PHP mail() when PHPMailer is not installed or SMTP is unconfigured.
 */
function rf_mail(string $to, string $toName, string $subject, string $htmlBody): bool {
    if (MAIL_ENABLED && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return rf_mail_smtp($to, $toName, $subject, $htmlBody);
    }
    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ".MAIL_FROM_NAME." <".MAIL_FROM.">\r\n";
    $headers .= "X-Mailer: RideFlow\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
}

function rf_mail_smtp(string $to, string $toName, string $subject, string $htmlBody): bool {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('RF Mail Error: '.$e->getMessage());
        return false;
    }
}

/** Base HTML template */
function mail_template(string $title, string $content, string $cta='', string $ctaUrl=''): string {
    $ctaBtn = $cta ? "<div style='text-align:center;margin:28px 0'><a href='$ctaUrl' style='display:inline-block;background:#ff6a00;color:#ffffff;text-decoration:none;padding:13px 32px;border-radius:8px;font-weight:700;font-size:15px;font-family:sans-serif'>$cta</a></div>" : '';
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0c0d10;font-family:'DM Sans','Segoe UI',sans-serif">
<div style="max-width:580px;margin:0 auto;padding:32px 16px">
  <div style="text-align:center;margin-bottom:28px">
    <div style="display:inline-block;background:#ff6a00;border-radius:14px;padding:10px 18px">
      <span style="font-size:20px;font-weight:900;color:#fff;letter-spacing:-.5px">Ride<span style="opacity:.85">Flow</span></span>
    </div>
  </div>
  <div style="background:#14161b;border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden">
    <div style="background:linear-gradient(135deg,#ff6a00,#e55c00);padding:28px 32px">
      <h1 style="margin:0;font-size:22px;font-weight:700;color:#fff;font-family:Georgia,serif">$title</h1>
    </div>
    <div style="padding:32px;color:#b8b4ac;font-size:15px;line-height:1.7">
      $content
      $ctaBtn
    </div>
    <div style="background:#1e2128;padding:20px 32px;font-size:12px;color:#4a4740;border-top:1px solid rgba(255,255,255,.06)">
      <p style="margin:0">This email was sent by <strong style="color:#7a7670">RideFlow</strong> · Sri Lanka Transport Booking</p>
      <p style="margin:6px 0 0">If you didn't request this, you can safely ignore it.</p>
    </div>
  </div>
</div>
</body></html>
HTML;
}

/* ── Email senders ─────────────────────────────────────────── */

function mail_send_verify(string $to, string $name, string $link): bool {
    $content = "<p>Hi <strong style='color:#f0ede6'>$name</strong>,</p>
<p>Thanks for joining RideFlow! Please verify your email address to activate your account.</p>
<p>This link expires in <strong style='color:#f0ede6'>24 hours</strong>.</p>";
    return rf_mail($to, $name,
        'Verify your RideFlow email address',
        mail_template('Verify Your Email', $content, 'Verify Email Address', $link));
}

function mail_send_reset(string $to, string $name, string $link): bool {
    $content = "<p>Hi <strong style='color:#f0ede6'>$name</strong>,</p>
<p>We received a request to reset your RideFlow password. Click the button below to choose a new one.</p>
<p>This link expires in <strong style='color:#f0ede6'>2 hours</strong>. If you didn't request this, please ignore this email.</p>";
    return rf_mail($to, $name,
        'Reset your RideFlow password',
        mail_template('Password Reset Request', $content, 'Reset My Password', $link));
}

function mail_send_booking_confirm(string $to, string $name, array $booking): bool {
    $ref  = htmlspecialchars($booking['booking_ref']);
    $orig = htmlspecialchars($booking['origin']);
    $dest = htmlspecialchars($booking['destination']);
    $date = date('D, M j, Y', strtotime($booking['departure_date']));
    $time = substr($booking['departure_time'], 0, 5);
    $fare = 'Rs.'.number_format($booking['total_fare'], 2);
    $link = SITE_URL.'/user/ticket.php?booking='.$booking['id'];
    $content = "<p>Hi <strong style='color:#f0ede6'>$name</strong>,</p>
<p>Your booking is confirmed! Here are your trip details:</p>
<table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
  <tr><td style='padding:8px 0;color:#7a7670;border-bottom:1px solid rgba(255,255,255,.06)'>Booking Ref</td><td style='padding:8px 0;color:#ff6a00;font-weight:700;font-family:monospace'>$ref</td></tr>
  <tr><td style='padding:8px 0;color:#7a7670;border-bottom:1px solid rgba(255,255,255,.06)'>Route</td><td style='padding:8px 0;color:#f0ede6;font-weight:600'>$orig → $dest</td></tr>
  <tr><td style='padding:8px 0;color:#7a7670;border-bottom:1px solid rgba(255,255,255,.06)'>Date</td><td style='padding:8px 0;color:#f0ede6'>$date at $time</td></tr>
  <tr><td style='padding:8px 0;color:#7a7670'>Total Fare</td><td style='padding:8px 0;color:#22c55e;font-weight:700'>$fare</td></tr>
</table>
<p>Show your QR code ticket to the conductor at departure.</p>";
    return rf_mail($to, $name,
        "Booking Confirmed — $ref",
        mail_template('Booking Confirmed ✓', $content, 'View Your E-Ticket', $link));
}

function mail_send_newsletter(string $to, string $name, string $subject, string $bodyHtml): bool {
    return rf_mail($to, $name, $subject, $bodyHtml);
}

function mail_send_contact_reply(string $to, string $name, string $replyMsg): bool {
    $content = "<p>Hi <strong style='color:#f0ede6'>$name</strong>,</p>
<p>Thank you for contacting RideFlow. Here's our response:</p>
<blockquote style='border-left:3px solid #ff6a00;margin:16px 0;padding:12px 16px;background:rgba(255,106,0,.05);color:#f0ede6;border-radius:0 8px 8px 0'>$replyMsg</blockquote>
<p>If you have further questions, please don't hesitate to reach out again.</p>";
    return rf_mail($to, $name, 'RE: Your RideFlow Enquiry', mail_template('Support Reply', $content));
}
