<?php
// includes/functions.php

/**
 * Global Intelligence Utilities
 * Centralized helper functions for the GCR High-Premium SaaS Interface.
 */

/**
 * Core Interface: Currency Formatter
 */
function formatCurrency($amount, $symbol = true)
{
    if ($amount === null)
        return '-';
    $formatted = number_format((float) $amount, 2);
    return $symbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

/**
 * Core Interface: Date Formatter
 */
function formatDate($date, $format = DATE_FORMAT)
{
    if (!$date || $date == '0000-00-00')
        return '-';
    return date($format, strtotime($date));
}

/**
 * Core Interface: DateTime Formatter
 */
function formatDateTime($datetime, $format = DATETIME_FORMAT)
{
    if (!$datetime || $datetime == '0000-00-00 00:00:00')
        return '-';
    return date($format, strtotime($datetime));
}

/**
 * Technical UI: Dynamic Badge Generator
 */
function getBadge($type, $label, $pulsing = false)
{
    $baseClass = 'badge';
    $styleClass = match ($type) {
        'success', 'active', 'available', 'completed' => 'badge-success',
        'warning', 'pending', 'due' => 'badge-warning',
        'danger', 'overdue', 'restricted', 'breached' => 'badge-danger',
        'info', 'reserved', 'scheduled' => 'badge-info',
        'secondary', 'draft', 'archived' => 'badge-secondary',
        default => 'badge-secondary'
    };

    $pulse = $pulsing ? '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;margin-right:6px;animation:pulse 1.5s infinite;"></span>' : '';

    return "<span class=\"$baseClass $styleClass\" style=\"display:inline-flex;align-items:center;\">$pulse" . strtoupper(htmlspecialchars($label)) . "</span>";
}

/**
 * Technical UI: Relative Time Intelligence
 */
function getRelativeTime($datetime)
{
    if (!$datetime)
        return '-';
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60)
        return 'Just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';

    return date('M j, Y', $time);
}

/**
 * Data Control: Input Sanitization
 */
function clean($data)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = clean($value);
        }
    } else {
        $data = htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Security: Get Client IP Address
 */
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can be a comma-separated list
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

/**
 * Fleet Intelligence: Asset Icon Resolver
 */
function getAssetIcon($category)
{
    $category = strtolower($category);
    return match (true) {
        str_contains($category, 'sedan') => 'car',
        str_contains($category, 'suv') => 'truck',
        str_contains($category, 'van') => 'users',
        str_contains($category, 'motor') => 'bike',
        default => 'box'
    };
}

/**
 * System Control: Secure Redirect
 */
function redirect($url, $message = null, $type = 'success')
{
    if ($message) {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $_SESSION[$type . '_message'] = $message;
    }
    $target = str_contains($url, 'http') ? $url : BASE_URL . ltrim($url, '/');
    header("Location: $target");
    exit;
}

/**
 * System Control: JSON Pulse (API Responses)
 */
function pulse($data, $status = 200)
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Intelligence Logs: Audit Logging
 */
function logAudit($action, $target_type, $target_id, $details = null)
{
    try {
        $db = Database::getInstance();
        $db->insert(
            "INSERT INTO audit_logs 
             (action, module, record_id, record_description, user_id, ip_address, action_timestamp, severity)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), 'info')",
            [
                $action,
                $target_type,
                $target_id,
                is_array($details) ? json_encode($details) : $details,
                $_SESSION['user_id'] ?? null,
                getClientIP()
            ]
        );
    } catch (Exception $e) {
        logError("Audit Log Failure: " . $e->getMessage());
    }
}

/**
 * Intelligence Logs: Error Logging
 */
function logError($message, $context = [])
{
    $timestamp = date('[Y-m-d H:i:s]');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "{$timestamp} {$message}{$contextStr}" . PHP_EOL;

    $logFile = LOGS_PATH . 'error.log';

    // Ensure logs directory exists
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }

    error_log($logEntry, 3, $logFile);
}

/**
 * Data Presentation: Status Indicators (Simple Dots)
 */
function getStatusDot($status)
{
    $color = match ($status) {
        'available', 'active', 'online' => 'var(--success)',
        'maintenance', 'busy', 'away' => 'var(--warning)',
        'rented', 'offline' => 'var(--text-muted)',
        'overdue', 'error', 'breached' => 'var(--danger)',
        default => '#cbd5e1'
    };
    return "<span style=\"display:inline-block;width:8px;height:8px;border-radius:50%;background:$color;\"></span>";
}

/**
 * Text processing: Human-readable ID
 */
function formatCode($prefix, $id, $length = 6)
{
    return $prefix . str_pad($id, $length, '0', STR_PAD_LEFT);
}

/**
 * UI Component: Pagination Controls
 *
 * @param int    $total      Total number of records
 * @param int    $page       Current page number
 * @param int    $perPage    Records per page
 * @param string $urlPattern Optional sprintf pattern for page URLs, e.g. '?page=%d&filter=foo'
 *                           If omitted, existing GET params are preserved and only `page` is swapped.
 * @return string            HTML pagination markup (empty string if only one page)
 */
function pagination(int $total, int $page, int $perPage, string $urlPattern = ''): string
{
    if ($perPage <= 0 || $total <= $perPage) {
        return '';
    }

    $totalPages = (int) ceil($total / $perPage);
    if ($totalPages <= 1) {
        return '';
    }

    // Build URL for a given page number
    $buildUrl = function (int $p) use ($urlPattern): string {
        if ($urlPattern !== '') {
            // Caller supplied a sprintf pattern like '?page=%d&foo=bar'
            return sprintf($urlPattern, $p);
        }
        // Default: keep existing GET params, just override `page`
        $params = array_merge($_GET, ['page' => $p]);
        return '?' . http_build_query($params);
    };

    $prev = $page > 1 ? $page - 1 : null;
    $next = $page < $totalPages ? $page + 1 : null;

    // Which page numbers to show (always show first, last, and a window around current)
    $window = 2;
    $showPages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= $window) {
            $showPages[] = $i;
        }
    }

    $html = '<nav class="pagination-nav" aria-label="Pagination">';
    $html .= '<ul class="pagination">';

    // Previous button
    if ($prev) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($prev)) . '" aria-label="Previous">';
        $html .= '<i data-lucide="chevron-left"></i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i data-lucide="chevron-left"></i></span></li>';
    }

    // Page numbers with ellipsis gaps
    $lastPrinted = null;
    foreach ($showPages as $p) {
        if ($lastPrinted !== null && $p - $lastPrinted > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link page-ellipsis">&hellip;</span></li>';
        }
        $activeClass = ($p === $page) ? ' active' : '';
        $html .= '<li class="page-item' . $activeClass . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($p)) . '">' . $p . '</a>';
        $html .= '</li>';
        $lastPrinted = $p;
    }

    // Next button
    if ($next) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($next)) . '" aria-label="Next">';
        $html .= '<i data-lucide="chevron-right"></i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i data-lucide="chevron-right"></i></span></li>';
    }

    $html .= '</ul>';
    $html .= '<p class="pagination-summary">Showing page ' . $page . ' of ' . $totalPages . ' (' . number_format($total) . ' records)</p>';
    $html .= '</nav>';

    return $html;
}

/**
 * UI Component: Floating Toast Notification
 */
function renderToast($message, $type = 'success', $id = 'toast-msg')
{
    if (empty($message)) return '';
    $icon = $type === 'success' ? 'check-circle' : 'alert-circle';
    $bg = $type === 'success' ? 'var(--success)' : 'var(--danger)';
    
    return <<<HTML
    <div id="{$id}" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:{$bg};color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
        <i data-lucide="{$icon}" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span>
            {$message}
        </span>
    </div>
    <script>setTimeout(() => { document.getElementById('{$id}')?.remove(); }, 3500);</script>
HTML;
}

/**
 * UI Component: Empty State for Tables/Cards
 */
function renderEmptyState($message = 'No data available', $icon = 'inbox', $colSpan = 6)
{
    return <<<HTML
    <tr>
        <td colspan="{$colSpan}" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.75rem;">
                <i data-lucide="{$icon}" style="width:36px;height:36px;color:var(--primary-200);"></i>
                <span style="font-weight:500;">{$message}</span>
            </div>
        </td>
    </tr>
HTML;
}
