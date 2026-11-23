<?php
/**
 * Expenses of approved PPAs, with receipt photos, and their audit review.
 *
 * The implementing office records each expense (date, description, amount,
 * receipt / OR number and a photo or scan of the receipt). Audit staff see
 * every expense on a timeline and mark it verified or flagged.
 *
 * Storage: MySQL table `ppa_expenses`. Receipt FILES are saved on the server
 * in uploads/receipts/ with random names and are only served through
 * pages/receipt.php after a permission check.
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

/** Kept for older callers; the database needs no initialisation. */
function expenseStoreInit(): void
{
}

function expenseFromRow(array $r): array
{
    return [
        'id'             => (int) $r['id'],
        'ref'            => $r['ref'],
        'date'           => $r['expense_date'],
        'description'    => $r['description'],
        'amount'         => (float) $r['amount'],
        'receipt_no'     => $r['receipt_no'],
        'file'           => $r['receipt_file'] === null ? null : ['stored' => $r['receipt_file'], 'name' => (string) $r['receipt_original_name'],
                                                              'mime' => (string) $r['receipt_mime'], 'size' => (int) $r['receipt_size']],
        'recorded_by'    => dbActorLabel($r['r_name'], $r['r_user']) ?: 'System (sample data)',
        'recorded_by_id' => $r['recorded_by'] === null ? null : (int) $r['recorded_by'],
        'recorded_at'    => $r['recorded_at'],
        'audit'          => ['status' => $r['audit_status'], 'by' => dbActorLabel($r['a_name'], $r['a_user']),
                             'at' => (string) $r['audited_at'], 'note' => (string) $r['audit_note']],
    ];
}

/** All expenses, newest date first. */
function storeExpenses(): array
{
    return dbCached('expenses', fn () => array_map('expenseFromRow', dbAll(
        'SELECT e.*, p.ref, r.full_name AS r_name, r.username AS r_user, a.full_name AS a_name, a.username AS a_user
         FROM ppa_expenses e
         JOIN ppas p ON p.id = e.ppa_id
         LEFT JOIN users r ON r.id = e.recorded_by
         LEFT JOIN users a ON a.id = e.audited_by
         ORDER BY e.expense_date DESC, e.id DESC')));
}

function storeFindExpense(int $id): ?array
{
    foreach (storeExpenses() as $expense) {
        if ($expense['id'] === $id) {
            return $expense;
        }
    }
    return null;
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
    [$actorId] = dbActor();
    try {
        dbExec('INSERT INTO ppa_expenses (ppa_id, expense_date, description, amount, receipt_no, receipt_file, receipt_original_name,
                                          receipt_mime, receipt_size, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            ppaId($ppa['ref']), $clean['date'], $clean['description'], $clean['amount'], $clean['receipt_no'],
            $stored, $clean['file']['name'], $clean['file']['mime'], $clean['file']['size'], $actorId,
        ]);
    } catch (Throwable $e) {
        @unlink($dir . '/' . $stored);   // do not leave an orphan file behind
        throw $e;
    }
    $record = storeFindExpense((int) db()->lastInsertId());
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
    dbExec('DELETE FROM ppa_expenses WHERE id = ?', [$expense['id']]);
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
    [$actorId] = dbActor();
    dbExec('UPDATE ppa_expenses SET audit_status = ?, audited_by = ?, audited_at = NOW(), audit_note = ? WHERE id = ?',
        [$status, $actorId, dbNull($note), $expense['id']]);
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
