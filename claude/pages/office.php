<?php
/**
 * One implementing office and everything linked to it: its PPAs,
 * the fund sources they draw on, their approval records and its users.
 */
$pageSection = 'Planning';
$activePage = 'linking';
$requiredPermission = 'linking.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$code = (string) ($_GET['code'] ?? '');
$offices = mockOffices();
if (!isset($offices[$code]) || !canSeeOffice($code)) {
    flash('error', 'That office was not found or you do not have access to it.');
    header('Location: project-linking.php?tab=offices');
    exit;
}
$pageTitle = $offices[$code];
$o = officeLinks($code);
$byStatus = array_count_values(array_column($o['ppas'], 'status'));
$tableId = 'office-ppas';

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="project-linking.php?tab=offices">Offices</a> / <?= e($code) ?></p>
        <h1><?= e($offices[$code]) ?></h1>
        <p class="muted">Implementing office · <?= count($o['ppas']) ?> submitted PPA<?= count($o['ppas']) === 1 ? '' : 's' ?> for FY <?= AIP_FISCAL_YEAR ?></p>
    </div>
    <div class="heading-actions">
        <?php if (can('consolidation.view')): ?><a class="secondary-button" href="aip-consolidation.php?office=<?= urlencode($code) ?>">View in consolidated AIP</a><?php endif; ?>
    </div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">PPAs</span><span class="stat-icon blue">▤</span></div><strong><?= count($o['ppas']) ?></strong><p><?= count($o['approved']) ?> approved · <?= ($byStatus['for_review'] ?? 0) + ($byStatus['recommended'] ?? 0) ?> in process · <?= $byStatus['returned'] ?? 0 ?> returned</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Proposed cost</span><span class="stat-icon green">₱</span></div><strong><?= pesoShort($o['cost']) ?></strong><p>Excluding disapproved PPAs</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Fund sources used</span><span class="stat-icon amber">▥</span></div><strong><?= count($o['funds']) ?></strong><p><?= e(implode(', ', array_map('fundName', array_keys($o['funds'])))) ?: '—' ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Approval records</span><span class="stat-icon blue">▣</span></div><strong><?= count($o['records']) ?></strong><p><?= count(array_filter($o['approved'], fn ($p) => !ppaApprovalRecords($p['ref']))) ?> approved PPA(s) without a record</p></article>
</section>

<section class="panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="10">
    <div class="panel-heading"><div><h2>PPAs of this office</h2><p class="muted">With the fund sources and approval records linked to each.</p></div><button class="filter-button" type="button" data-export-csv="<?= e(strtolower($code)) ?>-ppas.csv" data-for="<?= $tableId ?>">⇩ Export</button></div>
    <div class="table-wrap">
        <table class="activity-table ppa-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Status</th><th>Cost</th><th>Fund sources</th><th>Approval records</th></tr></thead>
            <tbody>
            <?php foreach ($o['ppas'] as $p): $recs = ppaApprovalRecords($p['ref']); ?>
                <tr data-row>
                    <td><a class="ref-link" href="ppa-view.php?ref=<?= urlencode($p['ref']) ?>"><?= e($p['ref']) ?></a></td>
                    <td class="title-cell"><strong><?= e($p['title']) ?></strong><small class="table-subtext"><?= e($p['type']) ?> · <?= e($p['program']) ?></small></td>
                    <td><?= statusPill($p['status']) ?></td>
                    <td class="amount" data-export="<?= number_format($p['cost'], 2, '.', '') ?>"><?= peso($p['cost']) ?></td>
                    <td><?= e(ppaFundLabel($p)) ?></td>
                    <td data-export="<?= e(implode('; ', array_column($recs, 'no'))) ?>"><?php foreach ($recs as $r): ?><a class="record-link" href="approval-record.php?no=<?= urlencode($r['no']) ?>"><?= e($r['no']) ?></a><?php endforeach; ?><?= $recs ? '' : '<span class="table-subtext">' . (in_array($p['status'], LINK_APPROVED_STATUSES, true) ? 'Missing' : '—') . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="6">This office has not submitted any PPAs yet.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="PPAs"></div>
</section>

<div class="consolidation-grid">
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon amber-bg">▥</span><div><h2>Funding</h2><p>Amounts charged to each fund source</p></div></div>
        <div class="form-divider"></div>
        <table class="summary-table">
            <?php foreach ($o['funds'] as $key => $amount): ?><tr><th><?php if (can('funds.view')): ?><a href="fund-source.php?key=<?= urlencode($key) ?>"><?= e(fundName($key)) ?></a><?php else: ?><?= e(fundName($key)) ?><?php endif; ?></th><td class="amount"><?= peso($amount) ?></td></tr><?php endforeach; ?>
            <?php if (!$o['funds']): ?><tr><td class="empty-cell">No funded PPAs yet.</td></tr><?php endif; ?>
        </table>
    </section>
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon blue-bg">♙</span><div><h2>Users</h2><p>Accounts assigned to this office</p></div></div>
        <div class="form-divider"></div>
        <?php if (!$o['encoders']): ?><p class="funding-issue warning">No active encoder is assigned to this office<?= can('users.manage') ? '. <a href="user-form.php">Create an account</a>' : '' ?>.</p><?php endif; ?>
        <ul class="linked-list">
            <?php foreach ($o['users'] as $u): ?><li><strong><?= e($u['name']) ?></strong> <?= statusPill($u['status'] === 'Active' ? 'approved' : 'draft', $u['status']) ?><small><?= e($u['role']) ?><?= $u['position'] !== '' ? ' · ' . e($u['position']) : '' ?></small></li><?php endforeach; ?>
            <?php if (!$o['users']): ?><li class="muted">No user accounts.</li><?php endif; ?>
        </ul>
    </section>
</div>
<?php include '../includes/footer.php'; ?>
