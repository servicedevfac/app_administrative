-- ============================================================================
-- BASE DE DONNÉES MYSQL : GESTION+ ENTERPRISE
-- Système interne de gestion des stocks, demandes et documents administratifs
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `gestion_materiel` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_materiel`;

-- 1. Table des Utilisateurs (Administrateurs & Collaborateurs)
DROP TABLE IF EXISTS `request_items`;
DROP TABLE IF EXISTS `administrative_documents`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `material_requests`;
DROP TABLE IF EXISTS `articles`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'collaborator') NOT NULL DEFAULT 'collaborator',
  `service` VARCHAR(100) DEFAULT 'Ressources Humaines',
  `matricule` VARCHAR(50) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table des Articles & Équipements en Stock (Parc Interne)
CREATE TABLE `articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'Fournitures',
  `unit` VARCHAR(50) NOT NULL DEFAULT 'Pièce',
  `quantity` INT NOT NULL DEFAULT 0,
  `threshold` INT NOT NULL DEFAULT 5,
  `location` VARCHAR(150) DEFAULT NULL,
  `status` ENUM('En stock', 'Stock faible', 'Rupture') NOT NULL DEFAULT 'En stock',
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table des Demandes d'Emprunt & Réquisitions Internes
CREATE TABLE `material_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type_operation` ENUM('Emprunt temporaire', 'Dotation permanente') NOT NULL DEFAULT 'Emprunt temporaire',
  `motif` VARCHAR(255) NOT NULL,
  `service` VARCHAR(100) NOT NULL,
  `date_needed` DATE DEFAULT NULL,
  `date_return` DATE DEFAULT NULL,
  `urgency` ENUM('Normale', 'Urgente', 'Planifiée') NOT NULL DEFAULT 'Normale',
  `status` ENUM('En attente', 'Approuvée', 'Rejetée', 'Restituée') NOT NULL DEFAULT 'En attente',
  `notes` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `delivered_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table des Lignes d'Articles par Demande
CREATE TABLE `request_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `material_request_id` BIGINT UNSIGNED NOT NULL,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `quantity_requested` INT NOT NULL DEFAULT 1,
  `quantity_approved` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_request_id`) REFERENCES `material_requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table des Mouvements de Stock (Traçabilité Entrées / Sorties)
CREATE TABLE `stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('Entrée', 'Sortie') NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table des Documents Administratifs & Bons de Décharge Officiels
CREATE TABLE `administrative_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `material_request_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'Documents internes',
  `type` ENUM('PDF', 'Word', 'Excel') NOT NULL DEFAULT 'PDF',
  `service` VARCHAR(100) NOT NULL DEFAULT 'Direction',
  `file_path` VARCHAR(255) DEFAULT NULL,
  `file_size` VARCHAR(50) DEFAULT '140 Ko',
  `reference` VARCHAR(50) DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_request_id`) REFERENCES `material_requests` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STRUCTURE DES TABLES PRÊTE POUR PRODUCTION
-- Aucun enregistrement fictif inséré.
-- Créez votre compte administrateur et vos utilisateurs via l'application.
-- ============================================================================

