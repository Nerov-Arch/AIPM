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
    'description'      => 'Monitor the execution and physical progress of approved PPAs for the fiscal year.',
    'step'             => 4,
    'pending'          => ['approved'],
    'processed'        => ['ongoing', 'completed'],
    'pendingHeading'   => 'Awaiting Implementation',
    'processedHeading' => 'Ongoing and Completed',
    'actionLabel'      => 'View',
    'showProgress'     => true,
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
