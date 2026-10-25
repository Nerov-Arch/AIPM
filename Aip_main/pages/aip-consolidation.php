<?php
$pageTitle = 'AIP Consolidation';
$pageSection = 'Reports & Funds';
$activePage = 'consolidation';
$requiredPermission = 'consolidation.view';
$assetPrefix = '../';
include '../includes/header.php';

// MOCK: PPAs that have reached authorization or beyond.
$statusGroups = [
    'recommended' => 'pending',
    'approved'    => 'approved',
    'ongoing'     => 'approved',
    'completed'   => 'approved',
];
$rows = array_values(array_filter(visiblePpas(), fn ($p) => isset($statusGroups[$p['status']])));
$offices = array_unique(array_column($rows, 'office'));
sort($offices);
$ceilings = array_map(fn ($f) => $f['ceiling'], mockFundSources());
$tableId = 'consolidation-table';
?>
<div class="page-heading report-heading">
    <div><p class="eyebrow">Reports / Consolidation</p><h1>AIP Consolidation</h1><p class="muted">Preview the consolidated Annual Investment Program for the selected fiscal year, then print or export it.</p></div>
    <div class="heading-actions">
        <button class="secondary-button" type="button" data-export-csv="aip-fy<?= AIP_FISCAL_YEAR ?>-draft.csv" data-for="<?= $tableId ?>">⇩ Export CSV</button>
        <button class="primary-button" type="button" data-print>▣ Print Report</button>
    </div>
</div>

<div class="print-header">
    <p>Republic of the Philippines · Province of Cavite</p>
    <p><strong>Municipality of Indang</strong></p>
    <h1>Annual Investment Program (AIP) — FY <?= AIP_FISCAL_YEAR ?></h1>
    <p>Prepared by the Municipal Planning and Development Office · DRAFT (development version, sample data)</p>
</div>

<div class="consolidation-layout">
    <div class="consolidation-sidebar">
        <section class="panel parameter-panel">
            <div class="panel-heading"><h2>☷ Report Parameters</h2></div>
            <label>Fiscal Year <em>*</em><select disabled><option>FY <?= AIP_FISCAL_YEAR ?></option></select></label>
            <label>Implementing Office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices (consolidated)</option><?php foreach ($offices as $code): ?><option value="<?= e($code) ?>"><?= e(officeName($code)) ?></option><?php endforeach; ?></select></label>
            <label>Funding Source<select data-filter="fund" data-for="<?= $tableId ?>"><option value="">All sources</option><?php foreach (mockFundSources() as $code => $fund): ?><option value="<?= e($code) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
            <fieldset>
                <legend>Project Status Inclusion</legend>
                <label class="check-option"><input type="checkbox" value="approved" data-filter-multi="group" data-for="<?= $tableId ?>" checked> <i class="green-dot"></i>Approved (finalized)</label>
                <label class="check-option"><input type="checkbox" value="pending" data-filter-multi="group" data-for="<?= $tableId ?>"> <i class="amber-dot"></i>Pending authorization</label>
            </fieldset>
            <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Reset parameters</button>
        </section>
        <section class="panel overview-panel">
            <div class="panel-heading"><h2>◉ Consolidation Overview</h2></div>
            <span class="stat-label">Total Estimated Cost</span>
            <strong class="big-currency" data-sum="cost" data-for="<?= $tableId ?>">₱0.00</strong>
            <div class="overview-stats">
                <div><span>PPAS INCLUDED</span><strong data-count data-for="<?= $tableId ?>">0</strong></div>
                <div><span>OFFICES</span><strong data-distinct="office" data-for="<?= $tableId ?>">0</strong></div>
            </div>
            <div data-ceiling-meter="<?= e(json_encode($ceilings)) ?>" data-for="<?= $tableId ?>">
                <div class="capital-row"><span>Share of fund ceiling</span><b data-meter-label>0%</b></div>
                <div class="progress-track"><span data-meter-bar style="width:0%"></span></div>
            </div>
        </section>
    </div>
    <section class="panel preview-panel" id="<?= $tableId ?>" data-table-panel data-page-size="8">
        <div class="panel-heading">
            <h2>⊙ Report Preview</h2>
            <div class="density-toggle"><span>Density:</span><button class="active" type="button" data-density="standard" data-for="<?= $tableId ?>">Standard</button><button type="button" data-density="dense" data-for="<?= $tableId ?>">Dense</button></div>
        </div>
        <div class="table-wrap">
            <table class="preview-table">
                <thead><tr><th>Ref No.</th><th>PPA Description</th><th>Implementing Office</th><th>Fund Source</th><th>Schedule</th><th>Estimated Cost</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $ppa): ?>
                    <tr data-row data-group="<?= $statusGroups[$ppa['status']] ?>" data-office="<?= e($ppa['office']) ?>" data-fund="<?= e($ppa['fund']) ?>" data-cost="<?= $ppa['cost'] ?>">
                        <td><?= e($ppa['ref']) ?></td>
                        <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><strong><?= e($ppa['title']) ?></strong><small><?= e($ppa['type']) ?> · <?= e($ppa['program']) ?></small></td>
                        <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                        <td><?= e(fundName($ppa['fund'])) ?></td>
                        <td data-export="<?= e($ppa['start'] . ' to ' . $ppa['end']) ?>"><?= e(date('M Y', strtotime($ppa['start']))) ?> – <?= e(date('M Y', strtotime($ppa['end']))) ?></td>
                        <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                        <td><?= statusPill($ppa['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr data-empty hidden><td class="empty-cell" colspan="7">No PPAs match the selected parameters.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar" data-pager data-noun="PPAs"></div>
    </section>
</div>

<div class="print-footer">
    <div>Prepared by:<br><br>______________________________<br>Municipal Planning and Development Coordinator</div>
    <div>Approved by:<br><br>______________________________<br>Municipal Mayor</div>
</div>
<?php include '../includes/footer.php'; ?>
