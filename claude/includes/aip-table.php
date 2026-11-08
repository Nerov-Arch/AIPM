<?php
/**
 * The consolidated AIP in the standard AIP form layout:
 * sector → implementing office → PPAs, with subtotals and a grand total.
 *
 * Expects: $aipEntries (list of entries), $aipThousands (bool),
 *          $aipCaption (string, e.g. "Version 2 · Final").
 */
$aipStructureRows = aipStructure($aipEntries);
$aipGrand = aipTotals($aipEntries);
$unit = $aipThousands ? "Amount (in thousand pesos, ₱'000)" : 'Amount (in pesos)';
$fmt = fn (float $n) => aipAmount($n, $aipThousands);
$colspanDesc = 6;
?>
<div class="aip-print-header">
    <p>Republic of the Philippines · Province of Cavite</p>
    <p><strong>MUNICIPALITY OF INDANG</strong></p>
    <h2>ANNUAL INVESTMENT PROGRAM (AIP)</h2>
    <p>CY <?= AIP_FISCAL_YEAR ?> · By Program/Project/Activity by Sector</p>
    <p class="aip-print-caption"><?= e($aipCaption) ?> · Development version: sample data</p>
</div>
<div class="table-wrap aip-form-wrap">
    <table class="aip-form">
        <thead>
            <tr>
                <th rowspan="2">AIP Reference Code</th>
                <th rowspan="2">Program / Project / Activity Description</th>
                <th rowspan="2">Implementing Office</th>
                <th colspan="2">Schedule of Implementation</th>
                <th rowspan="2">Expected Outputs</th>
                <th rowspan="2">Funding Source</th>
                <th colspan="4"><?= e($unit) ?></th>
                <th colspan="2">Climate Change Expenditure</th>
                <th rowspan="2">CC Typology Code</th>
            </tr>
            <tr><th>Start</th><th>Completion</th><th>PS</th><th>MOOE</th><th>CO</th><th>Total</th><th>Adaptation</th><th>Mitigation</th></tr>
        </thead>
        <tbody>
        <?php if (!$aipEntries): ?>
            <tr><td class="empty-cell" colspan="14">No approved PPAs to consolidate yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($aipStructureRows as $sectorCode => $sector): ?>
            <tr class="aip-sector-row"><td colspan="14"><?= e(strtoupper($sector['name'])) ?> <span>(<?= $sector['totals']['count'] ?> PPA<?= $sector['totals']['count'] === 1 ? '' : 's' ?>)</span></td></tr>
            <?php foreach ($sector['offices'] as $officeCode => $office): ?>
                <tr class="aip-office-row"><td colspan="14"><?= e($office['name']) ?></td></tr>
                <?php foreach ($office['entries'] as $entry): ?>
                    <tr>
                        <td class="nowrap"><?= e($entry['ref']) ?></td>
                        <td class="aip-desc"><strong><?= e($entry['title']) ?></strong><small><?= e($entry['type']) ?><?= $entry['program'] !== '' ? ' · ' . e($entry['program']) : '' ?></small></td>
                        <td><?= e($entry['office']) ?></td>
                        <td class="nowrap"><?= e(date('M Y', strtotime($entry['start']))) ?></td>
                        <td class="nowrap"><?= e(date('M Y', strtotime($entry['end']))) ?></td>
                        <td class="aip-desc"><?= e($entry['expected_output']) ?></td>
                        <td><?= e($entry['fund_label']) ?></td>
                        <td class="num"><?= $fmt($entry['ps']) ?></td>
                        <td class="num"><?= $fmt($entry['mooe']) ?></td>
                        <td class="num"><?= $fmt($entry['co']) ?></td>
                        <td class="num strong"><?= $fmt($entry['cost']) ?></td>
                        <td class="num"><?= $fmt($entry['cc_adaptation']) ?></td>
                        <td class="num"><?= $fmt($entry['cc_mitigation']) ?></td>
                        <td><?= e($entry['cc_typology']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="aip-subtotal"><td colspan="<?= $colspanDesc + 1 ?>">Subtotal, <?= e($office['name']) ?></td>
                    <td class="num"><?= $fmt($office['totals']['ps']) ?></td><td class="num"><?= $fmt($office['totals']['mooe']) ?></td><td class="num"><?= $fmt($office['totals']['co']) ?></td><td class="num"><?= $fmt($office['totals']['cost']) ?></td><td class="num"><?= $fmt($office['totals']['cc_adaptation']) ?></td><td class="num"><?= $fmt($office['totals']['cc_mitigation']) ?></td><td></td></tr>
            <?php endforeach; ?>
            <tr class="aip-sector-total"><td colspan="<?= $colspanDesc + 1 ?>">Total, <?= e($sector['name']) ?></td>
                <td class="num"><?= $fmt($sector['totals']['ps']) ?></td><td class="num"><?= $fmt($sector['totals']['mooe']) ?></td><td class="num"><?= $fmt($sector['totals']['co']) ?></td><td class="num"><?= $fmt($sector['totals']['cost']) ?></td><td class="num"><?= $fmt($sector['totals']['cc_adaptation']) ?></td><td class="num"><?= $fmt($sector['totals']['cc_mitigation']) ?></td><td></td></tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($aipEntries): ?>
        <tfoot>
            <tr class="aip-grand-total"><td colspan="<?= $colspanDesc + 1 ?>">GRAND TOTAL (<?= $aipGrand['count'] ?> PPA<?= $aipGrand['count'] === 1 ? '' : 's' ?>)</td>
                <td class="num"><?= $fmt($aipGrand['ps']) ?></td><td class="num"><?= $fmt($aipGrand['mooe']) ?></td><td class="num"><?= $fmt($aipGrand['co']) ?></td><td class="num"><?= $fmt($aipGrand['cost']) ?></td><td class="num"><?= $fmt($aipGrand['cc_adaptation']) ?></td><td class="num"><?= $fmt($aipGrand['cc_mitigation']) ?></td><td></td></tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
<div class="aip-signatories">
    <div><span>Prepared by:</span><b>&nbsp;</b><strong>Municipal Planning and Development Coordinator</strong></div>
    <div><span>Attested by:</span><b>&nbsp;</b><strong>Municipal Budget Officer</strong></div>
    <div><span>Approved by:</span><b>&nbsp;</b><strong>Municipal Mayor</strong></div>
</div>
