<?php
$pageTitle = 'Project Encoding';
$pageSection = 'Planning';
$activePage = 'projects';
$requiredPermission = 'ppa.encode';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

// Encoders may only encode PPAs for their own office.
$offices = array_filter(mockOffices(), fn ($name, $code) => canSeeOffice($code), ARRAY_FILTER_USE_BOTH);
$fundSources = mockFundSources();
$ppaTypes = mockPpaTypes();
$fyStart = AIP_FISCAL_YEAR . '-01-01';
$fyEnd = AIP_FISCAL_YEAR . '-12-31';

$errors = [];
$result = null;   // 'draft' or 'submitted' after a valid POST
$summary = [];
$old = [];

/**
 * Validate the PPA form. Drafts only need a title; every value that is
 * provided must still be valid. Submissions need every required field.
 */
function validatePpa(array $input, bool $isDraft, array $offices, array $fundSources, array $ppaTypes, string $fyStart, string $fyEnd): array
{
    $errors = [];
    $required = $isDraft
        ? ['project_title']
        : ['project_title', 'ppa_type', 'implementing_office', 'description', 'estimated_cost', 'fund_source', 'start_date', 'completion_date'];
    $labels = [
        'project_title' => 'Project title', 'ppa_type' => 'PPA type', 'implementing_office' => 'Implementing office',
        'description' => 'Description', 'estimated_cost' => 'Total estimated cost', 'fund_source' => 'Fund source',
        'start_date' => 'Target start date', 'completion_date' => 'Target completion date',
    ];
    foreach ($required as $field) {
        if ($input[$field] === '') {
            $errors[$field] = $labels[$field] . ' is required.';
        }
    }

    if (!isset($errors['project_title']) && mb_strlen($input['project_title']) > 200) {
        $errors['project_title'] = 'Use 200 characters or fewer.';
    }
    if ($input['ppa_type'] !== '' && !in_array($input['ppa_type'], $ppaTypes, true)) {
        $errors['ppa_type'] = 'Choose a PPA type from the list.';
    }
    if ($input['implementing_office'] !== '' && !isset($offices[$input['implementing_office']])) {
        $errors['implementing_office'] = 'Choose an implementing office from the list.';
    }
    if ($input['fund_source'] !== '' && !isset($fundSources[$input['fund_source']])) {
        $errors['fund_source'] = 'Choose a fund source from the list.';
    }
    if (mb_strlen($input['program']) > 150) {
        $errors['program'] = 'Use 150 characters or fewer.';
    }
    if (mb_strlen($input['description']) > 500) {
        $errors['description'] = 'Use 500 characters or fewer.';
    }
    if (mb_strlen($input['expected_output']) > 300) {
        $errors['expected_output'] = 'Use 300 characters or fewer.';
    }

    if ($input['estimated_cost'] !== '') {
        $cost = str_replace(',', '', $input['estimated_cost']);
        if (!preg_match('/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/', $input['estimated_cost'])) {
            $errors['estimated_cost'] = 'Enter a valid amount, e.g. 1,250,000.00';
        } elseif ((float) $cost <= 0) {
            $errors['estimated_cost'] = 'Amount must be greater than zero.';
        } elseif ((float) $cost > 999999999999.99) {
            $errors['estimated_cost'] = 'Amount is too large.';
        }
    }

    foreach (['start_date', 'completion_date'] as $field) {
        if ($input[$field] === '' || isset($errors[$field])) {
            continue;
        }
        $date = DateTime::createFromFormat('Y-m-d', $input[$field]);
        if (!$date || $date->format('Y-m-d') !== $input[$field]) {
            $errors[$field] = 'Enter a valid date.';
        } elseif ($input[$field] < $fyStart || $input[$field] > $fyEnd) {
            $errors[$field] = 'Date must fall within FY ' . AIP_FISCAL_YEAR . '.';
        }
    }
    if (!isset($errors['start_date']) && !isset($errors['completion_date'])
        && $input['start_date'] !== '' && $input['completion_date'] !== ''
        && $input['completion_date'] < $input['start_date']) {
        $errors['completion_date'] = 'Target completion date cannot be earlier than the start date.';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fields = ['project_title', 'ppa_type', 'implementing_office', 'program', 'description', 'expected_output', 'estimated_cost', 'fund_source', 'start_date', 'completion_date'];
    foreach ($fields as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $isDraft = isset($_POST['save_draft']);
    $errors = validatePpa($old, $isDraft, $offices, $fundSources, $ppaTypes, $fyStart, $fyEnd);

    if (!$errors) {
        $result = $isDraft ? 'draft' : 'submitted';
        $summary = [
            'Title'               => $old['project_title'],
            'Type'                => $old['ppa_type'] ?: '—',
            'Implementing office' => $old['implementing_office'] ? $offices[$old['implementing_office']] : '—',
            'Estimated cost'      => $old['estimated_cost'] !== '' ? peso((float) str_replace(',', '', $old['estimated_cost'])) : '—',
            'Fund source'         => $old['fund_source'] ? $fundSources[$old['fund_source']]['name'] : '—',
            'Schedule'            => ($old['start_date'] === '' && $old['completion_date'] === '') ? '—' : displayDate($old['start_date']) . ' – ' . displayDate($old['completion_date']),
        ];
        auditLog($isDraft ? 'ppa.draft_checked' : 'ppa.submit_checked', $old['project_title'], 'Validated only; not stored (no database yet)');
        // MOCK: nothing is stored. In the database phase this is where the PPA is saved
        // and, for submissions, its status is set to "Pending Review".
        if ($result === 'submitted') {
            $old = [];
        }
    }
}

/** Old input value for redisplaying the form. */
function old(array $old, string $field): string
{
    return e($old[$field] ?? '');
}

include '../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Preparation</p><h1>Project Encoding</h1><p class="muted">Enter the details of the proposed Program, Project, or Activity (PPA) for the FY <?= AIP_FISCAL_YEAR ?> AIP to start the review workflow.</p></div></div>

<section class="workflow-stepper" aria-label="Workflow stage"><div class="step active"><span>1</span><b>PREPARATION</b></div><div class="step"><span>2</span><b>REVIEW</b></div><div class="step"><span>3</span><b>AUTHORIZATION</b></div><div class="step"><span>4</span><b>IMPLEMENTATION</b></div></section>

<?php if ($result): ?>
    <div class="alert success" role="status">
        <strong><?= $result === 'draft' ? 'Draft checked: no errors found.' : 'Proposal checked: ready for MPDO review.' ?></strong>
        Development version: the proposal was <strong>not stored</strong> because the database is not connected yet.
        <ul><?php foreach ($summary as $label => $value): ?><li><?= e($label) ?>: <?= e($value) ?></li><?php endforeach; ?></ul>
    </div>
<?php elseif ($errors): ?>
    <div class="alert error" role="alert">
        <strong>Please correct <?= count($errors) === 1 ? 'the field' : 'the ' . count($errors) . ' fields' ?> below.</strong>
        <ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="encoding-form" data-encoding-form data-validate data-draft-required="project_title" novalidate>
    <?= csrfField() ?>
    <div class="encoding-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>General Identification</h2><p>Provide the basic details of the proposed investment.</p></div></div>
            <div class="form-divider"></div>
            <label>Project Title <em>*</em><input name="project_title" type="text" maxlength="200" placeholder="Enter formal title of the PPA" value="<?= old($old, 'project_title') ?>" required><small class="field-error" data-error-for="project_title"><?= e($errors['project_title'] ?? '') ?></small></label>
            <div class="form-columns">
                <label>PPA Type <em>*</em><select name="ppa_type" required><option value="">Select type</option><?php foreach ($ppaTypes as $type): ?><option value="<?= e($type) ?>" <?= ($old['ppa_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select><small class="field-error" data-error-for="ppa_type"><?= e($errors['ppa_type'] ?? '') ?></small></label>
                <label>Program Name<input name="program" type="text" maxlength="150" placeholder="e.g. Rural Infrastructure Program" value="<?= old($old, 'program') ?>"><small class="field-error" data-error-for="program"><?= e($errors['program'] ?? '') ?></small></label>
            </div>
            <label>Description <em>*</em><textarea name="description" rows="5" maxlength="500" placeholder="Provide a detailed rationale and expected outcomes..." required><?= old($old, 'description') ?></textarea><small data-counter-for="description">0 / 500 characters</small><small class="field-error" data-error-for="description"><?= e($errors['description'] ?? '') ?></small></label>
            <label>Expected Outputs<textarea name="expected_output" rows="3" maxlength="300" placeholder="e.g. 1.2 km concrete road completed"><?= old($old, 'expected_output') ?></textarea><small class="field-error" data-error-for="expected_output"><?= e($errors['expected_output'] ?? '') ?></small></label>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▦</span><div><h2>Implementation &amp; Logistics</h2><p>Identify the responsible office.</p></div></div>
            <div class="form-divider"></div>
            <label>Implementing Office <em>*</em><select name="implementing_office" required><option value="">Select department/office</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>" <?= ($old['implementing_office'] ?? (count($offices) === 1 ? $code : '')) === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?php if (count($offices) === 1): ?><small>PPAs are encoded under your assigned office.</small><?php endif; ?><small class="field-error" data-error-for="implementing_office"><?= e($errors['implementing_office'] ?? '') ?></small></label>
        </section>
    </div>
    <div class="encoding-side">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon amber-bg">▣</span><div><h2>Financial Estimates</h2><p>Set the expected cost and source.</p></div></div>
            <div class="form-divider"></div>
            <label>Total Estimated Cost <em>*</em><div class="currency-input"><span>₱</span><input name="estimated_cost" type="text" inputmode="decimal" placeholder="0.00" data-rule="money" value="<?= old($old, 'estimated_cost') ?>" required></div><small class="field-error" data-error-for="estimated_cost"><?= e($errors['estimated_cost'] ?? '') ?></small></label>
            <label>Primary Fund Source <em>*</em><select name="fund_source" required><option value="">Select source</option><?php foreach ($fundSources as $code => $fund): ?><option value="<?= e($code) ?>" <?= ($old['fund_source'] ?? '') === $code ? 'selected' : '' ?>><?= e($fund['name']) ?></option><?php endforeach; ?></select><small class="field-error" data-error-for="fund_source"><?= e($errors['fund_source'] ?? '') ?></small></label>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▣</span><div><h2>Implementation Timeline</h2><p>Dates must fall within FY <?= AIP_FISCAL_YEAR ?>.</p></div></div>
            <div class="form-divider"></div>
            <label>Target Start Date <em>*</em><input name="start_date" type="date" min="<?= $fyStart ?>" max="<?= $fyEnd ?>" value="<?= old($old, 'start_date') ?>" required><small class="field-error" data-error-for="start_date"><?= e($errors['start_date'] ?? '') ?></small></label>
            <label>Target Completion Date <em>*</em><input name="completion_date" type="date" min="<?= $fyStart ?>" max="<?= $fyEnd ?>" data-after="start_date" value="<?= old($old, 'completion_date') ?>" required><small class="field-error" data-error-for="completion_date"><?= e($errors['completion_date'] ?? '') ?></small></label>
        </section>
    </div>
    <div class="form-actions">
        <button class="secondary-button" type="button" data-reset-form>Clear form</button>
        <button class="secondary-button" type="submit" name="save_draft" value="1" data-draft>Save Draft</button>
        <button class="primary-button" type="submit" name="submit_review" value="1">Submit for Review <span>→</span></button>
    </div>
    <p class="form-note">Fields marked <em>*</em> are required to submit for review. A draft only needs a title.</p>
</form>
<?php include '../includes/footer.php'; ?>
