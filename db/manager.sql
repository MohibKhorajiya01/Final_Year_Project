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
    (1, 'Sarth Vaghela', 'sarth@eventease.com', '$2y$10$GviwwNA4wqfTXFLOb3OV/.oV.NzVajq.zriyO3iPdBp9ckpzPypaK', '9876543210'),
    (2, 'Mahir Kadivar', 'mahir@eventease.com', '$2y$10$TUXIt8chpm8Nixzvy0zJdOAnVg9BEI6nT/Jc7Fl7qeC1oYIXNzQ5i', '9876543211')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password = VALUES(password),
    phone = VALUES(phone);