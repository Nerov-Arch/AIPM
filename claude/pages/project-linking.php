<?php
/**
 * Project Linking & Traceability: every PPA with its implementing office,
 * fund sources and approval records; the approval-record registry; office
 * summaries; and checks for missing or broken links.
 */
$pageTitle = 'Project Linking & Traceability';
$pageSection = 'Planning';
$activePage = 'linking';
$requiredPermission = 'linking.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$canManage = can('links.manage');
$linkLabels = ['linked' => 'Fully Linked', 'missing' => 'Missing Approval Record', 'not-approved' => 'Not Yet Approved'];
$linkClasses = ['linked' => 'approved', 'missing' => 'pending', 'not-approved' => 'inactive'];
$links = [];
foreach (visiblePpas() as $ppa) {   // Encoders see only their own office
    $links[] = $ppa + ['link' => ppaLinkStatus($ppa), 'records' => ppaApprovalRecords($ppa['ref'])];
}
$visibleRefs = array_column($links, 'ref');
$records = array_filter(approvalRecords(), fn ($r) => can('scope.all_offices') || array_intersect($r['ppas'], $visibleRefs));
$offices = array_filter(mockOffices(), fn ($n, $code) => canSeeOffice($code), ARRAY_FILTER_USE_BOTH);
$issues = array_values(array_filter(linkIssues(), fn ($i) => can('scope.all_offices') || ($i['office'] !== '' && canSeeOffice($i['office']))));
$counts = array_count_values(array_column($links, 'link'));
$query = trim($_GET['q'] ?? '');
$tab = in_array($_GET['tab'] ?? '', ['ppas', 'records', 'offices', 'checks'], true) ? $_GET['tab'] : 'ppas';
$tableId = 'linking-table';

include '../includes/header.php';
?>
<div class="page-heading linking-heading">
    <div><p class="eyebrow">Traceability</p><h1>Project Linking &amp; Traceability</h1><p class="muted">Each PPA connected to its implementing office, fund sources and approval records, so its complete information can be traced in one place.</p></div>
    <div class="heading-actions">
        <?php if ($canManage): ?><a class="primary-button" href="approval-record.php?new=1">＋ Register Approval Record</a><?php endif; ?>
    </div>
</div>

<?php if ($query !== ''): ?>
    <div class="alert success" role="status">Showing results for “<?= e($query) ?>”. <a href="project-linking.php">Clear search</a></div>
<?php endif; ?>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Fully linked PPAs</span><span class="stat-icon green">⛓</span></div><strong><?= $counts['linked'] ?? 0 ?></strong><p>Office, fund source and approval record connected</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Missing approval record</span><span class="stat-icon amber">!</span></div><strong class="<?= ($counts['missing'] ?? 0) ? 'orange-number' : '' ?>"><?= $counts['missing'] ?? 0 ?></strong><p>Approved but no resolution linked</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Approval records</span><span class="stat-icon blue">▣</span></div><strong><?= count($records) ?></strong><p><?= count(array_filter($records, fn ($r) => !$r['ppas'] && !$r['aip_version'])) ?> not linked to anything</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Link checks</span><span class="stat-icon <?= $issues ? 'amber' : 'green' ?>">✓</span></div><strong class="<?= $issues ? 'orange-number' : '' ?>"><?= count($issues) ?></strong><p><?= $issues ? 'Issues to review' : 'All links are consistent' ?></p></article>
</section>

<div class="tab-bar" role="tablist" aria-label="Linking sections">
    <?php foreach (['ppas' => 'PPA links (' . count($links) . ')', 'records' => 'Approval records (' . count($records) . ')', 'offices' => 'Offices (' . count($offices) . ')', 'checks' => 'Link checks (' . count($issues) . ')'] as $key => $label): ?>
        <button type="button" role="tab" class="<?= $tab === $key ? 'active' : '' ?>" aria-pressed="<?= $tab === $key ? 'true' : 'false' ?>" data-view-group="link-tabs" data-view-show="#tab-<?= $key ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
</div>

<!-- ======================= PPA links ======================= -->
<div id="tab-ppas" <?= $tab !== 'ppas' ? 'hidden' : '' ?>>
    <div class="view-switch-row">
        <div class="segmented-control view-switch" role="group" aria-label="View">
            <button class="active" type="button" data-view-group="linking" data-view-show="#linking-standard" aria-pressed="true">Table View</button>
            <button type="button" data-view-group="linking" data-view-show="#linking-graph" aria-pressed="false">Graph View</button>
        </div>
        <button class="secondary-button" type="button" data-export-csv="ppa-traceability.csv" data-for="<?= $tableId ?>">⇩ Export</button>
    </div>
    <div id="linking-standard">
        <section class="panel linking-panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="8">
            <div class="filter-bar" id="linking-filters">
                <label>Link status<select data-filter="link" data-for="<?= $tableId ?>"><option value="">All</option><?php foreach ($linkLabels as $value => $label): ?><option value="<?= $value ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label>Implementing office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select></label>
                <label>Fund source<select data-filter="fund" data-for="<?= $tableId ?>"><option value="">All sources</option><?php foreach (fundSources() as $code => $fund): ?><option value="<?= e($code) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
                <label>Search<input type="search" placeholder="Title, ref no., office, resolution..." value="<?= e($query) ?>" data-search data-for="<?= $tableId ?>"></label>
                <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Clear filters</button>
            </div>
            <div class="table-wrap">
                <table class="activity-table linking-table">
                    <thead><tr><th>Source PPA<br>(Program/Project/Activity)</th><th>Implementing<br>Office</th><th>Funding Source &amp; Allocation</th><th>Approval Record</th><th>Status &amp; Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($links as $ppa): $alloc = ppaAllocations($ppa); ?>
                        <tr data-row data-link="<?= $ppa['link'] ?>" data-office="<?= e($ppa['office']) ?>" data-fund="<?= e($ppa['fund']) ?>" data-cost="<?= $ppa['cost'] ?>">
                            <td class="title-cell" data-export="<?= e($ppa['ref'] . ' — ' . $ppa['title']) ?>"><span class="ref-pill"><?= e($ppa['ref']) ?></span><a class="title-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>#trace"><strong><?= e($ppa['title']) ?></strong></a><small class="table-subtext">▧ <?= e($ppa['program']) ?> · <?= e(statusLabel($ppa['status'])) ?></small></td>
                            <td data-export="<?= e(officeName($ppa['office'])) ?>"><a class="title-link" href="office.php?code=<?= urlencode($ppa['office']) ?>"><strong><?= e($ppa['office']) ?></strong></a><small class="table-subtext"><?= e($ppa['officer']) ?></small></td>
                            <td data-export="<?= e(implode('; ', array_map(fn ($k, $v) => fundName($k) . ' ' . number_format($v, 2, '.', ''), array_keys($alloc), $alloc))) ?>">
                                <?php foreach ($alloc as $key => $amount): ?>
                                    <div class="alloc-line"><?php if (can('funds.view')): ?><a class="fund-link" href="fund-source.php?key=<?= urlencode($key) ?>">▥ <?= e(fundName($key)) ?></a><?php else: ?><span class="fund-link">▥ <?= e(fundName($key)) ?></span><?php endif; ?><b><?= peso($amount) ?></b></div>
                                <?php endforeach; ?>
                            </td>
                            <td data-export="<?= e(implode('; ', array_column($ppa['records'], 'no'))) ?>">
                                <?php foreach ($ppa['records'] as $record): ?>
                                    <a class="record-link" href="approval-record.php?no=<?= urlencode($record['no']) ?>"><?= e($record['no']) ?></a><small class="table-subtext"><?= e($record['type']) ?> · <?= e(displayDate($record['date'])) ?></small>
                                <?php endforeach; ?>
                                <?php if (!$ppa['records']): ?>
                                    <span class="pending-record"><?= $ppa['link'] === 'missing' ? 'Pending linkage' : 'Awaiting approval' ?></span>
                                    <?php if ($canManage && $ppa['link'] === 'missing'): ?><a class="attach-link" href="link-record.php?ref=<?= urlencode($ppa['ref']) ?>">Attach Record</a><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td data-export="<?= e($linkLabels[$ppa['link']]) ?>"><div class="status-action"><span class="status-pill <?= $linkClasses[$ppa['link']] ?>"><i></i><?= e($linkLabels[$ppa['link']]) ?></span>
                                <?php if ($canManage && $ppa['link'] !== 'not-approved'): ?><a class="text-button" href="link-record.php?ref=<?= urlencode($ppa['ref']) ?>">Manage</a><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr data-empty hidden><td class="empty-cell" colspan="5">No PPAs match your search or filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-bar" data-pager data-noun="PPAs"></div>
        </section>
    </div>

    <section class="panel linking-graph" id="linking-graph" hidden>
        <div class="panel-heading">
            <div><h2>Traceability graph</h2><p class="muted">Choose a PPA to see everything it is connected to.</p></div>
            <label class="visually-hidden" for="trace-select">PPA</label>
            <select id="trace-select" class="small-select" data-trace-select>
                <?php foreach ($links as $ppa): ?><option value="<?= e($ppa['ref']) ?>"><?= e($ppa['ref'] . ' — ' . $ppa['title']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php foreach ($links as $i => $ppa): ?>
            <div class="trace-map" data-trace="<?= e($ppa['ref']) ?>" <?= $i > 0 ? 'hidden' : '' ?>>
                <div class="trace-column"><span class="trace-heading">Implementing office</span><a class="trace-node" href="office.php?code=<?= urlencode($ppa['office']) ?>"><span><?= e($ppa['office']) ?></span><strong><?= e(officeName($ppa['office'])) ?></strong></a></div>
                <div class="trace-column center"><span class="trace-heading">PPA</span><a class="trace-node source" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><span><?= e($ppa['ref']) ?> · <?= e(statusLabel($ppa['status'])) ?></span><strong><?= e($ppa['title']) ?></strong><em><?= peso($ppa['cost']) ?></em></a></div>
                <div class="trace-column">
                    <span class="trace-heading">Fund sources</span>
                    <?php foreach (ppaAllocations($ppa) as $key => $amount): ?><div class="trace-node fund"><span><?= e(fundSources()[$key]['code'] ?? $key) ?></span><strong><?= e(fundName($key)) ?> · <?= e(pesoShort($amount)) ?></strong></div><?php endforeach; ?>
                    <span class="trace-heading">Approval records</span>
                    <?php foreach ($ppa['records'] as $record): ?><a class="trace-node record" href="approval-record.php?no=<?= urlencode($record['no']) ?>"><span><?= e($record['type']) ?> · <?= e(displayDate($record['date'])) ?></span><strong><?= e($record['no']) ?></strong></a><?php endforeach; ?>
                    <?php if (!$ppa['records']): ?><div class="trace-node missing"><span>Approval record</span><strong><?= e($linkLabels[$ppa['link']]) ?></strong></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<!-- ======================= Approval records ======================= -->
<div id="tab-records" <?= $tab !== 'records' ? 'hidden' : '' ?>>
    <section class="panel flush-panel" id="records-table" data-table-panel data-page-size="10">
        <div class="panel-heading"><div><h2>Approval records</h2><p class="muted">Resolutions and orders, each linked to the PPAs or AIP version it covers.</p></div><button class="filter-button" type="button" data-export-csv="approval-records.csv" data-for="records-table">⇩ Export</button></div>
        <div class="table-wrap">
            <table class="activity-table ppa-table">
                <thead><tr><th>Record No.</th><th>Type</th><th>Date</th><th>Subject</th><th>Linked to</th><th data-no-export></th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr data-row>
                        <td><a class="ref-link" href="approval-record.php?no=<?= urlencode($record['no']) ?>"><?= e($record['no']) ?></a></td>
                        <td><?= e($record['type']) ?></td>
                        <td data-export="<?= e($record['date']) ?>"><?= e(displayDate($record['date'])) ?></td>
                        <td class="title-cell"><?= e($record['subject']) ?></td>
                        <td data-export="<?= e(implode('; ', $record['ppas']) . ($record['aip_version'] ? '; AIP version ' . $record['aip_version'] : '')) ?>">
                            <?php foreach ($record['ppas'] as $ref): ?><a class="ref-pill" href="ppa-view.php?ref=<?= urlencode($ref) ?>"><?= e($ref) ?></a> <?php endforeach; ?>
                            <?php if ($record['aip_version']): ?><a class="ref-pill aip-pill" href="aip-version.php?no=<?= (int) $record['aip_version'] ?>">AIP v<?= (int) $record['aip_version'] ?></a><?php endif; ?>
                            <?php if (!$record['ppas'] && !$record['aip_version']): ?><span class="status-pill pending"><i></i>Not linked</span><?php endif; ?>
                        </td>
                        <td><a class="review-action quiet" href="approval-record.php?no=<?= urlencode($record['no']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                    <tr data-empty hidden><td class="empty-cell" colspan="6">No approval records yet.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar" data-pager data-noun="records"></div>
    </section>
</div>

<!-- ======================= Offices ======================= -->
<div id="tab-offices" <?= $tab !== 'offices' ? 'hidden' : '' ?>>
    <section class="office-grid">
        <?php foreach ($offices as $code => $name): $o = officeLinks($code); ?>
            <a class="panel office-card" href="office.php?code=<?= urlencode($code) ?>">
                <div class="office-card-head"><span class="ref-pill"><?= e($code) ?></span><?= $o['encoders'] ? '' : '<span class="status-pill pending"><i></i>No active encoder</span>' ?></div>
                <h3><?= e($name) ?></h3>
                <dl><dt>PPAs</dt><dd><?= count($o['ppas']) ?> (<?= count($o['approved']) ?> approved)</dd><dt>Proposed cost</dt><dd><?= pesoShort($o['cost']) ?></dd><dt>Fund sources</dt><dd><?= count($o['funds']) ?></dd><dt>Approval records</dt><dd><?= count($o['records']) ?></dd><dt>Users</dt><dd><?= count($o['users']) ?></dd></dl>
            </a>
        <?php endforeach; ?>
    </section>
</div>

<!-- ======================= Checks ======================= -->
<div id="tab-checks" <?= $tab !== 'checks' ? 'hidden' : '' ?>>
    <section class="panel flush-panel">
        <div class="panel-heading"><div><h2>Link checks</h2><p class="muted">Missing or broken relationships between PPAs, offices, fund sources and approval records.</p></div></div>
        <?php if (!$issues): ?>
            <p class="empty-state">All records are linked consistently.</p>
        <?php else: ?>
            <ul class="issue-list">
                <?php foreach ($issues as $issue): ?>
                    <li class="<?= $issue['level'] ?>">
                        <span class="issue-level"><?= ucfirst($issue['level']) ?></span>
                        <div><strong><?= e($issue['type']) ?></strong><p><?= e($issue['message']) ?></p></div>
                        <div class="issue-links">
                            <?php if ($issue['ref'] !== '' && storeFindPpa($issue['ref'])): ?><a class="text-button" href="<?= $canManage && $issue['type'] === 'No approval record' ? 'link-record.php' : 'ppa-view.php' ?>?ref=<?= urlencode($issue['ref']) ?>"><?= $canManage && $issue['type'] === 'No approval record' ? 'Attach record' : 'Open PPA' ?></a><?php endif; ?>
                            <?php if ($issue['record'] !== ''): ?><a class="text-button" href="approval-record.php?no=<?= urlencode($issue['record']) ?>">Open record</a><?php endif; ?>
                            <?php if ($issue['ref'] === '' && $issue['record'] === '' && $issue['office'] !== ''): ?><a class="text-button" href="office.php?code=<?= urlencode($issue['office']) ?>">Open office</a><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php include '../includes/footer.php'; ?>
