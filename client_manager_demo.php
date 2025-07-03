<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Gestionnaire de clients - Demo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-container { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .results { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        input[type="email"] { width: 100%; padding: 8px; margin: 5px 0; }
        input[type="submit"] { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #005a8b; }
        .step { margin: 10px 0; padding: 10px; border-left: 3px solid #007cba; }
    </style>
</head>
<body>

<h1>Gestionnaire de clients</h1>
<p>Gérez vos clients entre Facturation Pro et Stripe</p>

<?php
// Configuration API (à déplacer dans config.local.php en production)
$FACTURATION_API_URL = 'https://facturation.dev/llm';
$FACTURATION_API_KEY = ''; // À configurer
$STRIPE_API_KEY = ''; // À configurer

// Fonction pour simuler les appels API en mode démo
function simulate_api_call($service, $endpoint, $email) {
    // En mode démo, on simule les réponses
    if ($service === 'facturation') {
        if (strpos($email, 'existing') !== false) {
            return ['id' => 'fact_' . substr(md5($email), 0, 8), 'email' => $email];
        }
        return null; // Client non trouvé
    } elseif ($service === 'stripe') {
        if (strpos($email, 'existing') !== false) {
            return ['id' => 'cus_' . substr(md5($email), 0, 8), 'email' => $email];
        }
        return null; // Client non trouvé
    }
    return null;
}

function create_client_simulation($service, $email) {
    // Simulation de création
    if ($service === 'facturation') {
        return ['id' => 'fact_' . substr(md5($email . time()), 0, 8), 'email' => $email];
    } elseif ($service === 'stripe') {
        return ['id' => 'cus_' . substr(md5($email . time()), 0, 8), 'email' => $email];
    }
    return null;
}

$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Veuillez saisir une adresse e-mail valide.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse e-mail fournie n\'est pas valide.';
    } else {
        $results['email'] = $email;
        
        try {
            // Étape 1: Vérification Facturation Pro
            $results['step1'] = 'Vérification dans Facturation Pro...';
            
            $facturationClient = simulate_api_call('facturation', '/clients/search', $email);
            
            if ($facturationClient) {
                $results['facturation_status'] = 'Client trouvé dans Facturation Pro (ID: ' . $facturationClient['id'] . ')';
                $results['facturation_id'] = $facturationClient['id'];
            } else {
                $results['facturation_status'] = 'Client non trouvé dans Facturation Pro, création en cours...';
                
                // Création du client
                $facturationClient = create_client_simulation('facturation', $email);
                $results['facturation_status'] = 'Client créé dans Facturation Pro (ID: ' . $facturationClient['id'] . ')';
                $results['facturation_id'] = $facturationClient['id'];
            }
            
            // Étape 2: Vérification Stripe
            $results['step2'] = 'Vérification dans Stripe...';
            
            $stripeClient = simulate_api_call('stripe', '/customers', $email);
            
            if ($stripeClient) {
                $results['stripe_status'] = 'Client trouvé dans Stripe (ID: ' . $stripeClient['id'] . ')';
                $results['stripe_id'] = $stripeClient['id'];
            } else {
                $results['stripe_status'] = 'Client non trouvé dans Stripe, création en cours...';
                
                // Création du client
                $stripeClient = create_client_simulation('stripe', $email);
                $results['stripe_status'] = 'Client créé dans Stripe (ID: ' . $stripeClient['id'] . ')';
                $results['stripe_id'] = $stripeClient['id'];
            }
            
            $results['success'] = true;
            
        } catch (Exception $e) {
            $errors[] = 'Erreur: ' . $e->getMessage();
        }
    }
}
?>

<?php if (!empty($errors)): ?>
<div class="error">
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?= htmlentities($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="form-container">
    <h2>Recherche et création de client</h2>
    
    <form method="post">
        <label for="email">Adresse e-mail du client :</label><br>
        <input type="email" 
               id="email" 
               name="email" 
               maxlength="255" 
               required 
               value="<?= htmlentities($_POST['email'] ?? '') ?>"
               placeholder="client@example.com">
        
        <br><br>
        <input type="submit" value="Rechercher/Créer le client">
    </form>
    
    <p><em>Mode démo : Utilisez un email contenant "existing" pour simuler un client existant.</em></p>
</div>

<?php if (!empty($results)): ?>
<div class="results">
    <h2>Résultats</h2>
    
    <h3>Email: <?= htmlentities($results['email']) ?></h3>
    
    <div class="step">
        <h4>Facturation Pro:</h4>
        <p><?= htmlentities($results['facturation_status']) ?></p>
        <?php if (isset($results['facturation_id'])): ?>
        <p><strong>ID Facturation Pro:</strong> <?= htmlentities($results['facturation_id']) ?></p>
        <?php endif; ?>
    </div>
    
    <div class="step">
        <h4>Stripe:</h4>
        <p><?= htmlentities($results['stripe_status']) ?></p>
        <?php if (isset($results['stripe_id'])): ?>
        <p><strong>ID Stripe:</strong> <?= htmlentities($results['stripe_id']) ?></p>
        <?php endif; ?>
    </div>
    
    <?php if (isset($results['success']) && $results['success']): ?>
    <div class="success">
        <p><strong>✓ Opération terminée avec succès. Le client existe maintenant dans les deux systèmes.</strong></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<hr>
<h2>À propos de cette page</h2>
<p>Cette page démontre la fonctionnalité du gestionnaire de clients qui permet de :</p>
<ul>
    <li>Vérifier l'existence d'un client dans Facturation Pro</li>
    <li>Créer le client dans Facturation Pro s'il n'existe pas</li>
    <li>Vérifier l'existence du client dans Stripe</li>
    <li>Créer le client dans Stripe s'il n'existe pas</li>
</ul>

<p><strong>Configuration requise en production :</strong></p>
<ul>
    <li>Clé API Facturation Pro dans <code>config.local.php</code></li>
    <li>Clé API Stripe dans <code>config.local.php</code></li>
    <li>Adaptation des endpoints selon la documentation des APIs</li>
</ul>

<script>
// Validation côté client
document.querySelector('form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    
    if (!email) {
        e.preventDefault();
        alert('Veuillez saisir une adresse e-mail valide.');
        return false;
    }
    
    // Validation email basique
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('L\'adresse e-mail fournie n\'est pas valide.');
        return false;
    }
    
    // Message de traitement
    const submitButton = this.querySelector('input[type="submit"]');
    if (submitButton) {
        submitButton.value = 'Traitement en cours...';
        submitButton.disabled = true;
    }
});
</script>

</body>
</html>