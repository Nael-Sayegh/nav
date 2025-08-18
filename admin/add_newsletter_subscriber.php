<?php
/**
 * Script interne pour ajouter des abonnés à la newsletter sans notification
 * Usage interne uniquement - Ne pas exposer publiquement
 */

set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/consts.php');
require_once('include/dbconnect.php');

// Vérification que le script est exécuté depuis l'admin ou en ligne de commande
if (!isset($_SERVER['HTTP_HOST']) || (isset($_SERVER['REQUEST_URI']) && !str_contains($_SERVER['REQUEST_URI'], '/admin/'))) {
    // Autoriser uniquement si exécuté en CLI ou depuis l'admin
    if (php_sapi_name() !== 'cli' && !str_contains($_SERVER['REQUEST_URI'], '/admin/')) {
        http_response_code(403);
        die('Accès interdit');
    }
}

/**
 * Ajoute un abonné à la newsletter sans notification
 *
 * @param string $email L'adresse email de l'abonné
 * @param array $options Options d'abonnement (optionnel)
 * @return array Résultat de l'opération
 */
function addNewsletterSubscriber($email, $options = []) {
    global $bdd, $lang;

    // Validation de l'email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        return ['success' => false, 'error' => 'Adresse email invalide ou trop longue'];
    }

    // Vérifier si l'email existe déjà
    $SQL = "SELECT id FROM newsletter_mails WHERE mail = :mail LIMIT 1";
    $req = $bdd->prepare($SQL);
    $req->execute([':mail' => $email]);

    if ($req->fetch()) {
        return ['success' => false, 'error' => 'Cette adresse est déjà inscrite'];
    }

    // Configuration par défaut
    $defaults = [
        'freq' => 3,           // Hebdomadaire
        'freq_n' => 3,         // Hebdomadaire pour le blog
        'notif_site' => 1,     // Notifications du site activées
        'notif_upd' => 1,      // Newsletter principale activée
        'notif_upd_n' => 1,    // Newsletter blog activée
        'lang' => $lang ?? 'fr'
    ];

    // Fusionner avec les options fournies
    $config = array_merge($defaults, $options);

    // Générer un hash unique
    $hash = sha1(strval(random_int(0, mt_getrandmax()) + time()) . $email) .
            sha1($email . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . strval(random_int(0, mt_getrandmax())));

    try {
        $SQL = <<<SQL
        INSERT INTO newsletter_mails (
            hash, mail, freq, freq_n, notif_site, notif_upd, notif_upd_n,
            lastmail, lastmail_n, lang
        ) VALUES (
            :hash, :mail, :freq, :freq_n, :notif_site, :notif_upd, :notif_upd_n,
            :lastmail, :lastmail_n, :lang
        )
        SQL;

        $req = $bdd->prepare($SQL);
        $result = $req->execute([
            ':hash' => $hash,
            ':mail' => $email,
            ':freq' => $config['freq'],
            ':freq_n' => $config['freq_n'],
            ':notif_site' => $config['notif_site'] ? 1 : 0,
            ':notif_upd' => $config['notif_upd'] ? 1 : 0,
            ':notif_upd_n' => $config['notif_upd_n'] ? 1 : 0,
            ':lastmail' => time(),
            ':lastmail_n' => time(),
            ':lang' => $config['lang']
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => "Abonné $email ajouté avec succès",
                'hash' => $hash,
                'config' => $config
            ];
        } else {
            return ['success' => false, 'error' => 'Erreur lors de l\'insertion en base de données'];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()];
    }
}

/**
 * Ajoute plusieurs abonnés en lot
 *
 * @param array $emails Liste des emails
 * @param array $options Options communes
 * @return array Résultats des opérations
 */
function addMultipleSubscribers($emails, $options = []) {
    $results = [];
    $success_count = 0;
    $error_count = 0;

    foreach ($emails as $email) {
        $result = addNewsletterSubscriber(trim($email), $options);
        $results[] = $result;

        if ($result['success']) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    return [
        'results' => $results,
        'summary' => [
            'total' => count($emails),
            'success' => $success_count,
            'errors' => $error_count
        ]
    ];
}

// Interface en ligne de commande
if (php_sapi_name() === 'cli') {
    echo "=== Ajout d'abonnés newsletter (sans notification) ===\n\n";

    if ($argc < 2) {
        echo "Usage:\n";
        echo "  php add_newsletter_subscriber.php email@example.com\n";
        echo "  php add_newsletter_subscriber.php email1@example.com email2@example.com\n\n";
        echo "Options disponibles (à modifier dans le script):\n";
        echo "  - freq: Fréquence (1=quotidien, 2=2 jours, 3=hebdo, 4=quinzaine, 5=mensuel)\n";
        echo "  - notif_site: Notifications du site (1/0)\n";
        echo "  - notif_upd: Newsletter principale (1/0)\n";
        echo "  - notif_upd_n: Newsletter blog (1/0)\n";
        exit(1);
    }

    // Récupérer les emails depuis les arguments
    $emails = array_slice($argv, 1);

    if (count($emails) === 1) {
        $result = addNewsletterSubscriber($emails[0]);
        if ($result['success']) {
            echo "✓ " . $result['message'] . "\n";
            echo "  Hash: " . $result['hash'] . "\n";
        } else {
            echo "✗ Erreur: " . $result['error'] . "\n";
        }
    } else {
        $results = addMultipleSubscribers($emails);
        echo "Résultats:\n";
        echo "  Total: " . $results['summary']['total'] . "\n";
        echo "  Succès: " . $results['summary']['success'] . "\n";
        echo "  Erreurs: " . $results['summary']['errors'] . "\n\n";

        foreach ($results['results'] as $i => $result) {
            $email = $emails[$i];
            if ($result['success']) {
                echo "✓ $email\n";
            } else {
                echo "✗ $email: " . $result['error'] . "\n";
            }
        }
    }

    exit(0);
}

// Interface web (seulement si dans /admin/)
if (isset($_SERVER['HTTP_HOST'])) {
    $title = 'Ajout d\'abonnés newsletter (interne)';
    $log = '';

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $emails = array_filter(array_map('trim', explode("\n", $_POST['email'])));

            // Options depuis le formulaire
            $options = [
                'freq' => (int) ($_POST['freq'] ?? 3),
                'freq_n' => (int) ($_POST['freq_n'] ?? 3),
                'notif_site' => isset($_POST['notif_site']),
                'notif_upd' => isset($_POST['notif_upd']),
                'notif_upd_n' => isset($_POST['notif_upd_n']),
                'lang' => $_POST['lang'] ?? 'fr'
            ];

            if (count($emails) === 1) {
                $result = addNewsletterSubscriber($emails[0], $options);
                if ($result['success']) {
                    $log = '<div style="color: green;">' . htmlspecialchars($result['message']) . '</div>';
                } else {
                    $log = '<div style="color: red;">Erreur: ' . htmlspecialchars($result['error']) . '</div>';
                }
            } else {
                $results = addMultipleSubscribers($emails, $options);
                $log = '<div style="color: blue;">Traitement en lot terminé:</div>';
                $log .= '<div>Total: ' . $results['summary']['total'] . '</div>';
                $log .= '<div style="color: green;">Succès: ' . $results['summary']['success'] . '</div>';
                $log .= '<div style="color: red;">Erreurs: ' . $results['summary']['errors'] . '</div>';

                if ($results['summary']['errors'] > 0) {
                    $log .= '<details><summary>Détails des erreurs</summary><ul>';
                    foreach ($results['results'] as $i => $result) {
                        if (!$result['success']) {
                            $log .= '<li>' . htmlspecialchars($emails[$i]) . ': ' . htmlspecialchars($result['error']) . '</li>';
                        }
                    }
                    $log .= '</ul></details>';
                }
            }
        } else {
            $log = '<div style="color: red;">Veuillez fournir au moins une adresse email.</div>';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; font-weight: bold; margin-bottom: 5px; }
            input, select, textarea { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
            textarea { width: 100%; height: 100px; resize: vertical; }
            .checkbox-group { display: flex; gap: 20px; flex-wrap: wrap; }
            .checkbox-item { display: flex; align-items: center; gap: 5px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
            .submit-btn { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
            .submit-btn:hover { background: #005a87; }
        </style>
    </head>
    <body>
        <h1><?= htmlspecialchars($title) ?></h1>

        <div class="warning">
            <strong>⚠️ Script d'usage interne uniquement</strong><br>
            Ce script permet d'ajouter des abonnés à la newsletter sans leur envoyer de notification de confirmation.
            Utilisez-le uniquement pour des ajouts internes ou des migrations de données.
        </div>

        <?php if ($log): ?>
            <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <?= $log ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Adresse(s) email (une par ligne pour ajout en lot):</label>
                <textarea name="email" id="email" placeholder="exemple@domaine.com&#10;autre@domaine.com" required></textarea>
            </div>

            <div class="form-group">
                <label for="freq">Fréquence newsletter principale:</label>
                <select name="freq" id="freq">
                    <option value="1">Quotidiennement</option>
                    <option value="2">Tous les 2 jours</option>
                    <option value="3" selected>Hebdomadairement</option>
                    <option value="4">Quinzomadairement</option>
                    <option value="5">Mensuellement</option>
                </select>
            </div>

            <div class="form-group">
                <label for="freq_n">Fréquence newsletter blog:</label>
                <select name="freq_n" id="freq_n">
                    <option value="1">Quotidiennement</option>
                    <option value="2">Tous les 2 jours</option>
                    <option value="3" selected>Hebdomadairement</option>
                    <option value="4">Quinzomadairement</option>
                    <option value="5">Mensuellement</option>
                </select>
            </div>

            <div class="form-group">
                <label>Abonnements:</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="notif_site" id="notif_site" checked>
                        <label for="notif_site">Notifications du site</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="notif_upd" id="notif_upd" checked>
                        <label for="notif_upd">Newsletter principale</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="notif_upd_n" id="notif_upd_n" checked>
                        <label for="notif_upd_n">Newsletter blog</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="lang">Langue:</label>
                <select name="lang" id="lang">
                    <option value="fr" selected>Français</option>
                    <option value="en">English</option>
                </select>
            </div>

            <button type="submit" class="submit-btn">Ajouter abonné(s)</button>
        </form>

        <h2>Usage en ligne de commande</h2>
        <p>Vous pouvez également utiliser ce script en ligne de commande :</p>
        <pre><code>php add_newsletter_subscriber.php email@example.com
php add_newsletter_subscriber.php email1@example.com email2@example.com</code></pre>
    </body>
    </html>
    <?php
}
?>
