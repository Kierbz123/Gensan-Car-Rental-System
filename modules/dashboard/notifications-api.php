<?php
/**
 * Notifications API — Dashboard
 * Handles: fetch, mark-read, mark-all-read actions via AJAX (JSON)
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

header('Content-Type: application/json');

if (!$authUser) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $authUser->getData()['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'fetch';
$db     = Database::getInstance();

try {
    switch ($action) {

        /* ── Fetch notifications ─────────────────────────────── */
        case 'fetch':
            $limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

            $rows = $db->fetchAll(
                "SELECT notification_id, type, title, message, related_url,
                        related_module, related_record_id, is_read, created_at
                   FROM notifications
                  WHERE user_id = :uid
                    AND is_read = 0
                  ORDER BY created_at DESC
                  LIMIT :lim",
                [':uid' => $userId, ':lim' => $limit]
            );

            $unread = $db->fetchColumn(
                "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0",
                [':uid' => $userId]
            );

            echo json_encode([
                'success'       => true,
                'notifications' => $rows,
                'unread_count'  => (int) $unread,
            ]);
            break;

        /* ── Mark single notification as read ───────────────── */
        case 'mark_read':
            $id = (int) ($_POST['notification_id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
                exit;
            }

            $db->execute(
                "UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                  WHERE notification_id = :nid AND user_id = :uid",
                [':nid' => $id, ':uid' => $userId]
            );

            echo json_encode(['success' => true]);
            break;

        /* ── Mark all notifications as read ─────────────────── */
        case 'mark_all_read':
            $db->execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW()
                  WHERE user_id = :uid AND is_read = 0",
                [':uid' => $userId]
            );

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
