<?php
/**
 * Shared display helpers used by every page.
 */

// All dates and times are shown in Philippine time, whatever the server's default is.
date_default_timezone_set('Asia/Manila');

/** Fiscal year of the AIP currently being prepared. MOCK: will come from the AIP cycle settings later. */
const AIP_FISCAL_YEAR = 2027;

/** Every account must be linked to an email on this government domain (sign-in codes are sent there). */
const GOV_EMAIL_DOMAIN = 'indang.gov.ph';

/** Whether an email address belongs to the LGU's government domain. */
function isGovEmail(string $email): bool
{
    return (bool) preg_match('/^[^@\s]+@' . preg_quote(GOV_EMAIL_DOMAIN, '/') . '$/i', trim($email));
}

/** Escape a value for HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format an amount in pesos, e.g. ₱12,500,000.00 */
function peso(float $amount): string
{
    return ($amount < -0.004 ? '-₱' : '₱') . number_format(abs($amount), 2);
}

/** Short peso format for cards, e.g. ₱12.5M */
function pesoShort(float $amount): string
{
    if ($amount < -0.004) {
        return '-' . pesoShort(-$amount);
    }
    if ($amount >= 1000000000) {
        return '₱' . rtrim(rtrim(number_format($amount / 1000000000, 2), '0'), '.') . 'B';
    }
    if ($amount >= 1000000) {
        return '₱' . rtrim(rtrim(number_format($amount / 1000000, 2), '0'), '.') . 'M';
    }
    return peso($amount);
}

/** Format a Y-m-d date as "Oct 12, 2026". */
function displayDate(string $date): string
{
    if ($date === '') {
        return '—';
    }
    $time = strtotime($date);
    return $time ? date('M j, Y', $time) : $date;
}

/** Two-letter initials from a full name. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '' && ctype_alpha($part[0])) {
            $letters .= strtoupper($part[0]);
        }
    }
    if ($letters === '') {
        return '?';
    }
    return strlen($letters) > 2 ? $letters[0] . substr($letters, -1) : $letters;
}

/** Human-readable label for a PPA status code. */
function statusLabel(string $status): string
{
    return [
        'draft'       => 'Draft',
        'for_review'  => 'Pending Review',
        'returned'    => 'Returned for Revision',
        'for_budget'  => 'Budget Review',
        'recommended' => 'For Final Approval',
        'approved'    => 'Approved',
        'disapproved' => 'Disapproved',
        'ongoing'     => 'Ongoing',
        'completed'   => 'Completed',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/** CSS class for the status pill of a PPA status code. */
function statusClass(string $status): string
{
    return [
        'draft'       => 'inactive',
        'for_review'  => 'for-review',
        'returned'    => 'rejected',
        'for_budget'  => 'pending',
        'recommended' => 'pending',
        'approved'    => 'approved',
        'disapproved' => 'rejected',
        'ongoing'     => 'for-review',
        'completed'   => 'approved',
    ][$status] ?? 'inactive';
}

/** Render a status pill. */
function statusPill(string $status, ?string $label = null): string
{
    return '<span class="status-pill ' . e(statusClass($status)) . '"><i></i>' . e($label ?? statusLabel($status)) . '</span>';
}
