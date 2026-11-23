<?php
/**
 * Fund sources, PPA fund allocations, per-fund totals and funding checks.
 *
 * Storage: MySQL tables `fund_sources` and `fund_source_ceilings` (ceiling per
 * fiscal year); PPA splits are rows in `ppa_fund_allocations` (see ppa.php).
 */

/** Fund source categories. */
function fundCategories(): array
{
    return ['General Fund', 'Special Purpose Fund', 'Trust Fund', 'External Source'];
}

/** PPA statuses whose allocations count as programmed (approved) or pending. */
const FUND_PROGRAMMED_STATUSES = ['approved', 'ongoing', 'completed'];
const FUND_PENDING_STATUSES = ['for_review', 'for_budget', 'recommended', 'returned'];

/* ---------------- Store access ---------------- */

/** Kept for older callers; the database needs no initialisation. */
function fundStoreInit(): void
{
}

/** All fund sources keyed by short code (e.g. DF), with the ceiling for the AIP year. */
function fundSources(): array
{
    return dbCached('funds', function () {
        $rows = dbAll('SELECT f.*, COALESCE(c.ceiling, 0) AS ceiling,
                              cu.full_name AS c_name, cu.username AS c_user, uu.full_name AS u_name, uu.username AS u_user
                       FROM fund_sources f
                       LEFT JOIN fund_source_ceilings c ON c.fund_code = f.code AND c.fiscal_year = ?
                       LEFT JOIN users cu ON cu.id = f.created_by
                       LEFT JOIN users uu ON uu.id = f.updated_by
                       ORDER BY f.created_at, f.code', [AIP_FISCAL_YEAR]);
        $funds = [];
        foreach ($rows as $r) {
            $funds[$r['code']] = [
                'key'         => $r['code'],
                'name'        => $r['name'],
                'code'        => $r['account_code'],
                'category'    => $r['category'],
                'ceiling'     => (float) $r['ceiling'],
                'active'      => (bool) $r['is_active'],
                'description' => $r['description'],
                'created_by'  => dbActorLabel($r['c_name'], $r['c_user']) ?: 'System',
                'created_at'  => $r['created_at'],
                'updated_by'  => dbActorLabel($r['u_name'], $r['u_user']) ?: 'System',
                'updated_at'  => $r['updated_at'],
            ];
        }
        return $funds;
    });
}

/** Fund sources that may be chosen for new allocations. */
function activeFundSources(): array
{
    return array_filter(fundSources(), fn ($f) => !empty($f['active']));
}

function storeFindFund(string $key): ?array
{
    return fundSources()[$key] ?? null;
}

/** Insert or update a fund source (only the keys given change) and its ceiling for the AIP year. */
function storeSaveFund(array $fund): array
{
    [$actorId] = dbActor();
    dbTransaction(function () use ($fund, $actorId) {
        $map = ['name' => 'name', 'code' => 'account_code', 'category' => 'category', 'description' => 'description', 'active' => 'is_active'];
        $values = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $fund)) {
                $values[$column] = $key === 'active' ? (int) (bool) $fund[$key] : $fund[$key];
            }
        }
        if (storeFindFund($fund['key'])) {
            $values['updated_by'] = $actorId;
            $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
            dbExec("UPDATE fund_sources SET $sets WHERE code = ?", [...array_values($values), $fund['key']]);
        } else {
            $values += ['code' => $fund['key'], 'created_by' => $actorId, 'updated_by' => $actorId];
            $columns = array_keys($values);
            dbExec('INSERT INTO fund_sources (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                array_values($values));
        }
        if (array_key_exists('ceiling', $fund)) {
            dbExec('INSERT INTO fund_source_ceilings (fund_code, fiscal_year, ceiling) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE ceiling = VALUES(ceiling)', [$fund['key'], AIP_FISCAL_YEAR, $fund['ceiling']]);
        }
    });
    return storeFindFund($fund['key']);
}

/** Delete a fund source (the page only allows this when no PPA uses it). */
function storeDeleteFund(string $key): void
{
    dbExec('DELETE FROM fund_sources WHERE code = ?', [$key]);
}

/* ---------------- Allocations ---------------- */

/**
 * A PPA's allocation as [fund key => amount]. PPAs that were never split
 * are charged entirely to their primary fund source.
 */
function ppaAllocations(array $ppa): array
{
    if (!empty($ppa['allocations'])) {
        return $ppa['allocations'];
    }
    return ($ppa['fund'] ?? '') !== '' ? [$ppa['fund'] => (float) $ppa['cost']] : [];
}

function ppaAllocatedTotal(array $ppa): float
{
    return round(array_sum(ppaAllocations($ppa)), 2);
}

/** Whether a PPA's cost is split across more than one fund source. */
function ppaIsSplit(array $ppa): bool
{
    return count(ppaAllocations($ppa)) > 1;
}

/** Short label of a PPA's funding, e.g. "General Fund" or "General Fund + Trust Fund". */
function ppaFundLabel(array $ppa): string
{
    $keys = array_keys(ppaAllocations($ppa));
    if (!$keys) {
        return 'Not set';
    }
    return implode(' + ', array_map('fundName', $keys));
}

/** Which bucket a PPA's allocation counts towards: programmed, pending or none. */
function fundBucket(string $status): string
{
    if (in_array($status, FUND_PROGRAMMED_STATUSES, true)) {
        return 'programmed';
    }
    if (in_array($status, FUND_PENDING_STATUSES, true)) {
        return 'pending';
    }
    return 'none';
}

/**
 * Totals per fund source from all non-draft PPAs:
 * programmed, pending, utilized, available, count, share, issue level.
 */
function fundTotals(?array $ppas = null): array
{
    $ppas ??= array_filter(storePpas(), fn ($p) => $p['status'] !== 'draft');
    $totals = [];
    foreach (fundSources() as $key => $fund) {
        $totals[$key] = $fund + ['programmed' => 0.0, 'pending' => 0.0, 'utilized' => 0.0, 'count' => 0, 'pendingCount' => 0];
    }
    foreach ($ppas as $ppa) {
        $bucket = fundBucket($ppa['status']);
        if ($bucket === 'none') {
            continue;
        }
        $allocations = ppaAllocations($ppa);
        $allocated = array_sum($allocations) ?: 1;
        foreach ($allocations as $key => $amount) {
            if (!isset($totals[$key])) {
                continue;
            }
            $totals[$key][$bucket] += $amount;
            $totals[$key][$bucket === 'programmed' ? 'count' : 'pendingCount']++;
            // Utilization is shared across funds in proportion to each allocation.
            $totals[$key]['utilized'] += (float) $ppa['utilized'] * ($amount / $allocated);
        }
    }
    foreach ($totals as $key => $t) {
        $ceiling = (float) $t['ceiling'];
        $totals[$key]['available'] = $ceiling - $t['programmed'];
        $totals[$key]['share'] = $ceiling > 0 ? $t['programmed'] / $ceiling * 100 : 0;
        $totals[$key]['projectedShare'] = $ceiling > 0 ? ($t['programmed'] + $t['pending']) / $ceiling * 100 : 0;
        if ($t['programmed'] > $ceiling + 0.001) {
            $totals[$key]['state'] = ['Over ceiling', 'rejected'];
        } elseif ($t['programmed'] + $t['pending'] > $ceiling + 0.001) {
            $totals[$key]['state'] = ['At risk', 'pending'];
        } elseif (empty($t['active'])) {
            $totals[$key]['state'] = ['Inactive', 'inactive'];
        } else {
            $totals[$key]['state'] = ['Within ceiling', 'approved'];
        }
    }
    return $totals;
}

/**
 * Funding inconsistencies the MPDO should look at.
 * Each issue: level (error|warning), type, ref (PPA) or fund, message.
 */
function fundingIssues(): array
{
    $issues = [];
    $funds = fundSources();
    foreach (storePpas() as $ppa) {
        if ($ppa['status'] === 'draft' || $ppa['status'] === 'disapproved') {
            continue;
        }
        $allocations = ppaAllocations($ppa);
        $allocated = array_sum($allocations);
        if (!$allocations) {
            $issues[] = ['level' => 'error', 'type' => 'No fund source', 'ref' => $ppa['ref'], 'fund' => '',
                         'message' => 'No fund source is assigned to this PPA.'];
            continue;
        }
        $diff = round($ppa['cost'] - $allocated, 2);
        if (abs($diff) >= 0.01) {
            $issues[] = ['level' => 'error', 'type' => $diff > 0 ? 'Under-allocated' : 'Over-allocated', 'ref' => $ppa['ref'], 'fund' => '',
                         'message' => 'Allocations total ' . peso($allocated) . ' but the PPA costs ' . peso($ppa['cost'])
                                    . ' (' . ($diff > 0 ? peso($diff) . ' unutilized' : peso(-$diff) . ' too much') . ').'];
        }
        foreach ($allocations as $key => $amount) {
            if (!isset($funds[$key])) {
                $issues[] = ['level' => 'error', 'type' => 'Unknown fund source', 'ref' => $ppa['ref'], 'fund' => $key,
                             'message' => 'Charged to a fund source that no longer exists (' . $key . ').'];
            } elseif (empty($funds[$key]['active'])) {
                $issues[] = ['level' => 'warning', 'type' => 'Inactive fund source', 'ref' => $ppa['ref'], 'fund' => $key,
                             'message' => peso($amount) . ' is charged to ' . $funds[$key]['name'] . ', which is inactive.'];
            }
        }
        if (count($allocations) > 1 && ($ppa['fund'] ?? '') !== '' && !isset($allocations[$ppa['fund']])) {
            $issues[] = ['level' => 'warning', 'type' => 'Primary fund not allocated', 'ref' => $ppa['ref'], 'fund' => $ppa['fund'],
                         'message' => 'The primary fund (' . fundName($ppa['fund']) . ') is not part of the split allocation.'];
        }
        if ((float) $ppa['utilized'] > $ppa['cost'] + 0.001) {
            $issues[] = ['level' => 'error', 'type' => 'Over-utilized', 'ref' => $ppa['ref'], 'fund' => '',
                         'message' => 'Utilized ' . peso((float) $ppa['utilized']) . ', more than the PPA cost of ' . peso($ppa['cost']) . '.'];
        }
    }
    foreach (fundTotals() as $key => $t) {
        if ($t['state'][0] === 'Over ceiling') {
            $issues[] = ['level' => 'error', 'type' => 'Over ceiling', 'ref' => '', 'fund' => $key,
                         'message' => 'Approved PPAs total ' . peso($t['programmed']) . ', above the ' . peso($t['ceiling']) . ' ceiling.'];
        } elseif ($t['state'][0] === 'At risk') {
            $issues[] = ['level' => 'warning', 'type' => 'Ceiling at risk', 'ref' => '', 'fund' => $key,
                         'message' => 'Approved and pending PPAs total ' . peso($t['programmed'] + $t['pending'])
                                    . ', ' . peso($t['programmed'] + $t['pending'] - $t['ceiling']) . ' above the ' . peso($t['ceiling']) . ' ceiling if all are approved.'];
        }
    }
    usort($issues, fn ($a, $b) => strcmp($a['level'], $b['level']));   // errors first
    return $issues;
}

/** Issues affecting one PPA. */
function ppaFundingIssues(string $ref): array
{
    return array_values(array_filter(fundingIssues(), fn ($i) => $i['ref'] === $ref));
}
