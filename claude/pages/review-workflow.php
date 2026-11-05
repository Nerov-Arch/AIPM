<?php
$pageTitle = 'Review & Approval';
$pageSection = 'Workflow';
$activePage = 'review';
$requiredPermission = 'review.view';
$assetPrefix = '../';
include '../includes/header.php';

// One MPDO Reviewer role evaluates submitted PPAs and, after Finance approves
// the budget, gives the final approval. Both stages are pending here.
$queue = [
    'eyebrow'          => 'MPDO review and final approval',
    'title'            => 'Review & Approval',
    'description'      => 'Evaluate submitted PPAs and forward them to Finance, then give the final approval once Finance has approved the budget.',
    'step'             => 2,
    'pending'          => ['for_review', 'recommended'],
    'processed'        => ['returned', 'for_budget', 'approved', 'disapproved', 'ongoing', 'completed'],   // everything the MPDO has already acted on
    'pendingHeading'   => 'Awaiting MPDO action',
    'processedHeading' => 'Processed by the MPDO',
    'actionLabel'      => 'Review',
    'actionLabels'     => ['for_review' => 'Evaluate', 'recommended' => 'Approve'],
    'actPermission'    => 'review.act',
    'decisionPage'     => true,
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
