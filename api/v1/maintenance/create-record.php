<?php
/**
 * API Endpoint: Create Maintenance Record
 * Method: POST
 * Request: FormData (multipart for photos)
 * Response: {success, data, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();

$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

try {
    // Authenticate
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    $user = authenticateAPI($token);

    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }

    if (!hasPermission($user, 'maintenance.create')) {
        throw new Exception('Forbidden', 403);
    }

    // Get data
    $data = $_POST;

    // Validate required fields
    $required = ['vehicle_id', 'service_date', 'service_type', 'service_description', 'mileage_at_service'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("{$field} is required", 400);
        }
    }

    // Handle photo uploads
    $beforePhotos = [];
    $afterPhotos = [];

    if (!empty($_FILES['before_photos'])) {
        foreach ($_FILES['before_photos']['tmp_name'] as $index => $tmpName) {
            if ($tmpName) {
                $uploaded = uploadMaintenancePhoto($_FILES['before_photos'], $index, $data['vehicle_id'], 'before');
                if ($uploaded) {
                    $beforePhotos[] = $uploaded;
                }
            }
        }
    }

    if (!empty($_FILES['after_photos'])) {
        foreach ($_FILES['after_photos']['tmp_name'] as $index => $tmpName) {
            if ($tmpName) {
                $uploaded = uploadMaintenancePhoto($_FILES['after_photos'], $index, $data['vehicle_id'], 'after');
                if ($uploaded) {
                    $afterPhotos[] = $uploaded;
                }
            }
        }
    }

    $data['before_photos'] = $beforePhotos;
    $data['after_photos'] = $afterPhotos;

    // Parse parts used if provided
    if (!empty($data['parts_used'])) {
        if (is_string($data['parts_used'])) {
            $data['parts_used'] = json_decode($data['parts_used'], true);
        }
    }

    // Create maintenance record
    $maintenance = new MaintenanceSchedule();
    $logId = $maintenance->recordService($data, $user['user_id']);

    $response['success'] = true;
    $response['data'] = ['log_id' => $logId];
    $response['message'] = 'Maintenance record created successfully';
    http_response_code(201);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Helper function to upload maintenance photos
 */
function uploadMaintenancePhoto($files, $index, $vehicleId, $type)
{
    if (is_array($files['name'])) {
        $file = [
            'name' => $files['name'][$index],
            'type' => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error' => $files['error'][$index],
            'size' => $files['size'][$index]
        ];
    } else {
        $file = $files;
    }

    $verify = verifyUpload($file, ALLOWED_IMAGE_TYPES);
    if (!$verify['valid']) {
        return false;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $vehicleId . '_' . $type . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = MAINTENANCE_PHOTOS_PATH . $filename;

    if (!is_dir(MAINTENANCE_PHOTOS_PATH)) {
        mkdir(MAINTENANCE_PHOTOS_PATH, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }

    return str_replace(BASE_PATH, '', $filepath);
}
