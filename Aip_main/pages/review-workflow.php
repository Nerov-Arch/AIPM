<?php
$pageTitle = 'Review Workflow';
$pageSection = 'Workflow';
$activePage = 'review';
$requiredPermission = 'review.view';
$assetPrefix = '../';
include '../includes/header.php';

$queue = [
    'eyebrow'          => 'MPDO review',
    'title'            => 'Review Workflow',
    'description'      => 'Evaluate submitted PPA proposals from implementing offices for the upcoming fiscal year.',
    'step'             => 2,
    'pending'          => ['for_review'],
    'processed'        => ['returned', 'recommended', 'approved', 'disapproved', 'ongoing', 'completed'],   // everything the MPDO has already acted on
    'pendingHeading'   => 'Pending Evaluations',
    'processedHeading' => 'Processed Evaluations',
    'actionLabel'      => 'Review',
    'actPermission'    => 'review.act',
    'decisionPage'     => true,
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
