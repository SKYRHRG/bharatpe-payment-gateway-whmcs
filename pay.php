<?php
/**
 * UPI BharatPe Manual Payment Page
 * SKYRHRG Technologies Systems
 */

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

// ---------------------------------------------------------
// 1️⃣  Initialize WHMCS Framework
// ---------------------------------------------------------
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// ---------------------------------------------------------
// 2️⃣  Validate & Fetch Invoice
// ---------------------------------------------------------
$invoiceid = isset($_GET['invoiceid']) ? (int) $_GET['invoiceid'] : 0;

if (!$invoiceid) {
    die('Invalid Invoice ID.');
}

$invoice = Capsule::table('tblinvoices')->where('id', $invoiceid)->first();
if (!$invoice) {
    die('Invoice not found.');
}

$amount = number_format($invoice->total, 2);
$gatewayModule = 'upi_bharatpe';
$gatewayParams = getGatewayVariables($gatewayModule);
$systemUrl = rtrim($gatewayParams['systemurl'] ?? $GLOBALS['CONFIG']['SystemURL'], '/');

// ---------------------------------------------------------
// 3️⃣  Initialize WHMCS Client Area Environment
// ---------------------------------------------------------
$ca = new ClientArea();
$ca->setPageTitle('Pay via UPI - BharatPe');
$ca->initPage();

// ---------------------------------------------------------
// 4️⃣  Assign Template Variables
// ---------------------------------------------------------
$ca->assign('invoiceid', $invoiceid);
$ca->assign('amount', $amount);
$ca->assign('systemurl', $systemUrl);
$ca->assign('gatewayname', 'UPI BharatPe');
$ca->assign('companyname', $gatewayParams['displayName'] ?? 'SKYRHRG Technologies Systems');

// ---------------------------------------------------------
// 5️⃣  Display Template
// ---------------------------------------------------------
$ca->setTemplate('/modules/gateways/upi_bharatpe/templates/payform.tpl');
$ca->output();
