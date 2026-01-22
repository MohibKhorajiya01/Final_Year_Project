CREATE TABLE IF NOT EXISTS managers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO managers (id, name, email, password, phone) VALUES 
    (1, 'Sarth Vaghela', 'sarth@eventease.com', 'Sarth123', '9876543210'),
    (2, 'Mahir Kadivar', 'mahir@eventease.com', 'mahir123', '9876543211')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password = VALUES(password),
    phone = VALUES(phone);