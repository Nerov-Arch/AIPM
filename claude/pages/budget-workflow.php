<?php
$pageTitle = 'Budget Review';
$pageSection = 'Workflow';
$activePage = 'budget';
$requiredPermission = 'budget.view';
$assetPrefix = '../';
include '../includes/header.php';

$queue = [
    'eyebrow'          => 'Finance',
    'title'            => 'Budget Review',
    'description'      => 'Review the requested budget of PPAs evaluated by the MPDO and approve the budget before final approval.',
    'step'             => 3,
    'pending'          => ['for_budget'],
    'processed'        => ['recommended', 'approved', 'disapproved', 'ongoing', 'completed'],
    'pendingHeading'   => 'Awaiting budget review',
    'processedHeading' => 'Budget decided',
    'actionLabel'      => 'Review budget',
    'actPermission'    => 'budget.act',
    'decisionPage'     => true,
    'showBudget'       => true,
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
