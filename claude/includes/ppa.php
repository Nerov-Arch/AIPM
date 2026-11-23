<?php
/**
 * PPA (Program / Project / Activity) records: standardized fields,
 * storage, validation and status rules.
 *
 * Storage: MySQL tables `ppas`, `ppa_history`, `ppa_budgets`,
 * `ppa_fund_allocations` and `ppa_review_checklist`. PPAs are returned as
 * arrays keyed by the same field names the pages use (ref, title, office ...).
 */

/** Development sectors used in the AIP form. */
function ppaSectors(): array
{
    return dbCached('sectors', fn () => array_column(dbAll('SELECT code, name FROM sectors ORDER BY sort_order, code'), 'name', 'code'));
}

/**
 * Statuses in which the encoding office may still edit a PPA. While it is
 * Pending Review (before the MPDO forwards it to Finance) the office can still
 * correct it; every such edit is recorded and shown to the reviewer.
 */
const PPA_EDITABLE_STATUSES = ['draft', 'returned', 'for_review'];
/** Statuses from which the office may submit (or resubmit) a PPA. */
const PPA_SUBMITTABLE_STATUSES = ['draft', 'returned'];

/** Climate change expenditure fields: entered by the MPDO at final approval, not by the office. */
const PPA_CC_FIELDS = ['cc_adaptation', 'cc_mitigation', 'cc_typology'];

/** Every field of a PPA record, with its label (used for change logs). */
const PPA_FIELDS = [
    'title'           => 'Title',
    'type'            => 'PPA type',
    'program'         => 'Program',
    'sector'          => 'Sector',
    'description'     => 'Description',
    'expected_output' => 'Expected outputs',
    'office'          => 'Implementing office',
    'start'           => 'Start date',
    'end'             => 'Completion date',
    'ps'              => 'Personal Services',
    'mooe'            => 'MOOE',
    'co'              => 'Capital Outlay',
    'fund'            => 'Fund source',
    'cc_adaptation'   => 'Climate change adaptation',
    'cc_mitigation'   => 'Climate change mitigation',
    'cc_typology'     => 'CC typology code',
];

/* ---------------- Store access ---------------- */

/** Kept for older callers; the database needs no initialisation. */
function ppaStoreInit(): void
{
}

/** Build the PPA arrays the pages use from the database rows. */
function ppaLoadAll(): array
{
    return dbCached('ppas', function () {
        $rows = dbAll("SELECT p.*,
                   c.full_name AS c_name, c.username AS c_user,
                   u.full_name AS u_name, u.username AS u_user,
                   f.full_name AS f_name, f.username AS f_user,
                   d.full_name AS d_name, d.username AS d_user
            FROM ppas p
            LEFT JOIN users c ON c.id = p.created_by
            LEFT JOIN users u ON u.id = p.updated_by
            LEFT JOIN users f ON f.id = p.forwarded_by
            LEFT JOIN users d ON d.id = p.decided_by
            ORDER BY p.ref DESC");
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = [
                'id'                  => (int) $r['id'],
                'ref'                 => $r['ref'],
                'fiscal_year'         => (int) $r['fiscal_year'],
                'title'               => $r['title'],
                'type'                => (string) $r['ppa_type'],
                'program'             => $r['program'],
                'sector'              => (string) $r['sector_code'],
                'description'         => (string) $r['description'],
                'expected_output'     => (string) $r['expected_output'],
                'office'              => $r['office_code'],
                'start'               => (string) $r['start_date'],
                'end'                 => (string) $r['end_date'],
                'ps'                  => (float) $r['ps'],
                'mooe'                => (float) $r['mooe'],
                'co'                  => (float) $r['co'],
                'cost'                => (float) $r['total_cost'],
                'fund'                => (string) $r['fund_code'],
                'cc_adaptation'       => (float) $r['cc_adaptation'],
                'cc_mitigation'       => (float) $r['cc_mitigation'],
                'cc_typology'         => (string) $r['cc_typology'],
                'status'              => $r['status'],
                'progress'            => (int) $r['progress'],
                'utilized'            => 0.0,
                'remarks'             => (string) $r['remarks'],
                'officer'             => (string) $r['c_name'],
                'created_by'          => dbActorLabel($r['c_name'], $r['c_user']) ?: 'System',
                'created_by_id'       => $r['created_by'] === null ? null : (int) $r['created_by'],
                'created_at'          => $r['created_at'],
                'updated_by'          => dbActorLabel($r['u_name'], $r['u_user']) ?: 'System',
                'updated_at'          => $r['updated_at'],
                'updated'             => substr($r['updated_at'], 0, 10),
                'submitted_at'        => (string) $r['submitted_at'],
                'edited_in_review_at' => (string) $r['edited_in_review_at'],
                'recommended_by'      => dbActorLabel($r['f_name'], $r['f_user']),
                'recommended_by_id'   => $r['forwarded_by'] === null ? null : (int) $r['forwarded_by'],
                'recommended_at'      => (string) $r['forwarded_at'],
                'decided_by'          => dbActorLabel($r['d_name'], $r['d_user']),
                'decided_at'          => (string) $r['decided_at'],
                'decision_remarks'    => (string) $r['decision_remarks'],
                'allocations'         => [],
                'budget'              => null,
                'checklist'           => [],
                'history'             => [],
                'record'              => '',
                'recordDate'          => '',
            ];
        }
        if (!$byId) {
            return [];
        }
        foreach (dbAll('SELECT ppa_id, fund_code, amount FROM ppa_fund_allocations ORDER BY fund_code') as $a) {
            $byId[(int) $a['ppa_id']]['allocations'][$a['fund_code']] = (float) $a['amount'];
        }
        foreach ($byId as &$ppa) {
            if (count($ppa['allocations']) <= 1) {
                $ppa['allocations'] = [];   // not split: charged entirely to the primary fund
            }
        }
        unset($ppa);
        foreach (dbAll('SELECT b.*, u.full_name, u.username FROM ppa_budgets b LEFT JOIN users u ON u.id = b.decided_by') as $b) {
            $budget = ['requested' => ['ps' => (float) $b['requested_ps'], 'mooe' => (float) $b['requested_mooe'], 'co' => (float) $b['requested_co']]];
            if ($b['approved_ps'] !== null) {
                $budget += [
                    'approved' => ['ps' => (float) $b['approved_ps'], 'mooe' => (float) $b['approved_mooe'], 'co' => (float) $b['approved_co']],
                    'by'       => dbActorLabel($b['full_name'], $b['username']),
                    'by_id'    => $b['decided_by'] === null ? null : (int) $b['decided_by'],
                    'at'       => (string) $b['decided_at'],
                    'remarks'  => (string) $b['remarks'],
                ];
            }
            $byId[(int) $b['ppa_id']]['budget'] = $budget;
        }
        foreach (dbAll('SELECT ppa_id, item_key FROM ppa_review_checklist') as $c) {
            $byId[(int) $c['ppa_id']]['checklist'][] = $c['item_key'];
        }
        foreach (dbAll('SELECT ppa_id, action, status, remarks, actor_name, created_at FROM ppa_history ORDER BY created_at, id') as $h) {
            $byId[(int) $h['ppa_id']]['history'][] = ['time' => $h['created_at'], 'status' => $h['status'], 'action' => $h['action'],
                                                    'by' => $h['actor_name'], 'remarks' => (string) $h['remarks']];
        }
        // The PPA's approval record shown in lists and the AIP: its earliest linked record.
        foreach (dbAll('SELECT l.ppa_id, r.record_no, r.record_date FROM ppa_approval_records l
                        JOIN approval_records r ON r.id = l.approval_record_id ORDER BY r.record_date DESC, r.id DESC') as $l) {
            $byId[(int) $l['ppa_id']]['record'] = $l['record_no'];
            $byId[(int) $l['ppa_id']]['recordDate'] = $l['record_date'];
        }
        foreach (dbAll('SELECT ppa_id, SUM(amount) AS spent FROM ppa_expenses GROUP BY ppa_id') as $x) {
            $byId[(int) $x['ppa_id']]['utilized'] = round((float) $x['spent'], 2);
        }
        $byRef = [];
        foreach ($byId as $ppa) {
            $byRef[$ppa['ref']] = $ppa;
        }
        return $byRef;
    });
}

/** All PPAs, newest reference first. */
function storePpas(): array
{
    return array_values(ppaLoadAll());
}

function storeFindPpa(string $ref): ?array
{
    return ppaLoadAll()[$ref] ?? null;
}

/** Database id of a PPA reference (or null). */
function ppaId(string $ref): ?int
{
    $id = ppaLoadAll()[$ref]['id'] ?? dbValue('SELECT id FROM ppas WHERE ref = ?', [$ref]);
    return $id === null ? null : (int) $id;
}

/** Next reference number for the current AIP year, e.g. PPA-2027-015. */
function nextPpaRef(): string
{
    $prefix = 'PPA-' . AIP_FISCAL_YEAR . '-';
    $max = (int) dbValue('SELECT COALESCE(MAX(CAST(SUBSTRING(ref, ?) AS UNSIGNED)), 0) FROM ppas WHERE ref LIKE ?',
        [strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/** Array keys used by the pages => columns of the ppas table. */
const PPA_COLUMNS = [
    'title' => 'title', 'type' => 'ppa_type', 'program' => 'program', 'sector' => 'sector_code', 'description' => 'description',
    'expected_output' => 'expected_output', 'office' => 'office_code', 'start' => 'start_date', 'end' => 'end_date',
    'ps' => 'ps', 'mooe' => 'mooe', 'co' => 'co', 'fund' => 'fund_code', 'cc_adaptation' => 'cc_adaptation',
    'cc_mitigation' => 'cc_mitigation', 'cc_typology' => 'cc_typology', 'status' => 'status', 'progress' => 'progress',
    'remarks' => 'remarks', 'submitted_at' => 'submitted_at', 'edited_in_review_at' => 'edited_in_review_at',
    'recommended_by_id' => 'forwarded_by', 'recommended_at' => 'forwarded_at', 'decided_at' => 'decided_at',
    'decision_remarks' => 'decision_remarks',
];
/** Columns where an empty value is stored as NULL. */
const PPA_NULLABLE = ['ppa_type', 'sector_code', 'start_date', 'end_date', 'fund_code', 'cc_typology', 'submitted_at',
                      'edited_in_review_at', 'forwarded_by', 'forwarded_at', 'decided_at'];

/**
 * Insert (no ref) or update a PPA and append a history entry.
 * Only the keys present in $data change. Returns the saved record.
 */
function storeSavePpa(array $data, string $historyAction, string $remarks = ''): array
{
    [$actorId, $actorName] = dbActor();
    $ref = dbTransaction(function () use ($data, $historyAction, $remarks, $actorId, $actorName) {
        $values = [];
        foreach (PPA_COLUMNS as $key => $column) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                $values[$column] = in_array($column, PPA_NULLABLE, true) ? dbNull($value) : ($value ?? '');
            }
        }
        if (array_key_exists('decided_by', $data)) {
            $values['decided_by'] = $data['decided_by'] === '' ? null : $actorId;
        }
        $values['updated_by'] = $actorId;

        if (empty($data['ref'])) {
            $ref = nextPpaRef();
            $values += ['ref' => $ref, 'fiscal_year' => AIP_FISCAL_YEAR, 'created_by' => $actorId];
            $columns = array_keys($values);
            dbExec('INSERT INTO ppas (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                array_values($values));
            $id = (int) db()->lastInsertId();
        } else {
            $ref = $data['ref'];
            $id = ppaId($ref);
            if ($id === null) {
                throw new RuntimeException('PPA ' . $ref . ' does not exist.');
            }
            $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
            dbExec("UPDATE ppas SET $sets WHERE id = ?", [...array_values($values), $id]);
        }

        // A PPA that is not split across funds is charged entirely to its primary fund,
        // so only real splits (two or more funds) are stored as allocation rows.
        if (array_key_exists('allocations', $data)) {
            dbExec('DELETE FROM ppa_fund_allocations WHERE ppa_id = ?', [$id]);
            if (count($data['allocations'] ?? []) > 1) {
                foreach ($data['allocations'] as $fund => $amount) {
                    dbExec('INSERT INTO ppa_fund_allocations (ppa_id, fund_code, amount) VALUES (?, ?, ?)', [$id, $fund, $amount]);
                }
            }
        }
        if (array_key_exists('budget', $data)) {
            dbExec('DELETE FROM ppa_budgets WHERE ppa_id = ?', [$id]);
            $b = $data['budget'];
            if ($b) {
                $a = $b['approved'] ?? null;
                dbExec('INSERT INTO ppa_budgets (ppa_id, requested_ps, requested_mooe, requested_co, approved_ps, approved_mooe, approved_co, decided_by, decided_at, remarks)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $id, $b['requested']['ps'], $b['requested']['mooe'], $b['requested']['co'],
                    $a['ps'] ?? null, $a['mooe'] ?? null, $a['co'] ?? null,
                    $a ? ($b['by_id'] ?? $actorId) : null, $a ? dbNull($b['at'] ?? '') : null, $a ? dbNull($b['remarks'] ?? '') : null,
                ]);
            }
        }
        if (array_key_exists('checklist', $data)) {
            dbExec('DELETE FROM ppa_review_checklist WHERE ppa_id = ?', [$id]);
            foreach (array_unique((array) $data['checklist']) as $item) {
                dbExec('INSERT INTO ppa_review_checklist (ppa_id, item_key, checked_by) VALUES (?, ?, ?)', [$id, $item, $actorId]);
            }
        }
        $status = $values['status'] ?? dbValue('SELECT status FROM ppas WHERE id = ?', [$id]);
        dbExec('INSERT INTO ppa_history (ppa_id, action, status, remarks, user_id, actor_name) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, mb_substr($historyAction, 0, 200), $status, dbNull($remarks), $actorId, $actorName]);
        return $ref;
    });
    return storeFindPpa($ref);
}

function storeDeletePpa(string $ref): void
{
    dbExec('DELETE FROM ppas WHERE ref = ?', [$ref]);
}

/* ---------------- Rules ---------------- */

/** Total estimated cost = PS + MOOE + CO. */
function ppaTotal(array $ppa): float
{
    return round((float) ($ppa['ps'] ?? 0) + (float) ($ppa['mooe'] ?? 0) + (float) ($ppa['co'] ?? 0), 2);
}

/** Whether the encoding office may still edit (or resubmit) this PPA. */
function ppaIsEditable(array $ppa): bool
{
    return in_array($ppa['status'], PPA_EDITABLE_STATUSES, true);
}

/** Whether the office may submit (or resubmit) this PPA for review. */
function ppaCanSubmit(array $ppa): bool
{
    return in_array($ppa['status'], PPA_SUBMITTABLE_STATUSES, true);
}

/** Fields the implementing office fills in (everything except climate change expenditure). */
function ppaEncoderFields(): array
{
    return array_diff_key(PPA_FIELDS, array_flip(PPA_CC_FIELDS));
}

/**
 * The budget the office requested, by expense class. Before Finance decides,
 * this is simply the PPA's current cost; afterwards it is kept separately so it
 * can be compared with the approved budget.
 */
function ppaRequested(array $ppa): array
{
    $r = $ppa['budget']['requested'] ?? ['ps' => $ppa['ps'] ?? 0, 'mooe' => $ppa['mooe'] ?? 0, 'co' => $ppa['co'] ?? 0];
    $r = ['ps' => (float) $r['ps'], 'mooe' => (float) $r['mooe'], 'co' => (float) $r['co']];
    $r['total'] = round($r['ps'] + $r['mooe'] + $r['co'], 2);
    return $r;
}

/** The budget Finance approved, or null while it has not decided yet. */
function ppaApprovedBudget(array $ppa): ?array
{
    $a = $ppa['budget']['approved'] ?? null;
    if (!$a) {
        return null;
    }
    $a = ['ps' => (float) $a['ps'], 'mooe' => (float) $a['mooe'], 'co' => (float) $a['co']];
    $a['total'] = round($a['ps'] + $a['mooe'] + $a['co'], 2);
    return $a;
}

/** Whether the signed-in user may edit this PPA. */
function canEditPpa(array $ppa): bool
{
    return can('ppa.encode') && canSeeOffice($ppa['office']) && ppaIsEditable($ppa);
}

/**
 * Parse a peso amount typed by a user ("1,250,000.50"). Returns null when
 * the text is not a valid amount; an empty string counts as zero.
 */
function parseMoney(string $text): ?float
{
    $text = trim($text);
    if ($text === '') {
        return 0.0;
    }
    if (!preg_match('/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/', $text)) {
        return null;
    }
    return round((float) str_replace(',', '', $text), 2);
}

/** Normalize a title for duplicate checks. */
function normalizeTitle(string $title): string
{
    return strtolower(preg_replace('/\s+/', ' ', trim($title)));
}

/**
 * Validate encoding-form input.
 *
 * @param array       $input       raw strings from the form
 * @param bool        $isDraft     drafts need only title and office
 * @param array       $offices     offices the user may encode for
 * @param string|null $currentRef  the PPA being edited (excluded from duplicate checks)
 * @return array{0: array, 1: array} [errors, clean values]
 */
function validatePpa(array $input, bool $isDraft, array $offices, ?string $currentRef = null): array
{
    $errors = [];
    $clean = [];
    $fyStart = AIP_FISCAL_YEAR . '-01-01';
    $fyEnd = AIP_FISCAL_YEAR . '-12-31';

    $required = $isDraft
        ? ['title', 'office']
        : ['title', 'type', 'sector', 'description', 'expected_output', 'office', 'start', 'end', 'fund'];
    foreach ($required as $field) {
        if (($input[$field] ?? '') === '') {
            $errors[$field] = PPA_FIELDS[$field] . ' is required.';
        }
    }

    $lengths = ['title' => 200, 'program' => 150, 'description' => 1000, 'expected_output' => 500];
    foreach ($lengths as $field => $max) {
        $clean[$field] = $input[$field] ?? '';
        if (!isset($errors[$field]) && mb_strlen($clean[$field]) > $max) {
            $errors[$field] = 'Use ' . $max . ' characters or fewer.';
        }
    }

    $choices = [
        'type'   => ppaTypes(),
        'sector' => array_keys(ppaSectors()),
        'office' => array_keys($offices),
        'fund'   => array_keys(activeFundSources()),
    ];
    foreach ($choices as $field => $allowed) {
        $clean[$field] = $input[$field] ?? '';
        if ($clean[$field] !== '' && !in_array($clean[$field], $allowed, true)) {
            $errors[$field] = 'Choose ' . strtolower(PPA_FIELDS[$field]) . ' from the list.';
        }
    }
    if (!$isDraft && in_array($clean['type'], ['Project', 'Activity'], true) && $clean['program'] === '') {
        $errors['program'] = 'Name the program this ' . strtolower($clean['type']) . ' belongs to.';
    }

    foreach (['start', 'end'] as $field) {
        $clean[$field] = $input[$field] ?? '';
        if ($clean[$field] === '' || isset($errors[$field])) {
            continue;
        }
        $date = DateTime::createFromFormat('Y-m-d', $clean[$field]);
        if (!$date || $date->format('Y-m-d') !== $clean[$field]) {
            $errors[$field] = 'Enter a valid date.';
        } elseif ($clean[$field] < $fyStart || $clean[$field] > $fyEnd) {
            $errors[$field] = 'Date must fall within FY ' . AIP_FISCAL_YEAR . '.';
        }
    }
    if (!isset($errors['start']) && !isset($errors['end']) && $clean['start'] !== '' && $clean['end'] !== '' && $clean['end'] < $clean['start']) {
        $errors['end'] = 'Completion date cannot be earlier than the start date.';
    }

    foreach (['ps', 'mooe', 'co'] as $field) {
        $value = parseMoney($input[$field] ?? '');
        if ($value === null) {
            $errors[$field] = 'Enter a valid amount, e.g. 1,250,000.00';
            $clean[$field] = 0.0;
        } elseif ($value > 999999999999.99) {
            $errors[$field] = 'Amount is too large.';
            $clean[$field] = 0.0;
        } else {
            $clean[$field] = $value;
        }
    }
    $total = $clean['ps'] + $clean['mooe'] + $clean['co'];
    if (!$isDraft && $total <= 0 && !isset($errors['ps'], $errors['mooe'], $errors['co'])) {
        $errors['co'] = 'Enter the cost under at least one expense class (PS, MOOE or CO).';
    }
    // Prevent the same PPA from being encoded twice for one office in one AIP year.
    if (!isset($errors['title']) && $clean['title'] !== '') {
        foreach (storePpas() as $other) {
            if ($other['ref'] !== $currentRef && $other['office'] === $clean['office'] && $other['status'] !== 'disapproved'
                && str_contains($other['ref'], '-' . AIP_FISCAL_YEAR . '-')
                && normalizeTitle($other['title']) === normalizeTitle($clean['title'])) {
                $errors['title'] = 'This office already has a PPA with this title (' . $other['ref'] . ', ' . statusLabel($other['status']) . ').';
                break;
            }
        }
    }

    return [$errors, $clean];
}

/** Lines describing what changed between two versions (for the activity log). */
function ppaChanges(array $before, array $after): array
{
    $changes = [];
    foreach (PPA_FIELDS as $field => $label) {
        if (!array_key_exists($field, $after)) {
            continue;
        }
        $old = (string) ($before[$field] ?? '');
        $new = (string) ($after[$field] ?? '');
        if (in_array($field, ['ps', 'mooe', 'co', 'cc_adaptation', 'cc_mitigation'], true)) {
            $same = abs((float) $old - (float) $new) < 0.001;
            $old = number_format((float) $old, 2);
            $new = number_format((float) $new, 2);
        } else {
            $same = $old === $new;
        }
        if (!$same) {
            $changes[] = in_array($field, ['description', 'expected_output'], true) ? $label . ' edited' : $label . ': ' . ($old === '' ? '—' : $old) . ' → ' . ($new === '' ? '—' : $new);
        }
    }
    return $changes;
}
