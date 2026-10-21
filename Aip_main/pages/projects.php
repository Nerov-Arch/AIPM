<?php
$pageTitle = 'Project Encoding';
$activePage = 'projects';
$assetPrefix = '../';
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$formError = '';
if ($submitted) {
    $projectTitle = trim($_POST['project_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $estimatedCost = trim($_POST['estimated_cost'] ?? '');
    if ($projectTitle === '' || $description === '' || $estimatedCost === '') {
        $formError = 'Complete the required fields before saving this proposal.';
    } else {
        $submitted = true;
    }
}
include '../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Preparation</p><h1>Project Encoding</h1><p class="muted">Enter comprehensive details for the proposed Program, Project, or Activity (PPA) to initiate the formal review workflow.</p></div></div>
<section class="workflow-stepper"><div class="step active"><span>1</span><b>PREPARATION</b></div><div class="step"><span>2</span><b>REVIEW</b></div><div class="step"><span>3</span><b>AUTHORIZATION</b></div><div class="step"><span>4</span><b>IMPLEMENTATION</b></div></section>
<?php if ($submitted && $formError === ''): ?><div class="alert success" role="status">Your proposal has been saved as a draft and is ready for the next workflow step.</div><?php elseif ($formError): ?><div class="alert error" role="alert"><?= htmlspecialchars($formError) ?></div><?php endif; ?>
<form method="post" class="encoding-form" data-encoding-form>
    <div class="encoding-main">
        <section class="panel form-panel"><div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>General Identification</h2><p>Provide the basic details of the proposed investment.</p></div></div><div class="form-divider"></div><label>Project Title <em>*</em><input name="project_title" type="text" placeholder="Enter formal title of the PPA" value="<?= htmlspecialchars($_POST['project_title'] ?? '') ?>" required></label><label>Description <em>*</em><textarea name="description" rows="5" maxlength="500" placeholder="Provide a detailed rationale and expected outcomes..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea><small>Maximum 500 words.</small></label></section>
        <section class="panel form-panel"><div class="form-section-heading"><span class="section-icon blue-bg">▦</span><div><h2>Implementation &amp; Logistics</h2><p>Identify the responsible office and classification.</p></div></div><div class="form-divider"></div><div class="form-columns"><label>Implementing Office <em>*</em><select name="implementing_office" required><option value="">Select Department/Office</option><option>Department of Public Works</option><option>Health Services Office</option><option>Education Department</option></select></label><label>PPA Category<select name="category"><option value="">Select Category</option><option>Program</option><option>Project</option><option>Activity</option></select></label></div></section>
    </div>
    <div class="encoding-side"><section class="panel form-panel"><div class="form-section-heading"><span class="section-icon amber-bg">▣</span><div><h2>Financial Estimates</h2><p>Set the expected cost and source.</p></div></div><div class="form-divider"></div><label>Total Estimated Cost <em>*</em><div class="currency-input"><span>₱</span><input name="estimated_cost" type="text" inputmode="decimal" placeholder="0.00" value="<?= htmlspecialchars($_POST['estimated_cost'] ?? '') ?>" required></div></label><label>Primary Fund Source<select name="fund_source"><option>Select Source</option><option>General Fund (GF)</option><option>Special Education Fund (SEF)</option><option>Trust Fund</option></select></label></section><section class="panel form-panel"><div class="form-section-heading"><span class="section-icon blue-bg">▣</span><div><h2>Implementation Timeline</h2><p>Set the target delivery dates.</p></div></div><div class="form-divider"></div><label>Target Start Date<input name="start_date" type="date"></label><label>Target Completion Date<input name="completion_date" type="date"></label></section></div>
    <div class="form-actions"><button class="secondary-button" type="button" data-reset-form>Cancel</button><button class="secondary-button" type="submit" name="save_draft">Save Draft</button><button class="primary-button" type="submit" name="submit_review">Submit for Review <span>→</span></button></div>
</form>
<?php include '../includes/footer.php'; ?>
