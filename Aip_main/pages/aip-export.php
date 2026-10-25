<?php
/**
 * Download the consolidated AIP as CSV: the live consolidation, or a saved
 * version with ?version=N. Opens directly in Excel.
 */
$assetPrefix = '../';
require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission('consolidation.view', $assetPrefix);

$versionNo = isset($_GET['version']) ? (int) $_GET['version'] : 0;
if ($versionNo) {
    $version = aipFindVersion($versionNo);
    if (!$version) {
        flash('error', 'That AIP version was not found.');
        header('Location: aip-consolidation.php');
        exit;
    }
    $entries = $version['entries'];
    $label = 'v' . $versionNo;
} else {
    $entries = aipEntries();
    $label = 'current';
}
$thousands = ($_GET['units'] ?? '') === 'thousands';
$sectors = ppaSectors();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="aip-fy' . AIP_FISCAL_YEAR . '-' . $label . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows ₱ and accents correctly
fputcsv($out, ['Annual Investment Program CY ' . AIP_FISCAL_YEAR . ' - Municipality of Indang' . ($versionNo ? ' - Version ' . $versionNo : ' - Current consolidation') . ' (development version, sample data)']);
fputcsv($out, ['Amounts in ' . ($thousands ? 'thousand pesos' : 'pesos')]);
fputcsv($out, ['Sector', 'AIP Reference Code', 'PPA Description', 'Type', 'Program', 'Implementing Office', 'Start', 'Completion', 'Expected Outputs', 'Funding Source', 'PS', 'MOOE', 'CO', 'Total', 'CC Adaptation', 'CC Mitigation', 'CC Typology Code']);
$amount = fn (float $n) => $thousands ? round($n / 1000, 2) : round($n, 2);
foreach ($entries as $entry) {
    fputcsv($out, [
        $sectors[$entry['sector']] ?? $entry['sector'], $entry['ref'], $entry['title'], $entry['type'], $entry['program'],
        officeName($entry['office']), $entry['start'], $entry['end'], $entry['expected_output'], $entry['fund_label'],
        $amount($entry['ps']), $amount($entry['mooe']), $amount($entry['co']), $amount($entry['cost']),
        $amount($entry['cc_adaptation']), $amount($entry['cc_mitigation']), $entry['cc_typology'],
    ]);
}
$t = aipTotals($entries);
fputcsv($out, ['GRAND TOTAL', '', $t['count'] . ' PPAs', '', '', '', '', '', '', '', $amount($t['ps']), $amount($t['mooe']), $amount($t['co']), $amount($t['cost']), $amount($t['cc_adaptation']), $amount($t['cc_mitigation']), '']);
fclose($out);
