<?php
add_hook('ClientAreaPageCartComplete', 1, function($vars) {
    if (!empty($_SESSION['orderdetails']['InvoiceID'])) {
        header('Location: viewinvoice.php?id=' . $_SESSION['orderdetails']['InvoiceID']);
        exit;
    }
});
?>
