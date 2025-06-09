<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Simple test data
$testData = [
    ['date' => date('Y-m-d'), 'value' => 5],
    ['date' => date('Y-m-d', strtotime('-1 day')), 'value' => 3],
    ['date' => date('Y-m-d', strtotime('-2 days')), 'value' => 7]
];

echo json_encode($testData);
