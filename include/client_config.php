<?php

// Configuration pour la gestion des clients
// Copiez ce fichier vers client_config.local.php et remplissez avec vos vraies clés

// Clé secrète Stripe (commençant par sk_test_ pour les tests ou sk_live_ pour la production)
define('STRIPE_SECRET_KEY', 'sk_test_VOTRE_CLE_SECRETE_STRIPE');

// Clé API Facturation Pro
define('FACTURATION_PRO_API_KEY', 'VOTRE_CLE_API_FACTURATION_PRO');

// URL de base de l'API Facturation Pro (par défaut pour facturation.dev)
define('FACTURATION_PRO_BASE_URL', 'https://facturation.dev/api');

?>
