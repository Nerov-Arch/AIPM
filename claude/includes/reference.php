<?php
/**
 * Reference data shared by every module: offices, roles and name lookups.
 * Offices come from the `offices` table.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/db.php';

/** Active implementing offices (code => name). */
function officeList(): array
{
    return dbCached('offices', fn () => array_column(dbAll('SELECT code, name FROM offices WHERE is_active = 1 ORDER BY name'), 'name', 'code'));
}

/** Office name for a code. */
function officeName(string $code): string
{
    return officeList()[$code] ?? $code;
}

/** Fund source name for a code. */
function fundName(string $code): string
{
    return fundSources()[$code]['name'] ?? $code;
}

/** Roles offered in the user form. */
function roleOptions(): array
{
    return array_keys(ROLES);
}

/** PPA types used in the AIP form (match the ppas.ppa_type column). */
function ppaTypes(): array
{
    return ['Program', 'Project', 'Activity'];
}
