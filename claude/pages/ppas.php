<?php
/**
 * Project Encoding: the office's PPAs (drafts included), with actions to
 * edit, submit, resubmit or delete according to each PPA's status.
 */
$pageTitle = 'Project Encoding';
$pageSection = 'Planning';
$activePage = 'projects';
$requiredPermission = 'ppa.encode';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

/* ---------- Actions from the list (POST → redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $ppa = storeFindPpa((string) ($_POST['ref'] ?? ''));
    $action = $_POST['action'] ?? '';
    if (!$ppa || !canSeeOffice($ppa['office'])) {
        flash('error', 'That PPA was not found.');
    } elseif ($action === 'delete') {
        if ($ppa['status'] !== 'draft') {
            flash('error', 'Only drafts can be deleted. ' . e($ppa['ref']) . ' has already been submitted.');
        } else {
            storeDeletePpa($ppa['ref']);
            auditLog('ppa.delete', $ppa['ref'], $ppa['title']);
            flash('success', 'Draft ' . e($ppa['ref']) . ' was deleted.');
        }
    } elseif ($action === 'submit') {
        if (!ppaIsEditable($ppa)) {
            flash('error', e($ppa['ref']) . ' has already been submitted.');
        } else {
            $input = [];
            foreach (array_keys(PPA_FIELDS) as $field) {
                $input[$field] = in_array($field, ['ps', 'mooe', 'co', 'cc_adaptation', 'cc_mitigation'], true)
                    ? ((float) $ppa[$field] > 0 ? number_format((float) $ppa[$field], 2, '.', '') : '')
                    : (string) ($ppa[$field] ?? '');
            }
            $offices = array_filter(mockOffices(), fn ($n, $code) => canSeeOffice($code), ARRAY_FILTER_USE_BOTH);
            [$errors] = validatePpa($input, false, $offices, $ppa['ref']);
            if ($errors) {
                flash('error', e($ppa['ref']) . ' is not complete yet (' . count($errors) . ' field' . (count($errors) === 1 ? '' : 's') . ' to fix). <a href="projects.php?ref=' . urlencode($ppa['ref']) . '">Open it to finish encoding</a>.');
            } else {
                $wasReturned = $ppa['status'] === 'returned';
                storeSavePpa(['ref' => $ppa['ref'], 'status' => 'for_review', 'submitted_at' => date('Y-m-d H:i:s')], $wasReturned ? 'Resubmitted after revision' : 'Submitted for review');
                auditLog($wasReturned ? 'ppa.resubmit' : 'ppa.submit', $ppa['ref'], $ppa['title'] . ' · ' . peso($ppa['cost']));
                flash('success', e($ppa['ref']) . ' was ' . ($wasReturned ? 'resubmitted' : 'submitted') . ' to the MPDO for review.');
            }
        }
    }
    header('Location: ppas.php');
    exit;
}

$ppas = visiblePpas(true);
$me = currentUser();
$count = fn (array $statuses) => count(array_filter($ppas, fn ($p) => in_array($p['status'], $statuses, true)));
$groups = [
    'draft'     => ['draft'],
    'returned'  => ['returned'],
    'submitted' => ['for_review', 'recommended'],
    'decided'   => ['approved', 'disapproved', 'ongoing', 'completed'],
];
$groupOf = function (string $status) use ($groups): string {
    foreach ($groups as $group => $statuses) {
        if (in_array($status, $statuses, true)) {
            return $group;
        }
    }
    return 'decided';
};
$proposedTotal = array_sum(array_column(array_filter($ppas, fn ($p) => $p['status'] !== 'disapproved'), 'cost'));
$tableId = 'my-ppas';

include '../includes/header.php';
?>
<div class="page-heading">
    <div><p class="eyebrow">Preparation</p><h1>Project Encoding</h1><p class="muted">PPAs encoded by <?= e(officeName($me['office'])) ?> for the FY <?= AIP_FISCAL_YEAR ?> AIP.</p></div>
    <div class="heading-actions">
        <button class="secondary-button" type="button" data-export-csv="ppas-<?= e(strtolower($me['office'])) ?>.csv" data-for="<?= $tableId ?>">⇩ Export</button>
        <a class="primary-button" href="projects.php">＋ Encode New PPA</a>
    </div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Drafts</span><span class="stat-icon blue">▤</span></div><strong><?= $count($groups['draft']) ?></strong><p>Not yet submitted</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Returned for revision</span><span class="stat-icon amber">↺</span></div><strong class="<?= $count($groups['returned']) ? 'orange-number' : '' ?>"><?= $count($groups['returned']) ?></strong><p>Need changes before resubmitting</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">With the MPDO</span><span class="stat-icon green">✓</span></div><strong><?= $count($groups['submitted']) ?></strong><p>Under review or authorization</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total proposed cost</span><span class="stat-icon blue">₱</span></div><strong><?= pesoShort($proposedTotal) ?></strong><p>Excluding disapproved PPAs</p></article>
</section>

<section class="panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="8">
    <div class="panel-heading">
        <div class="segmented-control" role="group" aria-label="Show">
            <button class="active" type="button" data-tab="" data-for="<?= $tableId ?>" aria-pressed="true">All (<?= count($ppas) ?>)</button>
            <button type="button" data-tab="draft" data-for="<?= $tableId ?>" aria-pressed="false">Drafts (<?= $count($groups['draft']) ?>)</button>
            <button type="button" data-tab="returned" data-for="<?= $tableId ?>" aria-pressed="false">Returned (<?= $count($groups['returned']) ?>)</button>
            <button type="button" data-tab="submitted" data-for="<?= $tableId ?>" aria-pressed="false">With MPDO (<?= $count($groups['submitted']) ?>)</button>
            <button type="button" data-tab="decided" data-for="<?= $tableId ?>" aria-pressed="false">Decided (<?= $count($groups['decided']) ?>)</button>
        </div>
        <label class="search-box wide"><span aria-hidden="true">⌕</span><span class="visually-hidden">Search PPAs</span><input type="search" placeholder="Search title or ref no." data-search data-for="<?= $tableId ?>"></label>
    </div>
    <div class="table-wrap">
        <table class="activity-table ppa-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Schedule</th><th>Total Cost</th><th>Fund Source</th><th>Status</th><th>Last updated</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($ppas as $ppa): $editable = canEditPpa($ppa); ?>
                <tr data-row data-state="<?= $groupOf($ppa['status']) ?>">
                    <td><a class="ref-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a></td>
                    <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><strong><?= e($ppa['title'] !== '' ? $ppa['title'] : '(untitled)') ?></strong><small class="table-subtext"><?= e($ppa['type'] ?: 'Type not set') ?><?= $ppa['program'] !== '' ? ' · ' . e($ppa['program']) : '' ?></small></td>
                    <td data-export="<?= e($ppa['start'] . ' to ' . $ppa['end']) ?>"><?= $ppa['start'] !== '' ? e(date('M Y', strtotime($ppa['start']))) . ' – ' . e($ppa['end'] !== '' ? date('M Y', strtotime($ppa['end'])) : '?') : '<span class="table-subtext">Not set</span>' ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <td><?= $ppa['fund'] !== '' ? e(ppaFundLabel($ppa)) : '<span class="table-subtext">Not set</span>' ?></td>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td data-export="<?= e($ppa['updated_at']) ?>"><?= e(displayDate($ppa['updated'])) ?><small class="table-subtext"><?= e(explode(' (', $ppa['updated_by'])[0]) ?></small></td>
                    <td>
                        <div class="row-actions">
                            <a class="text-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">View</a>
                            <?php if ($editable): ?>
                                <a class="text-button" href="projects.php?ref=<?= urlencode($ppa['ref']) ?>"><?= $ppa['status'] === 'returned' ? 'Revise' : 'Edit' ?></a>
                                <form method="post" data-confirm="<?= $ppa['status'] === 'returned' ? 'Resubmit' : 'Submit' ?> <?= e($ppa['ref']) ?> to the MPDO for review? You will not be able to edit it unless it is returned.">
                                    <?= csrfField() ?><input type="hidden" name="action" value="submit"><input type="hidden" name="ref" value="<?= e($ppa['ref']) ?>">
                                    <button class="text-button" type="submit"><?= $ppa['status'] === 'returned' ? 'Resubmit' : 'Submit' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($ppa['status'] === 'draft'): ?>
                                <form method="post" data-confirm="Delete draft <?= e($ppa['ref']) ?>? This cannot be undone.">
                                    <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ref" value="<?= e($ppa['ref']) ?>">
                                    <button class="text-button danger-text" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="8">No PPAs here yet. <a href="projects.php">Encode a new PPA</a>.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="PPAs"></div>
</section>
<?php include '../includes/footer.php'; ?>
