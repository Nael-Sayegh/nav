<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once(__DIR__.'/../vendor/autoload.php');

function sendMail($recipients, string $subject, string $contentHtml, string $contentText, $replyTo = null, array $options = [])
{
    global $site_name;
    $css = $options['css'] ?? '';
    $includeNotice = $options['includeAutoReplyNotice'] ?? true;
    $logoUrl = SITE_URL.'/images/logo128-170.png';
    $styleBlock = $css
        ? <<<HTML
            <style>
            {$css}
            </style>
            HTML
        : '';

    $headerHtml = <<<HTML
        <header style="text-align:center; padding:1em 0;">
        <h1 style="margin:0;">{$site_name}</h1>
        <img src="{$logoUrl}" alt="Logo" style="max-height:60px;">
        </header>
        HTML;
    $noticeHtml = $includeNotice
        ? <<<HTML
            <p style="font-size:.9em; color:#666;">Ne répondez pas à ce mail qui a été envoyé automatiquement.<br>
            Pour nous contacter, écrivez nous à l'adresse <a href="mailto:infos@nael-accessvision.com">infos@nael-accessvision.com</a>.</p>
            HTML
        : '';

    $footerHtml = <<<HTML
        <footer style="margin-top:2em; border-top:1px solid #ddd; padding-top:1em; font-size:.9em; color:#333;">
        {$noticeHtml}
        <p style="margin:0;">Cordialement,<br>
        {$site_name}</p>
        </footer>
        HTML;

    $fullHtml = <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
        <meta charset="UTF-8">
        <title>{$subject}</title>
        {$styleBlock}
        </head>
        <body style="font-family:Arial, sans-serif; margin:0; padding:1em;">
        {$headerHtml}
        <main style="margin:2em 0;">
        {$contentHtml}
        </main>
        {$footerHtml}
        </body>
        </html>
        HTML;

    $underline = str_repeat('=', mb_strlen((string) $site_name));
    $textHeader = <<<TEXT
        {$site_name}
        {$underline}

        TEXT;

    $noticeText = $includeNotice
        ? <<<TEXT
            Ne répondez pas à ce mail qui a été envoyé automatiquement.
            Pour nous contacter, écrivez nos à : infos@nael-accessvision.com

            TEXT
        : '';

    $footerText = <<<TEXT
        Cordialement,
        {$site_name}
        TEXT;

    $altBody = <<<TEXT
        {$textHeader}{$contentText}

        {$noticeText}{$footerText}
        TEXT;

    $replyAddress = $replyTo === null
        ? SMTP_MAIL
        : (is_array($replyTo) ? $replyTo[0] : $replyTo);
    $replyName = $replyTo === null
        ? SMTP_NAME
        : (is_array($replyTo) ? ($replyTo[1] ?? '') : '');

    $sent = true;

    $sent = true;

    foreach ((array)$recipients as $dest)
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PSW;
        $mail->setFrom(SMTP_MAIL, SMTP_NAME);
        $mail->addReplyTo($replyAddress, $replyName);
        $mail->addAddress($dest);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = '['.$site_name.'] '.$subject;
        $mail->Body = str_replace('{SITE_URL}', SITE_URL, $fullHtml);
        $mail->AltBody = str_replace('{SITE_URL}', SITE_URL, $altBody);

        if (!$mail->send())
        {
            $sent = false;
            error_log("Failed to sent to $dest: ".$mail->ErrorInfo);
        }
    }

    return $sent;
}
