<?php
/**
 * check_status.php
 * Endpoint for polling the status of a UPI payment transaction.
 *
 * Receives: paymentId (GET parameter 'id')
 * Returns: JSON {'status': 'Pending'|'Approved'|'Rejected'|'Error', 'message': 'Optional error message'}
 */

// Path to WHMCS init (adjust if your module structure is different)
require_once __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;

// Set header to JSON output
header('Content-Type: application/json; charset=utf-8');

/**
 * Helper function to send JSON response and exit.
 *
 * @param string $status  The status ('Pending', 'Approved', 'Rejected', 'Error', 'NotFound')
 * @param string $message Optional message, mainly for errors
 */
function jsonResponse($status, $message = '') {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Get Payment ID from GET request
$paymentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Validate Payment ID
if ($paymentId <= 0) {
    jsonResponse('Error', 'Invalid Payment ID provided.');
}

try {
    // Fetch the payment record status from the database
    $payment = Capsule::table('tblupi_bharatpe_payments') // Ensure this table name is correct
        ->where('id', $paymentId)
        ->select('status') // Only select the status column for efficiency
        ->first();

    if ($payment) {
        // Record found, return its status
        jsonResponse($payment->status);
    } else {
        // Record not found for the given ID
        jsonResponse('NotFound', 'Payment record not found.');
    }

} catch (\Exception $e) {
    // Log the database error for admin debugging
    logActivity("[UPI BharatPe Check Status] Database Error: " . $e->getMessage() . " for Payment ID: " . $paymentId);

    // Return a generic error to the client
    jsonResponse('Error', 'Could not retrieve payment status. Please try again later or contact support.');
}

?>