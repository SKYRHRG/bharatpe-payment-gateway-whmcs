<?php
/**
 * verify.php
 * Endpoint for submitting UTR/Transaction ID for a UPI payment.
 * Saves the details and returns the status and the database record ID.
 */

// Path to WHMCS init (adjust if your module structure is different)
$initPath = __DIR__ . '/../../../../init.php';
if (!file_exists($initPath)) {
    // Attempt to log if possible, otherwise just die
    try { logActivity("[UPI BharatPe Verify] CRITICAL ERROR: init.php not found at {$initPath}"); } catch (\Exception $e) {}
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error. [Code: INIT_NF]']);
    exit;
}
require_once $initPath;

use WHMCS\Database\Capsule;
use WHMCS\Utility\Environment\WebHelper;

// Set header to JSON output
header('Content-Type: application/json; charset=utf-8');

/**
 * Helper function to send JSON response and exit.
 * @param string $status    Status ('success' or 'error')
 * @param string $message   Response message
 * @param int|null $paymentId Optional: The ID of the created/updated payment record
 */
function jsonExit($status, $message, $paymentId = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($paymentId !== null) {
        $response['paymentId'] = $paymentId;
    }
    // Attempt to log errors before exiting
    if ($status === 'error') {
        logActivity("[UPI BharatPe Verify] Exiting with error: " . $message);
    }
    echo json_encode($response);
    exit;
}

// --- Main Logic ---
$paymentId = null; // Initialize paymentId
$invoiceId = 0; // Initialize for logging in catch block
$utr = ''; // Initialize for logging in catch block

try {
    // Check if WHMCS DB connection is available
    if (!class_exists('WHMCS\Database\Capsule')) {
         throw new \Exception("WHMCS Database Capsule not available. WHMCS environment might not be fully loaded.");
    }

    // 1. Only accept POST requests
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        jsonExit('error', 'Invalid request method. Only POST is allowed.');
    }

    // 2. Validate input using WHMCS helper (use $_POST directly as WebHelper might not be reliable here)
    $invoiceId = isset($_POST['invoiceid']) ? (int) $_POST['invoiceid'] : 0;
    $utr_raw = isset($_POST['utr']) ? trim($_POST['utr']) : '';
    // Basic sanitization for UTR: allow alphanumeric, trim whitespace
    $utr = preg_replace('/[^a-zA-Z0-9]/', '', $utr_raw);

    logActivity("[UPI BharatPe Verify] Received request: InvoiceID={$invoiceId}, RawUTR='{$utr_raw}', SanitizedUTR='{$utr}'"); // Log input

    if ($invoiceId <= 0) {
        jsonExit('error', 'Missing or invalid invoice ID.');
    }
    if (empty($utr)) {
        jsonExit('error', 'Missing UTR/Transaction ID.');
    }
     if (strlen($utr) < 6 || strlen($utr) > 50) {
        jsonExit('error', 'UTR must be between 6 and 50 characters.');
     }

    // 3. Fetch associated WHMCS invoice to get amount and check existence
    logActivity("[UPI BharatPe Verify] Fetching invoice {$invoiceId}...");
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) {
        jsonExit('error', 'Invoice not found.');
    }
    logActivity("[UPI BharatPe Verify] Invoice {$invoiceId} found. Status: {$invoice->status}, Balance: {$invoice->balance}, Total: {$invoice->total}");


    // Ensure invoice is unpaid before allowing UTR submission
    if ($invoice->status !== 'Unpaid') {
         jsonExit('error', 'This invoice is not currently marked as Unpaid (Status: ' . $invoice->status . '). Please contact support if you believe this is an error.');
    }

    // 4. Determine the amount to save
    $amount = 0.00;
    if (isset($invoice->balance) && is_numeric($invoice->balance) && $invoice->balance > 0) {
        $amount = (float) $invoice->balance;
    } elseif (isset($invoice->total) && is_numeric($invoice->total)) {
        $amount = (float) $invoice->total;
    }
     logActivity("[UPI BharatPe Verify] Calculated amount for Invoice {$invoiceId}: {$amount}");

    // Ensure amount is valid
    if ($amount <= 0) {
         logActivity("[UPI BharatPe Verify] Error: Calculated amount for Invoice ID {$invoiceId} is zero or negative.");
         jsonExit('error', 'Could not determine a valid payment amount for this invoice.');
    }

    // 5. Check if a record already exists for this invoice ID
    $tableName = 'tblupi_bharatpe_payments';
    logActivity("[UPI BharatPe Verify] Checking for existing payment record for Invoice {$invoiceId} in table {$tableName}...");
    $existingPayment = Capsule::table($tableName)->where('invoice_id', $invoiceId)->first();

    if ($existingPayment) {
        logActivity("[UPI BharatPe Verify] Existing record found (ID: {$existingPayment->id}, Status: {$existingPayment->status}).");
        // Update existing record
        if ($existingPayment->status === 'Pending') {
             logActivity("[UPI BharatPe Verify] Updating existing Pending record ID: {$existingPayment->id}...");
             Capsule::table($tableName)->where('id', $existingPayment->id)->update([
                'utr'         => $utr,
                'amount'      => $amount, // Update amount
                'status'      => 'Pending',
                'updated_at'  => Capsule::raw('NOW()')
             ]);
             $paymentId = $existingPayment->id; // Use existing ID
             logActivity("[UPI BharatPe Verify] UTR updated for Invoice ID {$invoiceId}: UTR={$utr}, Amount={$amount}, Record ID={$paymentId}");
        } else {
            // Record exists but is already processed
            jsonExit('error', 'A payment for this invoice (UTR: ' . htmlspecialchars($existingPayment->utr) . ') has already been processed with status: ' . $existingPayment->status . '. Please contact support.', $existingPayment->id);
        }

    } else {
        // Insert new record and get the ID
        logActivity("[UPI BharatPe Verify] No existing record found. Inserting new record...");
        $paymentId = Capsule::table($tableName)->insertGetId([
            'invoice_id' => $invoiceId,
            'utr'        => $utr,
            'amount'     => $amount,
            'status'     => 'Pending', // Default status
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()')
        ]);
         logActivity("[UPI BharatPe Verify] UTR saved for Invoice ID {$invoiceId}: UTR={$utr}, Amount={$amount}, Record ID={$paymentId}");
    }

    // 6. Return success response including the payment record ID
    jsonExit('success', 'UTR submitted successfully! Please wait while we verify your payment.', $paymentId);

} catch (\Throwable $e) { // Catch any type of error/exception
    // Log the detailed error for admin debugging
    $errorMessage = $e->getMessage();
    // Include line number and file for better debugging
    $errorDetails = $errorMessage . " in " . $e->getFile() . " on line " . $e->getLine();
    logActivity("[UPI BharatPe Verify] CRITICAL ERROR: " . $errorDetails . " - Input: InvoiceID={$invoiceId}, UTR='{$utr}' - POST Data: " . print_r($_POST, true));

    // Return the generic error to the client as before
    jsonExit('error', 'A server error occurred while submitting your UTR. Please contact support. Error has been logged.');
}
?>
