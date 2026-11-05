<?php
/**
 * Expenditure Audit: every expense of approved PPAs, with its receipt and audit
 * status. Audit staff use it to find expenses to verify; offices see their own.
 */
$pageTitle = 'Expenditure Audit';
$pageSection = 'Workflow';
$activePage = 'expenses';
$requiredPermission = 'expenses.view';
$assetPrefix = '../';
include '../includes/header.php';

$expenses = visibleExpenses();
$counts = expenseAuditCounts($expenses);
$total = array_sum(array_column($expenses, 'amount'));
$flaggedAmount = array_sum(array_map(fn ($x) => $x['audit']['status'] === 'flagged' ? $x['amount'] : 0, $expenses));
$withReceipt = count(array_filter($expenses, fn ($x) => $x['file'] !== null));
$offices = array_unique(array_map(fn ($x) => $x['ppa']['office'], $expenses));
sort($offices);

// Spending per PPA under implementation
$ppaRows = [];
foreach (visiblePpas() as $ppa) {
    if (in_array($ppa['status'], ['approved', 'ongoing', 'completed'], true)) {
        $spent = ppaExpenseTotal($ppa['ref']);
        $ppaRows[] = ['ppa' => $ppa, 'spent' => $spent, 'count' => count(ppaExpenses($ppa['ref']))];
    }
}
$tableId = 'expense-table';
?>
<div class="page-heading">
    <div><p class="eyebrow">Implementation · Audit</p><h1>Expenditure Audit</h1><p class="muted">Expenses recorded by implementing offices, with receipts and an expenditure timeline for each PPA.<?= can('scope.all_offices') ? '' : ' Showing your office only.' ?></p></div>
    <div class="segmented-control" role="group" aria-label="Show expenses">
        <button class="active" type="button" data-tab="unverified" data-for="<?= $tableId ?>" aria-pressed="true">Not yet audited (<?= $counts['unverified'] ?>)</button>
        <button type="button" data-tab="flagged" data-for="<?= $tableId ?>" aria-pressed="false">Flagged (<?= $counts['flagged'] ?>)</button>
        <button type="button" data-tab="verified" data-for="<?= $tableId ?>" aria-pressed="false">Verified (<?= $counts['verified'] ?>)</button>
    </div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total expenses</span><span class="stat-icon blue">⎙</span></div><strong data-kpi="expense-total"><?= pesoShort($total) ?></strong><p><?= count($expenses) ?> expense<?= count($expenses) === 1 ? '' : 's' ?> recorded</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Not yet audited</span><span class="stat-icon amber">◔</span></div><strong data-kpi="unverified"><?= $counts['unverified'] ?></strong><p>Waiting for audit review</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Flagged</span><span class="stat-icon amber">⚑</span></div><strong data-kpi="flagged" class="<?= $counts['flagged'] ? 'orange-number' : '' ?>"><?= $counts['flagged'] ?></strong><p><?= pesoShort($flaggedAmount) ?> under question</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">With receipt image</span><span class="stat-icon green">▣</span></div><strong><?= $withReceipt ?> / <?= count($expenses) ?></strong><p>Sample records have no image</p></article>
</section>

<section class="panel review-table-panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="8">
    <div class="panel-heading">
        <h2>Expenses (<span data-count data-for="<?= $tableId ?>"><?= $counts['unverified'] ?></span>)</h2>
        <div class="heading-actions">
            <button class="filter-button" type="button" data-toggle-target="#expense-filters">☷ Filter Options</button>
            <button class="filter-button" type="button" data-export-csv="expenditures.csv" data-for="<?= $tableId ?>">⇩ Export</button>
        </div>
    </div>
    <div class="filter-bar" id="expense-filters" hidden>
        <label>Implementing office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices</option><?php foreach ($offices as $code): ?><option value="<?= e($code) ?>"><?= e(officeName($code)) ?></option><?php endforeach; ?></select></label>
        <label>Search<input type="search" placeholder="Description, receipt or ref no." data-search data-for="<?= $tableId ?>"></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th>Date</th><th>PPA</th><th>Description</th><th>Receipt no.</th><th>Amount</th><th>Receipt</th><th>Audit</th><th data-no-export></th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $x): ?>
                <tr data-row data-state="<?= e($x['audit']['status']) ?>" data-office="<?= e($x['ppa']['office']) ?>">
                    <td data-export="<?= e($x['date']) ?>"><?= e(displayDate($x['date'])) ?></td>
                    <td class="title-cell" data-export="<?= e($x['ref']) ?>"><a class="ref-link" href="ppa-expenses.php?ref=<?= urlencode($x['ref']) ?>"><?= e($x['ref']) ?></a><small class="table-subtext"><?= e($x['ppa']['office']) ?> · <?= e(mb_strimwidth($x['ppa']['title'], 0, 40, '…')) ?></small></td>
                    <td><?= e($x['description']) ?></td>
                    <td><?= e($x['receipt_no']) ?></td>
                    <td class="amount" data-export="<?= number_format($x['amount'], 2, '.', '') ?>"><?= peso($x['amount']) ?></td>
                    <td data-export="<?= $x['file'] ? 'Attached' : 'None' ?>"><?php if ($x['file']): ?><a class="text-button" href="receipt.php?id=<?= $x['id'] ?>" target="_blank" rel="noopener">View</a><?php else: ?><span class="table-subtext">None (sample)</span><?php endif; ?></td>
                    <td data-export="<?= e($x['audit']['status']) ?>"><?= expenseAuditPill($x) ?></td>
                    <td><a class="review-action" href="ppa-expenses.php?ref=<?= urlencode($x['ref']) ?>#expense-<?= $x['id'] ?>"><?= can('expenses.verify') && $x['audit']['status'] === 'unverified' ? 'Audit' : 'Timeline' ?></a></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="8">No expenses match the current view or filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="expenses"></div>
</section>

<section class="panel">
    <div class="panel-heading"><div><h2>Spending per PPA</h2><p class="muted">Approved budget against recorded expenses</p></div></div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Approved budget</th><th>Spent</th><th>Unutilized</th><th>Used</th><th>Expenses</th></tr></thead>
            <tbody>
            <?php foreach ($ppaRows as $row): $p = $row['ppa']; $pct = $p['cost'] > 0 ? $row['spent'] / $p['cost'] * 100 : 0; ?>
                <tr>
                    <td><a class="ref-link" href="ppa-expenses.php?ref=<?= urlencode($p['ref']) ?>"><?= e($p['ref']) ?></a></td>
                    <td class="title-cell"><strong><?= e($p['title']) ?></strong><small class="table-subtext"><?= e(officeName($p['office'])) ?></small></td>
                    <td class="amount"><?= peso((float) $p['cost']) ?></td>
                    <td><?= peso($row['spent']) ?></td>
                    <td><?= peso(max(0, (float) $p['cost'] - $row['spent'])) ?></td>
                    <td><div class="table-progress"><div class="progress-track"><span style="width:<?= min(100, round($pct)) ?>%"></span></div><b><?= round($pct) ?>%</b></div></td>
                    <td><?= $row['count'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ppaRows): ?><tr><td class="empty-cell" colspan="7">No approved PPAs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include '../includes/footer.php'; ?>
