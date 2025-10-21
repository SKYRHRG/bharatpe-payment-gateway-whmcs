<?php
if (!defined("WHMCS")) {
    require_once __DIR__ . '/../../../../init.php';
}

use WHMCS\Database\Capsule;

function upi_bharatpe_saveUTR($invoiceId, $utr, $amount) {
    try {
        // prevent duplicates
        $exists = Capsule::table('tblupi_bharatpe_payments')
            ->where('invoice_id', $invoiceId)
            ->first();

        if ($exists) {
            Capsule::table('tblupi_bharatpe_payments')
                ->where('invoice_id', $invoiceId)
                ->update([
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
        return true;
    } catch (Exception $e) {
        logActivity("UPI BharatPe Save UTR Failed: " . $e->getMessage());
        return false;
    }
}
