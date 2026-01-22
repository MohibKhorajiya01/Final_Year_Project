CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    otp VARCHAR(10),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo users to keep relational data intact
INSERT INTO users (id, name, email, phone, password, status)
VALUES
    (1, 'Rahul Sharma', 'rahul@example.com', '9876543210', 'rahul123', 1),
    (2, 'Neha Patel', 'neha@example.com', '9988776655', 'neha123', 1),
    (3, 'Arjun Mehta', 'arjun@example.com', '9123456780', 'arjun123', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    phone = VALUES(phone),
    password = VALUES(password),
    status = 1;

