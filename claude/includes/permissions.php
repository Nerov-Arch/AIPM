<?php
/**
 * Roles and permissions.
 *
 * Every page and every sensitive action checks a permission with can()
 * or requirePermission(). Hiding a button is never the only protection.
 */

/** The system roles and what each is responsible for in the AIP process. */
const ROLES = [
    'Administrator' => 'Manages user accounts, roles and office assignments, and reviews the activity log. Does not encode, review or approve PPAs.',
    'Encoder'       => 'Enters and submits PPA proposals for their own office, may still correct them while under MPDO review, and records expenses with receipts during implementation.',
    'Reviewer'      => 'MPDO staff who evaluate submitted PPAs, forward them to Finance for budget review, give the final approval (including the climate change expenditure), and prepare the consolidated AIP.',
    'Finance'       => 'Budget / Finance office staff who review the requested budget of PPAs forwarded by the MPDO and approve the budget, a lower amount, or return the PPA to the MPDO.',
    'Auditor'       => 'Audit staff who examine the expenses, receipts and expenditure timeline of PPAs under implementation and mark each expense verified or flagged.',
];

/** permission => roles allowed. */
const PERMISSIONS = [
    'dashboard.view'      => ['Administrator', 'Encoder', 'Reviewer', 'Finance', 'Auditor'],
    'ppa.encode'          => ['Encoder'],
    'linking.view'        => ['Administrator', 'Encoder', 'Reviewer', 'Finance', 'Auditor'],
    'links.manage'        => ['Reviewer'],
    'review.view'         => ['Administrator', 'Encoder', 'Reviewer'],
    'review.act'          => ['Reviewer'],
    'budget.view'         => ['Administrator', 'Reviewer', 'Finance'],
    'budget.act'          => ['Finance'],
    'implementation.view' => ['Administrator', 'Encoder', 'Reviewer', 'Finance', 'Auditor'],
    'expenses.view'       => ['Administrator', 'Encoder', 'Reviewer', 'Finance', 'Auditor'],
    'expenses.record'     => ['Encoder'],
    'expenses.verify'     => ['Auditor'],
    'consolidation.view'  => ['Administrator', 'Reviewer', 'Finance', 'Auditor'],
    'consolidation.manage'   => ['Reviewer'],
    'consolidation.finalize' => ['Reviewer'],
    'funds.view'          => ['Administrator', 'Reviewer', 'Finance', 'Auditor'],
    'funds.manage'        => ['Administrator', 'Reviewer', 'Finance'],
    'users.manage'        => ['Administrator'],
    'audit.view'          => ['Administrator'],
    'scope.all_offices'   => ['Administrator', 'Reviewer', 'Finance', 'Auditor'],
];

/** Plain-language labels used on the Roles & Permissions screen. */
const PERMISSION_LABELS = [
    'dashboard.view'      => 'View dashboard',
    'ppa.encode'          => 'Encode and submit PPAs (and correct them while under review)',
    'linking.view'        => 'View project linking and traceability',
    'links.manage'        => 'Record approval documents and link them to PPAs',
    'review.view'         => 'View the review and approval queue',
    'review.act'          => 'Evaluate, return, forward to Finance, and approve or disapprove PPAs',
    'budget.view'         => 'View the budget review queue and budget comparison',
    'budget.act'          => 'Approve or return the requested budget of PPAs',
    'implementation.view' => 'View implementation progress',
    'expenses.view'       => 'View expenses, receipts and expenditure timelines',
    'expenses.record'     => 'Record expenses with receipt photos for own office',
    'expenses.verify'     => 'Verify or flag expenses (audit)',
    'consolidation.view'  => 'View, print and export the consolidated AIP',
    'consolidation.manage'   => 'Generate consolidated AIP versions',
    'consolidation.finalize' => 'Finalize the consolidated AIP',
    'funds.view'          => 'View fund source monitoring',
    'funds.manage'        => 'Manage fund sources and PPA fund allocations',
    'users.manage'        => 'Create, edit and deactivate user accounts',
    'audit.view'          => 'View the activity log',
    'scope.all_offices'   => 'See records of all offices (others see own office only)',
];

/** Whether the signed-in user (or a given role) has a permission. */
function can(string $permission, ?string $role = null): bool
{
    $role ??= currentUser()['role'] ?? null;
    return $role !== null && in_array($role, PERMISSIONS[$permission] ?? [], true);
}

/**
 * Stop with a 403 page when the signed-in user lacks a permission.
 * The attempt is written to the activity log.
 */
function requirePermission(string $permission, string $rootPrefix = ''): void
{
    if (can($permission)) {
        return;
    }
    auditLog('access.denied', basename($_SERVER['SCRIPT_NAME'] ?? ''), 'Missing permission: ' . $permission);
    http_response_code(403);
    $assetPrefix = $rootPrefix;
    $pageTitle = 'Access denied';
    $deniedPermission = $permission;
    include __DIR__ . '/forbidden.php';
    exit;
}

/** Whether the current user may see records of the given office. */
function canSeeOffice(string $officeCode): bool
{
    if (can('scope.all_offices')) {
        return true;
    }
    return (currentUser()['office'] ?? null) === $officeCode;
}

/**
 * PPAs the current user is allowed to see. Drafts are private to the
 * encoding office and only included when asked for (My PPAs page).
 */
function visiblePpas(bool $includeDrafts = false): array
{
    return array_values(array_filter(storePpas(), fn ($ppa) => canSeeOffice($ppa['office'])
        && ($includeDrafts || $ppa['status'] !== 'draft')));
}
