<?php
/**
 * Roles and permissions.
 *
 * Every page and every sensitive action checks a permission with can()
 * or requirePermission(). Hiding a button is never the only protection.
 */

/** The four system roles and what each is responsible for in the AIP process. */
const ROLES = [
    'Administrator' => 'Manages user accounts, roles and office assignments, and reviews the activity log. Does not encode, review or approve PPAs.',
    'Encoder'       => 'Enters and submits PPA proposals for their own office, and follows their status through review, authorization and implementation.',
    'Reviewer'      => 'MPDO staff who evaluate submitted PPAs, return them for revision or recommend them for approval, and prepare the consolidated AIP.',
    'Approver'      => 'Makes the final decision to approve or disapprove PPAs recommended by the MPDO for inclusion in the AIP.',
];

/** permission => roles allowed. */
const PERMISSIONS = [
    'dashboard.view'      => ['Administrator', 'Encoder', 'Reviewer', 'Approver'],
    'ppa.encode'          => ['Encoder'],
    'linking.view'        => ['Administrator', 'Encoder', 'Reviewer', 'Approver'],
    'review.view'         => ['Administrator', 'Encoder', 'Reviewer', 'Approver'],
    'review.act'          => ['Reviewer'],
    'authorization.view'  => ['Administrator', 'Encoder', 'Reviewer', 'Approver'],
    'authorization.act'   => ['Approver'],
    'implementation.view' => ['Administrator', 'Encoder', 'Reviewer', 'Approver'],
    'consolidation.view'  => ['Administrator', 'Reviewer', 'Approver'],
    'funds.view'          => ['Administrator', 'Reviewer', 'Approver'],
    'users.manage'        => ['Administrator'],
    'audit.view'          => ['Administrator'],
    'scope.all_offices'   => ['Administrator', 'Reviewer', 'Approver'],
];

/** Plain-language labels used on the Roles & Permissions screen. */
const PERMISSION_LABELS = [
    'dashboard.view'      => 'View dashboard',
    'ppa.encode'          => 'Encode and submit PPAs',
    'linking.view'        => 'View project linking and traceability',
    'review.view'         => 'View review queue',
    'review.act'          => 'Evaluate, return or recommend PPAs',
    'authorization.view'  => 'View authorization queue',
    'authorization.act'   => 'Approve or disapprove PPAs',
    'implementation.view' => 'View implementation progress',
    'consolidation.view'  => 'Consolidate and print the AIP',
    'funds.view'          => 'View fund source monitoring',
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
