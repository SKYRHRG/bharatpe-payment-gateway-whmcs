<?php
/**
 * UPI BharatPe Gateway for WHMCS
 * Author: SKYRHRG Technologies Systems
 * Website: https://skyrhrgts.com
 * Version: 1.1
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Gateway Configuration
 */
function upi_bharatpe_config()
{
    return [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "UPI BharatPe",
        ],
        "merchantUpiId" => [
            "FriendlyName" => "Merchant UPI ID",
            "Type" => "text",
            "Size" => "40",
            "Description" => "Enter your BharatPe UPI ID (e.g. yourname@bharatpe)",
        ],
        "displayName" => [
            "FriendlyName" => "Display Name",
            "Type" => "text",
            "Size" => "40",
            "Default" => "SKYRHRG Technologies Systems",
            "Description" => "Name to display on QR / payment page",
        ],
        "noteText" => [
            "FriendlyName" => "Payment Note",
            "Type" => "text",
            "Size" => "60",
            "Default" => "Invoice Payment via WHMCS",
            "Description" => "Note text included with the payment QR",
        ],
    ];
}

/**
 * Redirects to standalone payment page
 */
function upi_bharatpe_link($params)
{
    $invoiceid = $params['invoiceid'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $redirectUrl = $systemUrl . '/modules/gateways/upi_bharatpe/pay.php?invoiceid=' . $invoiceid;

    $buttonStyle = 'background:#007bff;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:600;';

    $html = '
    <form action="' . $redirectUrl . '" method="GET">
        <input type="hidden" name="invoiceid" value="' . $invoiceid . '">
        <button type="submit" style="' . $buttonStyle . '">
            Pay via UPI BharatPe
        </button>
    </form>';

    return $html;
}

/**
 * No refund or callback handling in this manual gateway
 */
function upi_bharatpe_refund($params)
{
    return [
        'status' => 'error',
        'rawdata' => 'Refunds are not supported for this manual gateway.',
    ];
}
