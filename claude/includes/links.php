<?php
/**
 * Linking: relationships between PPAs and the records around them.
 *
 *   PPA ── implementing office      (ppa.office → office)
 *   PPA ── fund sources             (ppa allocations, includes/funds.php)
 *   PPA ── approval records         (many-to-many, this file)
 *   AIP version ── approval record  (adoption of the consolidated AIP)
 *
 * MOCK STORE: approval records live in the session store until the
 * database phase, when they become an `approval_records` table plus a
 * `ppa_approval_records` link table (ppa_id, record_id).
 */

/** Kinds of approval record. */
function approvalRecordTypes(): array
{
    return ['SB Resolution', 'LDC Resolution', 'Executive Order', 'Memorandum'];
}

/** MOCK: the approval records behind the sample PPAs, plus one not yet linked. */
function approvalRecordSeeds(): array
{
    $fy = AIP_FISCAL_YEAR;
    return [
        'SB Res. No. 2026-075' => ['type' => 'SB Resolution', 'date' => '2026-08-20', 'subject' => 'Approving the CLUP updating activity for inclusion in the FY ' . $fy . ' AIP', 'ppas' => ["PPA-$fy-014"]],
        'SB Res. No. 2026-098' => ['type' => 'SB Resolution', 'date' => '2026-09-14', 'subject' => 'Approving social protection and environmental PPAs for FY ' . $fy, 'ppas' => ["PPA-$fy-012", "PPA-$fy-013"]],
        'SB Res. No. 2026-114' => ['type' => 'SB Resolution', 'date' => '2026-10-06', 'subject' => 'Approving the barangay health workers capability building program', 'ppas' => ["PPA-$fy-010"]],
        'LDC Res. No. 2026-012' => ['type' => 'LDC Resolution', 'date' => '2026-10-10', 'subject' => 'Endorsing priority infrastructure PPAs to the Sangguniang Bayan', 'ppas' => []],
    ];
}

/* ---------------- Store access ---------------- */

function linkStoreInit(): void
{
    storeInit();
    if (!isset($_SESSION['mock_store']['approval_records'])) {
        $records = [];
        foreach (approvalRecordSeeds() as $no => $seed) {
            $records[$no] = $seed + ['no' => $no, 'aip_version' => null, 'remarks' => '', 'created_by' => 'System (sample data)', 'created_at' => $seed['date'] . ' 09:00:00'];
        }
        $_SESSION['mock_store']['approval_records'] = $records;
    }
}

/** All approval records, newest date first. */
function approvalRecords(): array
{
    linkStoreInit();
    $records = $_SESSION['mock_store']['approval_records'];
    uasort($records, fn ($a, $b) => [$b['date'], $b['no']] <=> [$a['date'], $a['no']]);
    return $records;
}

function findApprovalRecord(string $no): ?array
{
    linkStoreInit();
    return $_SESSION['mock_store']['approval_records'][$no] ?? null;
}

/** Find a record by number (case- and space-insensitive). */
function matchApprovalRecord(string $no): ?array
{
    $key = strtolower(preg_replace('/\s+/', ' ', trim($no)));
    foreach (approvalRecords() as $record) {
        if (strtolower($record['no']) === $key) {
            return $record;
        }
    }
    return null;
}

/** Create a record (number must be unique). Returns the record. */
function createApprovalRecord(string $no, string $type, string $date, string $subject, string $remarks = ''): array
{
    linkStoreInit();
    $user = currentUser();
    $no = preg_replace('/\s+/', ' ', trim($no));
    $record = ['no' => $no, 'type' => $type, 'date' => $date, 'subject' => $subject, 'remarks' => $remarks, 'ppas' => [], 'aip_version' => null,
               'created_by' => $user ? $user['name'] . ' (' . $user['username'] . ')' : 'System', 'created_at' => date('Y-m-d H:i:s')];
    $_SESSION['mock_store']['approval_records'][$no] = $record;
    auditLog('link.record_create', $no, $type . ' · ' . $subject);
    return $record;
}

function updateApprovalRecord(string $no, array $fields): void
{
    linkStoreInit();
    if (isset($_SESSION['mock_store']['approval_records'][$no])) {
        $_SESSION['mock_store']['approval_records'][$no] = array_merge($_SESSION['mock_store']['approval_records'][$no], $fields);
    }
}

function deleteApprovalRecord(string $no): void
{
    linkStoreInit();
    unset($_SESSION['mock_store']['approval_records'][$no]);
}

/* ---------------- PPA ↔ approval record links ---------------- */

/** Approval records linked to a PPA. */
function ppaApprovalRecords(string $ref): array
{
    return array_values(array_filter(approvalRecords(), fn ($r) => in_array($ref, $r['ppas'], true)));
}

/**
 * Keep the PPA's own "approval record" field (used in lists and the AIP)
 * in step with its links: it shows the earliest linked record.
 */
function syncPpaRecordField(string $ref, string $historyAction, string $remarks = ''): void
{
    $records = ppaApprovalRecords($ref);
    usort($records, fn ($a, $b) => strcmp($a['date'], $b['date']));
    $first = $records[0] ?? null;
    storeSavePpa(['ref' => $ref, 'record' => $first['no'] ?? '', 'recordDate' => $first['date'] ?? ''], $historyAction, $remarks);
}

function linkPpaToRecord(string $ref, string $no): bool
{
    linkStoreInit();
    $records = &$_SESSION['mock_store']['approval_records'];
    if (!isset($records[$no]) || in_array($ref, $records[$no]['ppas'], true)) {
        return false;
    }
    $records[$no]['ppas'][] = $ref;
    unset($records);
    syncPpaRecordField($ref, 'Linked to approval record ' . $no);
    auditLog('link.attach', $ref, 'Linked to ' . $no);
    return true;
}

function unlinkPpaFromRecord(string $ref, string $no, string $reason): bool
{
    linkStoreInit();
    $records = &$_SESSION['mock_store']['approval_records'];
    if (!isset($records[$no]) || !in_array($ref, $records[$no]['ppas'], true)) {
        return false;
    }
    $records[$no]['ppas'] = array_values(array_diff($records[$no]['ppas'], [$ref]));
    unset($records);
    syncPpaRecordField($ref, 'Unlinked from approval record ' . $no, $reason);
    auditLog('link.detach', $ref, 'Unlinked from ' . $no . ' · ' . $reason);
    return true;
}

/**
 * Link a PPA to a record by number, creating the record if it does not
 * exist yet (used when an approver enters a resolution while approving).
 */
function linkPpaByRecordNumber(string $ref, string $no, string $date, string $type = 'SB Resolution'): void
{
    $record = matchApprovalRecord($no) ?? createApprovalRecord($no, $type, $date, 'Approval of PPAs for the FY ' . AIP_FISCAL_YEAR . ' AIP');
    linkPpaToRecord($ref, $record['no']);
}

/** Link a record to the finalized AIP version (created if new). */
function linkAipVersionToRecord(int $versionNo, string $no, string $date): void
{
    $record = matchApprovalRecord($no) ?? createApprovalRecord($no, 'SB Resolution', $date, 'Adopting the FY ' . AIP_FISCAL_YEAR . ' Annual Investment Program');
    updateApprovalRecord($record['no'], ['aip_version' => $versionNo]);
    auditLog('link.attach', 'AIP version ' . $versionNo, 'Linked to ' . $record['no']);
}

/** Statuses for which an approval record is expected. */
const LINK_APPROVED_STATUSES = ['approved', 'ongoing', 'completed'];

/** linked | missing | not-approved */
function ppaLinkStatus(array $ppa): string
{
    if (ppaApprovalRecords($ppa['ref'])) {
        return 'linked';
    }
    return in_array($ppa['status'], LINK_APPROVED_STATUSES, true) ? 'missing' : 'not-approved';
}

/* ---------------- Offices ---------------- */

/** Everything linked to one implementing office. */
function officeLinks(string $code): array
{
    $ppas = array_values(array_filter(storePpas(), fn ($p) => $p['office'] === $code && $p['status'] !== 'draft'));
    $users = array_values(array_filter(storeUsers(), fn ($u) => $u['office'] === $code));
    $funds = [];
    $records = [];
    foreach ($ppas as $ppa) {
        if ($ppa['status'] === 'disapproved') {
            continue;
        }
        foreach (ppaAllocations($ppa) as $key => $amount) {
            $funds[$key] = ($funds[$key] ?? 0) + $amount;
        }
        foreach (ppaApprovalRecords($ppa['ref']) as $record) {
            $records[$record['no']] = $record;
        }
    }
    arsort($funds);
    return [
        'ppas'      => $ppas,
        'users'     => $users,
        'encoders'  => array_values(array_filter($users, fn ($u) => $u['role'] === 'Encoder' && $u['status'] === 'Active')),
        'funds'     => $funds,
        'records'   => array_values($records),
        'approved'  => array_values(array_filter($ppas, fn ($p) => in_array($p['status'], LINK_APPROVED_STATUSES, true))),
        'cost'      => array_sum(array_column(array_filter($ppas, fn ($p) => $p['status'] !== 'disapproved'), 'cost')),
    ];
}

/* ---------------- Full trace of one PPA ---------------- */

/** All records linked to a PPA, for the PPA record page. */
function ppaTrace(array $ppa): array
{
    $users = storeUsers();
    $findUser = function (?string $label) use ($users): ?array {
        if (!$label) {
            return null;
        }
        $username = preg_match('/\(([^)]+)\)$/', $label, $m) ? $m[1] : '';
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                return $u;
            }
        }
        return null;
    };
    $versions = array_values(array_filter(aipVersions(), fn ($v) => in_array($ppa['ref'], array_column($v['entries'], 'ref'), true)));
    return [
        'office'      => ['code' => $ppa['office'], 'name' => officeName($ppa['office'])],
        'funds'       => ppaAllocations($ppa),
        'records'     => ppaApprovalRecords($ppa['ref']),
        'versions'    => $versions,
        'people'      => array_filter([
            'Encoded by'     => ['label' => $ppa['created_by'] ?? '', 'user' => $findUser($ppa['created_by'] ?? null)],
            'Recommended by' => ['label' => $ppa['recommended_by'] ?? '', 'user' => $findUser($ppa['recommended_by'] ?? null)],
            'Decided by'     => ['label' => $ppa['decided_by'] ?? '', 'user' => $findUser($ppa['decided_by'] ?? null)],
        ], fn ($p) => $p['label'] !== ''),
    ];
}

/* ---------------- Link checks ---------------- */

/**
 * Broken or missing relationships. Each: level, type, message, ref, record, office.
 */
function linkIssues(): array
{
    $issues = [];
    $funds = fundSources();
    $offices = mockOffices();
    foreach (storePpas() as $ppa) {
        if ($ppa['status'] === 'draft') {
            continue;
        }
        if (ppaLinkStatus($ppa) === 'missing') {
            $issues[] = ['level' => 'warning', 'type' => 'No approval record', 'ref' => $ppa['ref'], 'record' => '', 'office' => $ppa['office'],
                         'message' => $ppa['ref'] . ' is ' . strtolower(statusLabel($ppa['status'])) . ' but no approval record is linked.'];
        }
        if (!isset($offices[$ppa['office']])) {
            $issues[] = ['level' => 'error', 'type' => 'Unknown office', 'ref' => $ppa['ref'], 'record' => '', 'office' => $ppa['office'],
                         'message' => $ppa['ref'] . ' belongs to an office that no longer exists (' . $ppa['office'] . ').'];
        }
        if ($ppa['status'] !== 'disapproved') {
            foreach (ppaAllocations($ppa) as $key => $amount) {
                if (!isset($funds[$key])) {
                    $issues[] = ['level' => 'error', 'type' => 'Unknown fund source', 'ref' => $ppa['ref'], 'record' => '', 'office' => $ppa['office'],
                                 'message' => $ppa['ref'] . ' is charged to a fund source that no longer exists (' . $key . ').'];
                } elseif (empty($funds[$key]['active'])) {
                    $issues[] = ['level' => 'warning', 'type' => 'Inactive fund source', 'ref' => $ppa['ref'], 'record' => '', 'office' => $ppa['office'],
                                 'message' => $ppa['ref'] . ' is charged to ' . $funds[$key]['name'] . ', which is inactive.'];
                }
            }
        }
        foreach (ppaApprovalRecords($ppa['ref']) as $record) {
            if (!in_array($ppa['status'], LINK_APPROVED_STATUSES, true)) {
                $issues[] = ['level' => 'warning', 'type' => 'Record on unapproved PPA', 'ref' => $ppa['ref'], 'record' => $record['no'], 'office' => $ppa['office'],
                             'message' => $record['no'] . ' is linked to ' . $ppa['ref'] . ', which is ' . strtolower(statusLabel($ppa['status'])) . '.'];
            }
        }
    }
    $refs = array_column(storePpas(), 'ref');
    foreach (approvalRecords() as $record) {
        if (!$record['ppas'] && !$record['aip_version']) {
            $issues[] = ['level' => 'warning', 'type' => 'Unlinked approval record', 'ref' => '', 'record' => $record['no'], 'office' => '',
                         'message' => $record['no'] . ' is not linked to any PPA or AIP version.'];
        }
        foreach ($record['ppas'] as $ref) {
            if (!in_array($ref, $refs, true)) {
                $issues[] = ['level' => 'error', 'type' => 'Link to missing PPA', 'ref' => $ref, 'record' => $record['no'], 'office' => '',
                             'message' => $record['no'] . ' points to ' . $ref . ', which no longer exists.'];
            }
        }
    }
    foreach ($offices as $code => $name) {
        $links = officeLinks($code);
        $open = array_filter($links['ppas'], fn ($p) => in_array($p['status'], ['returned', 'approved', 'ongoing'], true));
        if ($open && !$links['encoders']) {
            $issues[] = ['level' => 'warning', 'type' => 'No active encoder', 'ref' => '', 'record' => '', 'office' => $code,
                         'message' => $name . ' has ' . count($open) . ' PPA(s) needing follow-up but no active encoder account.'];
        }
    }
    usort($issues, fn ($a, $b) => strcmp($a['level'], $b['level']));
    return $issues;
}

/**
 * Validate approval-record form input. Returns [errors, clean values].
 * $currentNo is the record being edited (excluded from the uniqueness check).
 */
function validateApprovalRecord(array $input, ?string $currentNo = null): array
{
    $errors = [];
    $clean = [
        'no'      => preg_replace('/\s+/', ' ', trim((string) ($input['no'] ?? ''))),
        'type'    => trim((string) ($input['type'] ?? '')),
        'date'    => trim((string) ($input['date'] ?? '')),
        'subject' => trim((string) ($input['subject'] ?? '')),
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    if ($currentNo === null) {
        if ($clean['no'] === '') {
            $errors['no'] = 'Record number is required.';
        } elseif (!preg_match('/^[A-Za-z0-9 .,\/#\-]{3,40}$/', $clean['no'])) {
            $errors['no'] = 'Use 3–40 letters, numbers and . , / # - (e.g. SB Res. No. 2026-120).';
        } elseif (matchApprovalRecord($clean['no'])) {
            $errors['no'] = 'This record number is already registered.';
        }
    }
    if (!in_array($clean['type'], approvalRecordTypes(), true)) {
        $errors['type'] = 'Choose a record type from the list.';
    }
    $date = DateTime::createFromFormat('Y-m-d', $clean['date']);
    if ($clean['date'] === '') {
        $errors['date'] = 'Date is required.';
    } elseif (!$date || $date->format('Y-m-d') !== $clean['date']) {
        $errors['date'] = 'Enter a valid date.';
    } elseif ($clean['date'] > date('Y-m-d')) {
        $errors['date'] = 'The date cannot be in the future.';
    }
    if ($clean['subject'] === '') {
        $errors['subject'] = 'Subject is required.';
    } elseif (mb_strlen($clean['subject']) > 200) {
        $errors['subject'] = 'Use 200 characters or fewer.';
    }
    if (mb_strlen($clean['remarks']) > 300) {
        $errors['remarks'] = 'Use 300 characters or fewer.';
    }
    return [$errors, $clean];
}
