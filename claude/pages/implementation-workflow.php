<?php
$pageTitle = 'Implementation Workflow';
$pageSection = 'Workflow';
$activePage = 'implementation';
$requiredPermission = 'implementation.view';
$assetPrefix = '../';
include '../includes/header.php';

$queue = [
    'eyebrow'          => 'Execution monitoring',
    'title'            => 'Implementation Workflow',
    'description'      => 'Monitor the execution of approved PPAs. Offices record expenses with receipts; audit staff review them.',
    'step'             => 5,
    'pending'          => ['approved'],
    'processed'        => ['ongoing', 'completed'],
    'pendingHeading'   => 'Awaiting Implementation',
    'processedHeading' => 'Ongoing and Completed',
    'actionLabel'      => 'Expenses',
    'rowTarget'        => 'ppa-expenses.php',
    'showProgress'     => true,
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
