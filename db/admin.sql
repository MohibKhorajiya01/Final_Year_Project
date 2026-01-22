-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample admin data
INSERT INTO admins (name, email, password) VALUES 
('Admin User', 'admin@eventease.com', '$2y$10$9ay3QQMGpjOPl6PY1dPIEuKXgPTv9Z9GhHK3FBlGJ9RaMmmIGnEge'),
('Super Admin', 'superadmin@eventease.com', '$2y$10$tWF6Nl5yRihT.WXyDAwAZetlbNgoEhOoei8afBm8ebaynchAtxbmC');