USE Dt_registro;

ALTER TABLE usuarios
    ADD COLUMN rol VARCHAR(30) NOT NULL DEFAULT 'cliente',
    ADD COLUMN sucursal_id INT UNSIGNED NULL;

CREATE TABLE sucursales (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NOT NULL DEFAULT '',
    gerente_id INT UNSIGNED NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sucursales_nombre (nombre),
    CONSTRAINT fk_sucursales_gerente FOREIGN KEY (gerente_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL;

CREATE TABLE inventarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sucursal_id INT UNSIGNED NOT NULL,
    producto VARCHAR(160) NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_inventarios_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sucursal_id INT UNSIGNED NOT NULL,
    producto VARCHAR(160) NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    total DECIMAL(12,2) NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ventas_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- En una instalacion nueva, crea el administrador con una contrasena propia.
-- Genera un hash con: php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
-- Despues inserta el usuario usando ese hash:
-- INSERT INTO usuarios (nombre, email, password, rol)
-- VALUES ('Administrador General', 'admin@tudominio.com', 'PEGA_AQUI_EL_HASH', 'administrador_general');