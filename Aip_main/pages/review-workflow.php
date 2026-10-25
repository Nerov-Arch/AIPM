<?php
$pageTitle = 'Review Workflow';
$pageSection = 'Workflow';
$activePage = 'review';
$assetPrefix = '../';
include '../includes/header.php';

$queue = [
    'eyebrow'          => 'MPDO review',
    'title'            => 'Review Workflow',
    'description'      => 'Evaluate submitted PPA proposals from implementing offices for the upcoming fiscal year.',
    'step'             => 2,
    'pending'          => ['for_review'],
    'processed'        => ['returned', 'recommended', 'approved', 'disapproved', 'ongoing', 'completed'],
    'pendingHeading'   => 'Pending Evaluations',
    'processedHeading' => 'Processed Evaluations',
    'actionLabel'      => 'Review',
    'note'             => 'Development version: "Return for revision" and "Recommend for approval" will be added in the review workflow stage.',
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
