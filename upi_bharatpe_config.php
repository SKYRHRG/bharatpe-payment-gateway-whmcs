<?php
/**
 * upi_bharatpe_config.php
 * WHMCS Gateway Configuration for UPI BharatPe (Manual Payment)
 * 
 * Project: UPI UPI BharatPe
 * Developer: SKYRHRG Technologies Systems
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define gateway configuration parameters
 *
 * @return array
 */
function upi_bharatpe_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'UPI BharatPe (Manual Payment)',
        ],

        'merchantUpiId' => [
            'FriendlyName' => 'Merchant UPI ID',
            'Type'         => 'text',
            'Size'         => '50',
            'Default'      => 'BHARATPE.9T0X0P0W1N905507@unitype',
            'Description'  => 'Enter your UPI ID where customers will send the payment.',
        ],

        'merchantName' => [
            'FriendlyName' => 'Merchant Display Name',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => 'SKYRHRG Technologies',
            'Description'  => 'This name will appear in the UPI app as the receiver.',
        ],

        'noteText' => [
            'FriendlyName' => 'Default Payment Note',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => 'Invoice Payment',
            'Description'  => 'This note will appear in the UPI payment description.',
        ],

        'instructions' => [
            'FriendlyName' => 'Payment Instructions',
            'Type'         => 'textarea',
            'Rows'         => '4',
            'Cols'         => '70',
            'Default'      => "1. Scan the QR Code using any UPI App (PhonePe, GPay, Paytm).\n2. Enter the invoice amount exactly.\n3. After payment, submit the UTR/Transaction ID below.\n4. Wait for admin approval (payment under process).",
            'Description'  => 'Instructions displayed on the payment page below the QR code.',
        ],

        'debug' => [
            'FriendlyName' => 'Debug Mode',
            'Type'         => 'yesno',
            'Description'  => 'Enable to log gateway actions in the WHMCS Activity Log.',
        ],
    ];
}
