<?php
// /var/www/html/gensan-car-rental-system/classes/ReportGenerator.php

/**
 * Report Generation Class
 */

class ReportGenerator
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Fleet utilization report
     */
    public function getFleetUtilization($dateFrom, $dateTo, $categoryId = null)
    {
        $where = "v.deleted_at IS NULL";
        $params = [$dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom];

        if ($categoryId !== null) {
            $where .= " AND v.category_id = ?";
            $params[] = (int) $categoryId;
        }

        return $this->db->fetchAll(
            "SELECT 
                v.vehicle_id,
                v.plate_number,
                v.brand,
                v.model,
                vc.category_name,
                COUNT(ra.agreement_id) as rental_count,
                SUM(DATEDIFF(LEAST(ra.rental_end_date, ?), GREATEST(ra.rental_start_date, ?))) as rental_days,
                DATEDIFF(?, ?) + 1 as total_days,
                ROUND(
                    (SUM(DATEDIFF(LEAST(ra.rental_end_date, ?), GREATEST(ra.rental_start_date, ?))) / (DATEDIFF(?, ?) + 1)) * 100,
                    2
                ) as utilization_rate,
                SUM(ra.total_amount) as total_revenue
             FROM vehicles v
             JOIN vehicle_categories vc ON v.category_id = vc.category_id
             LEFT JOIN rental_agreements ra ON v.vehicle_id = ra.vehicle_id
                 AND ra.status IN ('active', 'returned', 'completed')
                 AND ra.rental_start_date <= ?
                 AND ra.rental_end_date >= ?
             WHERE {$where}
             GROUP BY v.vehicle_id
             ORDER BY utilization_rate DESC",
            $params
        );
    }

    /**
     * Maintenance cost analysis
     */
    public function getMaintenanceCosts($dateFrom, $dateTo, $groupBy = 'vehicle')
    {
        if ($groupBy === 'vehicle') {
            return $this->db->fetchAll(
                "SELECT 
                    v.vehicle_id,
                    v.plate_number,
                    v.brand,
                    v.model,
                    COUNT(ml.log_id) as service_count,
                    SUM(ml.labor_cost) as total_labor,
                    SUM(ml.parts_cost) as total_parts,
                    SUM(ml.other_costs) as other_costs,
                    SUM(ml.total_cost) as grand_total,
                    AVG(ml.total_cost) as avg_cost_per_service
                 FROM vehicles v
                 LEFT JOIN (
                     SELECT vehicle_id, service_date, labor_cost, parts_cost, other_costs, total_cost, log_id
                     FROM maintenance_logs
                 ) ml ON v.vehicle_id = ml.vehicle_id
                     AND ml.service_date BETWEEN ? AND ?
                 WHERE v.deleted_at IS NULL
                 GROUP BY v.vehicle_id
                 HAVING grand_total > 0
                 ORDER BY grand_total DESC",
                [$dateFrom, $dateTo]
            );
        } else if ($groupBy === 'category') {
            return $this->db->fetchAll(
                "SELECT 
                    vc.category_name,
                    COUNT(DISTINCT v.vehicle_id) as vehicle_count,
                    COUNT(ml.log_id) as service_count,
                    SUM(ml.total_cost) as total_cost,
                    AVG(ml.total_cost) as avg_cost_per_service
                 FROM vehicle_categories vc
                 JOIN vehicles v ON vc.category_id = v.category_id
                 LEFT JOIN maintenance_logs ml ON v.vehicle_id = ml.vehicle_id
                     AND ml.service_date BETWEEN ? AND ?
                 WHERE v.deleted_at IS NULL
                 GROUP BY vc.category_id
                 ORDER BY total_cost DESC",
                [$dateFrom, $dateTo]
            );
        } else if ($groupBy === 'type') {
            return $this->db->fetchAll(
                "SELECT 
                    ml.service_type,
                    COUNT(*) as service_count,
                    SUM(ml.total_cost) as total_cost,
                    AVG(ml.total_cost) as avg_cost
                 FROM maintenance_logs ml
                 WHERE ml.service_date BETWEEN ? AND ?
                 GROUP BY ml.service_type
                 ORDER BY total_cost DESC",
                [$dateFrom, $dateTo]
            );
        }
    }

    /**
     * Revenue report
     */
    public function getRevenueReport($dateFrom, $dateTo, $groupBy = 'daily')
    {
        $dateFormat = [
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly' => '%Y'
        ][$groupBy] ?? '%Y-%m-%d';

        return $this->db->fetchAll(
            "SELECT 
                DATE_FORMAT(ra.rental_start_date, ?) as period,
                COUNT(*) as rental_count,
                SUM(ra.base_amount) as rental_revenue,
                SUM(ra.additional_driver_fee + ra.insurance_fee + ra.gps_fee + ra.child_seat_fee + ra.other_charges) as addon_revenue,
                SUM(ra.total_amount) as total_revenue,
                AVG(ra.total_amount) as avg_rental_value
             FROM rental_agreements ra
             WHERE ra.status IN ('active', 'returned', 'completed')
             AND ra.rental_start_date BETWEEN ? AND ?
             GROUP BY period
             ORDER BY period",
            [$dateFormat, $dateFrom, $dateTo]
        );
    }

    /**
     * Customer analytics
     */
    public function getCustomerAnalytics($dateFrom, $dateTo)
    {
        return $this->db->fetchAll(
            "SELECT 
                c.customer_type,
                COUNT(DISTINCT c.customer_id) as customer_count,
                COUNT(ra.agreement_id) as rental_count,
                SUM(ra.total_amount) as total_revenue,
                AVG(ra.total_amount) as avg_rental_value,
                COUNT(DISTINCT CASE WHEN ra.rental_start_date >= DATE_SUB(?, INTERVAL 30 DAY) THEN c.customer_id END) as active_customers_30d
             FROM customers c
             LEFT JOIN rental_agreements ra ON c.customer_id = ra.customer_id
                 AND ra.rental_start_date BETWEEN ? AND ?
                 AND ra.status IN ('active', 'returned', 'completed')
             WHERE c.deleted_at IS NULL
             GROUP BY c.customer_type
             ORDER BY total_revenue DESC",
            [$dateTo, $dateFrom, $dateTo]
        );
    }

    /**
     * Get compliance status report
     */
    public function getComplianceStats()
    {
        return $this->db->fetchAll(
            "SELECT cr.*, v.plate_number, v.brand, v.model
             FROM compliance_records cr
             JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
             ORDER BY cr.expiry_date ASC"
        );
    }

    /**
     * Get monthly revenue summary
     */
    public function getMonthlyRevenueSummary()
    {
        return $this->db->fetchAll(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month, 
                SUM(total_amount) as total_revenue,
                COUNT(*) as rental_count
             FROM rental_agreements 
             WHERE status != 'cancelled'
             GROUP BY month 
             ORDER BY month DESC"
        );
    }

    /**
     * Get procurement history
     */
    public function getProcurementHistory()
    {
        return $this->db->fetchAll(
            "SELECT pr.*, u.username as requester 
             FROM procurement_requests pr
             LEFT JOIN users u ON pr.requestor_id = u.user_id
             ORDER BY pr.created_at DESC"
        );
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel($data, $headers, $filename)
    {
        // Implementation using PhpSpreadsheet
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new Exception("PhpSpreadsheet library is not installed or loaded.");
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Data
        $row = 2;
        foreach ($data as $record) {
            $col = 1;
            foreach ($record as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }

        // Auto-width columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
