<?php
/**
 * Full record of one PPA: every standardized field, cost breakdown,
 * reviewer remarks and the status history.
 */
$pageSection = 'Planning';
$requiredPermission = 'linking.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
$visible = $ppa && canSeeOffice($ppa['office']) && ($ppa['status'] !== 'draft' || can('ppa.encode'));
if (!$visible) {
    flash('error', 'That PPA was not found or you do not have access to it.');
    header('Location: ' . (can('ppa.encode') ? 'ppas.php' : 'project-linking.php'));
    exit;
}
$activePage = can('ppa.encode') ? 'projects' : 'linking';
$pageTitle = $ppa['ref'];
$editable = canEditPpa($ppa);
$sectors = ppaSectors();
$ccTotal = (float) $ppa['cc_adaptation'] + (float) $ppa['cc_mitigation'];

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="<?= can('ppa.encode') ? 'ppas.php' : 'project-linking.php' ?>"><?= can('ppa.encode') ? 'Project Encoding' : 'Project Linking' ?></a> / <?= e($ppa['ref']) ?></p>
        <h1><?= e($ppa['title'] !== '' ? $ppa['title'] : '(untitled draft)') ?></h1>
        <p class="muted"><?= e($ppa['type'] ?: 'Type not set') ?> · <?= e(officeName($ppa['office'])) ?> · FY <?= AIP_FISCAL_YEAR ?> AIP</p>
    </div>
    <div class="heading-actions no-print">
        <?= statusPill($ppa['status']) ?>
        <button class="secondary-button" type="button" data-print>▣ Print</button>
        <?php if ($editable): ?><a class="primary-button" href="projects.php?ref=<?= urlencode($ppa['ref']) ?>"><?= $ppa['status'] === 'returned' ? 'Revise and resubmit' : 'Edit draft' ?></a><?php endif; ?>
    </div>
</div>

<?php if ($ppa['status'] === 'returned' && $ppa['remarks'] !== ''): ?>
    <div class="alert error remarks-alert" role="status"><strong>Returned by the MPDO for revision.</strong> <?= e($ppa['remarks']) ?></div>
<?php elseif ($ppa['status'] === 'draft'): ?>
    <div class="alert info" role="status">This is a draft. Only <?= e(officeName($ppa['office'])) ?> can see it until it is submitted.</div>
<?php endif; ?>

<div class="ppa-view-layout">
    <div class="ppa-view-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>General Identification</h2><p>Standard AIP fields</p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list wide-terms">
                <dt>AIP reference code</dt><dd><?= e($ppa['ref']) ?></dd>
                <dt>PPA title</dt><dd><?= e($ppa['title'] ?: '—') ?></dd>
                <dt>Type</dt><dd><?= e($ppa['type'] ?: '—') ?></dd>
                <dt>Program</dt><dd><?= e($ppa['program'] ?: '—') ?></dd>
                <dt>Sector</dt><dd><?= e($sectors[$ppa['sector']] ?? '—') ?></dd>
                <dt>Rationale / description</dt><dd class="prewrap"><?= e($ppa['description'] ?: '—') ?></dd>
                <dt>Expected outputs</dt><dd class="prewrap"><?= e($ppa['expected_output'] ?: '—') ?></dd>
            </dl>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▦</span><div><h2>Implementation</h2><p>Office and schedule</p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list wide-terms">
                <dt>Implementing office</dt><dd><?= e(officeName($ppa['office'])) ?></dd>
                <dt>Encoded by</dt><dd><?= e($ppa['created_by']) ?> on <?= e(date('M j, Y g:i A', strtotime($ppa['created_at']))) ?></dd>
                <dt>Schedule</dt><dd><?= $ppa['start'] !== '' ? e(displayDate($ppa['start'])) . ' – ' . e(displayDate($ppa['end'])) : '—' ?></dd>
                <dt>Approval record</dt><dd><?= $ppa['record'] !== '' ? e($ppa['record']) . ' (' . e(displayDate($ppa['recordDate'])) . ')' : 'None attached' ?></dd>
                <?php if (in_array($ppa['status'], ['ongoing', 'completed'], true)): ?><dt>Physical progress</dt><dd><?= (int) $ppa['progress'] ?>% · <?= peso($ppa['utilized']) ?> utilized</dd><?php endif; ?>
            </dl>
        </section>
    </div>
    <div class="ppa-view-side">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon amber-bg">₱</span><div><h2>Cost and Funding</h2><p>Primary fund: <?= e($ppa['fund'] !== '' ? fundName($ppa['fund']) : 'not set') ?></p></div></div>
            <div class="form-divider"></div>
            <table class="cost-table">
                <tr><th>Personal Services</th><td><?= peso((float) $ppa['ps']) ?></td></tr>
                <tr><th>MOOE</th><td><?= peso((float) $ppa['mooe']) ?></td></tr>
                <tr><th>Capital Outlay</th><td><?= peso((float) $ppa['co']) ?></td></tr>
                <tr class="cost-total"><th>Total estimated cost</th><td><?= peso($ppa['cost']) ?></td></tr>
            </table>
            <div class="form-divider"></div>
            <div class="allocation-head"><h3>Fund allocation</h3><?php if (can('funds.manage') && $ppa['status'] !== 'draft'): ?><a class="text-button" href="ppa-funding.php?ref=<?= urlencode($ppa['ref']) ?>">Edit allocation</a><?php endif; ?></div>
            <table class="cost-table">
                <?php foreach (ppaAllocations($ppa) as $fundKey => $amount): ?>
                    <tr><th><?php if (can('funds.view')): ?><a href="fund-source.php?key=<?= urlencode($fundKey) ?>"><?= e(fundName($fundKey)) ?></a><?php else: ?><?= e(fundName($fundKey)) ?><?php endif; ?></th><td><?= peso($amount) ?> <small class="table-subtext"><?= $ppa['cost'] > 0 ? number_format($amount / $ppa['cost'] * 100, 1) : '0.0' ?>%</small></td></tr>
                <?php endforeach; ?>
                <?php if (!ppaAllocations($ppa)): ?><tr><th colspan="2">No fund source assigned</th></tr><?php endif; ?>
                <tr class="cost-total"><th>Total allocated</th><td><?= peso(ppaAllocatedTotal($ppa)) ?></td></tr>
            </table>
            <?php foreach (ppaFundingIssues($ppa['ref']) as $issue): ?>
                <p class="funding-issue <?= $issue['level'] ?>"><strong><?= e($issue['type']) ?>:</strong> <?= e($issue['message']) ?></p>
            <?php endforeach; ?>
            <?php if ($ccTotal > 0): ?>
                <div class="form-divider"></div>
                <table class="cost-table">
                    <tr><th>CC adaptation</th><td><?= peso((float) $ppa['cc_adaptation']) ?></td></tr>
                    <tr><th>CC mitigation</th><td><?= peso((float) $ppa['cc_mitigation']) ?></td></tr>
                    <tr><th>Typology code</th><td><?= e($ppa['cc_typology']) ?></td></tr>
                </table>
            <?php endif; ?>
        </section>
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">↺</span><div><h2>Status History</h2><p>Who did what, and when</p></div></div>
            <div class="form-divider"></div>
            <ol class="history-list">
                <?php foreach (array_reverse($ppa['history']) as $entry): ?>
                    <li>
                        <div class="history-head"><strong><?= e($entry['action']) ?></strong><?= statusPill($entry['status']) ?></div>
                        <small><?= e(date('M j, Y g:i A', strtotime($entry['time']))) ?> · <?= e($entry['by']) ?></small>
                        <?php if ($entry['remarks'] !== ''): ?><p><?= e($entry['remarks']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
