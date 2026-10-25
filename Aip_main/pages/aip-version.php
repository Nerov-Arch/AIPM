<?php
/**
 * One saved version of the consolidated AIP: the snapshot itself,
 * how it differs from the current approved PPAs, and finalization.
 */
$pageSection = 'Reports & Funds';
$activePage = 'consolidation';
$requiredPermission = 'consolidation.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$no = (int) ($_GET['no'] ?? 0);
$version = aipFindVersion($no);
if (!$version) {
    flash('error', 'That AIP version was not found.');
    header('Location: aip-consolidation.php');
    exit;
}

$errors = [];
$resolution = '';
$resolutionDate = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requirePermission('consolidation.finalize', $assetPrefix);
    $resolution = trim((string) ($_POST['resolution'] ?? ''));
    $resolutionDate = trim((string) ($_POST['resolution_date'] ?? ''));
    if ($version['status'] !== 'draft') {
        $errors[] = 'Only a draft version can be finalized.';
    }
    $pending = aipCompare($version['entries'], aipEntries());
    if ($pending['added'] || $pending['removed'] || $pending['changed']) {
        $errors[] = 'Approved PPAs have changed since this version was saved. Ask the MPDO to save a new version, then finalize that one.';
    }
    if ($resolution !== '' && !preg_match('/^[A-Za-z0-9 .,\/#\-]{3,40}$/', $resolution)) {
        $errors[] = 'Use letters, numbers and . , / # - for the resolution number.';
    }
    if ($resolution !== '' && $resolutionDate === '') {
        $errors[] = 'Enter the date of the resolution.';
    } elseif ($resolutionDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $resolutionDate);
        if (!$date || $date->format('Y-m-d') !== $resolutionDate) {
            $errors[] = 'Enter a valid resolution date.';
        } elseif ($resolutionDate > date('Y-m-d')) {
            $errors[] = 'The resolution date cannot be in the future.';
        } elseif ($resolution === '') {
            $errors[] = 'Enter the resolution number for this date.';
        }
    }
    if (!$errors) {
        aipFinalizeVersion($no, $resolution, $resolution !== '' ? $resolutionDate : '');
        if ($resolution !== '') {
            linkAipVersionToRecord($no, $resolution, $resolutionDate);
        }
        auditLog('aip.finalize', 'Version ' . $no, $version['totals']['count'] . ' PPAs · ' . peso($version['totals']['cost']) . ($resolution !== '' ? ' · ' . $resolution : ''));
        flash('success', 'Version ' . $no . ' is now the final consolidated AIP for FY ' . AIP_FISCAL_YEAR . '.');
        header('Location: aip-version.php?no=' . $no);
        exit;
    }
}

$pageTitle = 'AIP Version ' . $no;
$diff = aipCompare($version['entries'], aipEntries());
$changeCount = count($diff['added']) + count($diff['removed']) + count($diff['changed']);
$thousands = ($_GET['units'] ?? '') === 'thousands';
$aipEntries = $version['entries'];
$aipThousands = $thousands;
$statusText = ['draft' => 'Draft', 'final' => 'Final', 'superseded' => 'Superseded'][$version['status']];
$aipCaption = 'Version ' . $no . ' · ' . $statusText . ' · saved ' . date('M j, Y', strtotime($version['created_at']))
    . ($version['status'] === 'final' && !empty($version['resolution']) ? ' · ' . $version['resolution'] : '');

include '../includes/header.php';
?>
<div class="page-heading no-print">
    <div>
        <p class="eyebrow"><a href="aip-consolidation.php">AIP Consolidation</a> / Version <?= $no ?></p>
        <h1>AIP Version <?= $no ?> <small class="version-kind"><?= e($version['kind']) ?></small></h1>
        <p class="muted">Saved by <?= e($version['created_by']) ?> on <?= e(date('M j, Y g:i A', strtotime($version['created_at']))) ?><?= $version['remarks'] !== '' ? ' · ' . e($version['remarks']) : '' ?></p>
    </div>
    <div class="heading-actions">
        <?= $version['status'] === 'final' ? statusPill('approved', 'Final') : ($version['status'] === 'superseded' ? statusPill('draft', 'Superseded') : statusPill('for_review', 'Draft')) ?>
        <a class="secondary-button" href="aip-export.php?version=<?= $no ?><?= $thousands ? '&units=thousands' : '' ?>">⇩ Export CSV</a>
        <button class="secondary-button" type="button" data-print>▣ Print AIP</button>
    </div>
</div>

<?php if ($errors): ?><div class="alert error no-print" role="alert"><strong>The version was not finalized.</strong><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($version['status'] === 'final'): ?>
    <div class="alert success no-print" role="status"><strong>Final consolidated AIP.</strong> Finalized by <?= e($version['finalized_by']) ?> on <?= e(date('M j, Y g:i A', strtotime($version['finalized_at']))) ?><?= !empty($version['resolution']) ? ' · ' . e($version['resolution']) . ' (' . e(displayDate($version['resolution_date'])) . ')' : '' ?>.</div>
<?php elseif ($version['status'] === 'superseded'): ?>
    <div class="alert info no-print" role="status">This version was final until a later version was finalized.</div>
<?php endif; ?>

<div class="consolidation-grid no-print">
    <section class="panel">
        <div class="panel-heading"><div><h2>Changes since this version</h2><p class="muted">Compared with the approved PPAs right now.</p></div><?= $changeCount ? '<span class="status-pill pending"><i></i>' . $changeCount . ' change' . ($changeCount === 1 ? '' : 's') . '</span>' : '<span class="status-pill approved"><i></i>Up to date</span>' ?></div>
        <?php if (!$changeCount): ?>
            <p class="empty-state">No changes. This version matches the current approved PPAs.</p>
        <?php else: ?>
            <ul class="diff-list">
                <?php foreach ($diff['added'] as $entry): ?><li class="added"><b>Added</b> <a href="ppa-view.php?ref=<?= urlencode($entry['ref']) ?>"><?= e($entry['ref']) ?></a> <?= e($entry['title']) ?> · <?= peso($entry['cost']) ?></li><?php endforeach; ?>
                <?php foreach ($diff['removed'] as $entry): ?><li class="removed"><b>No longer approved</b> <a href="ppa-view.php?ref=<?= urlencode($entry['ref']) ?>"><?= e($entry['ref']) ?></a> <?= e($entry['title']) ?> · <?= peso($entry['cost']) ?></li><?php endforeach; ?>
                <?php foreach ($diff['changed'] as $ref => $change): ?><li class="changed"><b>Changed</b> <a href="ppa-view.php?ref=<?= urlencode($ref) ?>"><?= e($ref) ?></a> <?= e($change['entry']['title']) ?><small><?= e(implode('; ', $change['changes'])) ?></small></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Finalize</h2><p class="muted">The approver marks one version as the final consolidated AIP.</p></div></div>
        <?php if ($version['status'] !== 'draft'): ?>
            <p class="muted"><?= $version['status'] === 'final' ? 'This version is final.' : 'This version has been superseded.' ?></p>
        <?php elseif (!can('consolidation.finalize')): ?>
            <p class="muted">Waiting for the approver to finalize.</p>
        <?php elseif ($changeCount): ?>
            <p class="funding-issue warning">Approved PPAs changed after this version was saved. Ask the MPDO to save a new version and finalize that one.</p>
        <?php else: ?>
            <form method="post" class="decision-form" novalidate data-confirm="Finalize version <?= $no ?> as the FY <?= AIP_FISCAL_YEAR ?> AIP?<?= aipFinalVersion() ? ' The current final version will be superseded.' : '' ?>">
                <?= csrfField() ?>
                <div class="approval-record">
                    <p class="legend-like">Adoption record <small>(optional)</small></p>
                    <label>Resolution No.<input name="resolution" type="text" maxlength="40" placeholder="e.g. SB Res. No. 2026-150" value="<?= e($resolution) ?>"></label>
                    <label>Date<input name="resolution_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($resolutionDate) ?>"></label>
                </div>
                <button class="primary-button" type="submit">✓ Finalize version <?= $no ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="panel flush-panel aip-panel">
    <div class="panel-heading no-print">
        <div><h2>Version <?= $no ?> contents</h2><p class="muted"><?= $version['totals']['count'] ?> PPAs · <?= peso($version['totals']['cost']) ?></p></div>
        <div class="segmented-control" role="group" aria-label="Units">
            <a class="<?= $thousands ? '' : 'active' ?>" href="?no=<?= $no ?>">₱</a>
            <a class="<?= $thousands ? 'active' : '' ?>" href="?no=<?= $no ?>&units=thousands">₱'000</a>
        </div>
    </div>
    <?php include '../includes/aip-table.php'; ?>
</section>
<?php include '../includes/footer.php'; ?>
