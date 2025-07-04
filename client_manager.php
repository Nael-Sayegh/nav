<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/log.php');
require_once('include/consts.php');
require_once('vendor/autoload.php');

// Charger la configuration
if (file_exists('include/client_config.local.php')) {
    require_once('include/client_config.local.php');
} else {
    require_once('include/client_config.php');
}

// Initialize Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$tr = load_tr($lang, 'client_manager');
$title = 'Gestionnaire de Clients';

// Messages de retour
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $error_message = 'Adresse email invalide.';
    } else {
        try {
            // 1. Vérifier/créer le client sur Facturation Pro
            $facturation_client = handleFacturationProClient($email);

            // 2. Vérifier/créer le client sur Stripe
            $stripe_client = handleStripeClient($email, $facturation_client);

            $success_message = "Client traité avec succès !<br>" .
                             "ID Facturation Pro: " . $facturation_client['id'] . "<br>" .
                             "ID Stripe: " . $stripe_client['id'];

        } catch (Exception $e) {
            $error_message = "Erreur: " . $e->getMessage();
        }
    }
}

/**
 * Gère la création/récupération d'un client sur Facturation Pro
 */
function handleFacturationProClient($email) {
    $client = new GuzzleHttp\Client();

    // Rechercher le client par email
    try {
        $response = $client->get(FACTURATION_PRO_BASE_URL . '/clients', [
            'headers' => [
                'Authorization' => 'Bearer ' . FACTURATION_PRO_API_KEY,
                'Accept' => 'application/json',
            ],
            'query' => [
                'email' => $email
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        // Si le client existe, le retourner
        if (!empty($data['data'])) {
            return $data['data'][0];
        }

    } catch (GuzzleHttp\Exception\RequestException $e) {
        // Si erreur 404, le client n'existe pas, on va le créer
        if ($e->getResponse() && $e->getResponse()->getStatusCode() !== 404) {
            throw $e;
        }
    }

    // Créer le client s'il n'existe pas
    $response = $client->post(FACTURATION_PRO_BASE_URL . '/clients', [
        'headers' => [
            'Authorization' => 'Bearer ' . FACTURATION_PRO_API_KEY,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'email' => $email,
            'name' => explode('@', $email)[0], // Utilise la partie avant @ comme nom par défaut
        ]
    ]);

    $data = json_decode($response->getBody(), true);
    return $data['data'];
}

/**
 * Gère la création/récupération d'un client sur Stripe
 */
function handleStripeClient($email, $facturation_client) {
    // Rechercher le client par email
    $customers = \Stripe\Customer::all([
        'email' => $email,
        'limit' => 1
    ]);

    // Si le client existe, le retourner
    if (!empty($customers->data)) {
        return $customers->data[0];
    }

    // Créer le client s'il n'existe pas
    $customer = \Stripe\Customer::create([
        'email' => $email,
        'name' => $facturation_client['name'] ?? explode('@', $email)[0],
        'metadata' => [
            'facturation_pro_id' => $facturation_client['id']
        ]
    ]);

    return $customer;
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>

<div id="container">
    <h1>Gestionnaire de Clients</h1>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= $success_message ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Adresse email du client :</label>
            <input type="email"
                   id="email"
                   name="email"
                   required
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                   placeholder="client@example.com">
        </div>

        <button type="submit" class="btn btn-primary">
            Vérifier/Créer le client
        </button>
    </form>

    <div class="info-section">
        <h2>Comment ça fonctionne ?</h2>
        <ol>
            <li>Saisissez l'adresse email du client</li>
            <li>Le système vérifie si le client existe sur Facturation Pro</li>
            <li>Si le client n'existe pas, il est créé automatiquement</li>
            <li>Ensuite, le système vérifie si le client existe sur Stripe</li>
            <li>Si le client n'existe pas sur Stripe, il est créé avec une référence vers Facturation Pro</li>
        </ol>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.form-group input {
    width: 100%;
    max-width: 400px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.info-section {
    margin-top: 2rem;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.info-section h2 {
    margin-top: 0;
}

.info-section ol {
    margin: 0;
    padding-left: 1.5rem;
}

.info-section li {
    margin-bottom: 0.5rem;
}
</style>

<?php require_once('include/footer.php'); ?>
</body>
</html>
