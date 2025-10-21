<?php
/**
 * QR Generator for BharatPe UPI Gateway
 * SKYRHRG Technologies Systems
 */

require_once __DIR__ . '/../../../../init.php'; // WHMCS initialization (safe include)
require_once __DIR__ . '/phpqrcode/qrlib.php';  // Include the PHP QR Code library

// === CONFIGURATION ===
$upiId = 'BHARATPE.9T0X0P0W1N905507@unitype';
$merchantName = 'SKYRHRG Technologies';
$note = 'Invoice Payment';

// === FETCH DATA FROM URL ===
$invoiceid = isset($_GET['invoiceid']) ? preg_replace('/[^0-9]/', '', $_GET['invoiceid']) : '0';
$amount    = isset($_GET['amount']) ? preg_replace('/[^0-9.]/', '', $_GET['amount']) : '0.00';

// === GENERATE UPI PAYMENT STRING ===
$upiUrl = "upi://pay?pa={$upiId}&pn=" . urlencode($merchantName) . "&am={$amount}&cu=INR&tn=" . urlencode($note . " #{$invoiceid}");

// === OUTPUT QR CODE AS IMAGE ===
header('Content-Type: image/png');
QRcode::png($upiUrl, false, QR_ECLEVEL_L, 6);
exit;
?>
