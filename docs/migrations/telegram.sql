ALTER TABLE users ADD telegram_id BIGINT UNIQUE, telegram_username VARCHAR(255), last_telegram_activity TIMESTAMP;
ALTER TABLE logs ADD source_channel ENUM('web','telegram') DEFAULT 'web', telegram_chat_id BIGINT;
CREATE TABLE telegram_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT,
    user_id INT,
    session_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
