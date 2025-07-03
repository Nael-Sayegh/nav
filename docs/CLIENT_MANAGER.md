# Client Manager - Documentation

## Vue d'ensemble

Le gestionnaire de clients (`client_manager.php`) permet de synchroniser automatiquement les clients entre Facturation Pro et Stripe. Il vérifie l'existence d'un client dans chaque système et le crée automatiquement s'il n'existe pas.

## Fonctionnalités

- ✅ Vérification d'existence client dans Facturation Pro
- ✅ Création automatique dans Facturation Pro si inexistant
- ✅ Vérification d'existence client dans Stripe
- ✅ Création automatique dans Stripe si inexistant
- ✅ Interface utilisateur intégrée au site
- ✅ Validation côté client et serveur
- ✅ Gestion d'erreurs complète
- ✅ Messages de feedback utilisateur
- ✅ Support du système de traduction

## Configuration

### 1. Clés API

Ajoutez ces constantes dans `include/config.local.php` :

```php
// API Facturation Pro
define('FACTURATION_API_KEY', 'votre_cle_api_facturation_pro');

// API Stripe
define('STRIPE_API_KEY', 'sk_live_votre_cle_stripe_privee');
```

### 2. Endpoints API

Le code utilise actuellement ces endpoints :

**Facturation Pro :**
- Base URL : `https://facturation.dev/llm`
- Recherche client : `GET /clients/search?email={email}`
- Création client : `POST /clients`

**Stripe :**
- Base URL : `https://api.stripe.com/v1`
- Recherche client : `GET /customers?email={email}`
- Création client : `POST /customers`

⚠️ **Important :** Adaptez les endpoints selon la documentation officielle des APIs.

## Utilisation

### Accès à la page

Visitez : `https://votre-site.com/client_manager.php`

### Workflow

1. Saisir l'adresse e-mail du client
2. Cliquer sur "Rechercher/Créer le client"
3. Le système :
   - Vérifie dans Facturation Pro
   - Crée le client si nécessaire
   - Vérifie dans Stripe
   - Crée le client si nécessaire
   - Affiche les résultats avec les IDs

## Structure des fichiers

```
/
├── client_manager.php              # Page principale intégrée
├── client_manager_demo.php         # Version démo standalone
├── locales/fr/client_manager.tr.php # Traductions françaises
└── include/config.local.php        # Configuration (à créer)
```

## Fonctions API

### `call_facturation_api($endpoint, $data, $method)`
Effectue un appel à l'API Facturation Pro avec authentification Bearer.

### `call_stripe_api($endpoint, $data, $method)`
Effectue un appel à l'API Stripe avec authentification Bearer.

### `check_facturation_client($email)`
Vérifie l'existence d'un client par email dans Facturation Pro.

### `create_facturation_client($email)`
Crée un nouveau client dans Facturation Pro.

### `check_stripe_client($email)`
Vérifie l'existence d'un client par email dans Stripe.

### `create_stripe_client($email)`
Crée un nouveau client dans Stripe.

## Sécurité

- ✅ Validation d'email côté client et serveur
- ✅ Échappement HTML des données affichées
- ✅ Gestion sécurisée des clés API
- ✅ Timeout des requêtes API (30s)
- ✅ Gestion des erreurs cURL

## Personnalisation

### Traductions

Modifiez `locales/fr/client_manager.tr.php` pour personnaliser les messages.

### Styling

Le CSS suit la structure existante du site. Personnalisez via les feuilles de style globales.

### Champs supplémentaires

Pour ajouter des champs (nom, téléphone, etc.), modifiez :
1. Le formulaire HTML
2. Les fonctions `create_*_client()`
3. La validation

## Test en mode démo

La version démo (`client_manager_demo.php`) simule les APIs :
- Emails contenant "existing" → client trouvé
- Autres emails → client créé

## Support

Pour toute question ou problème :
1. Vérifiez les logs du serveur web
2. Testez les clés API séparément
3. Consultez la documentation des APIs Facturation Pro et Stripe