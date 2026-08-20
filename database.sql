CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    nik VARCHAR(50) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teknisi') NOT NULL,
    email VARCHAR(255) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_name VARCHAR(255) NOT NULL,
    reporter_nik VARCHAR(50) NOT NULL,
    work_type VARCHAR(50) NOT NULL,
    work_order VARCHAR(100) NOT NULL UNIQUE,
    no_inet VARCHAR(100) DEFAULT '',
    customer_name VARCHAR(255) NOT NULL,
    ps_date DATE NOT NULL,
    base_amount INT NOT NULL DEFAULT 125000,
    status VARCHAR(50) DEFAULT 'Selesai',
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS job_technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    technician_name VARCHAR(255) NOT NULL,
    technician_nik VARCHAR(50) NOT NULL,
    share_amount INT NOT NULL,
    FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    base_value INT NOT NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(100) NOT NULL,
    action_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin (Password: admin123)
INSERT IGNORE INTO users (name, nik, username, password_hash, role) VALUES 
('Administrator', 'ADM-001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
