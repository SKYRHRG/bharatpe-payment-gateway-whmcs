<?php
// modules/gateways/upi_bharatpe/callback/verify.php
// Robust UTR save endpoint - returns JSON only

// Path to WHMCS init (4 levels up from callback/)
require_once __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;

header('Content-Type: application/json; charset=utf-8');

// Helper: send JSON and exit
function jsonExit($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonExit('error', 'Invalid request method.');
    }

    // Validate input
    $invoiceId = isset($_POST['invoiceid']) ? (int) $_POST['invoiceid'] : 0;
    $utr       = isset($_POST['utr']) ? trim($_POST['utr']) : '';

    if ($invoiceId <= 0) {
        jsonExit('error', 'Missing or invalid invoice ID.');
    }
    if ($utr === '') {
        jsonExit('error', 'Missing UTR.');
    }

    // Ensure DB table exists (safe check)
    if (!Capsule::schema()->hasTable('tblupi_bharatpe_payments')) {
        // Try to create minimal table if missing
        Capsule::schema()->create('tblupi_bharatpe_payments', function ($table) {
            $table->increments('id');
            $table->integer('invoice_id');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('utr', 100);
            $table->string('status', 20)->default('Pending');
            $table->timestamp('created_at')->useCurrent();
        });
        logActivity('[UPI BharatPe] Created missing table tblupi_bharatpe_payments');
    }

    // Fetch invoice row to get amount
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) {
        jsonExit('error', 'Invoice not found.');
    }

    // Decide amount: use invoice balance if available, otherwise total
    $amount = 0.00;
    if (isset($invoice->balance) && $invoice->balance > 0) {
        $amount = (float) $invoice->balance;
    } elseif (isset($invoice->total)) {
        $amount = (float) $invoice->total;
    }

    // Upsert record (if invoice already has a record, update it)
    $existing = Capsule::table('tblupi_bharatpe_payments')->where('invoice_id', $invoiceId)->first();

    if ($existing) {
        Capsule::table('tblupi_bharatpe_payments')->where('invoice_id', $invoiceId)->update([
            'utr' => $utr,
            'amount' => $amount,
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        Capsule::table('tblupi_bharatpe_payments')->insert([
            'invoice_id' => $invoiceId,
            'utr' => $utr,
            'amount' => $amount,
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    logActivity("[UPI BharatPe] UTR saved: invoice={$invoiceId} utr={$utr} amount={$amount}");

    jsonExit('success', 'UTR submitted successfully! Please wait for admin approval.');

} catch (Throwable $e) {
    // Log internal error and return generic message
    $msg = $e->getMessage();
    logActivity("[UPI BharatPe] verify.php error: " . $msg);
    jsonExit('error', 'Server error, contact admin. Error logged.');
}
