<?php
/**
 * PPA encoding form: create a new PPA, or edit a draft / returned PPA.
 * Uses the standardized AIP fields defined in includes/ppa.php.
 */
$pageSection = 'Planning';
$activePage = 'projects';
$requiredPermission = 'ppa.encode';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

// Encoders may only encode PPAs for their own office.
$offices = array_filter(mockOffices(), fn ($name, $code) => canSeeOffice($code), ARRAY_FILTER_USE_BOTH);
$fundSources = activeFundSources();
$sectors = ppaSectors();
$fyStart = AIP_FISCAL_YEAR . '-01-01';
$fyEnd = AIP_FISCAL_YEAR . '-12-31';
$moneyFields = ['ps', 'mooe', 'co', 'cc_adaptation', 'cc_mitigation'];

$editing = null;
if (isset($_GET['ref'])) {
    $editing = storeFindPpa((string) $_GET['ref']);
    if (!$editing || !canSeeOffice($editing['office'])) {
        flash('error', 'That PPA was not found.');
        header('Location: ppas.php');
        exit;
    }
    if (!ppaIsEditable($editing)) {
        flash('error', e($editing['ref']) . ' is ' . e(strtolower(statusLabel($editing['status']))) . ' and can no longer be edited.');
        header('Location: ppa-view.php?ref=' . urlencode($editing['ref']));
        exit;
    }
}
$isReturned = $editing && $editing['status'] === 'returned';
$pageTitle = $editing ? 'Edit ' . $editing['ref'] : 'New PPA';

// Values shown in the form.
$old = [];
foreach (array_keys(PPA_FIELDS) as $field) {
    $value = $editing[$field] ?? '';
    if (in_array($field, $moneyFields, true)) {
        $value = ($editing && (float) $value > 0) ? number_format((float) $value, 2) : '';
    }
    $old[$field] = (string) $value;
}
if (!$editing && count($offices) === 1) {
    $old['office'] = array_key_first($offices);
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (array_keys(PPA_FIELDS) as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $isDraft = isset($_POST['save_draft']);
    [$errors, $clean] = validatePpa($old, $isDraft, $offices, $editing['ref'] ?? null);

    if (!$errors) {
        if ($editing) {
            $changes = ppaChanges($editing, $clean);
            if ($isDraft) {
                $saved = storeSavePpa(['ref' => $editing['ref']] + $clean, $changes ? 'Edited' : 'Saved without changes');
                if ($changes) {
                    auditLog('ppa.update', $saved['ref'], implode('; ', $changes));
                }
                flash('success', 'Saved ' . e($saved['ref']) . ($saved['status'] === 'returned' ? '. It is still returned for revision; resubmit it when ready.' : ' as a draft.'));
            } else {
                $saved = storeSavePpa(['ref' => $editing['ref'], 'status' => 'for_review', 'submitted_at' => date('Y-m-d H:i:s')] + $clean,
                    $isReturned ? 'Resubmitted after revision' : 'Submitted for review');
                if ($changes) {
                    auditLog('ppa.update', $saved['ref'], implode('; ', $changes));
                }
                auditLog($isReturned ? 'ppa.resubmit' : 'ppa.submit', $saved['ref'], $saved['title'] . ' · ' . peso($saved['cost']));
                flash('success', e($saved['ref']) . ' was ' . ($isReturned ? 'resubmitted' : 'submitted') . ' to the MPDO for review. It can no longer be edited unless it is returned.');
            }
        } else {
            $status = $isDraft ? 'draft' : 'for_review';
            $saved = storeSavePpa(['status' => $status, 'submitted_at' => $isDraft ? '' : date('Y-m-d H:i:s')] + $clean,
                $isDraft ? 'Draft created' : 'Encoded and submitted for review');
            auditLog('ppa.create', $saved['ref'], $saved['title'] . ' · ' . officeName($saved['office']));
            if (!$isDraft) {
                auditLog('ppa.submit', $saved['ref'], $saved['title'] . ' · ' . peso($saved['cost']));
            }
            flash('success', $isDraft
                ? 'Draft ' . e($saved['ref']) . ' saved. You can finish and submit it later from Project Encoding.'
                : e($saved['ref']) . ' was submitted to the MPDO for review.');
        }
        header('Location: ppa-view.php?ref=' . urlencode($saved['ref']));
        exit;
    }
}

function fieldError(array $errors, string $field): string
{
    return '<small class="field-error" data-error-for="' . $field . '">' . e($errors[$field] ?? '') . '</small>';
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="ppas.php">Project Encoding</a> / <?= $editing ? e($editing['ref']) : 'New PPA' ?></p>
        <h1><?= $editing ? 'Edit ' . e($editing['ref']) : 'Encode a New PPA' ?></h1>
        <p class="muted">Use the standard AIP fields for every Program, Project or Activity so the MPDO can review and consolidate submissions consistently.</p>
    </div>
    <?php if ($editing): ?><div class="heading-actions"><?= statusPill($editing['status']) ?></div><?php endif; ?>
</div>

<section class="workflow-stepper" aria-label="Workflow stage"><div class="step active"><span>1</span><b>PREPARATION</b></div><div class="step"><span>2</span><b>REVIEW</b></div><div class="step"><span>3</span><b>AUTHORIZATION</b></div><div class="step"><span>4</span><b>IMPLEMENTATION</b></div></section>

<?php if ($isReturned && $editing['remarks'] !== ''): ?>
    <div class="alert error remarks-alert" role="status"><strong>Returned by the MPDO for revision.</strong> <?= e($editing['remarks']) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert error" role="alert">
        <strong>Please correct <?= count($errors) === 1 ? 'the field' : 'the ' . count($errors) . ' fields' ?> below.</strong>
        <ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="encoding-form" data-validate data-draft-required="title office" novalidate>
    <?= csrfField() ?>
    <div class="encoding-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>1. General Identification</h2><p>What the PPA is and what it will deliver.</p></div></div>
            <div class="form-divider"></div>
            <?php if ($editing): ?><label>AIP Reference Code<input type="text" value="<?= e($editing['ref']) ?>" readonly class="readonly-input"></label><?php else: ?><p class="form-note ref-note">The AIP reference code (e.g. <?= e(nextPpaRef()) ?>) is assigned automatically when you save.</p><?php endif; ?>
            <label>PPA Title / Description <em>*</em><input name="title" type="text" maxlength="200" placeholder="e.g. Concreting of Barangay Farm-to-Market Road (1.2 km)" value="<?= e($old['title']) ?>" required><?= fieldError($errors, 'title') ?></label>
            <div class="form-columns">
                <label>PPA Type <em>*</em><select name="type" required><option value="">Select type</option><?php foreach (mockPpaTypes() as $type): ?><option value="<?= e($type) ?>" <?= $old['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select><?= fieldError($errors, 'type') ?></label>
                <label>Sector <em>*</em><select name="sector" required><option value="">Select sector</option><?php foreach ($sectors as $code => $name): ?><option value="<?= e($code) ?>" <?= $old['sector'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?= fieldError($errors, 'sector') ?></label>
            </div>
            <label>Program <span class="optional-label">(required for projects and activities)</span><input name="program" type="text" maxlength="150" placeholder="e.g. Rural Infrastructure Program" value="<?= e($old['program']) ?>" list="program-list"><?= fieldError($errors, 'program') ?></label>
            <datalist id="program-list"><?php foreach (array_unique(array_column(visiblePpas(true), 'program')) as $program): ?><option value="<?= e($program) ?>"><?php endforeach; ?></datalist>
            <label>Rationale / Description <em>*</em><textarea name="description" rows="4" maxlength="1000" placeholder="Why the PPA is needed and who will benefit" required><?= e($old['description']) ?></textarea><small data-counter-for="description">0 / 1000 characters</small><?= fieldError($errors, 'description') ?></label>
            <label>Expected Outputs <em>*</em><textarea name="expected_output" rows="3" maxlength="500" placeholder="e.g. 1.2 km concrete road completed; 3 barangays connected" required><?= e($old['expected_output']) ?></textarea><small data-counter-for="expected_output">0 / 500 characters</small><?= fieldError($errors, 'expected_output') ?></label>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▦</span><div><h2>2. Implementation</h2><p>Responsible office and schedule within FY <?= AIP_FISCAL_YEAR ?>.</p></div></div>
            <div class="form-divider"></div>
            <label>Implementing Office <em>*</em><select name="office" required <?= count($offices) === 1 ? 'data-locked' : '' ?>><?php if (count($offices) > 1): ?><option value="">Select office</option><?php endif; ?><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>" <?= $old['office'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?php if (count($offices) === 1): ?><small>PPAs are encoded under your assigned office.</small><?php endif; ?><?= fieldError($errors, 'office') ?></label>
            <div class="form-columns">
                <label>Start Date <em>*</em><input name="start" type="date" min="<?= $fyStart ?>" max="<?= $fyEnd ?>" value="<?= e($old['start']) ?>" required><?= fieldError($errors, 'start') ?></label>
                <label>Completion Date <em>*</em><input name="end" type="date" min="<?= $fyStart ?>" max="<?= $fyEnd ?>" data-after="start" value="<?= e($old['end']) ?>" required><?= fieldError($errors, 'end') ?></label>
            </div>
        </section>
    </div>

    <div class="encoding-side">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon amber-bg">₱</span><div><h2>3. Cost and Funding</h2><p>Amounts in pesos by expense class.</p></div></div>
            <div class="form-divider"></div>
            <?php foreach (['ps' => 'Personal Services (PS)', 'mooe' => 'Maintenance and Other Operating Expenses (MOOE)', 'co' => 'Capital Outlay (CO)'] as $field => $label): ?>
                <label><?= e($label) ?><div class="currency-input"><span>₱</span><input name="<?= $field ?>" type="text" inputmode="decimal" placeholder="0.00" data-rule="money-optional" data-total-part value="<?= e($old[$field]) ?>"></div><?= fieldError($errors, $field) ?></label>
            <?php endforeach; ?>
            <div class="total-cost"><span>Total Estimated Cost</span><strong data-total-display>₱0.00</strong></div>
            <label>Fund Source <em>*</em><select name="fund" required><option value="">Select source</option><?php foreach ($fundSources as $code => $fund): ?><option value="<?= e($code) ?>" <?= $old['fund'] === $code ? 'selected' : '' ?>><?= e($fund['name']) ?></option><?php endforeach; ?></select><?= fieldError($errors, 'fund') ?></label>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon green-bg">♻</span><div><h2>4. Climate Change Expenditure</h2><p>Leave blank if not applicable.</p></div></div>
            <div class="form-divider"></div>
            <label>Adaptation<div class="currency-input"><span>₱</span><input name="cc_adaptation" type="text" inputmode="decimal" placeholder="0.00" data-rule="money-optional" value="<?= e($old['cc_adaptation']) ?>"></div><?= fieldError($errors, 'cc_adaptation') ?></label>
            <label>Mitigation<div class="currency-input"><span>₱</span><input name="cc_mitigation" type="text" inputmode="decimal" placeholder="0.00" data-rule="money-optional" value="<?= e($old['cc_mitigation']) ?>"></div><?= fieldError($errors, 'cc_mitigation') ?></label>
            <label>CC Typology Code<input name="cc_typology" type="text" maxlength="20" placeholder="e.g. A612-01" value="<?= e($old['cc_typology']) ?>"><small>Required when an adaptation or mitigation amount is entered.</small><?= fieldError($errors, 'cc_typology') ?></label>
        </section>
    </div>

    <div class="form-actions">
        <a class="secondary-button" href="<?= $editing ? 'ppa-view.php?ref=' . urlencode($editing['ref']) : 'ppas.php' ?>">Cancel</a>
        <button class="secondary-button" type="submit" name="save_draft" value="1" data-draft><?= $isReturned ? 'Save changes' : 'Save Draft' ?></button>
        <button class="primary-button" type="submit" name="submit_review" value="1" data-confirm-submit="<?= $isReturned ? 'Resubmit this PPA to the MPDO? You will not be able to edit it unless it is returned again.' : 'Submit this PPA to the MPDO for review? You will not be able to edit it unless it is returned.' ?>"><?= $isReturned ? 'Resubmit for Review' : 'Submit for Review' ?> <span>→</span></button>
    </div>
    <p class="form-note">Fields marked <em>*</em> are required to submit. A draft needs only a title and office.</p>
</form>
<?php include '../includes/footer.php'; ?>
