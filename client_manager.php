<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
include_once('include/log.php');
require_once('include/consts.php');
require_once('include/sendMail.php');

$tr = load_tr($lang, 'client_manager');
$title = tr($tr, 'title');
$stats_page = 'client_manager';

$log = '';
$results = [];

// API Configuration from constants
$FACTURATION_API_URL = 'https://facturation.dev/llm';
$FACTURATION_API_KEY = defined('FACTURATION_API_KEY') ? FACTURATION_API_KEY : '';
$STRIPE_API_KEY = defined('STRIPE_API_KEY') ? STRIPE_API_KEY : '';

/**
 * Make API call to Facturation Pro
 */
function call_facturation_api($endpoint, $data = null, $method = 'GET') {
    global $FACTURATION_API_URL, $FACTURATION_API_KEY;
    
    $url = $FACTURATION_API_URL . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $FACTURATION_API_KEY
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'status_code' => $httpCode,
        'data' => $decoded,
        'raw_response' => $response
    ];
}

/**
 * Make API call to Stripe
 */
function call_stripe_api($endpoint, $data = null, $method = 'GET') {
    global $STRIPE_API_KEY;
    
    $url = 'https://api.stripe.com/v1' . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $STRIPE_API_KEY
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'status_code' => $httpCode,
        'data' => $decoded,
        'raw_response' => $response
    ];
}

/**
 * Check if client exists in Facturation Pro by email
 */
function check_facturation_client($email) {
    try {
        // This endpoint structure is hypothetical - needs to be adapted to actual API
        $response = call_facturation_api('/clients/search?email=' . urlencode($email));
        
        if ($response['status_code'] === 200 && !empty($response['data'])) {
            return $response['data'];
        }
        
        return null;
    } catch (Exception $e) {
        throw new Exception('Erreur Facturation Pro: ' . $e->getMessage());
    }
}

/**
 * Create client in Facturation Pro
 */
function create_facturation_client($email) {
    try {
        $data = [
            'email' => $email,
            'name' => $email, // Basic info, can be expanded
        ];
        
        $response = call_facturation_api('/clients', $data, 'POST');
        
        if ($response['status_code'] === 201 || $response['status_code'] === 200) {
            return $response['data'];
        }
        
        throw new Exception('Failed to create client: ' . $response['raw_response']);
    } catch (Exception $e) {
        throw new Exception('Erreur création Facturation Pro: ' . $e->getMessage());
    }
}

/**
 * Check if client exists in Stripe by email
 */
function check_stripe_client($email) {
    try {
        $response = call_stripe_api('/customers?email=' . urlencode($email));
        
        if ($response['status_code'] === 200 && !empty($response['data']['data'])) {
            return $response['data']['data'][0]; // Return first match
        }
        
        return null;
    } catch (Exception $e) {
        throw new Exception('Erreur Stripe: ' . $e->getMessage());
    }
}

/**
 * Create client in Stripe
 */
function create_stripe_client($email) {
    try {
        $data = [
            'email' => $email,
        ];
        
        $response = call_stripe_api('/customers', $data, 'POST');
        
        if ($response['status_code'] === 200) {
            return $response['data'];
        }
        
        throw new Exception('Failed to create customer: ' . $response['raw_response']);
    } catch (Exception $e) {
        throw new Exception('Erreur création Stripe: ' . $e->getMessage());
    }
}

// Process form submission
if (isset($_GET['act']) && $_GET['act'] === 'manage') {
    $email = trim((string)($_POST['email'] ?? ''));
    
    // Validate email
    if (empty($email)) {
        $log .= '<li>' . tr($tr, 'email_required') . '</li>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $log .= '<li>' . tr($tr, 'email_invalid') . '</li>';
    } else {
        try {
            // Step 1: Check Facturation Pro
            $results['email'] = $email;
            $results['facturation_status'] = tr($tr, 'facturation_check');
            
            $facturationClient = check_facturation_client($email);
            
            if ($facturationClient) {
                $results['facturation_status'] = tr($tr, 'facturation_found', ['id' => $facturationClient['id'] ?? 'N/A']);
                $results['facturation_id'] = $facturationClient['id'] ?? null;
            } else {
                $results['facturation_status'] = tr($tr, 'facturation_not_found');
                
                // Create client in Facturation Pro
                $facturationClient = create_facturation_client($email);
                $results['facturation_status'] = tr($tr, 'facturation_created', ['id' => $facturationClient['id'] ?? 'N/A']);
                $results['facturation_id'] = $facturationClient['id'] ?? null;
            }
            
            // Step 2: Check Stripe
            $results['stripe_status'] = tr($tr, 'stripe_check');
            
            $stripeClient = check_stripe_client($email);
            
            if ($stripeClient) {
                $results['stripe_status'] = tr($tr, 'stripe_found', ['id' => $stripeClient['id'] ?? 'N/A']);
                $results['stripe_id'] = $stripeClient['id'] ?? null;
            } else {
                $results['stripe_status'] = tr($tr, 'stripe_not_found');
                
                // Create client in Stripe
                $stripeClient = create_stripe_client($email);
                $results['stripe_status'] = tr($tr, 'stripe_created', ['id' => $stripeClient['id'] ?? 'N/A']);
                $results['stripe_id'] = $stripeClient['id'] ?? null;
            }
            
            $results['success'] = true;
            $log .= '<li style="color: green;">' . tr($tr, 'success_complete') . '</li>';
            
        } catch (Exception $e) {
            $log .= '<li style="color: red;">' . $e->getMessage() . '</li>';
            $results['error'] = $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<p><?php echo tr($tr, 'description'); ?></p>

<div id="alertZone" role="alert" aria-live="assertive"></div>

<?php if (!empty($log)): ?>
<noscript>
<ul id="log" role="alert"><?= $log ?></ul>
</noscript>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const log = `<?= $log ?>`;
    if (log) {
        document.getElementById('alertZone').innerHTML = `<ul>${log}</ul>`;
    }
});
</script>
<?php endif; ?>

<h2><?php echo tr($tr, 'form_title'); ?></h2>

<form action="?act=manage" method="post">
<fieldset>
<legend><?php echo tr($tr, 'form_title'); ?></legend>

<label for="f_email"><?php echo tr($tr, 'form_email'); ?></label><br>
<input type="email" 
       id="f_email" 
       name="email" 
       maxlength="255" 
       required 
       value="<?php if (isset($_POST['email'])) { echo htmlentities((string) $_POST['email']); } ?>"
       style="width: calc(100% - 10px); margin-bottom: 10px;"><br>

<input type="submit" value="<?php echo tr($tr, 'form_submit'); ?>">

</fieldset>
</form>

<?php if (!empty($results)): ?>
<h2><?php echo tr($tr, 'results_title'); ?></h2>
<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 20px;">
    <h3>Email: <?php echo htmlentities($results['email']); ?></h3>
    
    <h4>Facturation Pro:</h4>
    <p><?php echo $results['facturation_status']; ?></p>
    <?php if (isset($results['facturation_id'])): ?>
    <p><strong>ID:</strong> <?php echo htmlentities((string)$results['facturation_id']); ?></p>
    <?php endif; ?>
    
    <h4>Stripe:</h4>
    <p><?php echo $results['stripe_status']; ?></p>
    <?php if (isset($results['stripe_id'])): ?>
    <p><strong>ID:</strong> <?php echo htmlentities((string)$results['stripe_id']); ?></p>
    <?php endif; ?>
    
    <?php if (isset($results['success']) && $results['success']): ?>
    <p style="color: green; font-weight: bold;"><?php echo tr($tr, 'success_complete'); ?></p>
    <?php elseif (isset($results['error'])): ?>
    <p style="color: red; font-weight: bold;">Erreur: <?php echo htmlentities($results['error']); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>
<?php require_once('include/footer.php'); ?>

<script>
// Basic form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="?act=manage"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const email = document.getElementById('f_email').value.trim();
            
            if (!email) {
                e.preventDefault();
                alert('<?php echo addslashes(tr($tr, 'email_required')); ?>');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('<?php echo addslashes(tr($tr, 'email_invalid')); ?>');
                return false;
            }
            
            // Show processing message
            const submitButton = form.querySelector('input[type="submit"]');
            if (submitButton) {
                submitButton.value = '<?php echo addslashes(tr($tr, 'processing')); ?>';
                submitButton.disabled = true;
            }
        });
    }
});
</script>

</body>
</html>