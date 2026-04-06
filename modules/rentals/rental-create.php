<?php
/**
 * New Rental — Direct Create
 * Path: modules/rentals/rental-create.php
 * Alias / short-form redirect to reserve.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('rentals.create');

// Pass through any GET params and redirect to the main booking form
$query = http_build_query($_GET);
header('Location: reserve.php' . ($query ? '?' . $query : ''));
exit;
