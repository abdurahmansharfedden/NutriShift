-- =============================================================================
-- NutriShift Database Initialization Script
-- Run this once to set up all tables and seed an admin user.
-- =============================================================================

-- Create (or reset) the database
CREATE DATABASE IF NOT EXISTS nutrishift CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nutrishift;

-- =============================================================================
-- TABLE: users
-- Stores every registered person. The 'role' column controls what they can do.
-- ENUM restricts the value to ONLY 'admin' or 'user' — the DB enforces this!
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    email         VARCHAR(191)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,          -- Never store plain passwords!
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLE: cycles
-- A "Biological Cycle" is a custom time window the user defines (e.g. night shift).
-- start_time and end_time are DATETIME so they can span across midnight.
-- user_id is a FOREIGN KEY — it links each cycle back to its owner in 'users'.
-- ON DELETE CASCADE means: if you delete a user, all their cycles vanish too.
-- =============================================================================
CREATE TABLE IF NOT EXISTS cycles (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    cycle_name      VARCHAR(100)    NOT NULL,
    start_time      DATETIME        NOT NULL,
    end_time        DATETIME        NOT NULL,
    target_calories INT UNSIGNED    NOT NULL DEFAULT 2000,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_cycles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABLE: food_logs
-- Each row is ONE food item logged inside ONE cycle.
-- cycle_id is a FOREIGN KEY to cycles.id — a food log cannot exist without a cycle.
-- Nutritional values use DECIMAL(7,2) — e.g. 9999.99 grams max, two decimal places.
-- logged_at records WHEN inside the cycle the food was eaten.
-- =============================================================================
CREATE TABLE IF NOT EXISTS food_logs (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    cycle_id    INT UNSIGNED     NOT NULL,
    food_name   VARCHAR(150)     NOT NULL,
    calories    DECIMAL(7,2)     NOT NULL DEFAULT 0,
    protein     DECIMAL(7,2)     NOT NULL DEFAULT 0,   -- grams
    carbs       DECIMAL(7,2)     NOT NULL DEFAULT 0,   -- grams
    fat         DECIMAL(7,2)     NOT NULL DEFAULT 0,   -- grams
    logged_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_food_cycle
        FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- SEED DATA: Default Admin User
-- Password is "Admin@1234" — hashed with PHP's password_hash() (bcrypt).
-- IMPORTANT: Change this password immediately after first login!
-- To regenerate the hash run: php -r "echo password_hash('Admin@1234', PASSWORD_DEFAULT);"
-- =============================================================================
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES (
    'admin',
    'admin@nutrishift.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Admin@1234
    'admin'
);
