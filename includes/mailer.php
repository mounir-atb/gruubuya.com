<?php
declare(strict_types=1);

/**
 * Minimal dependency-free SMTP client (implicit SSL, AUTH LOGIN) so email
 * works on cPanel without Composer. Returns true on success; failures are
 * logged via error_log and reported as false.
 */
function send_mail(string $toEmail, string $toName, string $subject, string $html): bool
{
    $host   = (string) cfg('mail.host');
    $port   = (int) cfg('mail.port', 465);
    $secure = (string) cfg('mail.secure', 'ssl');
    $user   = (string) cfg('mail.user');
    $pass   = (string) cfg('mail.pass');
    $from   = (string) cfg('mail.from');
    $fromNm = (string) cfg('mail.from_name', 'Gruubuya');

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp     = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        error_log("SMTP connect failed ($remote): $errstr");
        return false;
    }
    stream_set_timeout($fp, 15);

    $readReply = function () use ($fp): string {
        $reply = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $reply;
    };
    $cmd = function (?string $command, string $expectCode) use ($fp, $readReply): bool {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        $reply = $readReply();
        if (!str_starts_with($reply, $expectCode)) {
            error_log('SMTP unexpected reply to [' . ($command ?? '<greeting>') . ']: ' . trim($reply));
            return false;
        }
        return true;
    };

    $heloHost = parse_url((string) cfg('app.url', 'https://localhost'), PHP_URL_HOST) ?: 'localhost';

    $toHeader = $toName !== ''
        ? '=?UTF-8?B?' . base64_encode($toName) . "?= <$toEmail>"
        : $toEmail;
    $headers = implode("\r\n", [
        'From: =?UTF-8?B?' . base64_encode($fromNm) . "?= <$from>",
        "To: $toHeader",
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $heloHost . '>',
    ]);
    $body = chunk_split(base64_encode($html), 76, "\r\n");

    $ok = $cmd(null, '220')
        && $cmd('EHLO ' . $heloHost, '250')
        && $cmd('AUTH LOGIN', '334')
        && $cmd(base64_encode($user), '334')
        && $cmd(base64_encode($pass), '235')
        && $cmd("MAIL FROM:<$from>", '250')
        && $cmd("RCPT TO:<$toEmail>", '250')
        && $cmd('DATA', '354')
        && $cmd($headers . "\r\n\r\n" . $body . "\r\n.", '250');

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

/** Branded HTML email wrapper: white card, purple button. */
function mail_template(string $heading, string $intro, ?string $btnText, ?string $btnUrl, string $footnote = ''): string
{
    $appName = e((string) cfg('app.name', 'Gruubuya'));
    $button  = '';
    if ($btnText !== null && $btnUrl !== null) {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto;"><tr><td style="border-radius:10px;background:#7c3aed;">'
            . '<a href="' . e($btnUrl) . '" style="display:inline-block;padding:13px 32px;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:10px;">'
            . e($btnText) . '</a></td></tr></table>'
            . '<p style="margin:0 0 8px;font-size:12px;color:#9ca3af;text-align:center;word-break:break-all;">Or paste this link into your browser:<br>'
            . '<a href="' . e($btnUrl) . '" style="color:#7c3aed;">' . e($btnUrl) . '</a></p>';
    }
    $foot = $footnote !== ''
        ? '<p style="margin:20px 0 0;font-size:12px;color:#9ca3af;text-align:center;">' . e($footnote) . '</p>'
        : '';
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f5f3ff;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ff;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;padding:36px 32px;font-family:Arial,sans-serif;">'
        . '<tr><td align="center" style="padding-bottom:18px;font-size:22px;font-weight:bold;color:#7c3aed;">' . $appName . '</td></tr>'
        . '<tr><td align="center" style="font-size:19px;font-weight:bold;color:#111827;padding-bottom:12px;">' . e($heading) . '</td></tr>'
        . '<tr><td style="font-size:14px;line-height:1.6;color:#4b5563;text-align:center;">' . $intro . '</td></tr>'
        . '<tr><td>' . $button . $foot . '</td></tr>'
        . '</table>'
        . '<p style="font-family:Arial,sans-serif;font-size:12px;color:#9ca3af;margin-top:20px;">&copy; ' . date('Y') . ' ' . $appName . '</p>'
        . '</td></tr></table></body></html>';
}

function send_verification_email(array $user, string $rawToken): bool
{
    $url = rtrim((string) cfg('app.url'), '/') . '/verify.php?token=' . $rawToken;
    return send_mail(
        $user['email'],
        $user['display_name'] ?: $user['username'],
        'Verify your Gruubuya email',
        mail_template(
            'Verify your email',
            'Hi <b>' . e($user['display_name'] ?: $user['username']) . '</b>,<br>'
            . 'welcome to Gruubuya! Click the button below to verify your email address.',
            'Verify Email',
            $url,
            'This link expires in 24 hours. If you did not create this account, you can ignore this email.'
        )
    );
}

function send_reset_email(array $user, string $rawToken): bool
{
    $url = rtrim((string) cfg('app.url'), '/') . '/reset.php?token=' . $rawToken;
    return send_mail(
        $user['email'],
        $user['display_name'] ?: $user['username'],
        'Reset your Gruubuya password',
        mail_template(
            'Reset your password',
            'Hi <b>' . e($user['display_name'] ?: $user['username']) . '</b>,<br>'
            . 'we received a request to reset your password. Click the button below to choose a new one.',
            'Reset Password',
            $url,
            'This link expires in 1 hour. If you did not request this, you can ignore this email.'
        )
    );
}
