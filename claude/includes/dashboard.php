<?php
/**
 * Dashboard indicators, computed live from the stored records every time
 * the dashboard loads. Respects the signed-in user's scope: encoders see
 * their own office (including drafts); MPDO roles see every office.
 */

/** Stages shown in the pipeline, in process order. */
const DASHBOARD_STAGES = [
    'draft'       => ['label' => 'Draft',                 'link' => 'pages/ppas.php'],
    'for_review'  => ['label' => 'Pending review',        'link' => 'pages/review-workflow.php'],
    'returned'    => ['label' => 'Returned for revision', 'link' => 'pages/review-workflow.php'],
    'recommended' => ['label' => 'Pending authorization', 'link' => 'pages/authorization-workflow.php'],
    'approved'    => ['label' => 'Approved',              'link' => 'pages/implementation-workflow.php'],
    'ongoing'     => ['label' => 'Ongoing',               'link' => 'pages/implementation-workflow.php'],
    'completed'   => ['label' => 'Completed',             'link' => 'pages/implementation-workflow.php'],
    'disapproved' => ['label' => 'Disapproved',           'link' => 'pages/authorization-workflow.php'],
];

/**
 * Average days PPAs spent in a status before moving on, from their history.
 * Returns ['avg' => float|null, 'count' => int].
 */
function dashboardTurnaround(array $ppas, string $status): array
{
    $durations = [];
    foreach ($ppas as $ppa) {
        $enteredAt = null;
        $previous = null;
        foreach ($ppa['history'] ?? [] as $entry) {
            if ($entry['status'] === $previous) {
                continue;
            }
            if ($previous === $status && $enteredAt !== null) {
                $durations[] = (strtotime($entry['time']) - strtotime($enteredAt)) / 86400;
            }
            if ($entry['status'] === $status) {
                $enteredAt = $entry['time'];
            }
            $previous = $entry['status'];
        }
    }
    return ['avg' => $durations ? array_sum($durations) / count($durations) : null, 'count' => count($durations)];
}

function dashboardDays(?float $days): string
{
    if ($days === null) {
        return '—';
    }
    if ($days < 1) {
        return $days * 24 < 1 ? 'Under 1 hour' : round($days * 24) . ' hours';
    }
    return number_format($days, 1) . ' days';
}

/** Every indicator the dashboard shows. */
function dashboardData(array $filters = []): array
{
    $includeDrafts = can('ppa.encode');
    $ppas = array_values(array_filter(visiblePpas($includeDrafts), function ($p) use ($filters) {
        return (empty($filters['office']) || $p['office'] === $filters['office'])
            && (empty($filters['sector']) || $p['sector'] === $filters['sector']);
    }));
    $submitted = array_values(array_filter($ppas, fn ($p) => $p['status'] !== 'draft'));
    $active = array_values(array_filter($submitted, fn ($p) => $p['status'] !== 'disapproved'));
    $in = fn (array $statuses) => array_values(array_filter($ppas, fn ($p) => in_array($p['status'], $statuses, true)));

    // Pipeline by stage
    $pipeline = [];
    foreach (DASHBOARD_STAGES as $status => $stage) {
        if ($status === 'draft' && !$includeDrafts) {
            continue;
        }
        $items = $in([$status]);
        $pipeline[$status] = $stage + ['count' => count($items), 'amount' => array_sum(array_column($items, 'cost'))];
    }

    $pending = $in(['for_review', 'recommended', 'returned']);
    $overdue = array_values(array_filter($pending, fn ($p) => daysInStatus($p) > WORKFLOW_OVERDUE_DAYS));
    usort($pending, fn ($a, $b) => strcmp(statusSince($a), statusSince($b)));
    $approved = $in(['approved', 'ongoing', 'completed']);
    $disapproved = $in(['disapproved']);
    $decided = count($approved) + count($disapproved);
    $implementing = $in(['ongoing', 'completed']);

    // Distribution
    $bySector = [];
    $byOffice = [];
    foreach ($active as $p) {
        $bySector[$p['sector']] ??= ['count' => 0, 'amount' => 0.0, 'approved' => 0.0];
        $bySector[$p['sector']]['count']++;
        $bySector[$p['sector']]['amount'] += $p['cost'];
        $byOffice[$p['office']] ??= ['count' => 0, 'amount' => 0.0, 'approved' => 0.0];
        $byOffice[$p['office']]['count']++;
        $byOffice[$p['office']]['amount'] += $p['cost'];
        if (in_array($p['status'], AIP_INCLUDED_STATUSES, true)) {
            $bySector[$p['sector']]['approved'] += $p['cost'];
            $byOffice[$p['office']]['approved'] += $p['cost'];
        }
    }
    uasort($bySector, fn ($a, $b) => $b['amount'] <=> $a['amount']);
    uasort($byOffice, fn ($a, $b) => $b['amount'] <=> $a['amount']);

    // Fund distribution (ceilings only make sense for the whole municipality)
    $funds = fundTotals($submitted);
    $funds = array_filter($funds, fn ($f) => !empty($f['active']) || $f['programmed'] + $f['pending'] > 0);
    $showCeilings = !$filters && can('funds.view');

    // Recent activity from PPA histories
    $activity = [];
    foreach ($ppas as $p) {
        foreach ($p['history'] ?? [] as $entry) {
            if ($entry['action'] === 'Sample record loaded') {
                continue;
            }
            $activity[] = $entry + ['ref' => $p['ref'], 'title' => $p['title']];
        }
    }
    usort($activity, fn ($a, $b) => strcmp($b['time'], $a['time']));

    // Compliance indicators
    $compliance = [];
    if (can('funds.view')) {
        $fi = fundingIssues();
        $compliance['funding'] = ['label' => 'Funding issues', 'value' => count($fi), 'errors' => count(array_filter($fi, fn ($i) => $i['level'] === 'error')), 'link' => 'pages/fund-monitoring.php?tab=issues'];
    }
    $li = array_filter(linkIssues(), fn ($i) => can('scope.all_offices') || ($i['office'] !== '' && canSeeOffice($i['office'])));
    $compliance['links'] = ['label' => 'Link issues', 'value' => count($li), 'errors' => count(array_filter($li, fn ($i) => $i['level'] === 'error')), 'link' => 'pages/project-linking.php?tab=checks'];
    $missingRecords = count(array_filter($approved, fn ($p) => !ppaApprovalRecords($p['ref'])));
    $compliance['records'] = ['label' => 'Approved without an approval record', 'value' => $missingRecords, 'errors' => 0, 'link' => 'pages/project-linking.php?tab=checks'];
    if (can('consolidation.view')) {
        $checks = aipReadiness();
        $final = aipFinalVersion();
        $compliance['aip'] = ['label' => 'AIP readiness', 'value' => aipHasBlockingErrors($checks) ? 'Not ready' : 'Ready', 'errors' => aipHasBlockingErrors($checks) ? 1 : 0,
                              'detail' => $final ? 'Final: version ' . $final['no'] : (aipVersions() ? count(aipVersions()) . ' draft version(s), none final' : 'No version saved yet'),
                              'link' => 'pages/aip-consolidation.php'];
    }

    // Administrator: account activity today
    $accounts = null;
    if (can('users.manage')) {
        $today = date('Y-m-d');
        $todayLog = array_filter(auditEntries(), fn ($e) => str_starts_with($e['time'], $today));
        $countAction = fn (string $a) => count(array_filter($todayLog, fn ($e) => $e['action'] === $a));
        $users = storeUsers();
        $accounts = [
            'active'   => count(array_filter($users, fn ($u) => $u['status'] === 'Active')),
            'total'    => count($users),
            'signins'  => $countAction('auth.login'),
            'failed'   => $countAction('auth.login_failed'),
            'denied'   => $countAction('access.denied'),
            'actions'  => count($todayLog),
        ];
    }

    return [
        'ppas'          => $ppas,
        'submitted'     => $submitted,
        'pipeline'      => $pipeline,
        'pending'       => $pending,
        'overdue'       => $overdue,
        'approved'      => $approved,
        'approvedCost'  => array_sum(array_column($approved, 'cost')),
        'totalCost'     => array_sum(array_column($active, 'cost')),
        'drafts'        => count($in(['draft'])),
        'decided'       => $decided,
        'approvalRate'  => $decided ? count($approved) / $decided * 100 : null,
        'reviewTime'    => dashboardTurnaround($ppas, 'for_review'),
        'authTime'      => dashboardTurnaround($ppas, 'recommended'),
        'implementing'  => $implementing,
        'avgProgress'   => $implementing ? array_sum(array_column($implementing, 'progress')) / count($implementing) : null,
        'utilized'      => array_sum(array_map(fn ($p) => (float) $p['utilized'], $implementing)),
        'implCost'      => array_sum(array_column($implementing, 'cost')),
        'bySector'      => $bySector,
        'byOffice'      => $byOffice,
        'funds'         => $funds,
        'showCeilings'  => $showCeilings,
        'activity'      => array_slice($activity, 0, 8),
        'compliance'    => $compliance,
        'accounts'      => $accounts,
    ];
}
