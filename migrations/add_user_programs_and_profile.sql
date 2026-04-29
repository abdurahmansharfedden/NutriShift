-- =============================================================================
-- Migration: AI Workout Generator Tables & Columns
-- Run this script once against the `nutrishift` database.
-- Safe to re-run: uses IF NOT EXISTS / IF NOT COLUMN checks via ALTER IGNORE.
-- =============================================================================

USE nutrishift;

-- -----------------------------------------------------------------------------
-- 1. Add health metric columns to the `users` table (if they don't exist yet).
--    These store the values the user enters in their profile settings page.
--
-- weight          → total body weight in kilograms (e.g. 80.5)
-- body_fat        → body fat percentage (e.g. 18.00 for 18%)
-- activity_level  → TDEE multiplier stored as a decimal (e.g. 1.375)
-- fitness_goal    → an ENUM — the DB enforces only these values are stored
-- -----------------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS weight          DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'kg',
    ADD COLUMN IF NOT EXISTS body_fat        DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'percentage',
    ADD COLUMN IF NOT EXISTS activity_level  DECIMAL(4,3) NULL DEFAULT NULL COMMENT 'TDEE multiplier e.g. 1.375',
    ADD COLUMN IF NOT EXISTS fitness_goal    ENUM(
                                                 'fat_loss',
                                                 'muscle_gain',
                                                 'maintenance',
                                                 'endurance'
                                             ) NULL DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- 2. Create the `user_programs` table.
--    Each row is one AI-generated weekly workout plan saved for a user.
--
-- program_data → stored as MariaDB JSON type so it can be queried with JSON
--                functions (e.g., JSON_EXTRACT) if needed later.
-- created_at   → auto-set to the moment of insertion.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_programs (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL,
    program_data    JSON          NOT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_programs_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_programs_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI-generated weekly workout programs';
