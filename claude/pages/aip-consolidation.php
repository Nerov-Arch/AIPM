<?php
/**
 * AIP Consolidation: compiles every approved PPA into the consolidated
 * Annual Investment Program, checks it before saving, and keeps versions.
 */
$pageTitle = 'AIP Consolidation';
$pageSection = 'Reports & Funds';
$activePage = 'consolidation';
$requiredPermission = 'consolidation.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

/* ---------- Generate a version (POST → redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requirePermission('consolidation.manage', $assetPrefix);
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    if (mb_strlen($remarks) > 300) {
        flash('error', 'Keep the version note to 300 characters or fewer.');
    } elseif (aipHasBlockingErrors()) {
        flash('error', 'The AIP was not saved. Resolve the errors under Readiness checks first.');
    } elseif (!aipEntries()) {
        flash('error', 'There are no approved PPAs to consolidate yet.');
    } else {
        $version = aipCreateVersion($remarks);
        auditLog('aip.version', 'Version ' . $version['no'], $version['totals']['count'] . ' PPAs · ' . peso($version['totals']['cost']) . ($remarks !== '' ? ' · ' . $remarks : ''));
        flash('success', 'Saved consolidated AIP version ' . $version['no'] . ' with ' . $version['totals']['count'] . ' approved PPAs (' . e(peso($version['totals']['cost'])) . ').');
        header('Location: aip-version.php?no=' . $version['no']);
        exit;
    }
    header('Location: aip-consolidation.php');
    exit;
}

$filters = [
    'office' => (string) ($_GET['office'] ?? ''),
    'sector' => (string) ($_GET['sector'] ?? ''),
    'fund'   => (string) ($_GET['fund'] ?? ''),
];
$filtered = (bool) array_filter($filters);
$thousands = ($_GET['units'] ?? '') === 'thousands';
$allEntries = aipEntries();
$aipEntries = $filtered ? aipEntries($filters) : $allEntries;
$totals = aipTotals($allEntries);
$checks = aipReadiness();
$blocking = aipHasBlockingErrors($checks);
$versions = aipVersions();
$final = aipFinalVersion();
$bySector = aipSummaryBy($allEntries, 'sector');
$byFund = aipSummaryBy($allEntries, 'fund');
$byOffice = aipSummaryBy($allEntries, 'office');
$undecided = count(array_filter(storePpas(), fn ($p) => in_array($p['status'], AIP_UNDECIDED_STATUSES, true)));
$query = fn (array $extra) => '?' . http_build_query(array_filter(array_merge($filters, ['units' => $thousands ? 'thousands' : ''], $extra)));
$aipCaption = $filtered ? 'Preview (filtered)' : 'Current consolidation, not yet saved as a version';

include '../includes/header.php';
?>
<div class="page-heading report-heading no-print">
    <div><p class="eyebrow">Reports / Consolidation</p><h1>AIP Consolidation</h1><p class="muted">All approved PPAs are compiled automatically into the FY <?= AIP_FISCAL_YEAR ?> Annual Investment Program, grouped by sector and implementing office.</p></div>
    <div class="heading-actions">
        <a class="secondary-button" href="aip-export.php<?= $thousands ? '?units=thousands' : '' ?>">⇩ Export CSV</a>
        <button class="secondary-button" type="button" data-print>▣ Print AIP</button>
        <?php if (can('consolidation.manage')): ?><button class="primary-button" type="button" data-dialog-open="#version-dialog" <?= $blocking ? 'disabled title="Resolve the readiness errors first"' : '' ?>>✓ Save as version</button><?php endif; ?>
    </div>
</div>

<section class="stat-grid four no-print">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Approved PPAs included</span><span class="stat-icon green">✓</span></div><strong><?= $totals['count'] ?></strong><p><?= $undecided ?> more still under review, authorization or revision</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total AIP cost</span><span class="stat-icon blue">₱</span></div><strong><?= pesoShort($totals['cost']) ?></strong><p>PS <?= pesoShort($totals['ps']) ?> · MOOE <?= pesoShort($totals['mooe']) ?> · CO <?= pesoShort($totals['co']) ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Climate change expenditure</span><span class="stat-icon green">♻</span></div><strong><?= pesoShort($totals['cc_adaptation'] + $totals['cc_mitigation']) ?></strong><p><?= $totals['cost'] > 0 ? number_format(($totals['cc_adaptation'] + $totals['cc_mitigation']) / $totals['cost'] * 100, 1) : '0.0' ?>% of total · adaptation <?= pesoShort($totals['cc_adaptation']) ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Saved versions</span><span class="stat-icon amber">▣</span></div><strong><?= count($versions) ?></strong><p><?= $final ? 'Final: version ' . $final['no'] . ' (' . e(date('M j, Y', strtotime($final['finalized_at']))) . ')' : 'No final version yet' ?></p></article>
</section>

<div class="consolidation-grid no-print">
    <section class="panel readiness-panel">
        <div class="panel-heading"><div><h2>Readiness checks</h2><p class="muted"><?= $blocking ? 'Errors must be resolved before a version can be saved.' : 'No blocking errors. The AIP can be saved as a version.' ?></p></div><?= $blocking ? '<span class="status-pill rejected"><i></i>Not ready</span>' : '<span class="status-pill approved"><i></i>Ready</span>' ?></div>
        <?php if (!$checks): ?><p class="empty-state">All checks passed.</p><?php endif; ?>
        <ul class="issue-list compact">
            <?php foreach ($checks as $check): ?>
                <li class="<?= $check['level'] ?>">
                    <span class="issue-level"><?= ucfirst($check['level']) ?></span>
                    <div><strong><?= e($check['title']) ?></strong><p><?= e($check['detail']) ?></p>
                        <?php if ($check['refs']): ?><p class="ref-list"><?php foreach (array_slice($check['refs'], 0, 8) as $ref): ?><a href="ppa-view.php?ref=<?= urlencode($ref) ?>"><?= e($ref) ?></a> <?php endforeach; ?><?= count($check['refs']) > 8 ? '…' : '' ?></p><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="panel summary-panel">
        <div class="panel-heading"><div><h2>Summary</h2><p class="muted">All approved PPAs</p></div>
            <div class="segmented-control" role="group" aria-label="Summary by">
                <button class="active" type="button" data-view-group="summary" data-view-show="#sum-sector" aria-pressed="true">Sector</button>
                <button type="button" data-view-group="summary" data-view-show="#sum-fund" aria-pressed="false">Fund</button>
                <button type="button" data-view-group="summary" data-view-show="#sum-office" aria-pressed="false">Office</button>
            </div>
        </div>
        <?php foreach (['sector' => [$bySector, fn ($k) => ppaSectors()[$k] ?? $k], 'fund' => [$byFund, 'fundName'], 'office' => [$byOffice, 'officeName']] as $id => [$rows, $namer]): ?>
            <table class="summary-table" id="sum-<?= $id ?>" <?= $id !== 'sector' ? 'hidden' : '' ?>>
                <?php foreach ($rows as $key => $row): $share = $totals['cost'] > 0 ? $row['amount'] / $totals['cost'] * 100 : 0; ?>
                    <tr><th><?= e($namer($key)) ?><small><?= $row['count'] ?> PPA<?= $row['count'] === 1 ? '' : 's' ?></small></th><td><div class="mini-bar"><span style="width:<?= round($share) ?>%"></span></div></td><td class="amount"><?= pesoShort($row['amount']) ?><small><?= number_format($share, 1) ?>%</small></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td class="empty-cell">No approved PPAs yet.</td></tr><?php endif; ?>
            </table>
        <?php endforeach; ?>
    </section>
</div>

<section class="panel flush-panel aip-panel">
    <div class="panel-heading no-print">
        <div><h2>Consolidated AIP</h2><p class="muted"><?= $filtered ? 'Filtered preview: ' . count($aipEntries) . ' of ' . $totals['count'] . ' PPAs. Saved versions always include all approved PPAs.' : 'Standard AIP form layout. ' . $totals['count'] . ' approved PPAs.' ?></p></div>
        <div class="heading-actions">
            <div class="segmented-control" role="group" aria-label="Units">
                <a class="<?= $thousands ? '' : 'active' ?>" href="<?= e($query(['units' => ''])) ?>">₱</a>
                <a class="<?= $thousands ? 'active' : '' ?>" href="<?= e($query(['units' => 'thousands'])) ?>">₱'000</a>
            </div>
        </div>
    </div>
    <form class="filter-bar no-print" method="get">
        <label>Sector<select name="sector"><option value="">All sectors</option><?php foreach (ppaSectors() as $code => $name): ?><option value="<?= e($code) ?>" <?= $filters['sector'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label>Implementing office<select name="office"><option value="">All offices</option><?php foreach (mockOffices() as $code => $name): ?><option value="<?= e($code) ?>" <?= $filters['office'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label>Fund source<select name="fund"><option value="">All sources</option><?php foreach (fundSources() as $code => $fund): ?><option value="<?= e($code) ?>" <?= $filters['fund'] === $code ? 'selected' : '' ?>><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
        <?php if ($thousands): ?><input type="hidden" name="units" value="thousands"><?php endif; ?>
        <button class="secondary-button" type="submit">Apply</button>
        <?php if ($filtered): ?><a class="text-button" href="aip-consolidation.php<?= $thousands ? '?units=thousands' : '' ?>">Clear filters</a><?php endif; ?>
    </form>
    <?php $aipThousands = $thousands; include '../includes/aip-table.php'; ?>
</section>

<section class="panel flush-panel no-print">
    <div class="panel-heading"><div><h2>Saved versions</h2><p class="muted">Each version is a snapshot of the consolidated AIP at the time it was saved.</p></div></div>
    <?php if (!$versions): ?>
        <p class="empty-state">No versions saved yet.<?= can('consolidation.manage') ? ' Use “Save as version” when the readiness checks pass.' : ' The MPDO reviewer saves versions.' ?></p>
    <?php else: ?>
        <div class="table-wrap"><table class="activity-table">
            <thead><tr><th>Version</th><th>Status</th><th>PPAs</th><th>Total cost</th><th>Saved by</th><th>Note</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($versions as $v): ?>
                <tr>
                    <td><a class="ref-link" href="aip-version.php?no=<?= $v['no'] ?>">Version <?= $v['no'] ?></a><small class="table-subtext"><?= e($v['kind']) ?></small></td>
                    <td><?= $v['status'] === 'final' ? statusPill('approved', 'Final') : ($v['status'] === 'superseded' ? statusPill('draft', 'Superseded') : statusPill('for_review', 'Draft')) ?></td>
                    <td><?= $v['totals']['count'] ?></td>
                    <td class="amount"><?= peso($v['totals']['cost']) ?></td>
                    <td><?= e(explode(' (', $v['created_by'])[0]) ?><small class="table-subtext"><?= e(date('M j, Y g:i A', strtotime($v['created_at']))) ?></small></td>
                    <td class="title-cell"><?= e($v['remarks'] ?: '—') ?></td>
                    <td><a class="review-action quiet" href="aip-version.php?no=<?= $v['no'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if (can('consolidation.manage')): ?>
<dialog class="form-dialog" id="version-dialog" aria-labelledby="version-dialog-title">
    <form method="post">
        <?= csrfField() ?>
        <div class="dialog-head"><div><p class="eyebrow">Consolidated AIP</p><h2 id="version-dialog-title">Save as version <?= count($versions) + 1 ?></h2></div><button class="dialog-close" type="button" aria-label="Close" data-dialog-close>×</button></div>
        <p class="muted">This saves a snapshot of all <?= $totals['count'] ?> approved PPAs (<?= peso($totals['cost']) ?>). Later changes to PPAs do not alter a saved version.</p>
        <label class="full">Version note <small>(optional)</small><input name="remarks" type="text" maxlength="300" placeholder="e.g. For Local Development Council review"></label>
        <?php if ($final): ?><p class="dialog-note">A final version already exists (version <?= $final['no'] ?>). This will be saved as an amendment.</p><?php endif; ?>
        <div class="dialog-actions"><button class="secondary-button" type="button" data-dialog-close>Cancel</button><button class="primary-button" type="submit">Save version</button></div>
    </form>
</dialog>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
