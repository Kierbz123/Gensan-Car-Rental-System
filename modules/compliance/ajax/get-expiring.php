<?php
/**
 * AJAX: Get Expiring Compliance Records
 * Path: modules/compliance/ajax/get-expiring.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$days = (int) ($_GET['days'] ?? 30);

$compliance = new ComplianceRecord();
$data = $compliance->getExpiring($days);

echo json_encode(['success' => true, 'data' => $data, 'days' => $days]);
