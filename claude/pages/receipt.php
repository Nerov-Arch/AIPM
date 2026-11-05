<?php
/**
 * Serves an uploaded receipt file after checking that the signed-in user may
 * see the PPA it belongs to. Files are never linked directly.
 */
require_once '../includes/auth.php';
requireLogin('../');
requirePermission('expenses.view', '../');

$expense = storeFindExpense((int) ($_GET['id'] ?? 0));
$ppa = $expense ? storeFindPpa($expense['ref']) : null;
$path = $expense && $expense['file'] ? receiptDir() . '/' . basename($expense['file']['stored']) : '';
if (!$ppa || !canSeeOffice($ppa['office']) || $path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Receipt not found.');
}
$mime = $expense['file']['mime'];
if (!isset(RECEIPT_TYPES[$mime])) {
    http_response_code(404);
    exit('Receipt not found.');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="receipt-' . $expense['id'] . '.' . RECEIPT_TYPES[$mime] . '"');
readfile($path);
