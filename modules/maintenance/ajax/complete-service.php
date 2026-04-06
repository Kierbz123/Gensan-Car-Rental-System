<?php
/**
 * AJAX: Complete a Service / Mark Maintenance Done
 * Path: modules/maintenance/ajax/complete-service.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $schedule = new MaintenanceSchedule();
    $logId = $schedule->recordService([
        'vehicle_id' => trim($input['vehicle_id'] ?? ''),
        'schedule_id' => !empty($input['schedule_id']) ? (int) $input['schedule_id'] : null,
        'service_date' => $input['service_date'] ?? date('Y-m-d'),
        'service_type' => sanitize($input['service_type'] ?? ''),
        'mileage_at_service' => (int) ($input['mileage_at_service'] ?? 0),
        'labor_cost' => (float) ($input['labor_cost'] ?? 0),
        'parts_cost' => (float) ($input['parts_cost'] ?? 0),
        'other_costs' => (float) ($input['other_costs'] ?? 0),
        'mechanic_id' => !empty($input['mechanic_id']) ? (int) $input['mechanic_id'] : null,
        'notes' => sanitize($input['notes'] ?? ''),
        'next_service_recommended_date' => $input['next_service_date'] ?? null,
        'next_service_recommended_mileage' => !empty($input['next_service_mileage']) ? (int) $input['next_service_mileage'] : null,
    ], $_SESSION['user_id']);

    // Consume parts from inventory (non-fatal)
    $invWarnings = [];
    $parts = $input['parts'] ?? [];
    if (!empty($parts) && is_array($parts)) {
        $invObj = new Inventory();
        foreach ($parts as $part) {
            $inventoryId = (int) ($part['inventory_id'] ?? 0);
            $qty = (float) ($part['qty'] ?? 0);
            if ($inventoryId && $qty > 0) {
                try {
                    $invObj->consume($inventoryId, $qty, $logId, $_SESSION['user_id'], 'Service log #' . $logId);
                } catch (Exception $ie) {
                    $invWarnings[] = $ie->getMessage();
                }
            }
        }
    }

    $response = ['success' => true, 'log_id' => $logId, 'message' => 'Service recorded successfully'];
    if ($invWarnings) {
        $response['inventory_warnings'] = $invWarnings;
    }
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
