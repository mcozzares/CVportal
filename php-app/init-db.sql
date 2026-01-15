USE cvportal;

CREATE TABLE IF NOT EXISTS cvs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    converted_filename VARCHAR(255) NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    mail VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    CONSTRAINT uq_usuarios_nombre UNIQUE (nombre)
);

CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nombre, password, mail, role)
VALUES ('admin', 'admin', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE nombre = nombre;
