<?php
/**
 * helper.php
 * Common helper functions for UPI BharatPe Gateway
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Sanitize text input
 */
function upi_clean($string)
{
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Show success or info message (for frontend)
 */
function upi_message($msg, $type = 'info')
{
    $color = ($type == 'success') ? 'green' : (($type == 'error') ? 'red' : 'blue');
    return "<div style='padding:8px;border:1px solid {$color};color:{$color};border-radius:4px;margin-top:10px;'>{$msg}</div>";
}

/**
 * Log activity to WHMCS Activity Log
 */
function upi_log($message)
{
    logActivity("[UPI BharatPe] " . $message);
}
