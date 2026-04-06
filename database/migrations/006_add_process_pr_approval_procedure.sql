-- =============================================================================
-- Migration 006: ProcessPRApproval Stored Procedure
-- Gensan Car Rental System
-- Run once against: gensan_car_rental_db
-- =============================================================================
-- This procedure handles the multi-level PR approval workflow.
-- It is idempotent: DROP IF EXISTS ensures a clean reinstall.
-- =============================================================================

DROP PROCEDURE IF EXISTS `ProcessPRApproval`;

DELIMITER ;;

CREATE PROCEDURE `ProcessPRApproval`(
    IN  p_pr_id          INT UNSIGNED,
    IN  p_approver_id    INT UNSIGNED,
    IN  p_approval_level TINYINT UNSIGNED,
    IN  p_notes          TEXT,
    IN  p_action         ENUM('approve', 'reject'),
    OUT p_result_message VARCHAR(255)
)
BEGIN
    DECLARE v_current_level TINYINT UNSIGNED;
    DECLARE v_total_cost    DECIMAL(12,2);
    DECLARE v_pr_status     VARCHAR(50);

    -- Fetch current PR state
    SELECT current_approval_level, total_estimated_cost, status
    INTO   v_current_level, v_total_cost, v_pr_status
    FROM   procurement_requests
    WHERE  pr_id = p_pr_id;

    -- Guard: must be in pending_approval status
    IF v_pr_status != 'pending_approval' THEN
        SET p_result_message = 'PR is not in pending approval status';

    -- Guard: level must match
    ELSEIF v_current_level != p_approval_level THEN
        SET p_result_message = 'Invalid approval level';

    -- Action: reject
    ELSEIF p_action = 'reject' THEN
        UPDATE procurement_requests
        SET    status           = 'rejected',
               rejected_by     = p_approver_id,
               rejected_at     = NOW(),
               rejection_reason = p_notes
        WHERE  pr_id = p_pr_id;

        SET p_result_message = 'PR rejected successfully';

    -- Action: approve
    ELSE
        IF p_approval_level = 1 THEN
            UPDATE procurement_requests
            SET    approved_by_level1    = p_approver_id,
                   approved_at_level1   = NOW(),
                   approval_notes_level1 = p_notes,
                   current_approval_level = 2
            WHERE  pr_id = p_pr_id;

            -- Amounts <= 5,000 only need level-1 approval; mark as fully approved
            IF v_total_cost <= 5000 THEN
                UPDATE procurement_requests
                SET status = 'approved'
                WHERE pr_id = p_pr_id;
            END IF;

        ELSEIF p_approval_level = 2 THEN
            UPDATE procurement_requests
            SET    approved_by_level2    = p_approver_id,
                   approved_at_level2   = NOW(),
                   approval_notes_level2 = p_notes,
                   current_approval_level = 3
            WHERE  pr_id = p_pr_id;

            -- Amounts <= 20,000 are fully approved after level 2
            IF v_total_cost <= 20000 THEN
                UPDATE procurement_requests
                SET status = 'approved'
                WHERE pr_id = p_pr_id;
            END IF;

        ELSEIF p_approval_level = 3 THEN
            UPDATE procurement_requests
            SET    approved_by_level3    = p_approver_id,
                   approved_at_level3   = NOW(),
                   approval_notes_level3 = p_notes,
                   status               = 'approved'
            WHERE  pr_id = p_pr_id;
        END IF;

        SET p_result_message = 'PR approved successfully';
    END IF;
END ;;

DELIMITER ;
