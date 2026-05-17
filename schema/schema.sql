DROP DATABASE minijuegos;
CREATE DATABASE minijuegos;
USE minijuegos;

CREATE TABLE usuarios (
  id_usuario   INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  email        VARCHAR(150) UNIQUE NOT NULL,
  activo       TINYINT(1) DEFAULT 1
);

CREATE TABLE puntajes (
  id_puntaje      INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario      INT NOT NULL,
  puntaje         INT NOT NULL,
  fecha_registro  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

INSERT INTO usuarios (nombre, email) VALUES
  ('Camilo',   'camilo@demo.com'),
  ('Lina',  'lina@demo.com'),
  ('Mario', 'mario@demo.com'),
  ('Nicol',    'nicol@demo.com');
  
  SELECT*FROM usuarios;
  SELECT*FROM puntajes;