CREATE DATABASE IF NOT EXISTS lab01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab01;

CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name       VARCHAR(80)  NOT NULL,
    email           VARCHAR(255) NOT NULL,
    telephone       CHAR(9)      NOT NULL,
    date_of_birth   DATE         NOT NULL,
    programme       VARCHAR(60)  NOT NULL,
    level           VARCHAR(20)  NOT NULL,
    gender          ENUM('Female','Male') NULL,
    policy_accepted TINYINT(1)   NOT NULL,
    submitted_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB;

-- Dedicated least-privilege user (change the password)
-- CREATE USER 'lab01_user'@'localhost' IDENTIFIED BY 'ChangeMe!123';
-- GRANT SELECT, INSERT ON lab01.students TO 'lab01_user'@'localhost';
