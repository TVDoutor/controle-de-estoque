-- Database schema for inventory control system
CREATE DATABASE IF NOT EXISTS controle_estoque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_estoque;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','gestor','usuario') NOT NULL DEFAULT 'usuario',
    phone VARCHAR(40) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20) NULL,
    contact_name VARCHAR(120) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(120) NULL,
    address TEXT NULL,
    city VARCHAR(120) NULL,
    state VARCHAR(10) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('android_box','monitor','outro') NOT NULL DEFAULT 'android_box',
    brand VARCHAR(80) NOT NULL,
    model_name VARCHAR(120) NOT NULL,
    monitor_size ENUM('42','49','50') NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_model (brand, model_name, monitor_size)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(100) NOT NULL UNIQUE,
    model_id INT NOT NULL,
    serial_number VARCHAR(120) NULL,
    mac_address VARCHAR(120) NULL,
    condition_status ENUM('novo','usado') NOT NULL DEFAULT 'novo',
    status ENUM('em_estoque','alocado','manutencao','baixado') NOT NULL DEFAULT 'em_estoque',
    entry_date DATE NOT NULL,
    batch VARCHAR(80) NULL,
    notes TEXT NULL,
    current_client_id INT NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (model_id) REFERENCES equipment_models(id),
    FOREIGN KEY (current_client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_type ENUM('ENTRADA','SAIDA','RETORNO') NOT NULL,
    operation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    client_id INT NULL,
    notes TEXT NULL,
    performed_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment_operation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_id INT NOT NULL,
    equipment_id INT NOT NULL,
    accessories_power TINYINT(1) NULL,
    accessories_hdmi TINYINT(1) NULL,
    accessories_remote TINYINT(1) NULL,
    condition_after_return ENUM('ok','manutencao','descartar') NULL,
    remarks TEXT NULL,
    FOREIGN KEY (operation_id) REFERENCES equipment_operations(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    UNIQUE KEY uq_operation_equipment (operation_id, equipment_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipment_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Seed default models
INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Proeletronic', '5000' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Proeletronic' AND model_name='5000');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Proeletronic', '3000' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Proeletronic' AND model_name='3000');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Aquario', '2000' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Aquario' AND model_name='2000');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Aquario', '3000' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Aquario' AND model_name='3000');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Generico', 'K-95W' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Generico' AND model_name='K-95W');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Generico', 'TX-9' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Generico' AND model_name='TX-9');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Generico', 'K3-Pro' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Generico' AND model_name='K3-Pro');

INSERT INTO equipment_models (category, brand, model_name)
SELECT 'android_box', 'Generico', 'Outros' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='Generico' AND model_name='Outros');

INSERT INTO equipment_models (category, brand, model_name, monitor_size)
SELECT 'monitor', 'LG', 'LG-42', '42' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='LG' AND model_name='LG-42');

INSERT INTO equipment_models (category, brand, model_name, monitor_size)
SELECT 'monitor', 'LG', 'LG-49', '49' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='LG' AND model_name='LG-49');

INSERT INTO equipment_models (category, brand, model_name, monitor_size)
SELECT 'monitor', 'LG', 'LG-50', '50' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM equipment_models WHERE brand='LG' AND model_name='LG-50');

