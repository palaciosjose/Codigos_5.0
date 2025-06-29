-- Database changes for Fase 1 (Telegram) and Fase 2 (Asignaciones)

-- Bot Telegram configuration table
CREATE TABLE IF NOT EXISTS telegram_bot_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs for Telegram bot actions
CREATE TABLE IF NOT EXISTS telegram_bot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    telegram_id BIGINT,
    action VARCHAR(50),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Templates for mass permission assignments
CREATE TABLE IF NOT EXISTS user_permission_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    email_ids JSON,
    platform_ids JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User groups with shared permissions
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    user_ids JSON,
    template_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
