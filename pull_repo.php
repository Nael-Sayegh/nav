<?php
require_once('include/config.local.php');
if(GIT_TYPE == 'GL')
{
        if(getallheaders()["X-Gitlab-Token"] == GIT_WEBHOOK_TOKEN)
                shell_exec('git --git-dir="'.GIT_DIR.'" pull');
}
elseif(GIT_TYPE == 'GH')
{
        $payload = file_get_contents('php://input');
        $githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        $hash = hash_hmac('sha256', $payload, GIT_WEBHOOK_TOKEN);
        $expectedSignature = 'sha256=' . $hash;
        if (hash_equals($expectedSignature, $githubSignature))
                shell_exec('git --git-dir="'.GIT_DIR.'" pull');
}
?>
