<?php
$nolog = true;
set_include_path($_SERVER['DOCUMENT_ROOT']);
$stats_page = 'mdpforget';
require_once('include/log.php');
require_once('include/consts.php');
require_once('include/sendMail.php');
$tr = load_tr($lang, 'fg_passwd');
$title = tr($tr, 'title');
$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? null;
$errors  = [];
$success = '';
if ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $requestedUser = trim((string) $_POST['login']);
    $SQL = <<<SQL
        SELECT id, email, username FROM accounts WHERE username=:user OR email=:user LIMIT 1
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':user' => $requestedUser]);
    $user = $req->fetch();
    if ($user)
    {
        $SQLDel = <<<SQL
            DELETE FROM password_resets WHERE user_id = :uid
            SQL;
        $reqDel = $bdd2->prepare($SQLDel);
        $reqDel->execute([':uid' => $user['id']]);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $SQLTok = <<<SQL
            INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :tok, :exp)
            SQL;
        $reqTok = $bdd2->prepare($SQLTok);
        $reqTok->execute([':uid' => $user['id'], ':tok' => $token, ':exp' => $expires]);
        $link = SITE_URL."/fg_password.php?step=reset&token={$token}";
        $subject = 'Réinitialisation de mot de passe';
        $body = <<<HTML
            <p>Bonjour {$user['username']},<br>
            Cliquez sur ce lien valable 1h pour choisir votre nouveau mot de passe&nbsp;:<br>
            <a href="{$link}">Choisir mon mot de passe</a>.</p>
            HTML;
        $altBody = <<<TEXT
            <p>Bonjour {$user['username']},
            Cliquez sur ce lien valable 1h pour choisir votre nouveau mot de passe:
            {$link}
            TEXT;
        sendMail($user['email'], $subject, $body, $altBody);
    }

    $success = tr($tr, 'mail_sent');
}

if ($step === 'reset')
{
    $SQL = <<<SQL
        SELECT pr.id AS pr_id, pr.user_id, a.username, a.password FROM password_resets pr JOIN accounts a ON a.id = pr.user_id WHERE pr.token = :token AND pr.expires_at > NOW() AND pr.used = FALSE LIMIT 1
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':token' => $token]);
    $reset = $req->fetch();

    if (!$reset)
    {
        $errors[] = tr($tr, 'invalid_or_expired');
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $currentHash = $reset['password'];
        $p1 = $_POST['password'];
        $p2 = $_POST['password2'];

        if ($p1 !== $p2)
        {
            $errors[] = tr($tr, 'pwd_mismatch');
        }
        elseif (strlen((string) $p1) < 8)
        {
            $errors[] = tr($tr, 'pwd_too_short');
        }
        elseif (password_verify((string) $p1, (string) $currentHash))
        {
            $errors[] = tr($tr, 'pwd_no_reuse');
        }
        else
        {
            $hash = password_hash((string) $p1, PASSWORD_DEFAULT);
            $SQLU = <<<SQL
                UPDATE accounts SET password = :h WHERE id = :uid
                SQL;
            $reqU = $bdd2->prepare($SQLU);
            $reqU->execute([':h' => $hash, ':uid' => $reset['user_id']]);
            $SQLU2 = <<<SQL
                UPDATE password_resets SET used = TRUE WHERE id = :prid
                SQL;
            $reqU2 = $bdd2->prepare($SQLU2);
            $reqU2->execute([':prid' => $reset['pr_id']]);

            $success = tr($tr, 'reset_success');
            header('Location: login.php?passwdreseted');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?php if ($success): ?>
<p class="success"><?php echo $success; ?></p>
<?php endif;
if ($errors):
    foreach ($errors as $e): ?>
<p role="alert" aria-live="assertive" class="error"><?php echo $e; ?></p>
<?php endforeach;
endif;
if ($step === 'request'): ?>
<p><?= tr($tr, 'intro_text', ['site' => $site_name]) ?></p>
<form method="post">
<label for="login"><?= tr($tr, 'login_field') ?></label>
<input type="text" name="login" id="login" required><br>
<button type="submit"><?= tr($tr, 'request_btn') ?></button>
</form>
<?php elseif ($step === 'reset' && $reset): ?>
<p><?= tr($tr, 'choose_new_pwd') ?></p>
<form method="post">
<label for="password"><?= tr($tr, 'new_password') ?></label>
<input type="password" name="password" id="password" autocomplete="new-password" required><br>
<button hidden type="button" id="btn-generate-psw"><?= tr($tr, 'gen-psw') ?></button><br>
<noscript>
<p><em><?= tr($tr, 'js-to-gen') ?></em></p>
</noscript>
<label for="password2"><?= tr($tr, 'confirm_password') ?></label>
<input type="password" name="password2" id="password2" autocomplete="new-password" required><br>
<button type="submit"><?= tr($tr, 'reset_btn') ?></button>
</form>
<?php endif; ?>
</main>
<?php require_once('include/footer.php'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const genBtn = document.getElementById('btn-generate-psw');
        if (genBtn) genBtn.hidden = false;
    });
    (function()
    {
        const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+[]{};:,.<>?';
        const DEFAULT_LENGTH = 16;
        function generatePassword(length = DEFAULT_LENGTH)
        {
            const array = new Uint32Array(length);
            window.crypto.getRandomValues(array);
            return Array.from(array, num => CHARS[num % CHARS.length]).join('');
        }
        document.getElementById('btn-generate-psw').addEventListener('click', function()
        {
            const pwd = generatePassword();
            document.getElementById('password').value = pwd;
            document.getElementById('password').type = 'text';
            document.getElementById('password2').value = pwd;
            document.getElementById('password').focus();
            document.getElementById('password').select();
        });
    })();
</script>
</body>
</html>
