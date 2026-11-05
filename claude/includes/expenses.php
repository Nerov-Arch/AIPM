<?php
/**
 * Expenses of approved PPAs, with receipt photos, and their audit review.
 *
 * The implementing office records each expense (date, description, amount,
 * receipt / OR number and a photo or scan of the receipt). Audit staff see
 * every expense on a timeline and mark it verified or flagged.
 *
 * MOCK STORE: expense records live in the session store until the database
 * phase (table `ppa_expenses`). Receipt FILES are saved on the server in
 * uploads/receipts/ and are only served through pages/receipt.php after a
 * permission check. Because the records are session-only, a file stays on
 * disk after the session ends; clean the folder when resetting test data.
 */

/** PPA statuses in which expenses can be recorded. */
const EXPENSE_STATUSES = ['approved', 'ongoing'];
const RECEIPT_MAX_BYTES = 5 * 1024 * 1024;
/** Allowed receipt types: detected MIME type => file extension. */
const RECEIPT_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

function receiptDir(): string
{
    return dirname(__DIR__) . '/uploads/receipts';
}

/** MOCK: sample expenses matching the utilized amounts of the sample PPAs (no receipt images). */
function mockExpenses(): array
{
    $fy = AIP_FISCAL_YEAR;
    $rows = [
        ["PPA-$fy-012", '2026-10-02', 'Seedlings and planting materials (2,000 pcs)', 150000, 'OR-55120'],
        ["PPA-$fy-012", '2026-10-05', 'Meals and transport of volunteers, 3 planting days', 120000, 'OR-55187'],
        ["PPA-$fy-013", '2026-09-20', 'Financial assistance released, batch 1 (200 beneficiaries)', 1000000, 'DV-2026-0918'],
        ["PPA-$fy-013", '2026-10-01', 'Financial assistance released, batch 2 (100 beneficiaries)', 500000, 'DV-2026-1004'],
        ["PPA-$fy-014", '2026-08-28', 'Consultancy services, CLUP technical working group', 700000, 'DV-2026-0811'],
        ["PPA-$fy-014", '2026-09-25', 'Printing and public hearing expenses', 480000, 'OR-54410'],
    ];
    $expenses = [];
    foreach ($rows as $i => [$ref, $date, $description, $amount, $receiptNo]) {
        $id = $i + 1;
        $expenses[$id] = [
            'id' => $id, 'ref' => $ref, 'date' => $date, 'description' => $description, 'amount' => (float) $amount,
            'receipt_no' => $receiptNo, 'file' => null,
            'recorded_by' => 'System (sample data)', 'recorded_by_id' => null, 'recorded_at' => $date . ' 16:00:00',
            'audit' => $i === 0 ? ['status' => 'verified', 'by' => 'Ramon Aquino (raquino)', 'at' => '2026-10-06 09:00:00', 'note' => 'Matches the delivery receipt.']
                                : ['status' => 'unverified', 'by' => '', 'at' => '', 'note' => ''],
        ];
    }
    return $expenses;
}

function expenseStoreInit(): void
{
    storeInit();
    if (!isset($_SESSION['mock_store']['expenses'])) {
        $_SESSION['mock_store']['expenses'] = mockExpenses();
        $_SESSION['mock_store']['next_expense_id'] = count($_SESSION['mock_store']['expenses']) + 1;
    }
}

/** All expenses, newest date first. */
function storeExpenses(): array
{
    expenseStoreInit();
    $rows = array_values($_SESSION['mock_store']['expenses']);
    usort($rows, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    return $rows;
}

function storeFindExpense(int $id): ?array
{
    expenseStoreInit();
    return $_SESSION['mock_store']['expenses'][$id] ?? null;
}

/** Expenses of one PPA in date order (the expenditure timeline). */
function ppaExpenses(string $ref): array
{
    $rows = array_values(array_filter(storeExpenses(), fn ($x) => $x['ref'] === $ref));
    usort($rows, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
    return $rows;
}

function ppaExpenseTotal(string $ref): float
{
    return round(array_sum(array_column(ppaExpenses($ref), 'amount')), 2);
}

/** Expenses the signed-in user may see (encoders: own office only). */
function visibleExpenses(): array
{
    $ppas = [];
    foreach (storePpas() as $ppa) {
        $ppas[$ppa['ref']] = $ppa;
    }
    $rows = [];
    foreach (storeExpenses() as $x) {
        $ppa = $ppas[$x['ref']] ?? null;
        if ($ppa && canSeeOffice($ppa['office'])) {
            $rows[] = $x + ['ppa' => $ppa];
        }
    }
    return $rows;
}

/** Whether the signed-in user may record expenses for this PPA now. */
function canRecordExpense(array $ppa): bool
{
    return can('expenses.record') && canSeeOffice($ppa['office']) && in_array($ppa['status'], EXPENSE_STATUSES, true);
}

/** Keep the PPA's utilized amount equal to its recorded expenses. */
function syncPpaUtilized(string $ref, string $historyAction, string $remarks = ''): void
{
    $ppa = storeFindPpa($ref);
    if (!$ppa) {
        return;
    }
    $update = ['ref' => $ref, 'utilized' => ppaExpenseTotal($ref)];
    if ($ppa['status'] === 'approved' && $update['utilized'] > 0) {
        $update['status'] = 'ongoing';   // the first expense starts implementation
        $historyAction .= '; implementation started';
    }
    storeSavePpa($update, $historyAction, $remarks);
}

/**
 * Validate an expense and its uploaded receipt.
 * @return array{0: array, 1: array} [errors, clean values]
 */
function validateExpense(array $ppa, array $input, ?array $file): array
{
    $errors = [];
    $clean = [
        'date'        => trim((string) ($input['date'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'receipt_no'  => strtoupper(trim((string) ($input['receipt_no'] ?? ''))),
    ];

    $date = DateTime::createFromFormat('Y-m-d', $clean['date']);
    if ($clean['date'] === '') {
        $errors['date'] = 'Enter the date of the expense.';
    } elseif (!$date || $date->format('Y-m-d') !== $clean['date']) {
        $errors['date'] = 'Enter a valid date.';
    } elseif ($clean['date'] > date('Y-m-d')) {
        $errors['date'] = 'The expense date cannot be in the future.';
    }

    if ($clean['description'] === '') {
        $errors['description'] = 'Describe what the expense was for.';
    } elseif (mb_strlen($clean['description']) > 200) {
        $errors['description'] = 'Use 200 characters or fewer.';
    }

    $amount = parseMoney((string) ($input['amount'] ?? ''));
    if ($amount === null || $amount <= 0) {
        $errors['amount'] = 'Enter the amount spent, e.g. 25,000.00';
        $amount = 0.0;
    } else {
        $remaining = round((float) $ppa['cost'] - ppaExpenseTotal($ppa['ref']), 2);
        if ($amount > $remaining + 0.001) {
            $errors['amount'] = 'This is more than the remaining approved budget of ' . peso(max(0, $remaining)) . '.';
        }
    }
    $clean['amount'] = $amount;

    if ($clean['receipt_no'] === '') {
        $errors['receipt_no'] = 'Enter the official receipt, invoice or voucher number.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9 .\/#\-]{1,39}$/', $clean['receipt_no'])) {
        $errors['receipt_no'] = 'Use letters, numbers and . / # - (e.g. OR-12345).';
    }

    // Receipt photo or scan (required as proof).
    $clean['file'] = null;
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        $errors['receipt'] = 'Attach a photo or scan of the receipt.';
    } elseif (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > RECEIPT_MAX_BYTES) {
        $errors['receipt'] = 'The receipt file is too large. The limit is 5 MB.';
    } elseif ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors['receipt'] = 'The receipt could not be uploaded. Please try again.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset(RECEIPT_TYPES[$mime])) {
            $errors['receipt'] = 'Upload a JPG, PNG or WEBP photo, or a PDF scan.';
        } elseif (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
            $errors['receipt'] = 'The image could not be read. Please upload another photo.';
        } else {
            $clean['file'] = ['tmp' => $file['tmp_name'], 'mime' => $mime, 'size' => (int) $file['size'],
                              'name' => mb_substr(basename((string) $file['name']), 0, 120)];
        }
    }
    return [$errors, $clean];
}

/** Save a validated expense: store the receipt file and the record. */
function storeAddExpense(array $ppa, array $clean): array
{
    expenseStoreInit();
    $dir = receiptDir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Receipt folder could not be created.');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . RECEIPT_TYPES[$clean['file']['mime']];
    if (!move_uploaded_file($clean['file']['tmp'], $dir . '/' . $stored)) {
        throw new RuntimeException('Receipt file could not be saved.');
    }
    $user = currentUser();
    $id = $_SESSION['mock_store']['next_expense_id']++;
    $record = [
        'id' => $id, 'ref' => $ppa['ref'], 'date' => $clean['date'], 'description' => $clean['description'],
        'amount' => $clean['amount'], 'receipt_no' => $clean['receipt_no'],
        'file' => ['stored' => $stored, 'name' => $clean['file']['name'], 'mime' => $clean['file']['mime'], 'size' => $clean['file']['size']],
        'recorded_by' => $user['name'] . ' (' . $user['username'] . ')', 'recorded_by_id' => $user['id'], 'recorded_at' => date('Y-m-d H:i:s'),
        'audit' => ['status' => 'unverified', 'by' => '', 'at' => '', 'note' => ''],
    ];
    $_SESSION['mock_store']['expenses'][$id] = $record;
    syncPpaUtilized($ppa['ref'], 'Expense recorded: ' . peso($record['amount']) . ' (' . $record['receipt_no'] . ')', $record['description']);
    auditLog('expense.add', $ppa['ref'], peso($record['amount']) . ' · ' . $record['receipt_no'] . ' · ' . $record['description']);
    return $record;
}

/** The office may remove its own expense until it has been audited. */
function canDeleteExpense(array $expense, array $ppa): bool
{
    return can('expenses.record') && canSeeOffice($ppa['office']) && $expense['audit']['status'] === 'unverified'
        && $ppa['status'] !== 'completed';
}

function storeDeleteExpense(array $expense): void
{
    expenseStoreInit();
    unset($_SESSION['mock_store']['expenses'][$expense['id']]);
    if ($expense['file']) {
        $path = receiptDir() . '/' . basename($expense['file']['stored']);
        if (is_file($path)) {
            unlink($path);
        }
    }
    syncPpaUtilized($expense['ref'], 'Expense removed: ' . peso($expense['amount']) . ' (' . $expense['receipt_no'] . ')');
    auditLog('expense.delete', $expense['ref'], peso($expense['amount']) . ' · ' . $expense['receipt_no']);
}

/** Audit decision on an expense. Returns an error message or ''. */
function auditExpense(array $expense, string $status, string $note): string
{
    if (!can('expenses.verify')) {
        return 'Only audit staff can verify or flag expenses.';
    }
    if (!in_array($status, ['verified', 'flagged'], true)) {
        return 'Choose verified or flagged.';
    }
    $note = trim($note);
    if ($status === 'flagged' && mb_strlen($note) < 10) {
        return 'Explain what is wrong with this expense (at least 10 characters) so the office can respond.';
    }
    if (mb_strlen($note) > 500) {
        return 'Use 500 characters or fewer for the audit note.';
    }
    expenseStoreInit();
    $user = currentUser();
    $_SESSION['mock_store']['expenses'][$expense['id']]['audit'] = [
        'status' => $status, 'by' => $user['name'] . ' (' . $user['username'] . ')', 'at' => date('Y-m-d H:i:s'), 'note' => $note,
    ];
    auditLog($status === 'verified' ? 'expense.verify' : 'expense.flag', $expense['ref'],
        peso($expense['amount']) . ' · ' . $expense['receipt_no'] . ($note !== '' ? ' · ' . $note : ''));
    return '';
}

function expenseAuditPill(array $expense): string
{
    return match ($expense['audit']['status']) {
        'verified' => statusPill('approved', 'Verified'),
        'flagged'  => statusPill('disapproved', 'Flagged'),
        default    => statusPill('recommended', 'Not yet audited'),
    };
}

/** Audit summary counts for a list of expenses. */
function expenseAuditCounts(array $expenses): array
{
    $counts = ['unverified' => 0, 'verified' => 0, 'flagged' => 0];
    foreach ($expenses as $x) {
        $counts[$x['audit']['status']]++;
    }
    return $counts;
}
