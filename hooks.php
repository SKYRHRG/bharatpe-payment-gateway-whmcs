<?php
use WHMCS\Database\Capsule;

add_hook('ClientAreaPageViewInvoice', 1, function($vars) {
    $invoiceid = $vars['invoiceid'];

    try {
        $utrData = Capsule::table('mod_upi_bharatpe_utr')
            ->where('invoiceid', $invoiceid)
            ->first();

        if ($utrData) {
            $statusLabel = '<span style="color:orange;">Pending Approval</span>';
            if ($utrData->status == 'approved') {
                $statusLabel = '<span style="color:green;">Approved</span>';
            } elseif ($utrData->status == 'rejected') {
                $statusLabel = '<span style="color:red;">Rejected</span>';
            }

            $extraHtml = "<div class='card mt-3'>
                <div class='card-body'>
                    <h5>UPI Payment Details</h5>
                    <p><strong>UTR Number:</strong> {$utrData->utr}</p>
                    <p><strong>Status:</strong> {$statusLabel}</p>
                </div>
            </div>";

            return ['customUtrBox' => $extraHtml];
        }
    } catch (Exception $e) {
        logActivity("UPI BharatPe Hook Error: " . $e->getMessage());
    }
});
