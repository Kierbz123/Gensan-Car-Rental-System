<?php
// modules/asset-tracking/functions/validation-functions.php

/**
 * Validate vehicle creation / update data
 */
function validateVehicleData($data, $isUpdate = false)
{
    $errors = [];

    // Required fields
    $required = [
        'category_id' => 'Category',
        'plate_number' => 'Plate Number',
        'brand' => 'Brand',
        'model' => 'Model',
        'year_model' => 'Year Model',
        'fuel_type' => 'Fuel Type',
        'transmission' => 'Transmission',
        'daily_rental_rate' => 'Daily Rental Rate'
    ];

    foreach ($required as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = "$label is required.";
        }
    }

    // Plate number format (simplified)
    if (!empty($data['plate_number'])) {
        if (!preg_match('/^[A-Z0-9 -]{4,10}$/i', $data['plate_number'])) {
            $errors[] = "Invalid plate number format.";
        }
    }

    // Year model validation
    if (!empty($data['year_model'])) {
        $currentYear = date('Y');
        if (!is_numeric($data['year_model']) || $data['year_model'] < 1900 || $data['year_model'] > $currentYear + 1) {
            $errors[] = "Invalid year model.";
        }
    }

    // Numeric validations
    $numericFields = [
        'seating_capacity' => 'Seating Capacity',
        'daily_rental_rate' => 'Daily Rental Rate',
        'weekly_rental_rate' => 'Weekly Rental Rate',
        'monthly_rental_rate' => 'Monthly Rental Rate',
        'acquisition_cost' => 'Acquisition Cost'
    ];

    foreach ($numericFields as $field => $label) {
        if (!empty($data[$field]) && !is_numeric($data[$field])) {
            $errors[] = "$label must be a numeric value.";
        }
    }

    // Acquisition date
    if (!empty($data['acquisition_date'])) {
        if (!strtotime($data['acquisition_date'])) {
            $errors[] = "Invalid acquisition date.";
        }
    }

    return $errors;
}

/**
 * Clean data for database insertion
 */
function sanitizeVehicleData($data)
{
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $sanitized[$key] = trim(htmlspecialchars($value));
        } else {
            $sanitized[$key] = $value;
        }
    }
    return $sanitized;
}
