<?php
/**
 * Manage the approval records linked to one PPA: attach an existing
 * record, register a new one and attach it, or unlink a record.
 */
$pageSection = 'Planning';
$activePage = 'linking';
$requiredPermission = 'links.manage';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
if (!$ppa || $ppa['status'] === 'draft') {
    flash('error', 'That PPA was not found.');
    header('Location: project-linking.php');
    exit;
}
$pageTitle = 'Approval records · ' . $ppa['ref'];
$canAttach = in_array($ppa['status'], LINK_APPROVED_STATUSES, true);
$errors = [];
$new = ['no' => '', 'type' => 'SB Resolution', 'date' => '', 'subject' => '', 'remarks' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'attach') {
        $record = findApprovalRecord((string) ($_POST['record'] ?? ''));
        if (!$canAttach) {
            $errors['attach'] = 'Approval records can only be linked after the PPA is approved.';
        } elseif (!$record) {
            $errors['attach'] = 'Choose an approval record from the list.';
        } elseif (!linkPpaToRecord($ppa['ref'], $record['no'])) {
            $errors['attach'] = $record['no'] . ' is already linked to this PPA.';
        } else {
            flash('success', 'Linked ' . e($ppa['ref']) . ' to ' . e($record['no']) . '.');
            header('Location: link-record.php?ref=' . urlencode($ppa['ref']));
            exit;
        }
    } elseif ($action === 'create') {
        [$errors, $new] = validateApprovalRecord($_POST);
        if (!$canAttach) {
            $errors['attach'] = 'Approval records can only be linked after the PPA is approved.';
        }
        if (!$errors) {
            $record = createApprovalRecord($new['no'], $new['type'], $new['date'], $new['subject'], $new['remarks']);
            linkPpaToRecord($ppa['ref'], $record['no']);
            flash('success', 'Registered ' . e($record['no']) . ' and linked it to ' . e($ppa['ref']) . '.');
            header('Location: link-record.php?ref=' . urlencode($ppa['ref']));
            exit;
        }
    } elseif ($action === 'unlink') {
        $no = (string) ($_POST['record'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            $errors['unlink'] = 'Give a short reason for unlinking (at least 5 characters).';
        } elseif (!unlinkPpaFromRecord($ppa['ref'], $no, $reason)) {
            $errors['unlink'] = 'That record is not linked to this PPA.';
        } else {
            flash('success', 'Unlinked ' . e($no) . ' from ' . e($ppa['ref']) . '.');
            header('Location: link-record.php?ref=' . urlencode($ppa['ref']));
            exit;
        }
    }
}

$linked = ppaApprovalRecords($ppa['ref']);
$available = array_filter(approvalRecords(), fn ($r) => !in_array($ppa['ref'], $r['ppas'], true));

function recordFieldError(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<small class="field-error">' . e($errors[$field]) . '</small>' : '';
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="project-linking.php">Project Linking</a> / <a href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a> / Approval records</p>
        <h1>Approval Records</h1>
        <p class="muted"><?= e($ppa['title']) ?> · <?= e(officeName($ppa['office'])) ?></p>
    </div>
    <div class="heading-actions"><?= statusPill($ppa['status']) ?></div>
</div>

<?php if ($errors): ?><div class="alert error" role="alert"><strong>Nothing was changed.</strong><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$canAttach): ?><div class="alert info" role="status">This PPA is <?= e(strtolower(statusLabel($ppa['status']))) ?>. Approval records can be linked once it is approved.</div><?php endif; ?>

<section class="panel flush-panel">
    <div class="panel-heading"><div><h2>Linked records (<?= count($linked) ?>)</h2><p class="muted">The documents that approved or adopted this PPA.</p></div></div>
    <?php if (!$linked): ?>
        <p class="empty-state">No approval record is linked yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="activity-table">
            <thead><tr><th>Record No.</th><th>Type</th><th>Date</th><th>Subject</th><th>Also covers</th><th>Unlink</th></tr></thead>
            <tbody>
            <?php foreach ($linked as $record): $others = array_diff($record['ppas'], [$ppa['ref']]); ?>
                <tr>
                    <td><a class="ref-link" href="approval-record.php?no=<?= urlencode($record['no']) ?>"><?= e($record['no']) ?></a></td>
                    <td><?= e($record['type']) ?></td>
                    <td><?= e(displayDate($record['date'])) ?></td>
                    <td class="title-cell"><?= e($record['subject']) ?></td>
                    <td><?= $others ? count($others) . ' other PPA' . (count($others) === 1 ? '' : 's') : '—' ?></td>
                    <td>
                        <form method="post" class="inline-form" data-confirm="Unlink <?= e($record['no']) ?> from <?= e($ppa['ref']) ?>?">
                            <?= csrfField() ?><input type="hidden" name="action" value="unlink"><input type="hidden" name="record" value="<?= e($record['no']) ?>">
                            <input type="text" name="reason" maxlength="200" placeholder="Reason" aria-label="Reason for unlinking <?= e($record['no']) ?>" required>
                            <button class="text-button danger-text" type="submit">Unlink</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if ($canAttach): ?>
<div class="consolidation-grid">
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon blue-bg">⛓</span><div><h2>Link an existing record</h2><p>For a resolution that covers several PPAs.</p></div></div>
        <div class="form-divider"></div>
        <?php if (!$available): ?>
            <p class="muted">Every registered record is already linked to this PPA.</p>
        <?php else: ?>
            <form method="post" novalidate>
                <?= csrfField() ?><input type="hidden" name="action" value="attach">
                <label>Approval record<select name="record" required><option value="">Select a record</option><?php foreach ($available as $record): ?><option value="<?= e($record['no']) ?>"><?= e($record['no'] . ' · ' . $record['type'] . ' · ' . displayDate($record['date']) . ' · ' . mb_strimwidth($record['subject'], 0, 60, '…')) ?></option><?php endforeach; ?></select></label>
                <div class="form-actions"><button class="primary-button" type="submit">Link record</button></div>
            </form>
        <?php endif; ?>
    </section>
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon green-bg">＋</span><div><h2>Register a new record</h2><p>It is linked to this PPA right away.</p></div></div>
        <div class="form-divider"></div>
        <form method="post" novalidate>
            <?= csrfField() ?><input type="hidden" name="action" value="create">
            <div class="form-columns">
                <label>Record No. <em>*</em><input name="no" type="text" maxlength="40" placeholder="e.g. SB Res. No. 2026-120" value="<?= e($new['no']) ?>" required><?= recordFieldError($errors, 'no') ?></label>
                <label>Type <em>*</em><select name="type" required><?php foreach (approvalRecordTypes() as $type): ?><option <?= $new['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select><?= recordFieldError($errors, 'type') ?></label>
            </div>
            <label>Date <em>*</em><input name="date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($new['date']) ?>" required><?= recordFieldError($errors, 'date') ?></label>
            <label>Subject <em>*</em><input name="subject" type="text" maxlength="200" placeholder="What the record approves" value="<?= e($new['subject']) ?>" required><?= recordFieldError($errors, 'subject') ?></label>
            <label>Remarks<input name="remarks" type="text" maxlength="300" value="<?= e($new['remarks']) ?>"><?= recordFieldError($errors, 'remarks') ?></label>
            <div class="form-actions"><button class="primary-button" type="submit">Register and link</button></div>
        </form>
    </section>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
