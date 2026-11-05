<?php
/**
 * Expenses and expenditure timeline of one approved PPA.
 *   Implementing office (Encoder): record expenses with a receipt photo, remove unaudited ones
 *   Audit (Auditor):               verify or flag each expense
 *   Others:                        view only
 */
$pageSection = 'Workflow';
$activePage = 'implementation';
$requiredPermission = 'expenses.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
if (!$ppa || !canSeeOffice($ppa['office']) || !in_array($ppa['status'], ['approved', 'ongoing', 'completed'], true)) {
    flash('error', 'Expenses can only be viewed for approved PPAs you have access to.');
    header('Location: implementation-workflow.php');
    exit;
}
$self = 'ppa-expenses.php?ref=' . urlencode($ppa['ref']);
$errors = [];
$old = ['date' => date('Y-m-d'), 'description' => '', 'amount' => '', 'receipt_no' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A file bigger than post_max_size empties $_POST, so explain that instead of "form expired".
    $postMax = ini_get('post_max_size');
    $postMaxBytes = (int) $postMax * (['K' => 1024, 'M' => 1048576, 'G' => 1073741824][strtoupper(substr($postMax, -1))] ?? 1);
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMaxBytes) {
        flash('error', 'The receipt file is too large to upload. The limit is 5 MB.');
        header('Location: ' . $self);
        exit;
    }
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        if (!canRecordExpense($ppa)) {
            requirePermission('expenses.record', $assetPrefix);
            flash('error', 'Expenses can no longer be recorded for this PPA.');
            header('Location: ' . $self);
            exit;
        }
        foreach (array_keys($old) as $field) {
            $old[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        [$errors, $clean] = validateExpense($ppa, $old, $_FILES['receipt'] ?? null);
        if (!$errors) {
            try {
                $expense = storeAddExpense($ppa, $clean);
                flash('success', 'Expense of ' . e(peso($expense['amount'])) . ' recorded with its receipt.');
                header('Location: ' . $self);
                exit;
            } catch (RuntimeException $ex) {
                $errors['receipt'] = 'The receipt could not be saved on the server. Check that the uploads/receipts folder is writable.';
            }
        }
    } else {
        $expense = storeFindExpense((int) ($_POST['id'] ?? 0));
        if (!$expense || $expense['ref'] !== $ppa['ref']) {
            flash('error', 'That expense was not found.');
        } elseif ($action === 'delete') {
            if (!canDeleteExpense($expense, $ppa)) {
                flash('error', 'Only the implementing office can remove an expense, and only before it is audited.');
            } else {
                storeDeleteExpense($expense);
                flash('success', 'Expense ' . e($expense['receipt_no']) . ' was removed.');
            }
        } elseif (in_array($action, ['verified', 'flagged'], true)) {
            if (!can('expenses.verify')) {
                requirePermission('expenses.verify', $assetPrefix);
            }
            $message = auditExpense($expense, $action, (string) ($_POST['note'] ?? ''));
            flash($message === '' ? 'success' : 'error', $message === '' ? 'Expense ' . e($expense['receipt_no']) . ' marked ' . $action . '.' : e($message));
        }
        header('Location: ' . $self . '#expense-' . (int) ($_POST['id'] ?? 0));
        exit;
    }
}

$ppa = storeFindPpa($ppa['ref']);
$expenses = ppaExpenses($ppa['ref']);
$spent = ppaExpenseTotal($ppa['ref']);
$remaining = (float) $ppa['cost'] - $spent;
$counts = expenseAuditCounts($expenses);
$canRecord = canRecordExpense($ppa);
$pageTitle = 'Expenses: ' . $ppa['ref'];

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="implementation-workflow.php">Implementation</a> / <?= e($ppa['ref']) ?></p>
        <h1><?= e($ppa['title']) ?></h1>
        <p class="muted"><?= e(officeName($ppa['office'])) ?> · <?= e(displayDate($ppa['start'])) ?> – <?= e(displayDate($ppa['end'])) ?></p>
    </div>
    <div class="heading-actions"><?= statusPill($ppa['status']) ?><a class="secondary-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">Open full record</a></div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Approved budget</span><span class="stat-icon blue">₱</span></div><strong><?= pesoShort((float) $ppa['cost']) ?></strong><p>Requested <?= pesoShort(ppaRequested($ppa)['total']) ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Spent so far</span><span class="stat-icon amber">⎙</span></div><strong data-spent><?= pesoShort($spent) ?></strong><div class="progress-track"><span style="width:<?= $ppa['cost'] > 0 ? min(100, round($spent / $ppa['cost'] * 100)) : 0 ?>%"></span></div><p><?= $ppa['cost'] > 0 ? number_format($spent / $ppa['cost'] * 100, 1) : '0.0' ?>% of the approved budget</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Unutilized</span><span class="stat-icon green">◔</span></div><strong data-remaining><?= pesoShort(max(0, $remaining)) ?></strong><p>Still available to spend</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Audit status</span><span class="stat-icon blue">✓</span></div><strong data-audit-counts><?= $counts['verified'] ?> / <?= count($expenses) ?></strong><p><?= $counts['verified'] ?> verified · <?= $counts['flagged'] ?> flagged · <?= $counts['unverified'] ?> not yet audited</p></article>
</section>

<div class="review-layout">
    <div class="review-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">↧</span><div><h2>Expenditure timeline</h2><p>Every expense in date order, with its receipt</p></div></div>
            <div class="form-divider"></div>
            <?php if (!$expenses): ?><p class="empty-state">No expenses recorded yet.</p><?php endif; ?>
            <ol class="expense-timeline">
                <?php $running = 0.0; foreach ($expenses as $x): $running += $x['amount']; ?>
                    <li id="expense-<?= $x['id'] ?>" class="expense-item <?= e($x['audit']['status']) ?>" data-expense="<?= $x['id'] ?>">
                        <div class="expense-date"><b><?= e(date('M j', strtotime($x['date']))) ?></b><small><?= e(date('Y', strtotime($x['date']))) ?></small></div>
                        <div class="expense-body">
                            <div class="history-head"><strong><?= e($x['description']) ?></strong><?= expenseAuditPill($x) ?></div>
                            <p class="expense-meta"><b><?= peso($x['amount']) ?></b> · <?= e($x['receipt_no']) ?> · running total <?= peso($running) ?></p>
                            <small class="table-subtext">Recorded by <?= e(explode(' (', $x['recorded_by'])[0]) ?> on <?= e(date('M j, Y g:i A', strtotime($x['recorded_at']))) ?></small>
                            <div class="expense-receipt">
                                <?php if ($x['file']): ?>
                                    <?php if (str_starts_with($x['file']['mime'], 'image/')): ?>
                                        <a href="receipt.php?id=<?= $x['id'] ?>" target="_blank" rel="noopener"><img src="receipt.php?id=<?= $x['id'] ?>" alt="Receipt <?= e($x['receipt_no']) ?>" loading="lazy"></a>
                                    <?php else: ?>
                                        <a class="trace-chip record" href="receipt.php?id=<?= $x['id'] ?>" target="_blank" rel="noopener">PDF receipt · <?= e($x['file']['name']) ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="trace-chip missing">Sample record: no receipt image</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($x['audit']['status'] !== 'unverified'): ?>
                                <p class="funding-issue <?= $x['audit']['status'] === 'flagged' ? 'error' : 'info' ?>"><strong><?= $x['audit']['status'] === 'flagged' ? 'Flagged' : 'Verified' ?> by <?= e(explode(' (', $x['audit']['by'])[0]) ?></strong> on <?= e(date('M j, Y', strtotime($x['audit']['at']))) ?><?= $x['audit']['note'] !== '' ? ': ' . e($x['audit']['note']) : '' ?></p>
                            <?php endif; ?>
                            <div class="row-actions">
                                <?php if (can('expenses.verify')): ?>
                                    <form method="post" class="audit-form">
                                        <?= csrfField() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
                                        <input type="text" name="note" maxlength="500" placeholder="Audit note (required when flagging)" aria-label="Audit note">
                                        <button class="text-button" type="submit" name="action" value="verified">✓ Verify</button>
                                        <button class="text-button danger-text" type="submit" name="action" value="flagged">⚑ Flag</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (canDeleteExpense($x, $ppa)): ?>
                                    <form method="post" data-confirm="Remove the expense <?= e($x['receipt_no']) ?> (<?= e(peso($x['amount'])) ?>)?">
                                        <?= csrfField() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><input type="hidden" name="action" value="delete">
                                        <button class="text-button danger-text" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
    <div class="review-side">
        <?php if ($canRecord): ?>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon amber-bg">＋</span><div><h2>Record an expense</h2><p>Attach a photo or scan of the receipt as proof</p></div></div>
            <div class="form-divider"></div>
            <?php if ($errors): ?><div class="alert error" role="alert"><strong>The expense was not saved.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="expense-form" novalidate>
                <?= csrfField() ?><input type="hidden" name="action" value="add">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= RECEIPT_MAX_BYTES ?>">
                <label>Date of expense <em>*</em><input name="date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($old['date']) ?>" required></label>
                <label>Description <em>*</em><input name="description" type="text" maxlength="200" placeholder="e.g. Cement, 200 bags" value="<?= e($old['description']) ?>" required></label>
                <label>Amount <em>*</em><div class="currency-input"><span>₱</span><input name="amount" type="text" inputmode="decimal" placeholder="0.00" value="<?= e($old['amount']) ?>" required></div><small>Up to <?= peso(max(0, $remaining)) ?> unutilized</small></label>
                <label>Receipt / OR / voucher no. <em>*</em><input name="receipt_no" type="text" maxlength="40" placeholder="e.g. OR-12345" value="<?= e($old['receipt_no']) ?>" required></label>
                <label>Receipt photo or scan <em>*</em><input name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required><small>JPG, PNG, WEBP or PDF, up to 5 MB.</small></label>
                <button class="primary-button" type="submit">Save expense</button>
            </form>
        </section>
        <?php elseif (can('expenses.record') && $ppa['status'] === 'completed'): ?>
            <section class="panel form-panel"><p class="muted">This PPA is completed; no more expenses can be recorded.</p></section>
        <?php endif; ?>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▥</span><div><h2>Spending by month</h2><p>Expenditure over time</p></div></div>
            <div class="form-divider"></div>
            <?php $byMonth = []; foreach ($expenses as $x) { $m = substr($x['date'], 0, 7); $byMonth[$m] = ($byMonth[$m] ?? 0) + $x['amount']; } $maxMonth = max([1] + array_values($byMonth)); ?>
            <?php if (!$byMonth): ?><p class="empty-state">Nothing spent yet.</p><?php endif; ?>
            <ul class="month-bars">
                <?php foreach ($byMonth as $m => $amount): ?>
                    <li><span class="fund-bar-label"><?= e(date('M Y', strtotime($m . '-01'))) ?></span><span class="pipeline-bar"><span class="approved" style="width:<?= round($amount / $maxMonth * 100) ?>%"></span></span><b><?= pesoShort($amount) ?></b></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
