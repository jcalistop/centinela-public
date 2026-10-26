/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `adp_agreement_period_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_agreement_period_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `weighted_global_percent` decimal(8,4) DEFAULT NULL,
  `n_indicators` smallint unsigned NOT NULL DEFAULT '0',
  `n_calculated` smallint unsigned NOT NULL DEFAULT '0',
  `n_failed` smallint unsigned NOT NULL DEFAULT '0',
  `details_json` json DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `calculated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_prd_res_prd` (`adp_agreement_period_id`),
  KEY `adp_prd_res_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_agreement_period_results_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_agreement_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_agreement_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_id` bigint unsigned NOT NULL,
  `sequence` tinyint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adp_agrmnt_prd_seq_uq` (`adp_agreement_id`,`sequence`),
  KEY `adp_agrmnt_prd_agr_idx` (`adp_agreement_id`),
  CONSTRAINT `adp_agreement_periods_adp_agreement_id_foreign` FOREIGN KEY (`adp_agreement_id`) REFERENCES `adp_agreements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_agreements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_position_assignment_id` bigint unsigned NOT NULL,
  `duration_years` tinyint unsigned NOT NULL,
  `agreement_start` date NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_agrmnts_hie_idx` (`cen_hie_dependency_id`),
  KEY `adp_agrmnts_asgmt_idx` (`adp_position_assignment_id`),
  CONSTRAINT `adp_agreements_adp_position_assignment_id_foreign` FOREIGN KEY (`adp_position_assignment_id`) REFERENCES `adp_position_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_final_result_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_final_result_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_final_result_id` bigint unsigned NOT NULL,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `preliminary_compliance_percent` decimal(8,4) DEFAULT NULL,
  `final_compliance_percent` decimal(8,4) DEFAULT NULL,
  `raw_result` decimal(15,6) DEFAULT NULL,
  `is_overridden` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_final_row_res_ind` (`adp_final_result_id`,`adp_indicator_id`),
  KEY `adp_final_result_rows_adp_indicator_id_foreign` (`adp_indicator_id`),
  CONSTRAINT `adp_final_result_rows_adp_final_result_id_foreign` FOREIGN KEY (`adp_final_result_id`) REFERENCES `adp_final_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_final_result_rows_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_final_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_final_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `adp_preliminary_snapshot_id` bigint unsigned DEFAULT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `weighted_global_percent` decimal(8,4) DEFAULT NULL,
  `published_without_preliminary` tinyint(1) NOT NULL DEFAULT '0',
  `published_without_preliminary_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `published_at` datetime DEFAULT NULL,
  `published_by_user_id` bigint unsigned DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_final_res_prd_ver` (`adp_agreement_period_id`,`version`),
  KEY `adp_final_results_adp_preliminary_snapshot_id_foreign` (`adp_preliminary_snapshot_id`),
  KEY `adp_final_results_published_by_user_id_foreign` (`published_by_user_id`),
  KEY `adp_final_res_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_final_results_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_final_results_adp_preliminary_snapshot_id_foreign` FOREIGN KEY (`adp_preliminary_snapshot_id`) REFERENCES `adp_preliminary_snapshots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `adp_final_results_published_by_user_id_foreign` FOREIGN KEY (`published_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_appeals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `adp_preliminary_snapshot_id` bigint unsigned NOT NULL,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `status` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolucion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_indicator_appeals_adp_preliminary_snapshot_id_foreign` (`adp_preliminary_snapshot_id`),
  KEY `adp_indicator_appeals_adp_indicator_id_foreign` (`adp_indicator_id`),
  KEY `adp_indicator_appeals_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `adp_appeals_prd_ind_status_idx` (`adp_agreement_period_id`,`adp_indicator_id`,`status`),
  KEY `adp_appeals_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_indicator_appeals_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_indicator_appeals_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_indicator_appeals_adp_preliminary_snapshot_id_foreign` FOREIGN KEY (`adp_preliminary_snapshot_id`) REFERENCES `adp_preliminary_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_indicator_appeals_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_catalog_formulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_catalog_formulas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_catalog_item_id` bigint unsigned NOT NULL,
  `expression` json NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_cat_formula_item` (`adp_indicator_catalog_item_id`),
  CONSTRAINT `fk_adp_cat_formula_item` FOREIGN KEY (`adp_indicator_catalog_item_id`) REFERENCES `adp_indicator_catalog_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_catalog_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_catalog_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objetivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_anual_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_valor` decimal(10,4) DEFAULT NULL,
  `meta_operador` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_reglas` json DEFAULT NULL,
  `umbral_inferior` decimal(5,2) DEFAULT NULL,
  `umbral_superior` decimal(5,2) DEFAULT NULL,
  `formula_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fuente_numerador` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fuente_denominador` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `periodicidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ANUAL',
  `nivel_aplicacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unidad_medida` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimales` tinyint unsigned NOT NULL DEFAULT '2',
  `decimales_modo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculation_method` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `orden` smallint unsigned DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `sensitivity_config` json DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_cat_hie_clave` (`cen_hie_dependency_id`,`clave`),
  KEY `adp_indicator_catalog_items_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `adp_cat_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_indicator_catalog_items_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_formulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_formulas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `expression` json NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_formula_ind` (`adp_indicator_id`),
  CONSTRAINT `adp_indicator_formulas_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `raw_result` decimal(15,6) DEFAULT NULL,
  `compliance_percent` decimal(8,4) DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `calculated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_ind_res_ind_prd` (`adp_indicator_id`,`adp_agreement_period_id`),
  KEY `adp_indicator_results_adp_agreement_period_id_foreign` (`adp_agreement_period_id`),
  KEY `adp_ind_res_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_indicator_results_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_indicator_results_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicator_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicator_targets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `weight_percent` decimal(5,2) DEFAULT NULL,
  `target_value` decimal(10,4) DEFAULT NULL,
  `target_operador` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_tgt_ind` (`adp_indicator_id`),
  CONSTRAINT `adp_indicator_targets_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_indicators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_objective_id` bigint unsigned NOT NULL,
  `adp_indicator_catalog_item_id` bigint unsigned DEFAULT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objetivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_anual_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_valor` decimal(10,4) DEFAULT NULL,
  `meta_operador` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_reglas` json DEFAULT NULL,
  `umbral_inferior` decimal(5,2) DEFAULT NULL,
  `umbral_superior` decimal(5,2) DEFAULT NULL,
  `formula_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fuente_numerador` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fuente_denominador` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `periodicidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ANUAL',
  `nivel_aplicacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unidad_medida` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimales` tinyint unsigned NOT NULL DEFAULT '2',
  `decimales_modo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculation_method` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `orden` smallint unsigned DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adp_ind_obj_clave_uq` (`adp_objective_id`,`clave`),
  KEY `adp_ind_obj_idx` (`adp_objective_id`),
  KEY `fk_adp_ind_cat_item` (`adp_indicator_catalog_item_id`),
  CONSTRAINT `adp_indicators_adp_objective_id_foreign` FOREIGN KEY (`adp_objective_id`) REFERENCES `adp_objectives` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adp_ind_cat_item` FOREIGN KEY (`adp_indicator_catalog_item_id`) REFERENCES `adp_indicator_catalog_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_midterm_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_midterm_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `evaluated_on` date NOT NULL,
  `status` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `results_snapshot` json DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_midterm_prd` (`adp_agreement_period_id`),
  KEY `adp_midterm_evaluations_created_by_foreign` (`created_by`),
  KEY `adp_midterm_evaluations_updated_by_foreign` (`updated_by`),
  KEY `adp_mid_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_midterm_evaluations_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_midterm_evaluations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `adp_midterm_evaluations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_monthly_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_monthly_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `month_date` date NOT NULL,
  `valor` decimal(15,4) DEFAULT NULL,
  `observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cen_center_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_mo_ind_prd_mo` (`adp_indicator_id`,`adp_agreement_period_id`,`month_date`),
  KEY `adp_monthly_data_cen_center_id_foreign` (`cen_center_id`),
  KEY `adp_monthly_data_created_by_foreign` (`created_by`),
  KEY `adp_monthly_data_updated_by_foreign` (`updated_by`),
  KEY `adp_mo_hie_idx` (`cen_hie_dependency_id`),
  KEY `adp_mo_prd_idx` (`adp_agreement_period_id`),
  CONSTRAINT `adp_monthly_data_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_monthly_data_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_monthly_data_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `adp_monthly_data_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `adp_monthly_data_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_objectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_objectives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `orden` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_obj_prd_idx` (`adp_agreement_period_id`),
  CONSTRAINT `adp_objectives_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_position_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_position_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `adp_position_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_position_assignments_adp_position_id_foreign` (`adp_position_id`),
  KEY `adp_position_assignments_cen_center_id_foreign` (`cen_center_id`),
  KEY `adp_pos_asg_dep_idx` (`cen_hie_dependency_id`),
  KEY `adp_pos_asg_usr_ctr_pos_idx` (`user_id`,`cen_center_id`,`adp_position_id`),
  CONSTRAINT `adp_position_assignments_adp_position_id_foreign` FOREIGN KEY (`adp_position_id`) REFERENCES `adp_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_position_assignments_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_position_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adp_positions_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_preliminary_snapshot_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_preliminary_snapshot_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_preliminary_snapshot_id` bigint unsigned NOT NULL,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `raw_result` decimal(15,6) DEFAULT NULL,
  `compliance_percent` decimal(8,4) DEFAULT NULL,
  `weight_percent` decimal(8,4) DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_prelim_snap_row_snap_ind` (`adp_preliminary_snapshot_id`,`adp_indicator_id`),
  KEY `fk_adp_prelim_row_ind` (`adp_indicator_id`),
  CONSTRAINT `fk_adp_prelim_row_ind` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adp_prelim_row_snap` FOREIGN KEY (`adp_preliminary_snapshot_id`) REFERENCES `adp_preliminary_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_preliminary_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_preliminary_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_agreement_period_id` bigint unsigned NOT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `weighted_global_percent` decimal(8,4) DEFAULT NULL,
  `n_indicators` int unsigned DEFAULT NULL,
  `n_calculated` int unsigned DEFAULT NULL,
  `n_failed` int unsigned DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_prelim_snap_prd_ver` (`adp_agreement_period_id`,`version`),
  KEY `adp_preliminary_snapshots_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `adp_prelim_snap_hie_idx` (`cen_hie_dependency_id`),
  CONSTRAINT `adp_preliminary_snapshots_adp_agreement_period_id_foreign` FOREIGN KEY (`adp_agreement_period_id`) REFERENCES `adp_agreement_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_preliminary_snapshots_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_result_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_result_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_final_result_id` bigint unsigned NOT NULL,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `field_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final_compliance_percent',
  `previous_value` decimal(15,6) DEFAULT NULL,
  `new_value` decimal(15,6) DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_result_overrides_adp_indicator_id_foreign` (`adp_indicator_id`),
  KEY `adp_result_overrides_user_id_foreign` (`user_id`),
  KEY `adp_overrides_res_ind_idx` (`adp_final_result_id`,`adp_indicator_id`),
  CONSTRAINT `adp_result_overrides_adp_final_result_id_foreign` FOREIGN KEY (`adp_final_result_id`) REFERENCES `adp_final_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_result_overrides_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adp_result_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_sensitivity_table_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_sensitivity_table_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_sensitivity_table_id` bigint unsigned NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '1',
  `valor_minimo` decimal(12,4) DEFAULT NULL,
  `operador_minimo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '>=',
  `valor_maximo` decimal(12,4) DEFAULT NULL,
  `operador_maximo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '<',
  `cumplimiento` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adp_sens_row_tbl_ord` (`adp_sensitivity_table_id`,`orden`),
  CONSTRAINT `adp_sensitivity_table_rows_adp_sensitivity_table_id_foreign` FOREIGN KEY (`adp_sensitivity_table_id`) REFERENCES `adp_sensitivity_tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adp_sensitivity_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adp_sensitivity_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adp_indicator_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adp_sens_ind` (`adp_indicator_id`),
  CONSTRAINT `adp_sensitivity_tables_adp_indicator_id_foreign` FOREIGN KEY (`adp_indicator_id`) REFERENCES `adp_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `auditable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `auditable_id` bigint unsigned NOT NULL,
  `old_values` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `new_values` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_agent` varchar(1023) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `cen_commune_id` bigint unsigned NOT NULL,
  `via` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `numero` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `telefono` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `latitud` decimal(11,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_addresses_cen_center_id_foreign` (`cen_center_id`),
  KEY `cen_addresses_cen_commune_id_foreign` (`cen_commune_id`),
  CONSTRAINT `cen_addresses_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `cen_addresses_cen_commune_id_foreign` FOREIGN KEY (`cen_commune_id`) REFERENCES `cen_communes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_catalog_glosa_equivalences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_catalog_glosa_equivalences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_glosa` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_glosa_normalized` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_kind` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alias',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `reviewed_by_user_id` bigint unsigned DEFAULT NULL,
  `source_sync_run_issue_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cen_catalog_glosa_equiv_field_source_uq` (`field_key`,`source_glosa_normalized`),
  KEY `cen_catalog_glosa_equivalences_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `cen_catalog_glosa_equivalences_source_sync_run_issue_id_foreign` (`source_sync_run_issue_id`),
  KEY `cen_catalog_glosa_equivalences_field_key_active_index` (`field_key`,`active`),
  CONSTRAINT `cen_catalog_glosa_equivalences_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cen_catalog_glosa_equivalences_source_sync_run_issue_id_foreign` FOREIGN KEY (`source_sync_run_issue_id`) REFERENCES `cen_center_sync_run_issues` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_center_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_center_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note_kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_center_notes_cen_center_id_foreign` (`cen_center_id`),
  KEY `cen_center_notes_user_id_foreign` (`user_id`),
  CONSTRAINT `cen_center_notes_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cen_center_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_center_sync_run_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_center_sync_run_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_sync_run_id` bigint unsigned NOT NULL,
  `establecimiento_codigo` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_display` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_center_sync_run_changes_cen_center_sync_run_id_action_index` (`cen_center_sync_run_id`,`action`),
  KEY `cen_center_sync_run_changes_establecimiento_codigo_index` (`establecimiento_codigo`),
  CONSTRAINT `cen_center_sync_run_changes_cen_center_sync_run_id_foreign` FOREIGN KEY (`cen_center_sync_run_id`) REFERENCES `cen_center_sync_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_center_sync_run_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_center_sync_run_issues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_sync_run_id` bigint unsigned NOT NULL,
  `establecimiento_codigo` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `promoted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_center_sync_run_issues_cen_center_sync_run_id_foreign` (`cen_center_sync_run_id`),
  CONSTRAINT `cen_center_sync_run_issues_cen_center_sync_run_id_foreign` FOREIGN KEY (`cen_center_sync_run_id`) REFERENCES `cen_center_sync_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_center_sync_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_center_sync_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ss_codigo` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triggered_by_user_id` bigint unsigned DEFAULT NULL,
  `created_count` int unsigned NOT NULL DEFAULT '0',
  `updated_count` int unsigned NOT NULL DEFAULT '0',
  `soft_deleted_count` int unsigned NOT NULL DEFAULT '0',
  `restored_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_count` int unsigned NOT NULL DEFAULT '0',
  `warning_count` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_center_sync_runs_triggered_by_user_id_foreign` (`triggered_by_user_id`),
  CONSTRAINT `cen_center_sync_runs_triggered_by_user_id_foreign` FOREIGN KEY (`triggered_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_centers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `origen` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api',
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `cod_antiguo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `codigo_madre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cod_madre_antiguo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `pertenencia_snss` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre_oficial` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre_corto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nivel_atencion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tiene_urgencia` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tipo_sapu` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `estado_funcionamiento` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nivel_complejidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tipo_atencion` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `tarea_pendiente` tinyint(1) NOT NULL DEFAULT '0',
  `cen_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cen_scope` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cen_certification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cen_adm_dependency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cen_emergency_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cen_provider_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cen_centers_codigo_unique` (`codigo`),
  KEY `cen_centers_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  CONSTRAINT `cen_centers_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_communes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_communes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `cen_province_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cen_communes_cen_province_id_foreign` (`cen_province_id`),
  CONSTRAINT `cen_communes_cen_province_id_foreign` FOREIGN KEY (`cen_province_id`) REFERENCES `cen_provinces` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_hie_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_hie_dependencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tipo_dependencia` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_hie_dependencies_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_hie_dependencies_regions` (
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `cen_region_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `cen_hie_dependencies_regions_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `cen_hie_dependencies_regions_cen_region_id_foreign` (`cen_region_id`),
  CONSTRAINT `cen_hie_dependencies_regions_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `cen_hie_dependencies_regions_cen_region_id_foreign` FOREIGN KEY (`cen_region_id`) REFERENCES `cen_regions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_provinces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_provinces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `cen_region_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cen_provinces_codigo_unique` (`codigo`),
  KEY `cen_provinces_cen_region_id_foreign` (`cen_region_id`),
  CONSTRAINT `cen_provinces_cen_region_id_foreign` FOREIGN KEY (`cen_region_id`) REFERENCES `cen_regions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cen_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cen_regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cen_regions_codigo_unique` (`codigo`),
  UNIQUE KEY `cen_regions_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_indicator_cutoff_id` bigint NOT NULL,
  `numero` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `meta_texto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `tipo_medio` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `medio_verificacion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `formula` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `fuente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `ponderacion_accion` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_actions_cutoff_id` (`com_indicator_cutoff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_commitments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_commitments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_type_id` bigint unsigned NOT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `orden` int NOT NULL DEFAULT '0',
  `year_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_commitments_year_type` (`year_id`,`indicator_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_action_id` bigint NOT NULL,
  `cen_hie_dependency_id` bigint NOT NULL,
  `numerador` decimal(15,2) NOT NULL,
  `denominador` decimal(15,2) NOT NULL,
  `resultado` decimal(6,2) NOT NULL,
  `meta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cumplimiento` int NOT NULL,
  `observaciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `tipo_evaluacion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_eval_action_id` (`com_action_id`),
  KEY `idx_com_eval_dep_id` (`cen_hie_dependency_id`),
  KEY `idx_com_eval_compound` (`com_action_id`,`cen_hie_dependency_id`,`tipo_evaluacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_indicator_cutoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_indicator_cutoffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_indicator_id` bigint NOT NULL,
  `cutoff_id` bigint NOT NULL,
  `ponderacion_corte` decimal(6,3) NOT NULL,
  `promedio_pais` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `evaluacion_local` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_com_ind_cutoffs_indicator_id` (`com_indicator_id`),
  KEY `idx_com_ind_cutoffs_cutoff_id` (`cutoff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_indicator_setting_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_indicator_setting_user` (
  `com_indicator_setting_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  UNIQUE KEY `uq_com_ind_set_user` (`com_indicator_setting_id`,`user_id`),
  KEY `idx_com_ind_set_user_setting` (`com_indicator_setting_id`),
  KEY `idx_com_ind_set_user_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_indicator_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_indicator_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_hie_dependency_id` bigint NOT NULL,
  `com_indicator_id` bigint NOT NULL,
  `fecha_apertura` date DEFAULT NULL,
  `fecha_cierre` date DEFAULT NULL,
  `mantener_abierto` tinyint DEFAULT NULL,
  `pestanas_editables` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cen_hie_dependency_id_com_indicator_id` (`cen_hie_dependency_id`,`com_indicator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_indicators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_commitment_id` bigint unsigned NOT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ponderacion_indicador` decimal(5,4) NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `com_indicators_com_commitment_id_foreign` (`com_commitment_id`),
  KEY `com_indicators_com_commitment_id_orden_index` (`com_commitment_id`,`orden`),
  CONSTRAINT `com_indicators_com_commitment_id_foreign` FOREIGN KEY (`com_commitment_id`) REFERENCES `com_commitments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_sensitivity_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_sensitivity_dependencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_sensitivity_table_id` bigint unsigned NOT NULL,
  `com_action_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `com_sensitivity_dependencies_com_sensitivity_table_id_foreign` (`com_sensitivity_table_id`),
  KEY `com_sensitivity_dependencies_com_action_id_foreign` (`com_action_id`),
  KEY `com_sensitivity_dependencies_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  CONSTRAINT `com_sensitivity_dependencies_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `com_sensitivity_dependencies_com_action_id_foreign` FOREIGN KEY (`com_action_id`) REFERENCES `com_actions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `com_sensitivity_dependencies_com_sensitivity_table_id_foreign` FOREIGN KEY (`com_sensitivity_table_id`) REFERENCES `com_sensitivity_tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_sensitivity_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_sensitivity_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_action_id` bigint unsigned NOT NULL,
  `meta_numero` decimal(5,2) NOT NULL,
  `valores` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `com_sensitivity_tables_com_action_id_foreign` (`com_action_id`),
  CONSTRAINT `com_sensitivity_tables_com_action_id_foreign` FOREIGN KEY (`com_action_id`) REFERENCES `com_actions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_verifiable_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_verifiable_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_action_id` bigint NOT NULL,
  `cen_hie_dependency_id` bigint NOT NULL,
  `archivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `descripcion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `verifiable_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'EVALUACION',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_verif_docs_action_id` (`com_action_id`),
  KEY `idx_com_verif_docs_dep_id` (`cen_hie_dependency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `com_visualizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `com_visualizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `com_indicator_cutoff_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `habilitado` tinyint NOT NULL DEFAULT '0',
  `titulo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `texto_enriquecido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `codigo_incrustado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `tipo_grafico` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `muestra_tabla` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_vis_cutoff_id` (`com_indicator_cutoff_id`),
  KEY `idx_com_vis_dep_id` (`cen_hie_dependency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cutoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cutoffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tipo_corte` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `meses` json NOT NULL,
  `nombre_corte` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `orden` int NOT NULL DEFAULT '0',
  `habilitado` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_rows` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `data_type_id` int unsigned NOT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `browse` tinyint(1) NOT NULL DEFAULT '1',
  `read` tinyint(1) NOT NULL DEFAULT '1',
  `edit` tinyint(1) NOT NULL DEFAULT '1',
  `add` tinyint(1) NOT NULL DEFAULT '1',
  `delete` tinyint(1) NOT NULL DEFAULT '1',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `order` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `data_rows_data_type_id_foreign` (`data_type_id`),
  CONSTRAINT `data_rows_data_type_id_foreign` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `display_name_singular` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `display_name_plural` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `model_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `policy_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `controller` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `generate_permissions` tinyint(1) NOT NULL DEFAULT '0',
  `server_side` tinyint NOT NULL DEFAULT '0',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_types_name_unique` (`name`),
  UNIQUE KEY `data_types_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deploy_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deploy_executions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `from_sha` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_sha` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `backup_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migrate_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rollback_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deploy_executions_status_mode_index` (`status`,`mode`),
  KEY `deploy_executions_started_at_index` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descripcion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documents_year_dep` (`year_id`,`cen_hie_dependency_id`),
  KEY `idx_documents_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `github_release_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `github_release_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `github_release_id` bigint unsigned DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `github_release_snapshots_tag_name_unique` (`tag_name`),
  KEY `github_release_snapshots_published_at_index` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicator_id` bigint unsigned NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `indicator_activities_user_id_foreign` (`user_id`),
  KEY `indicator_activities_indicator_type_indicator_id_index` (`indicator_type`,`indicator_id`),
  KEY `indicator_activities_action_index` (`action`),
  KEY `indicator_activities_created_at_index` (`created_at`),
  KEY `indicator_activities_indicator_type_indicator_id_is_pinned_index` (`indicator_type`,`indicator_id`,`is_pinned`),
  CONSTRAINT `indicator_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_ambito_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_ambito_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_ambito_id` bigint unsigned NOT NULL,
  `indicatable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicatable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ind_ambito_assign_uq` (`indicator_ambito_id`,`indicatable_type`,`indicatable_id`),
  KEY `ind_ambito_morph_idx` (`indicatable_type`,`indicatable_id`),
  CONSTRAINT `indicator_ambito_assignments_indicator_ambito_id_foreign` FOREIGN KEY (`indicator_ambito_id`) REFERENCES `indicator_ambitos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_ambitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_ambitos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicator_ambitos_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_type_global_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_type_global_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_type_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `min_indicadores` tinyint unsigned NOT NULL DEFAULT '4' COMMENT 'Mínimo de indicadores seleccionados por establecimiento',
  `min_ponderacion` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Ponderación mínima permitida por indicador (inclusive)',
  `max_ponderacion` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT 'Ponderación máxima permitida por indicador (inclusive)',
  `semaforo_verde_min` decimal(5,2) DEFAULT NULL COMMENT 'Umbral verde del semáforo global (%). NULL = herencia por route',
  `semaforo_amarillo_min` decimal(5,2) DEFAULT NULL COMMENT 'Umbral amarillo del semáforo global (%). NULL = herencia por route',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_itgs_type_year` (`indicator_type_id`,`year_id`),
  KEY `indicator_type_global_settings_year_id_foreign` (`year_id`),
  CONSTRAINT `indicator_type_global_settings_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`),
  CONSTRAINT `indicator_type_global_settings_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_type_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_type_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT '0',
  `panel_minsal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `etapa_vigente` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'CERRADO',
  `tipo_evaluacion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'NO DEFINIDO',
  `texto_enriquecido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_its_compound` (`year_id`,`cen_hie_dependency_id`,`indicator_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clave_indicador` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre_indicador` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `descripcion_indicador` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `icon_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `route` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `view` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '0',
  `orden` int DEFAULT NULL,
  `measurement_granularity` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'establishment',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicator_types_clave_indicador_unique` (`clave_indicador`),
  UNIQUE KEY `indicator_types_nombre_indicador_unique` (`nombre_indicador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '_self',
  `icon_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `order` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `route` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `parameters` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  KEY `menu_items_menu_id_foreign` (`menu_id`),
  CONSTRAINT `menu_items_menu_id_foreign` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_visualizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_visualizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `menu_superior` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `nombre_menu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `titulo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `texto_enriquecido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `icon_class` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `route` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `estado` tinyint NOT NULL,
  `orden` tinyint NOT NULL,
  `nombre_orden` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_vis_dep_year` (`cen_hie_dependency_id`,`year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menus_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_center_adosados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_center_adosados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_madre_id` bigint unsigned NOT NULL,
  `cen_center_adosado_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_center_adosado_per_instrument` (`cen_center_adosado_id`,`year_id`,`cen_hie_dependency_id`,`indicator_type_id`),
  KEY `met_ctr_ados_madre_idx` (`cen_center_madre_id`,`year_id`),
  KEY `met_center_adosados_indicator_type_id_foreign` (`indicator_type_id`),
  KEY `met_ctr_ados_scope_idx` (`cen_center_madre_id`,`year_id`,`indicator_type_id`),
  KEY `met_center_adosados_year_id_foreign` (`year_id`),
  KEY `met_center_adosados_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  CONSTRAINT `met_center_adosados_cen_center_adosado_id_foreign` FOREIGN KEY (`cen_center_adosado_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_center_adosados_cen_center_madre_id_foreign` FOREIGN KEY (`cen_center_madre_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_center_adosados_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_center_adosados_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`),
  CONSTRAINT `met_center_adosados_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_center_inclusions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_center_inclusions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_center_inclusion_per_instrument` (`cen_center_id`,`year_id`,`indicator_type_id`),
  KEY `met_center_inclusions_indicator_type_id_foreign` (`indicator_type_id`),
  KEY `met_ctr_incl_scope_idx` (`year_id`,`cen_hie_dependency_id`,`indicator_type_id`),
  KEY `met_center_inclusions_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  CONSTRAINT `met_center_inclusions_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_center_inclusions_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_center_inclusions_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`),
  CONSTRAINT `met_center_inclusions_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_data_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_data_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variable_key` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `componente` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `aggregation_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sumatoria',
  `fuente` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_field_key` (`met_indicator_id`,`variable_key`),
  CONSTRAINT `met_data_fields_indicator_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_establishment_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_establishment_agreements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `estado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `submitted_at` datetime DEFAULT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by` bigint unsigned DEFAULT NULL,
  `reopen_motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_establishment_agreement` (`cen_center_id`,`year_id`,`indicator_type_id`),
  KEY `met_establishment_agreements_year_id_foreign` (`year_id`),
  KEY `met_establishment_agreements_indicator_type_id_foreign` (`indicator_type_id`),
  KEY `met_establishment_agreements_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_establishment_agreements_submitted_by_foreign` (`submitted_by`),
  KEY `met_establishment_agreements_reopened_by_foreign` (`reopened_by`),
  CONSTRAINT `met_establishment_agreements_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_establishment_agreements_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_establishment_agreements_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_establishment_agreements_reopened_by_foreign` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_establishment_agreements_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_establishment_agreements_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_establishment_responsibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_establishment_responsibles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_est_responsible_user_year_type` (`user_id`,`year_id`,`indicator_type_id`),
  KEY `met_establishment_appeal_designees_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_establishment_appeal_designees_assigned_by_foreign` (`assigned_by`),
  KEY `idx_est_responsible_center_year_type` (`cen_center_id`,`year_id`,`indicator_type_id`),
  KEY `met_establishment_responsibles_year_id_foreign` (`year_id`),
  KEY `met_establishment_responsibles_indicator_type_id_foreign` (`indicator_type_id`),
  CONSTRAINT `met_establishment_appeal_designees_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_establishment_appeal_designees_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_establishment_appeal_designees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `met_establishment_responsibles_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_establishment_responsibles_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_establishment_responsibles_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_establishment_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_establishment_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `indicator_type_id` bigint unsigned NOT NULL,
  `etapa` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AUTOEVALUACION',
  `periodo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultado_global` decimal(10,4) DEFAULT NULL,
  `n_seleccionados` smallint unsigned DEFAULT NULL,
  `n_calculados` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_estab_result_per_instrument_etapa` (`cen_center_id`,`year_id`,`periodo`,`indicator_type_id`,`etapa`),
  KEY `met_establishment_results_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_establishment_results_year_id_foreign` (`year_id`),
  KEY `met_establishment_results_indicator_type_id_foreign` (`indicator_type_id`),
  CONSTRAINT `met_establishment_results_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_establishment_results_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_establishment_results_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`),
  CONSTRAINT `met_establishment_results_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_field_ss_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_field_ss_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_data_field_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `input_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregation_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field_ss_year` (`met_data_field_id`,`cen_hie_dependency_id`,`year_id`),
  KEY `met_field_ss_configs_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_field_ss_configs_year_id_foreign` (`year_id`),
  CONSTRAINT `met_field_ss_configs_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_field_ss_configs_met_data_field_id_foreign` FOREIGN KEY (`met_data_field_id`) REFERENCES `met_data_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_field_ss_configs_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_final_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_final_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `numerador` decimal(15,4) DEFAULT NULL,
  `denominador` decimal(15,4) DEFAULT NULL,
  `apelacion_acogida` tinyint(1) DEFAULT NULL,
  `resolucion_notas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final_eval_indicator_center_year` (`met_indicator_id`,`cen_center_id`,`year_id`),
  KEY `met_final_evaluations_cen_center_id_foreign` (`cen_center_id`),
  KEY `met_final_evaluations_year_id_foreign` (`year_id`),
  KEY `met_final_evaluations_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_final_evaluations_created_by_foreign` (`created_by`),
  KEY `met_final_evaluations_updated_by_foreign` (`updated_by`),
  CONSTRAINT `met_final_evaluations_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_final_evaluations_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_final_evaluations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `met_final_evaluations_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_final_evaluations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_final_evaluations_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_formulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_formulas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `expression` json NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_formula_ind` (`met_indicator_id`),
  CONSTRAINT `met_formulas_indicator_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_indicator_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_indicator_appeals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `appeal_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivos` json DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indicator_appeal_indicator_center_year` (`met_indicator_id`,`cen_center_id`,`year_id`),
  KEY `met_indicator_appeals_year_id_foreign` (`year_id`),
  KEY `met_indicator_appeals_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_indicator_appeals_created_by_foreign` (`created_by`),
  KEY `met_indicator_appeals_updated_by_foreign` (`updated_by`),
  KEY `idx_indicator_appeal_center_year_dep` (`cen_center_id`,`year_id`,`cen_hie_dependency_id`),
  CONSTRAINT `met_indicator_appeals_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_indicator_appeals_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_indicator_appeals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `met_indicator_appeals_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_indicator_appeals_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_indicator_appeals_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_indicator_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_indicator_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned DEFAULT NULL,
  `met_measurement_unit_id` bigint unsigned DEFAULT NULL,
  `configuration_unit_scope_key` bigint unsigned NOT NULL DEFAULT '0',
  `aplica` tinyint(1) NOT NULL DEFAULT '1',
  `agrupa_ss` tinyint(1) NOT NULL DEFAULT '0',
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `seleccionado` tinyint(1) NOT NULL DEFAULT '0',
  `ponderacion` decimal(5,2) DEFAULT NULL,
  `meta_efectiva` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_ind_config_per_unit_scope` (`met_indicator_id`,`year_id`,`cen_center_id`,`configuration_unit_scope_key`),
  KEY `met_indicator_configurations_year_id_foreign` (`year_id`),
  KEY `met_indicator_configurations_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_indicator_configurations_cen_center_id_foreign` (`cen_center_id`),
  KEY `met_indicator_configurations_met_measurement_unit_id_foreign` (`met_measurement_unit_id`),
  CONSTRAINT `met_indicator_configurations_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_indicator_configurations_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_indicator_configurations_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_indicator_configurations_met_measurement_unit_id_foreign` FOREIGN KEY (`met_measurement_unit_id`) REFERENCES `met_measurement_units` (`id`),
  CONSTRAINT `met_indicator_configurations_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_indicator_referentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_indicator_referentes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_ind_ref` (`met_indicator_id`,`year_id`,`cen_hie_dependency_id`,`user_id`),
  KEY `met_indicator_referentes_year_id_foreign` (`year_id`),
  KEY `met_indicator_referentes_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_indicator_referentes_user_id_foreign` (`user_id`),
  CONSTRAINT `met_indicator_referentes_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_indicator_referentes_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_indicator_referentes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_indicator_referentes_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_indicators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_type_id` bigint unsigned NOT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objetivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_anual_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_valor` decimal(10,4) DEFAULT NULL,
  `meta_operador` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_reglas` json DEFAULT NULL,
  `umbral_inferior` decimal(5,2) NOT NULL DEFAULT '0.00',
  `umbral_superior` decimal(5,2) NOT NULL DEFAULT '100.00',
  `nivel_aplicacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `exclusiones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unidad_medida` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimales` tinyint unsigned NOT NULL DEFAULT '2',
  `decimales_modo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'redondear',
  `year_id` bigint unsigned NOT NULL,
  `orden` smallint unsigned DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `prioridad` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_prioridad',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `met_indicators_clave_unique` (`clave`),
  KEY `met_indicators_year_id_foreign` (`year_id`),
  KEY `met_indicators_indicator_type_id_foreign` (`indicator_type_id`),
  CONSTRAINT `met_indicators_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`),
  CONSTRAINT `met_indicators_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_measurement_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_measurement_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `met_unit_type_id` bigint unsigned NOT NULL,
  `codigo` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `cen_hie_dependency_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_measurement_units_center_year_codigo` (`cen_center_id`,`year_id`,`codigo`),
  KEY `met_measurement_units_year_id_foreign` (`year_id`),
  KEY `met_measurement_units_met_unit_type_id_foreign` (`met_unit_type_id`),
  KEY `met_measurement_units_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `idx_met_measurement_units_center_year_activo` (`cen_center_id`,`year_id`,`activo`),
  CONSTRAINT `met_measurement_units_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_measurement_units_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_measurement_units_met_unit_type_id_foreign` FOREIGN KEY (`met_unit_type_id`) REFERENCES `met_unit_types` (`id`),
  CONSTRAINT `met_measurement_units_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_monthly_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_monthly_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_data_field_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `met_measurement_unit_id` bigint unsigned DEFAULT NULL,
  `measurement_unit_scope_key` bigint unsigned NOT NULL DEFAULT '0',
  `mes` tinyint unsigned DEFAULT NULL,
  `valor` decimal(15,4) DEFAULT NULL,
  `observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_monthly_per_unit_scope` (`met_data_field_id`,`cen_center_id`,`year_id`,`mes`,`measurement_unit_scope_key`),
  KEY `met_monthly_data_cen_center_id_foreign` (`cen_center_id`),
  KEY `met_monthly_data_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_monthly_data_year_id_foreign` (`year_id`),
  KEY `met_monthly_data_created_by_foreign` (`created_by`),
  KEY `met_monthly_data_updated_by_foreign` (`updated_by`),
  KEY `met_monthly_data_met_measurement_unit_id_foreign` (`met_measurement_unit_id`),
  CONSTRAINT `met_monthly_data_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_monthly_data_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_monthly_data_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_monthly_data_met_data_field_id_foreign` FOREIGN KEY (`met_data_field_id`) REFERENCES `met_data_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_monthly_data_met_measurement_unit_id_foreign` FOREIGN KEY (`met_measurement_unit_id`) REFERENCES `met_measurement_units` (`id`),
  CONSTRAINT `met_monthly_data_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_monthly_data_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_preliminary_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_preliminary_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `numerador` decimal(15,4) DEFAULT NULL,
  `denominador` decimal(15,4) DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_preliminary_eval_indicator_center_year` (`met_indicator_id`,`cen_center_id`,`year_id`),
  KEY `met_preliminary_evaluations_cen_center_id_foreign` (`cen_center_id`),
  KEY `met_preliminary_evaluations_year_id_foreign` (`year_id`),
  KEY `met_preliminary_evaluations_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_preliminary_evaluations_created_by_foreign` (`created_by`),
  KEY `met_preliminary_evaluations_updated_by_foreign` (`updated_by`),
  CONSTRAINT `met_preliminary_evaluations_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_preliminary_evaluations_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_preliminary_evaluations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `met_preliminary_evaluations_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_preliminary_evaluations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_preliminary_evaluations_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `met_indicator_id` bigint unsigned NOT NULL,
  `cen_center_id` bigint unsigned NOT NULL,
  `met_measurement_unit_id` bigint unsigned DEFAULT NULL,
  `result_unit_scope_key` bigint unsigned NOT NULL DEFAULT '0',
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `etapa` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AUTOEVALUACION',
  `periodo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numerador` decimal(15,4) DEFAULT NULL,
  `denominador` decimal(15,4) DEFAULT NULL,
  `resultado` decimal(15,4) DEFAULT NULL,
  `cumple_meta` tinyint(1) DEFAULT NULL,
  `error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_met_result_per_unit_scope_etapa` (`met_indicator_id`,`cen_center_id`,`year_id`,`periodo`,`result_unit_scope_key`,`etapa`),
  KEY `met_results_cen_center_id_foreign` (`cen_center_id`),
  KEY `met_results_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_results_year_id_foreign` (`year_id`),
  KEY `met_results_met_measurement_unit_id_foreign` (`met_measurement_unit_id`),
  CONSTRAINT `met_results_cen_center_id_foreign` FOREIGN KEY (`cen_center_id`) REFERENCES `cen_centers` (`id`),
  CONSTRAINT `met_results_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `met_results_met_indicator_id_foreign` FOREIGN KEY (`met_indicator_id`) REFERENCES `met_indicators` (`id`),
  CONSTRAINT `met_results_met_measurement_unit_id_foreign` FOREIGN KEY (`met_measurement_unit_id`) REFERENCES `met_measurement_units` (`id`),
  CONSTRAINT `met_results_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_stage_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_stage_transitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `indicator_type_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `year_id` bigint unsigned NOT NULL,
  `etapa_desde` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_hasta` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `met_stage_transitions_cen_hie_dependency_id_foreign` (`cen_hie_dependency_id`),
  KEY `met_stage_transitions_year_id_foreign` (`year_id`),
  KEY `met_stage_transitions_user_id_foreign` (`user_id`),
  KEY `idx_met_stage_transitions_scope` (`indicator_type_id`,`cen_hie_dependency_id`,`year_id`,`created_at`),
  CONSTRAINT `met_stage_transitions_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_stage_transitions_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `met_stage_transitions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `met_stage_transitions_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `met_unit_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `met_unit_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `met_unit_types_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_permission_id_index` (`permission_id`),
  KEY `permission_role_role_id_index` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permissions_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_area_usage_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_area_usage_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned DEFAULT NULL,
  `area` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `laravel_session_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `paus_created_at_idx` (`created_at`),
  KEY `paus_ss_created_idx` (`cen_hie_dependency_id`,`created_at`),
  KEY `paus_area_created_idx` (`area`,`created_at`),
  KEY `paus_user_created_idx` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_auth_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_auth_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cen_hie_dependency_id` bigint unsigned DEFAULT NULL,
  `laravel_session_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ended_at` timestamp NULL DEFAULT NULL,
  `end_reason` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pas_user_started_idx` (`user_id`,`started_at`),
  KEY `pas_ss_started_idx` (`cen_hie_dependency_id`,`started_at`),
  KEY `pas_ended_at_idx` (`ended_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_module_toggles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_module_toggles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `module_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_smt_ss_module` (`cen_hie_dependency_id`,`module_key`),
  CONSTRAINT `service_module_toggles_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `order` int NOT NULL DEFAULT '1',
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_ticket_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned NOT NULL,
  `type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `ste_ticket_created` (`support_ticket_id`,`created_at`),
  KEY `ste_type_created` (`type`,`created_at`),
  KEY `ste_internal_created` (`is_internal`,`created_at`),
  CONSTRAINT `support_ticket_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_events_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_messages_user_id_foreign` (`user_id`),
  KEY `stm_ticket_created` (`support_ticket_id`,`created_at`),
  CONSTRAINT `support_ticket_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_ticket_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `last_read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_ticket_reads_support_ticket_id_user_id_unique` (`support_ticket_id`,`user_id`),
  KEY `support_ticket_reads_user_id_foreign` (`user_id`),
  CONSTRAINT `support_ticket_reads_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_ticket_receivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_receivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cen_hie_dependency_id` bigint unsigned DEFAULT NULL,
  `area` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_receivers_user_id_foreign` (`user_id`),
  KEY `str_scope_area_en` (`scope`,`area`,`enabled`),
  KEY `str_cen_area_en` (`cen_hie_dependency_id`,`area`,`enabled`),
  CONSTRAINT `support_ticket_receivers_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_receivers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment` json DEFAULT NULL,
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `area` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cen_hie_dependency_id` bigint unsigned NOT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `assigned_to_user_id` bigint unsigned DEFAULT NULL,
  `indicator_type_id` bigint unsigned DEFAULT NULL,
  `year_id` bigint unsigned DEFAULT NULL,
  `github_issue_number` bigint unsigned DEFAULT NULL,
  `github_issue_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_linked_at` timestamp NULL DEFAULT NULL,
  `github_linked_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `support_tickets_year_id_foreign` (`year_id`),
  KEY `support_tickets_github_linked_by_user_id_foreign` (`github_linked_by_user_id`),
  KEY `st_cen_dep_stat_created` (`cen_hie_dependency_id`,`status`,`created_at`),
  KEY `st_assign_stat_created` (`assigned_to_user_id`,`status`,`created_at`),
  KEY `st_ind_type_created` (`indicator_type_id`,`created_at`),
  CONSTRAINT `support_tickets_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_cen_hie_dependency_id_foreign` FOREIGN KEY (`cen_hie_dependency_id`) REFERENCES `cen_hie_dependencies` (`id`),
  CONSTRAINT `support_tickets_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `support_tickets_github_linked_by_user_id_foreign` FOREIGN KEY (`github_linked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_indicator_type_id_foreign` FOREIGN KEY (`indicator_type_id`) REFERENCES `indicator_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_year_id_foreign` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tab_visualizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tab_visualizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_visualization_id` bigint unsigned NOT NULL,
  `nombre_tab` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `habilitado` tinyint DEFAULT NULL,
  `texto_enriquecido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `codigo_incrustado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `data_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `tipo_grafico` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `muestra_tabla` tinyint NOT NULL DEFAULT '0',
  `orden` int NOT NULL DEFAULT '0',
  `nombre_orden` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tab_visualizations_menu_visualization_id_index` (`menu_visualization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `column_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `foreign_key` int unsigned NOT NULL,
  `locale` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `translations_table_name_column_name_foreign_key_locale_unique` (`table_name`,`column_name`,`foreign_key`,`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `user_roles_user_id_index` (`user_id`),
  KEY `user_roles_role_id_index` (`role_id`),
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'users/default.png',
  `unidad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `anexo` int DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `cen_hie_dependency_id` int NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `settings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `years` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL,
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2016_01_01_000000_add_voyager_user_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2016_01_01_000000_create_data_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2016_05_19_173453_create_menu_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2016_10_21_190000_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2016_11_30_135954_create_permission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2016_11_30_141208_create_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2016_12_26_201236_data_types__add__server_side',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2017_01_13_000000_add_route_to_menu_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2017_01_14_005015_create_translations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2017_01_15_000000_make_table_name_nullable_in_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2017_03_06_000000_add_controller_to_data_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2017_04_21_000000_add_order_to_data_rows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2017_07_05_210000_add_policyname_to_data_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2017_08_05_000000_add_group_to_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2017_11_26_013050_add_user_role_relationship',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2017_11_26_015000_create_user_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2018_03_11_000000_add_user_settings',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2018_03_14_000000_add_details_to_data_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2018_03_16_000000_make_settings_value_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2024_02_12_134003_create_audits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_02_14_111043_create_indicator_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_02_20_211505_create_cen_adm_dependencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_02_20_211505_create_cen_certifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_02_20_211505_create_cen_emergency_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_02_20_211505_create_cen_hie_dependencies_regions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_02_20_211505_create_cen_provider_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_02_20_211505_create_cen_scopes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2024_02_20_211505_create_cen_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2024_03_05_130446_create_years_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2024_03_06_083348_create_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2024_03_07_095656_create_com_commitments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2024_03_07_144602_create_com_indicators_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_03_07_211515_create_foreign_keys',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2024_03_08_113958_create_cutoffs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2024_03_08_114927_create_indicator_type_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2024_03_10_170539_create_com_actions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2024_03_16_194348_create_com_indicator_cutoffs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2024_03_16_201301_create_com_verifiable_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2024_03_16_213325_create_com_evaluations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2024_04_04_111406_create_com_indicator_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2024_04_04_113529_create_com_indicator_setting_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2024_04_04_155933_create_com_sensitivity_tables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2014_10_21_190000_create_roles_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2014_10_21_200000_create_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2023_08_21_000000_create_jobs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2023_08_21_000001_create_failed_jobs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2024_02_20_000007_create_cen_regions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2024_02_20_000008_create_cen_provinces_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2024_02_20_000009_create_cen_communes_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2024_02_20_000010_create_cen_centers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2024_02_20_000020_create_cen_addresses_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2024_02_20_000030_create_cen_hie_dependencies_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2024_04_28_230249_create_com_sensitivity_dependencies_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2024_08_22_000001_create_menu_visualizations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2024_08_22_000002_create_tab_visualizations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2024_08_22_000003_create_com_visualizations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_04_18_214154_create_met_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_04_18_214158_create_met_indicator_configurations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_04_18_214159_create_met_indicator_referentes_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_04_18_222121_create_met_data_fields_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_04_18_222121_create_met_formulas_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_04_18_230000_add_decimales_modo_to_met_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_04_18_233359_create_met_monthly_data_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_19_000100_create_met_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_19_000200_create_met_establishment_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_19_002753_add_indicator_type_id_to_met_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_19_212822_add_cen_center_id_to_met_indicator_configurations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_19_212822_create_met_center_inclusions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_20_150041_add_meta_efectiva_to_met_indicator_configurations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_20_161316_add_aggregation_type_to_met_data_fields_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_21_074449_create_met_center_adosados_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_04_21_084722_add_aplica_to_met_indicator_configurations',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_04_21_114858_add_agrupa_ss_to_met_indicator_configurations',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_04_22_113927_create_indicator_type_global_settings_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_04_22_120000_rename_role_to_componente_on_met_data_fields_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_04_22_124407_add_thresholds_to_met_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_04_22_195157_add_measurement_granularity_to_indicator_types_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_04_22_195158_add_indicator_type_id_to_met_establishment_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_04_22_220400_insert_indicator_type_metas_18834',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_04_22_220500_insert_indicator_type_metas_19664',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_04_23_100000_create_met_unit_types_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_04_23_100001_create_met_measurement_units_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_04_23_100002_add_measurement_unit_columns_to_met_core_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_04_23_100003_insert_indicator_type_metas_20707',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_04_23_120000_add_indicator_type_id_to_met_center_inclusions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_04_23_140000_update_indicator_types_bread_slug_and_controller',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_04_24_100000_null_view_on_indicator_types_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_04_24_120000_indicator_types_bread_browse_icon_view',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_04_24_130000_instrumentos_evaluacion_browse_granularity_spanish_hide_view',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_04_25_105143_create_adp_indicator_catalog_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_04_25_105152_create_adp_indicator_catalog_formulas_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_04_25_160000_create_adp_positions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_04_25_160100_create_adp_position_assignments_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_04_25_160200_insert_indicator_type_adp',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_04_25_231233_add_sensitivity_config_to_adp_indicator_catalog_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_04_26_170000_create_adp_agreements_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_04_26_170100_create_adp_agreement_periods_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_04_26_170200_create_adp_objectives_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_04_26_170300_create_adp_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_04_26_170400_create_adp_indicator_formulas_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_04_26_170500_create_adp_indicator_targets_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_04_27_100000_create_adp_monthly_data_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_04_27_100100_create_adp_midterm_evaluations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_04_27_180000_create_adp_sensitivity_tables_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_04_27_180100_create_adp_sensitivity_table_rows_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_04_27_180200_create_adp_indicator_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_04_27_180300_create_adp_agreement_period_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_04_27_222240_create_platform_area_usage_samples_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_04_27_222424_create_platform_auth_sessions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_04_28_100000_create_adp_preliminary_snapshots_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_04_28_100100_create_adp_preliminary_snapshot_rows_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_04_28_100200_create_adp_indicator_appeals_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_04_28_100300_create_adp_final_results_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_04_28_100400_create_adp_final_result_rows_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_04_28_100500_create_adp_result_overrides_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_04_28_120000_add_adp_indicator_catalog_item_id_to_adp_indicators_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_04_28_121600_add_centinela_deploy_permission',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_04_28_131700_add_centinela_ops_permission',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_04_30_120000_seed_guest_invitado_role_and_user',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_05_05_122111_create_support_ticket_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_05_05_151240_seed_voyager_support_menu_item',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_05_05_154449_add_browse_soporte_permission_and_role_grants',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_05_06_081130_create_support_ticket_events_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_05_06_150306_sync_support_soporte_role_grants_revoke_parametrizador',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_05_06_160000_set_users_bread_controller_centinela_voyager_user',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_05_08_100000_ensure_documents_voyager_data_type',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_05_08_154450_enable_documents_bread_server_side_search',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_05_11_101301_refactor_indicator_formula_and_sources',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_05_11_125534_create_met_field_ss_configs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_05_11_141747_drop_periodicidad_from_met_indicators_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_05_11_145205_drop_cen_catalog_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_05_11_145206_replace_mysql_enums_with_varchar',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_05_11_161354_fix_establecimientos_voyager_data_rows_after_cen_catalog_drop',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_05_15_100000_v420_env_settings_and_ss_habilitado_schema',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_05_15_100100_v420_servicios_de_salud_bread',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_05_15_100200_v420_service_module_toggles_bread',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_05_19_140000_update_direccion_servicio_cen_centers_nombre_corto',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_05_19_160000_create_met_stage_tables',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_05_19_160001_add_etapa_to_met_results_tables',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_05_19_160002_backfill_etapa_autoevaluacion_on_met_results',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_05_20_120000_migrate_etapa_vigente_anual_to_cerrado',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_05_20_234526_add_exclusiones_to_met_indicators_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_05_25_120000_add_national_establishments_catalog_support',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_05_25_120100_add_national_establishments_settings',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_05_25_142721_set_establecimientos_voyager_controller',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_05_25_154622_add_tarea_pendiente_to_cen_centers_and_note_kind_to_cen_center_notes',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_05_26_120000_add_establecimientos_admin_menu_above_users',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_05_26_140000_collapse_cen_center_closure_estados',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_05_26_140100_backfill_cen_centers_legacy_enum_glosas',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_05_26_160000_create_cen_catalog_glosa_equivalences_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_05_26_170000_add_promoted_at_to_cen_center_sync_run_issues',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_05_27_100000_create_cen_center_sync_run_changes_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_05_27_120000_add_context_to_cen_center_sync_run_changes',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_05_27_120000_set_users_bread_voyager_actor_manageable_scope',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_05_28_100000_v440_deploy_executions_settings_and_ss',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_05_28_120000_create_github_release_snapshots_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_05_29_100000_remove_guest_invitado_role_and_user',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_06_04_100000_v442_metas_recalc_settings',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_06_16_100000_create_indicator_ambitos_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_06_16_100100_create_indicator_ambito_assignments_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_06_16_100200_seed_indicator_ambitos_and_voyager_bread',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_06_16_100300_ensure_indicator_ambitos_voyager_permissions',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_06_16_100400_ensure_indicator_ambitos_voyager_menu_item',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_06_16_131851_create_indicator_activities_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_06_16_161251_add_is_pinned_to_indicator_activities_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_06_19_120000_add_indicator_type_id_to_met_center_adosados_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_07_01_100000_indicadores_comges_commitment_select_edit_view',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_07_01_120000_add_orden_to_com_indicators',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_07_03_091851_support_tickets_nullable_subcategory_and_attachment',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_07_06_120000_admin_voyager_compass_enabled_setting',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_07_08_100000_add_enabled_to_met_unit_types_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_07_08_100100_met_unit_types_voyager_bread',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_08_07_140409_dedupe_and_unique_com_indicator_setting_user',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_08_07_160612_add_deploy_require_ci_gate_settings',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_08_13_101517_add_semaforo_thresholds_to_indicator_type_global_settings',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_08_28_105156_rename_met_establishment_appeal_designees_to_responsibles',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_08_28_155959_extend_indicator_type_settings_etapa_vigente_sqlite',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_08_28_160000_create_met_stage_transitions_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_08_28_160001_migrate_metas_habilitado_cerrado_to_no_iniciado',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_08_29_130000_create_met_establishment_agreements_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_08_29_130001_seed_met_establishment_agreements_from_complete_configs',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_08_29_130002_add_submitted_at_to_met_indicator_appeals_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_08_31_220000_add_prioridad_to_met_indicators_table',16);
