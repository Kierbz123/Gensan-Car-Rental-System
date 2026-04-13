<?php
// /var/www/html/gensan-car-rental-system/classes/ProcurementRequest.php

/**
 * Procurement Request Management Class
 */

class ProcurementRequest
{
    private $db;
    private $prId;

    public function __construct($prId = null)
    {
        $this->db = Database::getInstance();
        $this->prId = $prId;
    }

    /**
     * Create new procurement request
     */
    public function create($data, $requestorId)
    {
        // Validate
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception("At least one item is required.");
        }

        // Generate PR number
        $prNumber = $this->generatePRNumber();

        // Calculate totals
        $totalCost = 0;
        foreach ($data['items'] as $item) {
            $totalCost += ($item['quantity'] * $item['estimated_unit_cost']);
        }

        // Determine approval workflow
        $approvalWorkflow = $this->determineApprovalWorkflow($totalCost);

        // Insert PR
        $this->db->beginTransaction();

        try {
            $prId = $this->db->insert(
                "INSERT INTO procurement_requests 
                 (pr_number, requestor_id, department, request_date, required_date,
                  urgency, total_estimated_cost, purpose_summary, approval_workflow, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $prNumber,
                    $requestorId,
                    $data['department'] ?? 'operations',
                    date('Y-m-d'),
                    $data['required_date'],
                    $data['urgency'] ?? 'medium',
                    $totalCost,
                    $data['purpose_summary'] ?? null,
                    json_encode($approvalWorkflow),
                    PR_STATUS_DRAFT
                ]
            );

            // Insert items
            $lineNumber = 1;
            foreach ($data['items'] as $item) {
                $this->db->execute(
                    "INSERT INTO procurement_items 
                     (pr_id, line_number, item_description, item_category, specification,
                      quantity, unit, estimated_unit_cost, estimated_total_cost, supplier_id, vehicle_id, purpose)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $prId,
                        $lineNumber++,
                        $item['description'],
                        $item['category'] ?? 'other',
                        $item['specification'] ?? null,
                        $item['quantity'],
                        $item['unit'],
                        $item['estimated_unit_cost'],
                        $item['quantity'] * $item['estimated_unit_cost'],
                        $item['supplier_id'] ?? null,
                        $item['vehicle_id'] ?? null,
                        $item['purpose'] ?? null
                    ]
                );
            }

            $this->db->commit();

            // Log audit
            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $requestorId,
                    null,
                    null,
                    'create',
                    'procurement',
                    'procurement_requests',
                    $prId,
                    "Created PR: {$prNumber}",
                    null,
                    json_encode(['pr_number' => $prNumber, 'total_cost' => $totalCost, 'items_count' => count($data['items'])]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/procurement/create',
                    'info'
                );
            }

            return ['pr_id' => $prId, 'pr_number' => $prNumber];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Submit PR for approval
     */
    public function submitForApproval($prId, $requestorId)
    {
        $pr = $this->db->fetchOne(
            "SELECT status, requestor_id FROM procurement_requests WHERE pr_id = ?",
            [$prId]
        );

        if (!$pr) {
            throw new Exception("PR not found.");
        }

        if ($pr['status'] !== PR_STATUS_DRAFT) {
            throw new Exception("Only draft PRs can be submitted for approval.");
        }

        if ($pr['requestor_id'] != $requestorId) {
            throw new Exception("Only the requestor can submit this PR.");
        }

        $this->db->execute(
            "UPDATE procurement_requests SET status = ?, current_approval_level = 1 WHERE pr_id = ?",
            [PR_STATUS_PENDING, $prId]
        );

        // Notify approvers
        $this->notifyApprovers($prId, 1);

        return true;
    }

    /**
     * Process approval or rejection
     */
    public function processApproval($prId, $approverId, $action, $notes = null)
    {
        $pr = $this->db->fetchOne(
            "SELECT * FROM procurement_requests WHERE pr_id = ?",
            [$prId]
        );

        if (!$pr || $pr['status'] !== PR_STATUS_PENDING) {
            throw new Exception("PR not found or not in pending status.");
        }

        $currentLevel = (int) $pr['current_approval_level'];

        // Verify approver has authority for this current level
        if (!$this->canApprove($approverId, $currentLevel, $pr['total_estimated_cost'])) {
            throw new Exception("You do not have approval authority for this level.");
        }

        if ($action === 'reject') {
            $this->db->query("CALL ProcessPRApproval(?, ?, ?, ?, ?, @res)", [$prId, $approverId, $currentLevel, $notes, $action]);
            $this->notifyRequestor($prId, 'rejected', $notes);
            return true;
        }

        // Keep attempting approval for subsequent levels if the user has permission to do so.
        do {
            $this->db->query("CALL ProcessPRApproval(?, ?, ?, ?, ?, @res)", [$prId, $approverId, $currentLevel, $notes, $action]);

            $updatedPr = $this->db->fetchOne(
                "SELECT status, current_approval_level, total_estimated_cost FROM procurement_requests WHERE pr_id = ?",
                [$prId]
            );

            if ($updatedPr['status'] !== PR_STATUS_PENDING) {
                break; // Fully approved or rejected
            }

            $currentLevel = (int) $updatedPr['current_approval_level'];

            // Check if user is authorized to auto-approve the NEXT level too (e.g. they are System Admin or have higher limit)
            if (!$this->canApprove($approverId, $currentLevel, $updatedPr['total_estimated_cost'])) {
                break; // They can't approve further. It stays pending for the next assigned role.
            }
        } while (true);

        // Fetch final status
        $finalPr = $this->db->fetchOne("SELECT status, current_approval_level FROM procurement_requests WHERE pr_id = ?", [$prId]);

        // If fully approved, notify
        if ($finalPr['status'] === PR_STATUS_APPROVED) {
            $this->notifyProcurementOfficers($prId, 'approved');
            $this->notifyRequestor($prId, 'approved', $notes);
        } else {
            // Notify next level approvers
            $this->notifyApprovers($prId, $finalPr['current_approval_level']);
        }

        return true;
    }

    /**
     * Generate Purchase Order
     */
    public function generatePO($prId, $generatedBy)
    {
        $pr = $this->getById($prId);

        if (!$pr || $pr['status'] !== PR_STATUS_APPROVED) {
            throw new Exception("PR must be approved before generating PO.");
        }

        $poNumber = $this->generatePONumber();

        $this->db->execute(
            "UPDATE procurement_requests 
             SET po_number = ?, po_generated_at = NOW(), po_generated_by = ?, status = ?
             WHERE pr_id = ?",
            [$poNumber, $generatedBy, PR_STATUS_ORDERED, $prId]
        );

        // Update items status
        $this->db->execute(
            "UPDATE procurement_items SET status = ?, quantity_ordered = quantity WHERE pr_id = ?",
            ['ordered', $prId]
        );

        // Generate PDF
        $pdfPath = $this->generatePOPDF($prId, $poNumber);

        return ['po_number' => $poNumber, 'pdf_path' => $pdfPath];
    }

    /**
     * Record item receipt
     */
    public function recordReceipt($itemId, $quantityReceived, $qualityRating, $receivedBy, $notes = null)
    {
        $item = $this->db->fetchOne(
            "SELECT * FROM procurement_items WHERE item_id = ?",
            [$itemId]
        );

        if (!$item) {
            throw new Exception("Item not found.");
        }

        $newReceived = $item['quantity_received'] + $quantityReceived;
        $status = ($newReceived >= $item['quantity']) ? 'fully_received' : 'partially_received';

        $this->db->execute(
            "UPDATE procurement_items 
             SET quantity_received = ?, received_at = NOW(), received_by = ?, 
                 quality_rating = ?, status = ?
             WHERE item_id = ?",
            [$newReceived, $receivedBy, $qualityRating, $status, $itemId]
        );

        // Check if all items received
        $pendingItems = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM procurement_items 
             WHERE pr_id = ? AND status NOT IN ('fully_received', 'cancelled')",
            [$item['pr_id']]
        );

        if ($pendingItems == 0) {
            $this->db->execute(
                "UPDATE procurement_requests 
                 SET status = ?, fully_received_at = NOW(), received_by = ?
                 WHERE pr_id = ?",
                [PR_STATUS_FULLY_RECEIVED, $receivedBy, $item['pr_id']]
            );
        } else {
            $this->db->execute(
                "UPDATE procurement_requests SET status = ? WHERE pr_id = ?",
                [PR_STATUS_PARTIALLY_RECEIVED, $item['pr_id']]
            );
        }

        return true;
    }

    /**
     * Get PR by ID with items
     */
    public function getById($prId)
    {
        $pr = $this->db->fetchOne(
            "SELECT pr.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as requestor_name,
                    CONCAT(a1.first_name, ' ', a1.last_name) as approver1_name,
                    CONCAT(a2.first_name, ' ', a2.last_name) as approver2_name,
                    CONCAT(a3.first_name, ' ', a3.last_name) as approver3_name
             FROM procurement_requests pr
             LEFT JOIN users u ON pr.requestor_id = u.user_id
             LEFT JOIN users a1 ON pr.approved_by_level1 = a1.user_id
             LEFT JOIN users a2 ON pr.approved_by_level2 = a2.user_id
             LEFT JOIN users a3 ON pr.approved_by_level3 = a3.user_id
             WHERE pr.pr_id = ?",
            [$prId]
        );

        if (!$pr) {
            return null;
        }

        // Get items
        $pr['items'] = $this->db->fetchAll(
            "SELECT pi.*, s.company_name as supplier_name, v.plate_number as vehicle_plate
             FROM procurement_items pi
             LEFT JOIN suppliers s ON pi.supplier_id = s.supplier_id
             LEFT JOIN vehicles v ON pi.vehicle_id = v.vehicle_id
             WHERE pi.pr_id = ?
             ORDER BY pi.line_number",
            [$prId]
        );

        return $pr;
    }

    /**
     * Get all PRs with filtering
     */
    public function getAll($filters = [], $page = 1, $perPage = ITEMS_PER_PAGE): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "pr.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['requestor_id'])) {
            $where[] = "pr.requestor_id = ?";
            $params[] = $filters['requestor_id'];
        }

        if (!empty($filters['department'])) {
            $where[] = "pr.department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['urgency'])) {
            $where[] = "pr.urgency = ?";
            $params[] = $filters['urgency'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "pr.request_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "pr.request_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = "(pr.pr_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Pending approval filter for current user
        if (!empty($filters['pending_my_approval'])) {
            $userRole = $this->db->fetchColumn("SELECT role FROM users WHERE user_id = ?", [$filters['pending_my_approval']]);

            $where[] = "pr.status = ?";
            $params[] = PR_STATUS_PENDING;

            if ($userRole === ROLE_SYSTEM_ADMIN) {
                // System admin can approve any pending request
            } elseif ($userRole === ROLE_FLEET_MANAGER) {
                $where[] = "(pr.current_approval_level = 1 OR (pr.current_approval_level = 2 AND pr.total_estimated_cost <= ?))";
                $params[] = PR_APPROVAL_LEVEL2_LIMIT;
            } elseif ($userRole === ROLE_MAINTENANCE_SUPERVISOR) {
                $where[] = "pr.current_approval_level = 1";
            } else {
                $where[] = "1 = 0"; // Not allowed to approve
            }
        }

        $whereClause = implode(' AND ', $where);

        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM procurement_requests pr 
             LEFT JOIN users u ON pr.requestor_id = u.user_id 
             WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;

        $sortBy = 'pr.created_at';
        $sortOrder = 'DESC';

        if (!empty($filters['sort_by'])) {
            $allowedSorts = ['pr.created_at', 'pr.request_date', 'pr.total_estimated_cost', 'pr.pr_number', 'pr.status'];
            // Allow unqualified names to default to pr. prefix
            $sortByParam = $filters['sort_by'];
            if (strpos($sortByParam, '.') === false && in_array('pr.' . $sortByParam, $allowedSorts)) {
                $sortByParam = 'pr.' . $sortByParam;
            }
            if (in_array($sortByParam, $allowedSorts)) {
                $sortBy = $sortByParam;
            }
        }

        if (!empty($filters['sort_order']) && in_array(strtoupper($filters['sort_order']), ['ASC', 'DESC'])) {
            $sortOrder = strtoupper($filters['sort_order']);
        }

        $prs = $this->db->fetchAll(
            "SELECT pr.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as requestor_name,
                    (SELECT COUNT(*) FROM procurement_items WHERE pr_id = pr.pr_id) as item_count
             FROM procurement_requests pr
             LEFT JOIN users u ON pr.requestor_id = u.user_id
             WHERE {$whereClause}
             ORDER BY {$sortBy} {$sortOrder}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $prs,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($count / $perPage)
        ];
    }

    /**
     * Generate PR number
     */
    private function generatePRNumber(): string
    {
        $year = date('Y');
        $result = $this->db->fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(pr_number, -4) AS UNSIGNED)), 0) + 1 as next_seq
             FROM procurement_requests
             WHERE YEAR(created_at) = ?",
            [$year]
        );

        $nextSeq = $result['next_seq'] ?? 1;
        $sequence = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        return "PR-GCR-{$year}-{$sequence}";
    }

    /**
     * Generate PO number
     */
    private function generatePONumber(): string
    {
        $year = date('Y');
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) + 1 as next_seq FROM procurement_requests WHERE po_number IS NOT NULL AND YEAR(po_generated_at) = ?",
            [$year]
        );
        $nextSeq = $result['next_seq'] ?? 1;
        $sequence = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        return "PO-GCR-{$year}-{$sequence}";
    }

    /**
     * Generate PO PDF (placeholder)
     */
    private function generatePOPDF($prId, $poNumber)
    {
        // Implementation for PDF generation
        return "assets/documents/pos/{$poNumber}.pdf";
    }

    /**
     * Determine approval workflow based on amount
     */
    private function determineApprovalWorkflow($amount)
    {
        $workflow = [
            'levels' => [],
            'current_level' => 1
        ];

        // Level 1: Maintenance Supervisor (up to 5,000)
        $workflow['levels'][1] = [
            'role' => 'maintenance_supervisor',
            'limit' => 5000,
            'can_approve' => $amount <= 5000
        ];

        // Level 2: Fleet Manager (up to 20,000)
        if ($amount > 5000) {
            $workflow['levels'][2] = [
                'role' => 'fleet_manager',
                'limit' => 20000,
                'can_approve' => $amount <= 20000
            ];
        }

        // Level 3: Owner/Admin (above 20,000)
        if ($amount > 20000) {
            $workflow['levels'][3] = [
                'role' => 'system_admin',
                'limit' => null,
                'can_approve' => true
            ];
        }

        return $workflow;
    }

    /**
     * Check if user can approve at specific level
     */
    public function canApprove($userId, $level, $amount)
    {
        $user = $this->db->fetchOne(
            "SELECT role FROM users WHERE user_id = ?",
            [$userId]
        );

        if (!$user)
            return false;

        // System admin can approve anything
        if ($user['role'] === ROLE_SYSTEM_ADMIN)
            return true;

        // Level-based checks
        switch ($level) {
            case 1:
                return in_array($user['role'], [ROLE_MAINTENANCE_SUPERVISOR, ROLE_FLEET_MANAGER]);
            case 2:
                return $user['role'] === ROLE_FLEET_MANAGER && $amount <= PR_APPROVAL_LEVEL2_LIMIT;
            case 3:
                return $user['role'] === ROLE_SYSTEM_ADMIN;
            default:
                return false;
        }
    }

    /**
     * Get user's approval level
     */
    private function getUserApprovalLevel($userId)
    {
        $user = $this->db->fetchOne(
            "SELECT role FROM users WHERE user_id = ?",
            [$userId]
        );

        if (!$user)
            return 0;
        if ($user['role'] === ROLE_MAINTENANCE_SUPERVISOR)
            return 1;
        if ($user['role'] === ROLE_FLEET_MANAGER)
            return 2;
        if ($user['role'] === ROLE_SYSTEM_ADMIN)
            return 3;

        return 0;
    }

    /**
     * Notify approvers
     */
    private function notifyApprovers($prId, $level)
    {
        // Get users who can approve at this level
        $approvers = $this->db->fetchAll(
            "SELECT user_id FROM users 
             WHERE role IN (?, ?, ?) 
             AND status = 'active'",
            [ROLE_MAINTENANCE_SUPERVISOR, ROLE_FLEET_MANAGER, ROLE_SYSTEM_ADMIN]
        );

        $pr = $this->getById($prId);
        if (!$pr)
            return;

        foreach ($approvers as $approver) {
            // Create notification
            $this->db->execute(
                "INSERT INTO notifications 
                 (user_id, type, title, message, related_module, related_record_id, related_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $approver['user_id'],
                    'pr_pending_approval',
                    'Purchase Request Pending Approval',
                    "PR {$pr['pr_number']} requires your approval (Level {$level})",
                    'procurement',
                    $prId,
                    'modules/procurement/pr-view.php?id=' . $prId
                ]
            );
        }
    }

    /**
     * Notify requestor
     */
    private function notifyRequestor($prId, $action, $reason)
    {
        $pr = $this->getById($prId);
        if (!$pr)
            return;

        $title = $action === 'approved' ? 'PR Approved' : 'PR Rejected';
        $message = $action === 'approved'
            ? "Your PR {$pr['pr_number']} has been approved."
            : "Your PR {$pr['pr_number']} has been rejected. Reason: {$reason}";

        $this->db->execute(
            "INSERT INTO notifications 
             (user_id, type, title, message, related_module, related_record_id)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $pr['requestor_id'],
                $action === 'approved' ? 'pr_approved' : 'pr_rejected',
                $title,
                $message,
                'procurement',
                $prId
            ]
        );
    }

    /**
     * Notify procurement officers
     */
    private function notifyProcurementOfficers($prId, $status)
    {
        // Implementation for notifying procurement team
    }

    /**
     * Get procurement statistics
     */
    public function getStats()
    {
        return [
            'pending' => $this->db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = ?", [PR_STATUS_PENDING]) ?? 0,
            'draft' => $this->db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = ?", [PR_STATUS_DRAFT]) ?? 0,
            'ordered' => $this->db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = ?", [PR_STATUS_ORDERED]) ?? 0,
            'delays' => $this->db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = ? AND required_date < CURDATE()", [PR_STATUS_ORDERED]) ?? 0
        ];
    }

    /**
     * Check if a user can approve the current level of a PR
     */
    public function canUserApprove($prId, $userId)
    {
        $pr = $this->db->fetchOne(
            "SELECT status, current_approval_level, total_estimated_cost FROM procurement_requests WHERE pr_id = ?",
            [$prId]
        );
        if (!$pr || $pr['status'] !== PR_STATUS_PENDING) {
            return false;
        }

        return $this->canApprove($userId, $pr['current_approval_level'], $pr['total_estimated_cost']);
    }
}
