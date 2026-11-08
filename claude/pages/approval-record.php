<?php
/**
 * One approval record (resolution, order or memo): what it covers, and
 * registering or editing it. ?new=1 registers a new record.
 */
$pageSection = 'Planning';
$activePage = 'linking';
$requiredPermission = 'linking.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$isNew = isset($_GET['new']);
$record = $isNew ? null : findApprovalRecord((string) ($_GET['no'] ?? ''));
if ($isNew) {
    requirePermission('links.manage', $assetPrefix);
} elseif (!$record || (!can('scope.all_offices') && !array_filter($record['ppas'], fn ($ref) => ($p = storeFindPpa($ref)) && canSeeOffice($p['office'])))) {
    flash('error', 'That approval record was not found.');
    header('Location: project-linking.php?tab=records');
    exit;
}
$canManage = can('links.manage');
$errors = [];
$old = $record ?? ['no' => '', 'type' => 'SB Resolution', 'date' => '', 'subject' => '', 'remarks' => ''];
$approvedUnlinked = array_filter(storePpas(), fn ($p) => in_array($p['status'], LINK_APPROVED_STATUSES, true) && !ppaApprovalRecords($p['ref']));
$picked = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requirePermission('links.manage', $assetPrefix);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $record) {
        if ($record['ppas'] || $record['aip_version']) {
            $errors[] = 'Unlink every PPA and AIP version from this record before deleting it.';
        } else {
            deleteApprovalRecord($record['no']);
            auditLog('link.record_delete', $record['no'], $record['subject']);
            flash('success', 'Deleted approval record ' . e($record['no']) . '.');
            header('Location: project-linking.php?tab=records');
            exit;
        }
    } else {
        [$fieldErrors, $clean] = validateApprovalRecord($_POST, $record['no'] ?? null);
        $errors = array_values($fieldErrors);
        $old = array_merge($old, $clean, $record ? ['no' => $record['no']] : []);
        $picked = array_values(array_intersect((array) ($_POST['ppas'] ?? []), array_column($approvedUnlinked, 'ref')));
        if (!$errors && $record) {
            $changes = [];
            foreach (['type' => 'Type', 'date' => 'Date', 'subject' => 'Subject', 'remarks' => 'Remarks'] as $f => $label) {
                if ((string) $record[$f] !== $clean[$f]) {
                    $changes[] = $label . ': ' . ($record[$f] ?: '—') . ' → ' . ($clean[$f] ?: '—');
                }
            }
            if ($changes) {
                updateApprovalRecord($record['no'], ['type' => $clean['type'], 'date' => $clean['date'], 'subject' => $clean['subject'], 'remarks' => $clean['remarks']]);
                foreach ($record['ppas'] as $ref) {
                    if (storeFindPpa($ref)) {
                        syncPpaRecordField($ref, 'Approval record ' . $record['no'] . ' details updated');
                    }
                }
                auditLog('link.record_update', $record['no'], implode('; ', $changes));
                flash('success', 'Saved changes to ' . e($record['no']) . '.');
            } else {
                flash('info', 'No changes were made.');
            }
            header('Location: approval-record.php?no=' . urlencode($record['no']));
            exit;
        }
        if (!$errors && !$record) {
            $created = createApprovalRecord($clean['no'], $clean['type'], $clean['date'], $clean['subject'], $clean['remarks']);
            foreach ($picked as $ref) {
                linkPpaToRecord($ref, $created['no']);
            }
            flash('success', 'Registered ' . e($created['no']) . ($picked ? ' and linked it to ' . count($picked) . ' PPA' . (count($picked) === 1 ? '' : 's') : '') . '.');
            header('Location: approval-record.php?no=' . urlencode($created['no']));
            exit;
        }
    }
}

$pageTitle = $record ? $record['no'] : 'Register Approval Record';
$linkedPpas = $record ? array_values(array_filter(array_map('storeFindPpa', $record['ppas']))) : [];
$linkedTotal = array_sum(array_column($linkedPpas, 'cost'));
$editing = $isNew || ($canManage && isset($_GET['edit'])) || ($errors && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete');

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="project-linking.php?tab=records">Approval records</a> / <?= $record ? e($record['no']) : 'New' ?></p>
        <h1><?= $record ? e($record['no']) : 'Register Approval Record' ?></h1>
        <p class="muted"><?= $record ? e($record['type']) . ' · ' . e(displayDate($record['date'])) . ' · registered by ' . e($record['created_by']) : 'A resolution, order or memorandum that approves PPAs or adopts the AIP.' ?></p>
    </div>
    <?php if ($record && $canManage && !$editing): ?>
        <div class="heading-actions">
            <a class="secondary-button" href="approval-record.php?no=<?= urlencode($record['no']) ?>&edit=1">Edit details</a>
            <?php if (!$record['ppas'] && !$record['aip_version']): ?>
                <form method="post" data-confirm="Delete <?= e($record['no']) ?>? This cannot be undone."><?= csrfField() ?><input type="hidden" name="action" value="delete"><button class="secondary-button danger-button" type="submit">Delete</button></form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert error" role="alert"><strong>Nothing was saved.</strong><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($editing): ?>
    <form method="post" class="user-form-layout" novalidate>
        <?= csrfField() ?><input type="hidden" name="action" value="save">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▣</span><div><h2>Record details</h2><p>As written on the document.</p></div></div>
            <div class="form-divider"></div>
            <div class="form-columns">
                <label>Record No. <em>*</em><input name="no" type="text" maxlength="40" placeholder="e.g. SB Res. No. 2026-120" value="<?= e($old['no']) ?>" <?= $record ? 'readonly class="readonly-input"' : 'required' ?>></label>
                <label>Type <em>*</em><select name="type"><?php foreach (approvalRecordTypes() as $type): ?><option <?= $old['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label>
            </div>
            <label>Date <em>*</em><input name="date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($old['date']) ?>"></label>
            <label>Subject <em>*</em><input name="subject" type="text" maxlength="200" value="<?= e($old['subject']) ?>" placeholder="What the record approves"></label>
            <label>Remarks<input name="remarks" type="text" maxlength="300" value="<?= e($old['remarks']) ?>"></label>
        </section>
        <?php if (!$record): ?>
            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-icon green-bg">⛓</span><div><h2>Link to approved PPAs</h2><p>Optional. Approved PPAs with no record yet.</p></div></div>
                <div class="form-divider"></div>
                <?php if (!$approvedUnlinked): ?><p class="muted">Every approved PPA already has an approval record.</p><?php endif; ?>
                <div class="checklist">
                    <?php foreach ($approvedUnlinked as $p): ?>
                        <label class="check-option"><input type="checkbox" name="ppas[]" value="<?= e($p['ref']) ?>" <?= in_array($p['ref'], $picked, true) ? 'checked' : '' ?>> <span><strong><?= e($p['ref']) ?></strong> <?= e($p['title']) ?> · <?= e($p['office']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <div class="form-actions">
            <a class="secondary-button" href="<?= $record ? 'approval-record.php?no=' . urlencode($record['no']) : 'project-linking.php?tab=records' ?>">Cancel</a>
            <button class="primary-button" type="submit"><?= $record ? 'Save changes' : 'Register record' ?></button>
        </div>
    </form>
<?php else: ?>
    <div class="consolidation-grid">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▣</span><div><h2>Details</h2><p><?= e($record['type']) ?></p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list wide-terms">
                <dt>Record No.</dt><dd><?= e($record['no']) ?></dd>
                <dt>Date</dt><dd><?= e(displayDate($record['date'])) ?></dd>
                <dt>Subject</dt><dd><?= e($record['subject']) ?></dd>
                <dt>Remarks</dt><dd><?= e($record['remarks'] ?: '—') ?></dd>
                <dt>AIP version</dt><dd><?= $record['aip_version'] ? '<a class="ref-link" href="aip-version.php?no=' . (int) $record['aip_version'] . '">Version ' . (int) $record['aip_version'] . '</a> (adopted)' : '—' ?></dd>
                <dt>Registered</dt><dd><?= e(date('M j, Y g:i A', strtotime($record['created_at']))) ?> by <?= e($record['created_by']) ?></dd>
            </dl>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon green-bg">⛓</span><div><h2>Covers <?= count($linkedPpas) ?> PPA<?= count($linkedPpas) === 1 ? '' : 's' ?></h2><p>Total <?= peso($linkedTotal) ?></p></div></div>
            <div class="form-divider"></div>
            <?php if (!$linkedPpas && !$record['aip_version']): ?><p class="funding-issue warning">This record is not linked to any PPA or AIP version. Link it from a PPA's <em>Manage</em> page in Project Linking.</p><?php endif; ?>
            <ul class="linked-list">
                <?php foreach ($linkedPpas as $p): ?>
                    <li><a class="ref-link" href="ppa-view.php?ref=<?= urlencode($p['ref']) ?>"><?= e($p['ref']) ?></a> <?= e($p['title']) ?><small><?= e(officeName($p['office'])) ?> · <?= peso($p['cost']) ?> · <?= e(statusLabel($p['status'])) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
