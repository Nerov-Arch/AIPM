<?php
/**
 * AIP Consolidation: compiles approved PPAs into the Annual Investment
 * Program, grouped by sector and implementing office, with readiness
 * checks and saved (numbered) versions.
 *
 * Storage: MySQL tables `aip_versions` and `aip_version_entries` (a frozen
 * copy of every PPA row at the time the version was saved).
 */

/** PPA statuses included in the consolidated AIP. */
const AIP_INCLUDED_STATUSES = ['approved', 'ongoing', 'completed'];
/** Statuses that are still undecided (would be missing from the AIP). */
const AIP_UNDECIDED_STATUSES = ['for_review', 'for_budget', 'recommended', 'returned'];

/** The fields copied into a consolidated AIP entry. */
function aipEntryFromPpa(array $ppa): array
{
    return [
        'ref'             => $ppa['ref'],
        'title'           => $ppa['title'],
        'type'            => $ppa['type'],
        'program'         => $ppa['program'],
        'sector'          => $ppa['sector'],
        'office'          => $ppa['office'],
        'start'           => $ppa['start'],
        'end'             => $ppa['end'],
        'expected_output' => $ppa['expected_output'],
        'fund'            => $ppa['fund'],
        'allocations'     => ppaAllocations($ppa),
        'fund_label'      => ppaFundLabel($ppa),
        'ps'              => (float) $ppa['ps'],
        'mooe'            => (float) $ppa['mooe'],
        'co'              => (float) $ppa['co'],
        'cost'            => (float) $ppa['cost'],
        'cc_adaptation'   => (float) $ppa['cc_adaptation'],
        'cc_mitigation'   => (float) $ppa['cc_mitigation'],
        'cc_typology'     => $ppa['cc_typology'],
        'status'          => $ppa['status'],
        'record'          => $ppa['record'],
    ];
}

/**
 * Approved PPAs as AIP entries, ordered by sector, office, then reference.
 * Optional filters: office, sector, fund (any allocation).
 */
function aipEntries(array $filters = []): array
{
    $sectorOrder = array_flip(array_keys(ppaSectors()));
    $entries = [];
    foreach (storePpas() as $ppa) {
        if (!in_array($ppa['status'], AIP_INCLUDED_STATUSES, true)) {
            continue;
        }
        if (!empty($filters['office']) && $ppa['office'] !== $filters['office']) {
            continue;
        }
        if (!empty($filters['sector']) && $ppa['sector'] !== $filters['sector']) {
            continue;
        }
        if (!empty($filters['fund']) && !isset(ppaAllocations($ppa)[$filters['fund']])) {
            continue;
        }
        $entries[] = aipEntryFromPpa($ppa);
    }
    usort($entries, fn ($a, $b) => [$sectorOrder[$a['sector']] ?? 99, officeName($a['office']), $a['ref']]
                                <=> [$sectorOrder[$b['sector']] ?? 99, officeName($b['office']), $b['ref']]);
    return $entries;
}

/** Sum of the money columns of a list of entries. */
function aipTotals(array $entries): array
{
    $totals = ['count' => count($entries), 'ps' => 0.0, 'mooe' => 0.0, 'co' => 0.0, 'cost' => 0.0, 'cc_adaptation' => 0.0, 'cc_mitigation' => 0.0];
    foreach ($entries as $entry) {
        foreach (['ps', 'mooe', 'co', 'cost', 'cc_adaptation', 'cc_mitigation'] as $field) {
            $totals[$field] += $entry[$field];
        }
    }
    return $totals;
}

/**
 * Group entries as sector → office → entries, with subtotals:
 * [sectorCode => ['name', 'totals', 'offices' => [officeCode => ['name', 'totals', 'entries']]]]
 */
function aipStructure(array $entries): array
{
    $structure = [];
    foreach ($entries as $entry) {
        $structure[$entry['sector']]['offices'][$entry['office']]['entries'][] = $entry;
    }
    $sectors = ppaSectors();
    foreach ($structure as $sector => &$group) {
        $group['name'] = $sectors[$sector] ?? $sector;
        $all = [];
        foreach ($group['offices'] as $office => &$officeGroup) {
            $officeGroup['name'] = officeName($office);
            $officeGroup['totals'] = aipTotals($officeGroup['entries']);
            $all = array_merge($all, $officeGroup['entries']);
        }
        unset($officeGroup);
        $group['totals'] = aipTotals($all);
    }
    unset($group);
    return $structure;
}

/** Totals by a grouping key (sector, office) or by fund source allocation. */
function aipSummaryBy(array $entries, string $by): array
{
    $rows = [];
    foreach ($entries as $entry) {
        if ($by === 'fund') {
            foreach ($entry['allocations'] as $key => $amount) {
                $rows[$key] ??= ['count' => 0, 'amount' => 0.0];
                $rows[$key]['count']++;
                $rows[$key]['amount'] += $amount;
            }
        } else {
            $key = $entry[$by];
            $rows[$key] ??= ['count' => 0, 'amount' => 0.0];
            $rows[$key]['count']++;
            $rows[$key]['amount'] += $entry['cost'];
        }
    }
    uasort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);
    return $rows;
}

/**
 * Checks before consolidating. Errors block saving a version.
 * Each item: level (error|warning|info), title, detail, refs.
 */
function aipReadiness(): array
{
    $checks = [];
    $ppas = storePpas();
    $included = array_filter($ppas, fn ($p) => in_array($p['status'], AIP_INCLUDED_STATUSES, true));

    $undecided = array_filter($ppas, fn ($p) => in_array($p['status'], AIP_UNDECIDED_STATUSES, true));
    if ($undecided) {
        $byStatus = array_count_values(array_map(fn ($p) => statusLabel($p['status']), $undecided));
        $parts = [];
        foreach ($byStatus as $label => $n) {
            $parts[] = $n . ' ' . strtolower($label);
        }
        $checks[] = ['level' => 'warning', 'title' => count($undecided) . ' PPA' . (count($undecided) === 1 ? ' is' : 's are') . ' not yet decided and will not be included',
                     'detail' => ucfirst(implode(', ', $parts)) . '.', 'refs' => array_column($undecided, 'ref')];
    }

    $required = ['type' => 'type', 'sector' => 'sector', 'expected_output' => 'expected outputs', 'start' => 'start date', 'end' => 'completion date', 'fund' => 'fund source'];
    foreach ($included as $ppa) {
        $missing = [];
        foreach ($required as $field => $label) {
            if (($ppa[$field] ?? '') === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            $checks[] = ['level' => 'error', 'title' => $ppa['ref'] . ' is missing required AIP information',
                         'detail' => 'Missing: ' . implode(', ', $missing) . '.', 'refs' => [$ppa['ref']]];
        }
    }

    $includedRefs = array_column($included, 'ref');
    foreach (fundingIssues() as $issue) {
        $isIncluded = in_array($issue['ref'], $includedRefs, true);
        if ($issue['level'] === 'error' && ($isIncluded || $issue['type'] === 'Over ceiling')) {
            $checks[] = ['level' => 'error', 'title' => $issue['type'] . ($issue['ref'] !== '' ? ': ' . $issue['ref'] : ': ' . fundName($issue['fund'])),
                         'detail' => $issue['message'], 'refs' => $issue['ref'] !== '' ? [$issue['ref']] : []];
        }
    }

    // Possible duplicates: the same title approved for more than one office.
    $titles = [];
    foreach ($included as $ppa) {
        $titles[normalizeTitle($ppa['title'])][] = $ppa;
    }
    foreach ($titles as $group) {
        if (count($group) > 1) {
            $checks[] = ['level' => 'warning', 'title' => 'Possible duplicate: "' . $group[0]['title'] . '"',
                         'detail' => 'Approved for ' . implode(', ', array_map(fn ($p) => $p['ref'] . ' (' . $p['office'] . ')', $group)) . '. Confirm these are different PPAs.',
                         'refs' => array_column($group, 'ref')];
        }
    }

    $latest = aipVersions()[0] ?? null;
    if ($latest) {
        $diff = aipCompare($latest['entries'], aipEntries());
        $changes = count($diff['added']) + count($diff['removed']) + count($diff['changed']);
        $checks[] = $changes
            ? ['level' => 'info', 'title' => $changes . ' change' . ($changes === 1 ? '' : 's') . ' since version ' . $latest['no'],
               'detail' => count($diff['added']) . ' added, ' . count($diff['removed']) . ' removed, ' . count($diff['changed']) . ' changed. Generate a new version to include them.', 'refs' => []]
            : ['level' => 'info', 'title' => 'No changes since version ' . $latest['no'], 'detail' => 'The saved version matches the current approved PPAs.', 'refs' => []];
    }

    $order = ['error' => 0, 'warning' => 1, 'info' => 2];
    usort($checks, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
    return $checks;
}

function aipHasBlockingErrors(?array $checks = null): bool
{
    foreach ($checks ?? aipReadiness() as $check) {
        if ($check['level'] === 'error') {
            return true;
        }
    }
    return false;
}

/* ---------------- Versions ---------------- */

/** Kept for older callers; the database needs no initialisation. */
function aipStoreInit(): void
{
}

/** Saved versions of the AIP year, newest first, each with its entries and totals. */
function aipVersions(): array
{
    return dbCached('aip_versions', function () {
        $rows = dbAll('SELECT v.*, c.full_name AS c_name, c.username AS c_user, f.full_name AS f_name, f.username AS f_user,
                              r.record_no, r.record_date
                       FROM aip_versions v
                       LEFT JOIN users c ON c.id = v.created_by
                       LEFT JOIN users f ON f.id = v.finalized_by
                       LEFT JOIN approval_records r ON r.id = v.approval_record_id
                       WHERE v.fiscal_year = ? ORDER BY v.version_no DESC', [AIP_FISCAL_YEAR]);
        $entries = [];
        foreach (dbAll('SELECT e.* FROM aip_version_entries e JOIN aip_versions v ON v.id = e.aip_version_id
                        WHERE v.fiscal_year = ? ORDER BY e.id', [AIP_FISCAL_YEAR]) as $e) {
            $entries[(int) $e['aip_version_id']][] = [
                'ref' => $e['ref'], 'title' => $e['title'], 'type' => $e['ppa_type'], 'program' => $e['program'],
                'sector' => $e['sector_code'], 'office' => $e['office_code'], 'start' => (string) $e['start_date'],
                'end' => (string) $e['end_date'], 'expected_output' => (string) $e['expected_output'],
                'fund' => (string) $e['fund_code'], 'allocations' => json_decode((string) $e['allocations'], true) ?: [],
                'fund_label' => $e['fund_label'], 'ps' => (float) $e['ps'], 'mooe' => (float) $e['mooe'], 'co' => (float) $e['co'],
                'cost' => (float) $e['total_cost'], 'cc_adaptation' => (float) $e['cc_adaptation'],
                'cc_mitigation' => (float) $e['cc_mitigation'], 'cc_typology' => (string) $e['cc_typology'],
                'status' => $e['status'], 'record' => (string) $e['record_no'],
            ];
        }
        $versions = [];
        foreach ($rows as $v) {
            $list = $entries[(int) $v['id']] ?? [];
            $versions[] = [
                'id'              => (int) $v['id'],
                'no'              => (int) $v['version_no'],
                'fy'              => (int) $v['fiscal_year'],
                'kind'            => $v['kind'],
                'status'          => $v['status'],
                'created_by'      => dbActorLabel($v['c_name'], $v['c_user']) ?: 'System',
                'created_at'      => $v['created_at'],
                'remarks'         => (string) $v['remarks'],
                'entries'         => $list,
                'totals'          => aipTotals($list),
                'finalized_by'    => dbActorLabel($v['f_name'], $v['f_user']),
                'finalized_at'    => (string) $v['finalized_at'],
                'resolution'      => (string) $v['record_no'],
                'resolution_date' => (string) $v['record_date'],
            ];
        }
        return $versions;
    });
}

function aipFindVersion(int $no): ?array
{
    foreach (aipVersions() as $version) {
        if ($version['no'] === $no) {
            return $version;
        }
    }
    return null;
}

function aipFinalVersion(): ?array
{
    foreach (aipVersions() as $version) {
        if ($version['status'] === 'final') {
            return $version;
        }
    }
    return null;
}

/** Save a snapshot of the current consolidated AIP. */
function aipCreateVersion(string $remarks): array
{
    [$actorId] = dbActor();
    $no = dbTransaction(function () use ($remarks, $actorId) {
        $entries = aipEntries();
        $totals = aipTotals($entries);
        $no = (int) dbValue('SELECT COALESCE(MAX(version_no), 0) + 1 FROM aip_versions WHERE fiscal_year = ?', [AIP_FISCAL_YEAR]);
        $kind = aipFinalVersion() ? 'Amendment' : 'Original';
        dbExec('INSERT INTO aip_versions (fiscal_year, version_no, kind, status, remarks, ppa_count, total_ps, total_mooe, total_co,
                                          total_cc_adaptation, total_cc_mitigation, created_by)
                VALUES (?, ?, ?, \'draft\', ?, ?, ?, ?, ?, ?, ?, ?)', [
            AIP_FISCAL_YEAR, $no, $kind, dbNull($remarks), count($entries),
            array_sum(array_column($entries, 'ps')), array_sum(array_column($entries, 'mooe')), array_sum(array_column($entries, 'co')),
            array_sum(array_column($entries, 'cc_adaptation')), array_sum(array_column($entries, 'cc_mitigation')), $actorId,
        ]);
        $versionId = (int) db()->lastInsertId();
        foreach ($entries as $e) {
            dbExec('INSERT INTO aip_version_entries (aip_version_id, ppa_id, ref, title, ppa_type, program, sector_code, office_code,
                        start_date, end_date, expected_output, fund_code, allocations, fund_label, record_no, status, ps, mooe, co,
                        cc_adaptation, cc_mitigation, cc_typology)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $versionId, ppaId($e['ref']), $e['ref'], $e['title'], $e['type'], $e['program'], $e['sector'], $e['office'],
                dbNull($e['start']), dbNull($e['end']), $e['expected_output'], dbNull($e['fund']),
                $e['allocations'] ? json_encode($e['allocations']) : null, $e['fund_label'], dbNull($e['record']), $e['status'],
                $e['ps'], $e['mooe'], $e['co'], $e['cc_adaptation'], $e['cc_mitigation'], dbNull($e['cc_typology']),
            ]);
        }
        return $no;
    });
    return aipFindVersion($no);
}

/** Mark a version as the final consolidated AIP (supersedes any earlier final). */
function aipFinalizeVersion(int $no, string $resolution, string $resolutionDate): ?array
{
    if (!aipFindVersion($no)) {
        return null;
    }
    [$actorId] = dbActor();
    dbTransaction(function () use ($no, $actorId) {
        dbExec("UPDATE aip_versions SET status = 'superseded' WHERE fiscal_year = ? AND status = 'final'", [AIP_FISCAL_YEAR]);
        dbExec("UPDATE aip_versions SET status = 'final', finalized_by = ?, finalized_at = NOW() WHERE fiscal_year = ? AND version_no = ?",
            [$actorId, AIP_FISCAL_YEAR, $no]);
    });
    $version = aipFindVersion($no);
    if ($resolution !== '') {
        // the record itself is created and linked by linkAipVersionToRecord()
        $version['resolution'] = $resolution;
        $version['resolution_date'] = $resolutionDate;
    }
    return $version;
}

/**
 * Differences between two lists of entries (e.g. a saved version and now).
 * changed: [ref => list of "Field: old → new"].
 */
function aipCompare(array $before, array $after): array
{
    $old = array_column($before, null, 'ref');
    $new = array_column($after, null, 'ref');
    $diff = ['added' => [], 'removed' => [], 'changed' => []];
    foreach ($new as $ref => $entry) {
        if (!isset($old[$ref])) {
            $diff['added'][] = $entry;
            continue;
        }
        $changes = [];
        foreach (['title' => 'Title', 'office' => 'Office', 'sector' => 'Sector', 'fund_label' => 'Fund source', 'start' => 'Start', 'end' => 'Completion'] as $field => $label) {
            if ((string) $old[$ref][$field] !== (string) $entry[$field]) {
                $changes[] = $label . ': ' . $old[$ref][$field] . ' → ' . $entry[$field];
            }
        }
        foreach (['cost' => 'Total cost', 'ps' => 'PS', 'mooe' => 'MOOE', 'co' => 'CO'] as $field => $label) {
            if (abs($old[$ref][$field] - $entry[$field]) >= 0.01) {
                $changes[] = $label . ': ' . peso($old[$ref][$field]) . ' → ' . peso($entry[$field]);
            }
        }
        if ($changes) {
            $diff['changed'][$ref] = ['entry' => $entry, 'changes' => $changes];
        }
    }
    foreach ($old as $ref => $entry) {
        if (!isset($new[$ref])) {
            $diff['removed'][] = $entry;
        }
    }
    return $diff;
}

/** Format an amount in pesos or in thousands of pesos. */
function aipAmount(float $amount, bool $thousands): string
{
    return $thousands ? number_format($amount / 1000, 2) : number_format($amount, 2);
}
