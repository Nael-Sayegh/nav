<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Liste des abonnés à la lettre d\'informations';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('manage_newsletter');?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Liste des abonnés à la lettre d'informations</title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<table>
<thead>
<tr><th>Adresse e-mail</th><th>Hash</th><th>Fréquence</th><th>Dernier mail</th></tr>
</thead>
<tbody>
<?php
$SQL = <<<SQL
    SELECT * FROM newsletter_mails
    SQL;
function getFrequency($numFreq)
{
    $stringFreq = '';
    switch ($numFreq)
    {
        case 1: $stringFreq = 'Quotidiennement';
            break;
        case 2: $stringFreq = 'Tous les 2 jours';
            break;
        case 3: $stringFreq = 'Hebdomadairement';
            break;
        case 4: $stringFreq = 'Quinzomadairement';
            break;
        case 5: $stringFreq = 'Mensuellement';
            break;
        default: break;
    }
    return $stringFreq;
}
foreach ($bdd->query($SQL) as $data)
{
    echo '<tr><td>'.$data['mail'].'</td><td><details><summary>'.substr((string) $data['hash'], 0, 7).'</summary>'.$data['hash'].'</details></td><td>'.getFrequency($data['freq']).'</td><td>'.date('d/m/Y H:i', $data['lastmail']).'</td></tr>';
}

?>
</tbody>
</table>
</body>
</html>
