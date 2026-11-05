<?php
/**
 * Create or edit a fund source and its ceiling for the AIP year.
 */
$pageSection = 'Reports & Funds';
$activePage = 'funds';
$requiredPermission = 'funds.manage';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$editing = null;
if (isset($_GET['key'])) {
    $editing = storeFindFund((string) $_GET['key']);
    if (!$editing) {
        flash('error', 'That fund source was not found.');
        header('Location: fund-monitoring.php');
        exit;
    }
}
$pageTitle = $editing ? 'Edit ' . $editing['name'] : 'Add Fund Source';
$totals = $editing ? fundTotals()[$editing['key']] : null;

$old = $editing
    ? ['key' => $editing['key'], 'name' => $editing['name'], 'code' => $editing['code'], 'category' => $editing['category'],
       'ceiling' => number_format((float) $editing['ceiling'], 2), 'description' => $editing['description'], 'active' => !empty($editing['active']) ? '1' : '']
    : ['key' => '', 'name' => '', 'code' => '', 'category' => '', 'ceiling' => '', 'description' => '', 'active' => '1'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (['key', 'name', 'code', 'category', 'ceiling', 'description'] as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $old['active'] = isset($_POST['active']) ? '1' : '';
    if ($editing) {
        $old['key'] = $editing['key'];   // the short code cannot change once PPAs may refer to it
    } else {
        $old['key'] = strtoupper($old['key']);
    }
    $old['code'] = strtoupper($old['code']);

    if (!$editing) {
        if ($old['key'] === '') {
            $errors['key'] = 'Short code is required.';
        } elseif (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $old['key'])) {
            $errors['key'] = 'Use 2–10 capital letters or numbers, starting with a letter (e.g. GF).';
        } elseif (storeFindFund($old['key'])) {
            $errors['key'] = 'This short code is already used.';
        }
    }
    if ($old['name'] === '') {
        $errors['name'] = 'Fund source name is required.';
    } elseif (mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Use 100 characters or fewer.';
    }
    if ($old['code'] === '') {
        $errors['code'] = 'Account code is required.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9.\-]{1,19}$/', $old['code'])) {
        $errors['code'] = 'Use letters, numbers, dots or dashes (e.g. GF-101).';
    }
    foreach (fundSources() as $other) {
        if ($editing && $other['key'] === $editing['key']) {
            continue;
        }
        if (!isset($errors['name']) && strtolower($other['name']) === strtolower($old['name'])) {
            $errors['name'] = 'Another fund source already has this name.';
        }
        if (!isset($errors['code']) && $other['code'] === $old['code']) {
            $errors['code'] = 'Another fund source already uses this account code.';
        }
    }
    if (!in_array($old['category'], fundCategories(), true)) {
        $errors['category'] = 'Choose a category from the list.';
    }
    $ceiling = parseMoney($old['ceiling']);
    if ($old['ceiling'] === '') {
        $errors['ceiling'] = 'Enter the ceiling for FY ' . AIP_FISCAL_YEAR . ' (0 if none).';
    } elseif ($ceiling === null) {
        $errors['ceiling'] = 'Enter a valid amount, e.g. 12,000,000.00';
    } elseif ($ceiling > 999999999999.99) {
        $errors['ceiling'] = 'Amount is too large.';
    }
    if (mb_strlen($old['description']) > 300) {
        $errors['description'] = 'Use 300 characters or fewer.';
    }

    if (!$errors) {
        $record = ['key' => $old['key'], 'name' => $old['name'], 'code' => $old['code'], 'category' => $old['category'],
                   'ceiling' => $ceiling, 'description' => $old['description'], 'active' => $old['active'] === '1'];
        if ($editing) {
            $changes = [];
            $labels = ['name' => 'Name', 'code' => 'Account code', 'category' => 'Category', 'description' => 'Description'];
            foreach ($labels as $field => $label) {
                if ((string) $editing[$field] !== (string) $record[$field]) {
                    $changes[] = $field === 'description' ? 'Description edited' : $label . ': ' . $editing[$field] . ' → ' . $record[$field];
                }
            }
            if (abs((float) $editing['ceiling'] - $ceiling) >= 0.01) {
                $changes[] = 'Ceiling: ' . peso((float) $editing['ceiling']) . ' → ' . peso($ceiling);
            }
            if (!empty($editing['active']) !== $record['active']) {
                $changes[] = 'Status: ' . ($record['active'] ? 'Inactive → Active' : 'Active → Inactive');
            }
            storeSaveFund($record);
            if ($changes) {
                auditLog('fund.update', $record['key'], implode('; ', $changes));
                $message = 'Saved changes to ' . e($record['name']) . '.';
                if ($totals && $ceiling < $totals['programmed']) {
                    $message .= ' Note: the new ceiling is below the ' . e(peso($totals['programmed'])) . ' already programmed; this is shown under Funding issues.';
                }
                flash('success', $message);
            } else {
                flash('info', 'No changes were made to ' . e($record['name']) . '.');
            }
        } else {
            storeSaveFund($record);
            auditLog('fund.create', $record['key'], $record['name'] . ' · ' . $record['category'] . ' · ceiling ' . peso($ceiling));
            flash('success', 'Added fund source ' . e($record['name']) . '.');
        }
        header('Location: fund-source.php?key=' . urlencode($record['key']));
        exit;
    }
}

function fundFieldError(array $errors, string $field): string
{
    return '<small class="field-error" data-error-for="' . $field . '">' . e($errors[$field] ?? '') . '</small>';
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="fund-monitoring.php">Fund Monitoring</a> / <?= $editing ? e($editing['code']) : 'New fund source' ?></p>
        <h1><?= $editing ? 'Edit ' . e($editing['name']) : 'Add Fund Source' ?></h1>
        <p class="muted">Fund sources and their FY <?= AIP_FISCAL_YEAR ?> ceilings are used to check that PPAs can be funded.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>Please correct <?= count($errors) === 1 ? 'the field' : 'the ' . count($errors) . ' fields' ?> below.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="user-form-layout" data-validate novalidate>
    <?= csrfField() ?>
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon blue-bg">▥</span><div><h2>Fund source details</h2><p>How the source is identified in the AIP.</p></div></div>
        <div class="form-divider"></div>
        <div class="form-columns">
            <label>Short Code <em>*</em><input name="key" type="text" maxlength="10" placeholder="e.g. GF" value="<?= e($old['key']) ?>" <?= $editing ? 'readonly class="readonly-input"' : 'required' ?>><?= $editing ? '<small>Cannot be changed after creation.</small>' : '' ?><?= fundFieldError($errors, 'key') ?></label>
            <label>Account Code <em>*</em><input name="code" type="text" maxlength="20" placeholder="e.g. GF-101" value="<?= e($old['code']) ?>" required><?= fundFieldError($errors, 'code') ?></label>
        </div>
        <label>Fund Source Name <em>*</em><input name="name" type="text" maxlength="100" placeholder="e.g. General Fund" value="<?= e($old['name']) ?>" required><?= fundFieldError($errors, 'name') ?></label>
        <label>Category <em>*</em><select name="category" required><option value="">Select category</option><?php foreach (fundCategories() as $category): ?><option value="<?= e($category) ?>" <?= $old['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select><?= fundFieldError($errors, 'category') ?></label>
        <label>Description<textarea name="description" rows="3" maxlength="300" placeholder="What this fund is for"><?= e($old['description']) ?></textarea><?= fundFieldError($errors, 'description') ?></label>
    </section>

    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon amber-bg">₱</span><div><h2>Ceiling and availability</h2><p>The amount available for PPAs in FY <?= AIP_FISCAL_YEAR ?>.</p></div></div>
        <div class="form-divider"></div>
        <label>FY <?= AIP_FISCAL_YEAR ?> Ceiling <em>*</em><div class="currency-input"><span>₱</span><input name="ceiling" type="text" inputmode="decimal" placeholder="0.00" data-rule="money-optional" value="<?= e($old['ceiling']) ?>" required></div><?= fundFieldError($errors, 'ceiling') ?></label>
        <?php if ($totals): ?>
            <p class="form-note">Currently programmed: <strong><?= peso($totals['programmed']) ?></strong> · pending: <strong><?= peso($totals['pending']) ?></strong></p>
        <?php endif; ?>
        <label class="check-option toggle-option"><input type="checkbox" name="active" value="1" <?= $old['active'] === '1' ? 'checked' : '' ?>> <span><strong>Active</strong><small>Inactive sources cannot be chosen for PPAs. PPAs already charged to them are flagged.</small></span></label>
    </section>

    <div class="form-actions">
        <a class="secondary-button" href="<?= $editing ? 'fund-source.php?key=' . urlencode($editing['key']) : 'fund-monitoring.php' ?>">Cancel</a>
        <button class="primary-button" type="submit"><?= $editing ? 'Save changes' : 'Add fund source' ?> <span>→</span></button>
    </div>
</form>
<?php include '../includes/footer.php'; ?>
