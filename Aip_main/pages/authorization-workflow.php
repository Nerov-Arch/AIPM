<?php
$pageTitle = 'Authorization Workflow';
$pageSection = 'Workflow';
$activePage = 'authorization';
$requiredPermission = 'authorization.view';
$assetPrefix = '../';
include '../includes/header.php';

$queue = [
    'eyebrow'          => 'Final approval',
    'title'            => 'Authorization Workflow',
    'description'      => 'Authorize PPAs recommended by the MPDO for inclusion in the Annual Investment Program.',
    'step'             => 3,
    'pending'          => ['recommended'],
    'processed'        => ['approved', 'disapproved', 'ongoing', 'completed'],
    'pendingHeading'   => 'Pending Authorization',
    'processedHeading' => 'Authorized and Disapproved',
    'actionLabel'      => 'Authorize',
    'actPermission'    => 'authorization.act',
    'note'             => 'Development version: "Approve" and "Disapprove" will be added in the approval workflow stage.',
];
include '../includes/workflow-queue.php';
include '../includes/footer.php';
