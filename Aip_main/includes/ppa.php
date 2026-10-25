<?php
/**
 * PPA (Program / Project / Activity) records: standardized fields,
 * storage, validation and status rules.
 *
 * MOCK STORE: records live in the session store (see includes/store.php)
 * until the database phase, when these functions are reimplemented with
 * prepared statements against a `ppas` table (plus `ppa_history`).
 */

/** Development sectors used in the AIP form. */
function ppaSectors(): array
{
    return [
        'GPS' => 'General Public Services',
        'SS'  => 'Social Services',
        'ES'  => 'Economic Services',
        'OS'  => 'Other Services',
    ];
}

/** Statuses in which the encoding office may still edit a PPA. */
const PPA_EDITABLE_STATUSES = ['draft', 'returned'];

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

/** MOCK: turn the sample PPAs into full records for the store. */
function ppaSeedRecords(): array
{
    $sectorByOffice = ['MEO' => 'ES', 'MHO' => 'SS', 'MSWDO' => 'SS', 'MAO' => 'ES', 'MDRRMO' => 'SS', 'MENRO' => 'OS', 'MPDO' => 'GPS'];
    $records = [];
    foreach (mockPpas() as $ppa) {
        $isProject = $ppa['type'] === 'Project';
        $record = $ppa + [
            'sector'          => $sectorByOffice[$ppa['office']] ?? 'GPS',
            'description'     => 'Sample description for ' . $ppa['title'] . '.',
            'expected_output' => $isProject ? 'Completed facility turned over to the implementing office.' : 'Activity conducted for target beneficiaries.',
            'ps'              => 0,
            'mooe'            => $isProject ? 0 : $ppa['cost'],
            'co'              => $isProject ? $ppa['cost'] : 0,
            'cc_adaptation'   => in_array($ppa['office'], ['MDRRMO', 'MENRO'], true) ? round($ppa['cost'] * 0.5, 2) : 0,
            'cc_mitigation'   => 0,
            'cc_typology'     => in_array($ppa['office'], ['MDRRMO', 'MENRO'], true) ? 'A612-01' : '',
            'remarks'         => '',
            'created_by'      => 'System (sample data)',
            'created_at'      => $ppa['updated'] . ' 08:00:00',
            'updated_by'      => 'System (sample data)',
            'updated_at'      => $ppa['updated'] . ' 08:00:00',
            'submitted_at'    => $ppa['updated'] . ' 08:00:00',
            'history'         => [['time' => $ppa['updated'] . ' 08:00:00', 'status' => $ppa['status'], 'action' => 'Sample record loaded', 'by' => 'System (sample data)', 'remarks' => '']],
        ];
        if ($ppa['ref'] === 'PPA-' . AIP_FISCAL_YEAR . '-013') {
            // MOCK: a split allocation that does not add up, to demonstrate the funding checks.
            $record['allocations'] = ['GF' => 2000000, 'TF' => 400000];
        }
        if ($ppa['status'] === 'returned') {
            $record['remarks'] = 'Please indicate the exact site and number of barangays served, and break down the cost into MOOE and Capital Outlay.';
            $record['history'][] = ['time' => $ppa['updated'] . ' 15:30:00', 'status' => 'returned', 'action' => 'Returned for revision', 'by' => 'Jose Bautista (jbautista)', 'remarks' => $record['remarks']];
        }
        $records[$ppa['ref']] = $record;
    }
    return $records;
}

/* ---------------- Store access ---------------- */

/** Make sure PPAs are in the store (also upgrades sessions created before PPAs were stored). */
function ppaStoreInit(): void
{
    storeInit();
    if (!isset($_SESSION['mock_store']['ppas'])) {
        $_SESSION['mock_store']['ppas'] = ppaSeedRecords();
    }
}

/** All PPAs, newest reference first. */
function storePpas(): array
{
    ppaStoreInit();
    $ppas = array_values($_SESSION['mock_store']['ppas']);
    usort($ppas, fn ($a, $b) => strcmp($b['ref'], $a['ref']));
    return $ppas;
}

function storeFindPpa(string $ref): ?array
{
    ppaStoreInit();
    return $_SESSION['mock_store']['ppas'][$ref] ?? null;
}

/** Next reference number for the current AIP year, e.g. PPA-2027-015. */
function nextPpaRef(): string
{
    ppaStoreInit();
    $prefix = 'PPA-' . AIP_FISCAL_YEAR . '-';
    $max = 0;
    foreach (array_keys($_SESSION['mock_store']['ppas']) as $ref) {
        if (str_starts_with($ref, $prefix)) {
            $max = max($max, (int) substr($ref, strlen($prefix)));
        }
    }
    return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * Insert (no ref) or update a PPA and append a history entry.
 * Returns the saved record.
 */
function storeSavePpa(array $data, string $historyAction, string $remarks = ''): array
{
    ppaStoreInit();
    $actor = currentUser();
    $actorName = $actor ? $actor['name'] . ' (' . $actor['username'] . ')' : 'System';
    $now = date('Y-m-d H:i:s');

    if (empty($data['ref'])) {
        $data['ref'] = nextPpaRef();
        $data += ['created_by' => $actorName, 'created_at' => $now, 'history' => [], 'remarks' => '', 'progress' => 0, 'utilized' => 0,
                  'record' => '', 'recordDate' => '', 'officer' => $actor['name'] ?? '', 'submitted_at' => ''];
    }
    $existing = $_SESSION['mock_store']['ppas'][$data['ref']] ?? [];
    $record = array_merge($existing, $data);
    $record['cost'] = ppaTotal($record);
    // A PPA that is not split across funds is charged entirely to its primary fund,
    // so its allocation follows the primary fund and cost automatically.
    if (count($record['allocations'] ?? []) <= 1) {
        $record['allocations'] = [];
    }
    $record['updated_by'] = $actorName;
    $record['updated_at'] = $now;
    $record['updated'] = date('Y-m-d');
    $record['history'][] = ['time' => $now, 'status' => $record['status'], 'action' => $historyAction, 'by' => $actorName, 'remarks' => $remarks];
    $_SESSION['mock_store']['ppas'][$record['ref']] = $record;
    return $record;
}

function storeDeletePpa(string $ref): void
{
    ppaStoreInit();
    unset($_SESSION['mock_store']['ppas'][$ref]);
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
        'type'   => mockPpaTypes(),
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

    foreach (['ps', 'mooe', 'co', 'cc_adaptation', 'cc_mitigation'] as $field) {
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
    $ccTotal = $clean['cc_adaptation'] + $clean['cc_mitigation'];
    if ($ccTotal > $total + 0.001 && !isset($errors['cc_adaptation'])) {
        $errors['cc_adaptation'] = 'Climate change expenditure cannot exceed the total cost.';
    }

    $clean['cc_typology'] = strtoupper($input['cc_typology'] ?? '');
    if ($clean['cc_typology'] !== '' && !preg_match('/^[A-Z0-9][A-Z0-9.\-]{1,19}$/', $clean['cc_typology'])) {
        $errors['cc_typology'] = 'Use letters, numbers, dots or dashes (e.g. A612-01).';
    }
    if ($ccTotal > 0 && $clean['cc_typology'] === '' && !$isDraft) {
        $errors['cc_typology'] = 'A typology code is required when climate change expenditure is entered.';
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
