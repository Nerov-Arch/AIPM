<?php
/**
 * Assign a PPA's cost to one or more fund sources.
 * The allocations must add up exactly to the PPA's total cost.
 */
$pageSection = 'Reports & Funds';
$activePage = 'funds';
$requiredPermission = 'funds.manage';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
if (!$ppa || $ppa['status'] === 'draft') {
    flash('error', 'That PPA was not found, or it is still a draft.');
    header('Location: fund-monitoring.php?tab=ppas');
    exit;
}
$pageTitle = 'Fund allocation · ' . $ppa['ref'];
$current = ppaAllocations($ppa);
$totals = fundTotals();

// Funds shown: every active fund, plus inactive ones this PPA is already charged to.
$funds = array_filter(fundSources(), fn ($f, $key) => !empty($f['active']) || isset($current[$key]), ARRAY_FILTER_USE_BOTH);

$old = [];
foreach ($funds as $key => $_) {
    $old[$key] = isset($current[$key]) ? number_format($current[$key], 2) : '';
}
$remarks = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    $allocations = [];
    foreach ($funds as $key => $fund) {
        $old[$key] = trim((string) ($_POST['alloc'][$key] ?? ''));
        $amount = parseMoney($old[$key]);
        if ($amount === null) {
            $errors['alloc_' . $key] = 'Enter a valid amount for ' . $fund['name'] . '.';
        } elseif ($amount > 0) {
            if (empty($fund['active']) && round($amount, 2) > round($current[$key] ?? 0, 2)) {
                $errors['alloc_' . $key] = $fund['name'] . ' is inactive; its allocation can only be reduced or removed.';
            }
            $allocations[$key] = $amount;
        }
    }
    $allocated = round(array_sum($allocations), 2);
    if (!$errors) {
        if (!$allocations) {
            $errors['total'] = 'Allocate the PPA cost to at least one fund source.';
        } elseif (abs($allocated - $ppa['cost']) >= 0.01) {
            $diff = $ppa['cost'] - $allocated;
            $errors['total'] = 'Allocations total ' . peso($allocated) . ' but the PPA costs ' . peso($ppa['cost'])
                . ' (' . ($diff > 0 ? peso($diff) . ' still unallocated' : peso(-$diff) . ' over') . ').';
        }
    }
    if ($remarks === '') {
        $errors['remarks'] = 'State the basis for this allocation (e.g. budget call, LFC advice).';
    } elseif (mb_strlen($remarks) > 300) {
        $errors['remarks'] = 'Use 300 characters or fewer.';
    }

    if (!$errors) {
        arsort($allocations);
        $primary = isset($allocations[$ppa['fund']]) ? $ppa['fund'] : array_key_first($allocations);
        $before = implode('; ', array_map(fn ($k, $v) => fundName($k) . ' ' . peso($v), array_keys($current), $current)) ?: 'none';
        $after = implode('; ', array_map(fn ($k, $v) => fundName($k) . ' ' . peso($v), array_keys($allocations), $allocations));
        storeSavePpa(['ref' => $ppa['ref'], 'fund' => $primary, 'allocations' => $allocations], 'Fund allocation updated', $after . ' — ' . $remarks);
        auditLog('ppa.funding', $ppa['ref'], $before . ' → ' . $after . ' · ' . $remarks);
        flash('success', 'Saved the fund allocation of ' . e($ppa['ref']) . '.');
        header('Location: ppa-view.php?ref=' . urlencode($ppa['ref']));
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="fund-monitoring.php?tab=ppas">Fund Monitoring</a> / <a href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a> / Fund allocation</p>
        <h1>Fund Allocation</h1>
        <p class="muted"><?= e($ppa['title']) ?> · <?= e(officeName($ppa['office'])) ?></p>
    </div>
    <div class="heading-actions"><?= statusPill($ppa['status']) ?></div>
</div>

<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>The allocation was not saved.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="allocation-form" data-allocation-form data-cost="<?= $ppa['cost'] ?>" novalidate>
    <?= csrfField() ?>
    <section class="panel flush-panel">
        <div class="panel-heading"><div><h2>Charge <?= peso($ppa['cost']) ?> to fund sources</h2><p class="muted">Enter how much of the PPA cost each fund source will cover. The total must equal the PPA cost.</p></div></div>
        <div class="table-wrap">
            <table class="activity-table allocation-table">
                <thead><tr><th>Fund source</th><th>Available (after approved PPAs)</th><th>Current allocation</th><th>New allocation</th></tr></thead>
                <tbody>
                <?php foreach ($funds as $key => $fund): $t = $totals[$key]; ?>
                    <tr>
                        <td><strong><?= e($fund['name']) ?></strong><small class="table-subtext"><?= e($fund['code']) ?><?= empty($fund['active']) ? ' · inactive' : '' ?><?= $key === $ppa['fund'] ? ' · primary fund' : '' ?></small></td>
                        <td class="amount <?= $t['available'] < 0 ? 'danger-text' : '' ?>"><?= peso($t['available']) ?></td>
                        <td><?= isset($current[$key]) ? peso($current[$key]) : '<span class="table-subtext">—</span>' ?></td>
                        <td>
                            <div class="currency-input"><span>₱</span><input name="alloc[<?= e($key) ?>]" type="text" inputmode="decimal" placeholder="0.00" data-rule="money-optional" data-alloc-part value="<?= e($old[$key]) ?>" aria-label="Allocation from <?= e($fund['name']) ?>"></div>
                            <?php if (isset($errors['alloc_' . $key])): ?><small class="field-error"><?= e($errors['alloc_' . $key]) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3">Total allocated</th><td class="amount" data-alloc-total>₱0.00</td></tr>
                    <tr><th colspan="3">PPA cost</th><td class="amount"><?= peso($ppa['cost']) ?></td></tr>
                    <tr class="alloc-balance"><th colspan="3">Difference</th><td class="amount" data-alloc-diff>₱0.00</td></tr>
                </tfoot>
            </table>
        </div>
    </section>
    <section class="panel form-panel">
        <label>Basis for this allocation <em>*</em><textarea name="remarks" rows="2" maxlength="300" placeholder="e.g. Per Local Finance Committee advice, Oct 2026" required><?= e($remarks) ?></textarea><?php if (isset($errors['remarks'])): ?><small class="field-error"><?= e($errors['remarks']) ?></small><?php endif; ?></label>
        <p class="form-note">The change is added to the PPA's status history and the activity log.</p>
        <div class="form-actions">
            <a class="secondary-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">Cancel</a>
            <button class="primary-button" type="submit">Save allocation <span>→</span></button>
        </div>
    </section>
</form>
<?php include '../includes/footer.php'; ?>
