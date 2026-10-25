<?php
$pageTitle = 'Project Linking & Traceability';
$pageSection = 'Planning';
$activePage = 'linking';
$requiredPermission = 'linking.view';
$assetPrefix = '../';
include '../includes/header.php';

// MOCK: link status is derived from the sample records.
$approvedStatuses = ['approved', 'ongoing', 'completed'];
$links = [];
foreach (visiblePpas() as $ppa) {   // Encoders see only their own office
    if ($ppa['record'] !== '') {
        $link = 'linked';
    } elseif (in_array($ppa['status'], $approvedStatuses, true)) {
        $link = 'missing';
    } else {
        $link = 'not-approved';
    }
    $links[] = $ppa + ['link' => $link];
}
$linkLabels = ['linked' => 'Fully Linked', 'missing' => 'Missing Link', 'not-approved' => 'Not Yet Approved'];
$linkClasses = ['linked' => 'approved', 'missing' => 'pending', 'not-approved' => 'inactive'];
$query = trim($_GET['q'] ?? '');
$tableId = 'linking-table';
?>
<div class="page-heading linking-heading">
    <div><p class="eyebrow">Traceability</p><h1>Project Linking &amp; Traceability</h1><p class="muted">Track connections between PPAs, implementing offices, funding sources and approval records.</p></div>
    <div class="heading-actions">
        <div class="segmented-control view-switch" role="group" aria-label="View">
            <button class="active" type="button" data-view-group="linking" data-view-show="#linking-standard" aria-pressed="true">Standard View</button>
            <button type="button" data-view-group="linking" data-view-show="#linking-graph" aria-pressed="false">Graph View</button>
        </div>
        <button class="secondary-button" type="button" data-toggle-target="#linking-filters">☷ Filter</button>
        <button class="secondary-button" type="button" data-export-csv="ppa-traceability.csv" data-for="<?= $tableId ?>">⇩ Export</button>
    </div>
</div>

<?php if ($query !== ''): ?>
    <div class="alert success" role="status">Showing results for “<?= e($query) ?>”. <a href="project-linking.php">Clear search</a></div>
<?php endif; ?>

<div id="linking-standard">
    <section class="panel linking-panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="6">
        <div class="filter-bar" id="linking-filters" <?= $query === '' ? 'hidden' : '' ?>>
            <label>Link status<select data-filter="link" data-for="<?= $tableId ?>"><option value="">All</option><?php foreach ($linkLabels as $value => $label): ?><option value="<?= $value ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Implementing office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices</option><?php foreach (mockOffices() as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select></label>
            <label>Fund source<select data-filter="fund" data-for="<?= $tableId ?>"><option value="">All sources</option><?php foreach (mockFundSources() as $code => $fund): ?><option value="<?= e($code) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
            <label>Search<input type="search" placeholder="Title, ref no., office..." value="<?= e($query) ?>" data-search data-for="<?= $tableId ?>"></label>
            <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Clear filters</button>
        </div>
        <div class="table-wrap">
            <table class="activity-table linking-table">
                <thead><tr><th>Source PPA<br>(Program/Project/Activity)</th><th>Implementing<br>Office</th><th>Funding Source &amp; Allocation</th><th>Approval Record<br>ID</th><th>Status &amp; Action</th></tr></thead>
                <tbody>
                <?php foreach ($links as $ppa):
                    $detail = [
                        'eyebrow' => 'Traceability record',
                        'title'   => $ppa['title'],
                        'fields'  => [
                            ['Reference no.', $ppa['ref']],
                            ['Program', $ppa['program']],
                            ['Implementing office', officeName($ppa['office'])],
                            ['Responsible officer', $ppa['officer']],
                            ['Fund source', fundName($ppa['fund'])],
                            ['Allocation', peso($ppa['cost'])],
                            ['PPA status', statusLabel($ppa['status'])],
                            ['Approval record', $ppa['record'] !== '' ? $ppa['record'] . ' (' . displayDate($ppa['recordDate']) . ')' : 'None attached'],
                            ['Link status', $linkLabels[$ppa['link']]],
                        ],
                    ];
                ?>
                    <tr data-row data-link="<?= $ppa['link'] ?>" data-office="<?= e($ppa['office']) ?>" data-fund="<?= e($ppa['fund']) ?>" data-cost="<?= $ppa['cost'] ?>">
                        <td class="title-cell" data-export="<?= e($ppa['ref'] . ' — ' . $ppa['title']) ?>"><span class="ref-pill"><?= e($ppa['ref']) ?></span><a class="title-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><strong><?= e($ppa['title']) ?></strong></a><small class="table-subtext">▧ <?= e($ppa['program']) ?></small></td>
                        <td data-export="<?= e(officeName($ppa['office'])) ?>"><strong><?= e($ppa['office']) ?></strong><small class="table-subtext"><?= e($ppa['officer']) ?></small></td>
                        <td data-export="<?= e(fundName($ppa['fund']) . ' — ' . number_format($ppa['cost'], 2, '.', '')) ?>"><strong class="fund-link">▥ <?= e(fundName($ppa['fund'])) ?></strong><strong class="table-cost"><?= peso($ppa['cost']) ?></strong></td>
                        <td data-export="<?= e($ppa['record']) ?>">
                            <?php if ($ppa['link'] === 'linked'): ?>
                                <strong class="record-link"><?= e($ppa['record']) ?></strong><small class="table-subtext">SB approved: <?= e(displayDate($ppa['recordDate'])) ?></small>
                            <?php elseif ($ppa['link'] === 'missing'): ?>
                                <span class="pending-record">Pending linkage</span><button class="attach-link" type="button" data-toast="Attaching approval records will be available once file storage and the database are set up.">Attach Record</button>
                            <?php else: ?>
                                <span class="pending-record">Awaiting approval</span>
                            <?php endif; ?>
                        </td>
                        <td data-export="<?= e($linkLabels[$ppa['link']]) ?>"><div class="status-action"><span class="status-pill <?= $linkClasses[$ppa['link']] ?>"><i></i><?= e($linkLabels[$ppa['link']]) ?></span><button class="row-action" type="button" aria-label="View details for <?= e($ppa['ref']) ?>" data-detail="<?= e(json_encode($detail)) ?>">⋮</button></div></td>
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
        <div><h2>Traceability graph</h2><p class="muted">Choose a PPA to see how it connects to its office, fund source and approval record.</p></div>
        <label class="visually-hidden" for="trace-select">PPA</label>
        <select id="trace-select" class="small-select" data-trace-select>
            <?php foreach ($links as $ppa): ?><option value="<?= e($ppa['ref']) ?>"><?= e($ppa['ref'] . ' — ' . $ppa['title']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php foreach ($links as $i => $ppa): ?>
        <div class="trace-graph" data-trace="<?= e($ppa['ref']) ?>" <?= $i > 0 ? 'hidden' : '' ?>>
            <div class="trace-node source"><span><?= e($ppa['ref']) ?></span><strong><?= e($ppa['title']) ?></strong></div>
            <div class="trace-connector"></div>
            <div class="trace-node"><span>Implementing Office</span><strong><?= e(officeName($ppa['office'])) ?></strong></div>
            <div class="trace-connector"></div>
            <div class="trace-node fund"><span>Funding Source</span><strong><?= e(fundName($ppa['fund'])) ?> · <?= e(pesoShort($ppa['cost'])) ?></strong></div>
            <div class="trace-connector"></div>
            <div class="trace-node <?= $ppa['link'] === 'linked' ? 'record' : 'missing' ?>"><span>Approval Record</span><strong><?= e($ppa['record'] !== '' ? $ppa['record'] : $linkLabels[$ppa['link']]) ?></strong></div>
        </div>
    <?php endforeach; ?>
</section>
<?php include '../includes/footer.php'; ?>
