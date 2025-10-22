<?php
/**
 * Standalone Payment Page for UPI BharatPe
 * Modern Design with Timers and Polling for Status Check
 */

// Define constants BEFORE init.php to bypass theme
define('CLIENTAREA', true);
define('SKIPTHEME', true);

// Adjust the path depth according to your file structure relative to WHMCS root
require_once __DIR__ . '/../../../init.php'; // Assuming pay.php is in modules/gateways/upi_bharatpe/
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;
// Remove incorrect use statement: use WHMCS\Utility\Environment\WebHelper;

// Disable output buffering from theme remnants if any
if (ob_get_level()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

$gatewayModuleName = 'upi_bharatpe'; // Ensure this matches your gateway filename
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Check if gateway is active
if (!$gatewayParams || !isset($gatewayParams['type']) || !$gatewayParams['type']) {
    // Attempt to get SystemURL for graceful error display
    // Ensure DI is available or fall back to global $CONFIG
    $systemUrl = '';
    if (class_exists('\DI')) {
        try {
            $systemUrl = DI::make('config')->get('SystemURL');
        } catch (\Exception $e) {
            // Fallback if DI fails or config isn't loaded yet
            global $CONFIG;
            if (isset($CONFIG['SystemURL'])) {
                 $systemUrl = $CONFIG['SystemURL'];
            }
        }
    } else {
         global $CONFIG;
         if (isset($CONFIG['SystemURL'])) {
             $systemUrl = $CONFIG['SystemURL'];
         }
    }

    $clientAreaUrl = rtrim($systemUrl, '/') . '/clientarea.php'; // Default client area URL

    // Log the error for the admin
    logActivity("[UPI BharatPe Pay Page] Error: Gateway module '{$gatewayModuleName}' is not activated or configuration is missing.", 0);

    // Provide a user-friendly error page
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Error</title>
    <style>body{font-family: sans-serif; padding: 20px; text-align: center; color: #555;} .error{color: #dc3545; font-weight: bold;}</style>
</head>
<body>
    <h1>Payment Gateway Error</h1>
    <p class="error">The UPI payment gateway is currently unavailable or not configured correctly.</p>
    <p>Please contact support or try a different payment method.</p>
    <p><a href="{$clientAreaUrl}">Return to Client Area</a></p>
</body>
</html>
HTML;
    exit;
}


// Get Invoice ID from GET and validate *** FIXED LINE HERE ***
$invoiceId = isset($_GET['invoiceid']) ? (int) $_GET['invoiceid'] : 0;
if ($invoiceId <= 0) {
    die("Invalid Invoice ID provided."); // Simple error for direct access attempt
}

// Fetch invoice details
$invoice = null;
try {
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
} catch (\Exception $e) {
     logActivity("[UPI BharatPe Pay Page] Database Error fetching invoice {$invoiceId}: " . $e->getMessage());
     die("Error retrieving invoice details. Please contact support.");
}

if (!$invoice) {
    die("Invoice #{$invoiceId} not found.");
}

// Check if invoice is already paid or cancelled
if ($invoice->status !== 'Unpaid') {
     $systemUrl = rtrim($gatewayParams['systemurl'], '/');
     $invoiceUrl = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
     header('Location: ' . $invoiceUrl); // Redirect to invoice page if already paid/cancelled
     exit;
}


// Prepare data for the payment page
$amount = number_format((float) ($invoice->balance ?? $invoice->total), 2, '.', ''); // Use balance first, fallback to total
$currencyData = getCurrency($invoice->userid); // Assuming getCurrency function is available
$currencyCode = $currencyData['code'] ?? 'INR'; // Default to INR

$merchantUpiId = $gatewayParams['merchantUpiId'] ?? 'YOUR_UPI_ID@provider'; // Fallback UPI ID
$displayName = $gatewayParams['displayName'] ?? 'Your Company Name';      // Fallback Name
$noteText = $gatewayParams['noteText'] ?? 'Invoice Payment';            // Fallback Note

// Get WHMCS System URL safely
$systemUrl = rtrim($gatewayParams['systemurl'], '/');

// Generate UPI deep link string
$upiNote = $noteText . ' #' . $invoiceId; // Include invoice ID in the note
$upiLink = "upi://pay?pa=" . urlencode($merchantUpiId)
         . "&pn=" . urlencode($displayName)
         . "&am=" . $amount // Amount should not have thousand separators
         . "&cu=INR" // Currency Code
         . "&tn=" . urlencode($upiNote); // Transaction Note

// Generate QR Code URL using external service
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($upiLink);
// Alternative: Use internal QR generator if preferred (requires setup)
// $qrCodeUrl = $systemUrl . '/modules/gateways/' . $gatewayModuleName . '/includes/qr_generator.php?invoiceid=' . $invoiceId . '&amount=' . $amount;

// Base URL for callback scripts
$callbackBaseUrl = $systemUrl . '/modules/gateways/' . $gatewayModuleName . '/callback/';

// Clear any potential output buffer before sending headers/HTML
// Already done above

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice #<?php echo htmlspecialchars($invoiceId); ?> - UPI Payment</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($systemUrl . '/modules/gateways/' . $gatewayModuleName . '/payment.css'); // Use gateway module name variable ?>" />

</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">🔐</div>
            <h1>Secure UPI Payment</h1>
            <p>Complete your payment quickly & securely</p>
        </div>

        <div class="content">
            <div class="invoice-info">
                <div class="info-row">
                    <span class="info-label">
                        <span>📄</span> Invoice ID
                    </span>
                    <span class="info-value">#<?php echo htmlspecialchars($invoiceId); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">
                        <span>💰</span> Amount Due
                    </span>
                    <span class="info-value amount-highlight">
                        <?php echo $currencyData['prefix'] ?? ''; // Display currency prefix (e.g., ₹) ?>
                        <?php echo htmlspecialchars($amount); ?>
                        <?php echo $currencyData['suffix'] ?? ''; ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">
                        <span>🏷️</span> Status
                    </span>
                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($invoice->status)); ?>">
                        <?php echo htmlspecialchars(ucfirst($invoice->status)); ?>
                    </span>
                </div>
            </div>

            <div class="qr-section">
                <div class="qr-label">
                    <span class="qr-icon">📱</span>
                    <span>Scan QR Code with any UPI App</span>
                </div>
                <div class="qr-code">
                    <img id="qrCodeImage" src="<?php echo htmlspecialchars($qrCodeUrl); ?>"
                         alt="UPI QR Code"
                         loading="lazy">
                </div>
                 <div id="qrTimerDisplay" class="qr-timer">
                    QR Code valid for: <span id="qrTimeLeft">02:00</span>
                </div>
                <div id="qrExpiredMessage" class="qr-expired-message" style="display: none;">
                    QR Code has expired. Please refresh the page to generate a new one.
                </div>
            </div>

            <div class="divider">
                <span>OR PAY MANUALLY</span>
            </div>

            <div class="upi-id-box">
                <div class="upi-label">UPI ID / VPA</div>
                <div class="upi-id"><?php echo htmlspecialchars($merchantUpiId); ?></div>
            </div>

            <form class="utr-form" id="utrForm">
                <label class="form-label" for="utrInput">
                    <span>🧾</span>
                    <span>Enter Transaction Reference (UTR/Ref No.)</span>
                </label>

                <div class="input-group">
                    <input
                        type="text"
                        id="utrInput"
                        class="form-input"
                        placeholder="Enter UTR from your UPI app"
                        maxlength="50"
                        required
                        aria-label="UTR Input"
                    >
                    <span class="input-icon">📝</span>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="btn-text">✅ Verify Payment</span>
                    <span class="spinner btn-icon"></span>
                </button>
            </form>

             <div id="verificationStatus" class="verification-status processing" style="display: none;">
                <p><span class="spinner"></span> Verifying your payment with admin...</p>
                <div class="timer">Time left: <span id="verificationTimeLeft">05:00</span></div>
            </div>

             <div id="finalMessage" class="message" style="display: none;"></div>

        </div> <div class="footer">
            Secured Payment Gateway <span class="footer-icon">🔒</span>
        </div>
    </div> <script>
        // --- Constants and DOM Elements ---
        const utrForm = document.getElementById('utrForm');
        const utrInput = document.getElementById('utrInput');
        const submitBtn = document.getElementById('submitBtn');
        const finalMessage = document.getElementById('finalMessage'); // Use this for final status messages
        const verificationStatusDiv = document.getElementById('verificationStatus');
        const verificationTimeLeftSpan = document.getElementById('verificationTimeLeft');
        const qrTimeLeftSpan = document.getElementById('qrTimeLeft');
        const qrTimerDisplay = document.getElementById('qrTimerDisplay');
        const qrExpiredMessage = document.getElementById('qrExpiredMessage');
        const qrCodeImage = document.getElementById('qrCodeImage');

        const invoiceId = <?php echo (int)$invoiceId; ?>;
        const verifyUrl = '<?php echo htmlspecialchars($callbackBaseUrl . 'verify.php'); ?>';
        const checkStatusUrl = '<?php echo htmlspecialchars($callbackBaseUrl . 'check_status.php'); ?>';
        const invoiceUrl = '<?php echo htmlspecialchars($systemUrl . '/viewinvoice.php?id=' . $invoiceId); ?>';

        const QR_EXPIRY_SECONDS = 120; // 2 minutes
        const VERIFICATION_TIMEOUT_SECONDS = 300; // 5 minutes
        const POLLING_INTERVAL_MS = 5000; // Check status every 5 seconds

        let qrTimerInterval = null;
        let verificationTimerInterval = null;
        let pollingInterval = null;
        let currentPaymentId = null; // Store the ID received from verify.php

        // --- Timer Functions ---
        function startQrTimer() {
            let timeLeft = QR_EXPIRY_SECONDS;
            qrTimerDisplay.style.display = 'block';
            qrExpiredMessage.style.display = 'none';
            // Reset any expiry visual cues if page is refreshed
            // qrCodeImage.style.opacity = '1';
            // utrInput.disabled = false;
            // submitBtn.disabled = false;

            function updateTimerDisplay() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                qrTimeLeftSpan.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }

            updateTimerDisplay(); // Initial display

            qrTimerInterval = setInterval(() => {
                timeLeft--;
                if (timeLeft >= 0) {
                    updateTimerDisplay();
                } else {
                    clearInterval(qrTimerInterval);
                    qrTimerDisplay.style.display = 'none';
                    qrExpiredMessage.style.display = 'block';
                    // Optionally disable the UTR form or visually indicate expiry
                    // qrCodeImage.style.opacity = '0.5'; // Example visual cue
                    // utrInput.disabled = true;
                    // submitBtn.disabled = true;
                }
            }, 1000);
        }

        function startVerificationTimer() {
            let timeLeft = VERIFICATION_TIMEOUT_SECONDS;
            verificationStatusDiv.style.display = 'block'; // Show verification box

            function updateTimerDisplay() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                verificationTimeLeftSpan.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }

            updateTimerDisplay(); // Initial display

            verificationTimerInterval = setInterval(() => {
                timeLeft--;
                if (timeLeft >= 0) {
                    updateTimerDisplay();
                } else {
                    // Timer expired - Stop polling and timer
                    stopPollingAndTimer();
                    verificationStatusDiv.style.display = 'none'; // Hide verification box
                    // Show timeout message
                    showFinalMessage('warning', '⏳ Verification time limit reached. Your payment status will be updated shortly after admin review. You will receive an email once confirmed. Redirecting back to invoice...');
                    // Redirect after a delay
                    setTimeout(() => { window.location.href = invoiceUrl; }, 4000);
                }
            }, 1000);
        }

        function stopPollingAndTimer() {
             if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
             }
             if (verificationTimerInterval) {
                 clearInterval(verificationTimerInterval);
                 verificationTimerInterval = null;
             }
        }

        // --- Payment Status Polling ---
        function checkPaymentStatus() {
            if (!currentPaymentId) return; // Exit if no payment ID is set

            fetch(`${checkStatusUrl}?id=${currentPaymentId}`)
                .then(response => {
                    if (!response.ok) {
                        // Handle HTTP errors (like 404, 500) during polling
                        console.error(`Polling HTTP error! status: ${response.status}`);
                        // Optionally stop polling after too many errors, or just log and continue
                        // For now, we just log and let the timer handle the timeout
                        return null; // Indicate error to next .then()
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Exit if fetch failed

                    console.log('Poll status:', data.status); // For debugging
                    switch (data.status) {
                        case 'Approved':
                            stopPollingAndTimer();
                            verificationStatusDiv.style.display = 'none';
                            showFinalMessage('success', '✅ Payment Approved! Your invoice has been marked as paid. Redirecting...');
                            setTimeout(() => { window.location.href = invoiceUrl; }, 3000);
                            break;
                        case 'Rejected':
                            stopPollingAndTimer();
                            verificationStatusDiv.style.display = 'none';
                            showFinalMessage('error', '❌ Payment Rejected by Admin. Please double-check the UTR you entered or contact support if you believe this is an error.');
                            // Re-enable the form to allow correction or resubmission
                            utrForm.style.display = 'block'; // Show form again
                            utrInput.disabled = false;
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('loading');
                            submitBtn.querySelector('.btn-text').textContent = '✅ Verify Payment';
                            currentPaymentId = null; // Reset payment ID as this attempt failed
                            break;
                        case 'Pending':
                            // Status is still pending, continue polling. Timer will handle timeout.
                            break;
                        case 'NotFound':
                        case 'Error':
                            // An error occurred on the server checking status, or ID was wrong
                            stopPollingAndTimer();
                            verificationStatusDiv.style.display = 'none';
                            showFinalMessage('error', `⚠️ Error checking payment status: ${data.message || 'Unknown polling error'}. Please contact support.`);
                            console.error('Polling Error Response:', data);
                             // Re-enable form might be appropriate here too
                             utrForm.style.display = 'block';
                             utrInput.disabled = false;
                             submitBtn.disabled = false;
                             submitBtn.classList.remove('loading');
                             submitBtn.querySelector('.btn-text').textContent = '✅ Verify Payment';
                             currentPaymentId = null;
                            break;
                        default:
                             console.warn('Unknown status received from polling:', data.status);
                             // Treat unexpected status as pending and let timer expire?
                             break;
                    }
                })
                .catch(error => {
                    // Network error or JSON parsing error during polling
                    console.error('Polling Fetch/Network Error:', error);
                    // Log but continue polling - don't stop process for temporary network issue
                    // Let the main verification timer handle the timeout if network persists
                });
        }

        // --- UTR Submission ---
        function submitUtr(utr) {
            // Clear any previous final messages, disable button, show loading
            finalMessage.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.querySelector('.btn-text').textContent = 'Submitting...';


            fetch(verifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json' // Explicitly accept JSON
                },
                body: `invoiceid=${invoiceId}&utr=${encodeURIComponent(utr)}`
            })
            .then(response => {
                if (!response.ok) { // Check for HTTP errors (like 404, 500)
                     return response.text().then(text => { // Try to get error text
                         throw new Error(`HTTP error! status: ${response.status} - ${text}`);
                     });
                }
                 // Check content type before parsing JSON
                 const contentType = response.headers.get("content-type");
                 if (!contentType || !contentType.includes("application/json")) {
                     return response.text().then(text => {
                         throw new TypeError(`Expected JSON, got ${contentType}. Content: ${text}`);
                     });
                 }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.paymentId) {
                    currentPaymentId = data.paymentId;
                    // UTR submitted successfully, hide form, start timer & polling
                    utrForm.style.display = 'none'; // Hide the form
                    showFinalMessage('info', '✅ UTR Submitted! Waiting for admin verification...'); // Initial info
                    startVerificationTimer(); // Start the 5-min timer
                    pollingInterval = setInterval(checkPaymentStatus, POLLING_INTERVAL_MS); // Start polling
                    checkPaymentStatus(); // Check immediately once
                } else {
                    // Error submitting UTR (e.g., validation, invoice not found, server error reported in JSON)
                    showFinalMessage('error', `❌ Submission Failed: ${data.message || 'Unknown error during submission.'}`);
                     // Re-enable button and form
                     submitBtn.disabled = false;
                     submitBtn.classList.remove('loading');
                     submitBtn.querySelector('.btn-text').textContent = '✅ Verify Payment';
                     utrForm.style.display = 'block'; // Ensure form is visible
                }
            })
            .catch(error => {
                console.error('Submit UTR Fetch/Network Error:', error);
                // Network error or other fetch issue (e.g., JSON parse error)
                 submitBtn.disabled = false;
                 submitBtn.classList.remove('loading');
                 submitBtn.querySelector('.btn-text').textContent = '✅ Verify Payment';
                showFinalMessage('error', `⚠️ Network or Server Error: ${error.message}. Please check connection and try again.`);
                utrForm.style.display = 'block'; // Ensure form is visible
            });
        }

        // --- Display Final Messages ---
        function showFinalMessage(type, text) {
            finalMessage.className = 'message ' + type; // Apply class (success, error, warning, info)
            finalMessage.innerHTML = text; // Use innerHTML to allow basic tags if needed
            finalMessage.style.display = 'block';
             // Hide the intermediate verification status box if it's visible
            verificationStatusDiv.style.display = 'none';
        }

        // --- Event Listeners ---
        utrForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            // Clear previous message before validating/submitting again
            finalMessage.style.display = 'none';

            const utr = utrInput.value.trim();

            if (!utr) {
                showFinalMessage('error', '⚠️ Please enter your UTR/Transaction ID.');
                return;
            }
            // Basic length check (adjust min/max as per typical UTR lengths if known)
            if (utr.length < 6 || utr.length > 50) {
                 showFinalMessage('error', '⚠️ UTR must be between 6 and 50 characters.');
                 return;
            }

            // If validation passes, proceed to submit
            submitUtr(utr);
        });

        // --- Input Auto-Formatting ---
        utrInput.addEventListener('input', function() {
            // Allow only alphanumeric characters, convert to uppercase
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        });

        // --- Initial Setup ---
        window.onload = function() {
            utrInput.focus(); // Auto-focus on UTR input when page loads
            startQrTimer();   // Start the 2-minute QR timer on page load
        };

    </script>
</body>
</html>
