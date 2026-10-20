<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `unicobpm`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration unicobpm: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'activity_log' => [
            'create' => 'CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `attribute_changes` json DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `risk_score` tinyint unsigned DEFAULT NULL,
  `risk_level` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retention_hold` tinyint(1) NOT NULL DEFAULT \'0\',
  `integrity_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `activity_log_risk_score_index` (`risk_score`),
  KEY `activity_log_risk_level_index` (`risk_level`),
  KEY `activity_log_retention_hold_index` (`retention_hold`),
  KEY `activity_log_integrity_hash_index` (`integrity_hash`),
  KEY `activity_log_log_created_index` (`log_name`,`created_at`),
  KEY `activity_log_event_created_index` (`event`,`created_at`),
  KEY `activity_log_subject_created_index` (`subject_type`,`subject_id`,`created_at`),
  KEY `activity_log_causer_created_index` (`causer_type`,`causer_id`,`created_at`),
  KEY `activity_log_risk_created_index` (`risk_level`,`created_at`),
  KEY `activity_log_request_created_index` (`request_id`,`created_at`),
  KEY `activity_log_ip_created_index` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'log_name' => '`log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `log_name`',
                'subject_type' => '`subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`',
                'subject_id' => '`subject_id` bigint unsigned DEFAULT NULL AFTER `subject_type`',
                'event' => '`event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `subject_id`',
                'causer_type' => '`causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `event`',
                'causer_id' => '`causer_id` bigint unsigned DEFAULT NULL AFTER `causer_type`',
                'attribute_changes' => '`attribute_changes` json DEFAULT NULL AFTER `causer_id`',
                'properties' => '`properties` json DEFAULT NULL AFTER `attribute_changes`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `properties`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'risk_score' => '`risk_score` tinyint unsigned DEFAULT NULL AFTER `updated_at`',
                'risk_level' => '`risk_level` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `risk_score`',
                'retention_hold' => '`retention_hold` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `risk_level`',
                'integrity_hash' => '`integrity_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `retention_hold`',
                'request_id' => '`request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `integrity_hash`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `request_id`',
            ],
            'indexes' => [
                'subject' => 'KEY `subject` (`subject_type`,`subject_id`)',
                'causer' => 'KEY `causer` (`causer_type`,`causer_id`)',
                'activity_log_log_name_index' => 'KEY `activity_log_log_name_index` (`log_name`)',
                'activity_log_risk_score_index' => 'KEY `activity_log_risk_score_index` (`risk_score`)',
                'activity_log_risk_level_index' => 'KEY `activity_log_risk_level_index` (`risk_level`)',
                'activity_log_retention_hold_index' => 'KEY `activity_log_retention_hold_index` (`retention_hold`)',
                'activity_log_integrity_hash_index' => 'KEY `activity_log_integrity_hash_index` (`integrity_hash`)',
                'activity_log_log_created_index' => 'KEY `activity_log_log_created_index` (`log_name`,`created_at`)',
                'activity_log_event_created_index' => 'KEY `activity_log_event_created_index` (`event`,`created_at`)',
                'activity_log_subject_created_index' => 'KEY `activity_log_subject_created_index` (`subject_type`,`subject_id`,`created_at`)',
                'activity_log_causer_created_index' => 'KEY `activity_log_causer_created_index` (`causer_type`,`causer_id`,`created_at`)',
                'activity_log_risk_created_index' => 'KEY `activity_log_risk_created_index` (`risk_level`,`created_at`)',
                'activity_log_request_created_index' => 'KEY `activity_log_request_created_index` (`request_id`,`created_at`)',
                'activity_log_ip_created_index' => 'KEY `activity_log_ip_created_index` (`ip_address`,`created_at`)',
            ],
            'foreign' => [
            ],
        ],
        'ai_action_drafts' => [
            'create' => 'CREATE TABLE `ai_action_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'send_email\',
  `payload` json NOT NULL,
  `status` enum(\'pending\',\'sent\',\'cancelled\',\'failed\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'pending\',
  `created_by` bigint unsigned NOT NULL,
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_action_drafts_created_by_foreign` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'type' => '`type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'send_email\' AFTER `id`',
                'payload' => '`payload` json NOT NULL AFTER `type`',
                'status' => '`status` enum(\'pending\',\'sent\',\'cancelled\',\'failed\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'pending\' AFTER `payload`',
                'created_by' => '`created_by` bigint unsigned NOT NULL AFTER `status`',
                'error' => '`error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `created_by`',
                'sent_at' => '`sent_at` timestamp NULL DEFAULT NULL AFTER `error`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `sent_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'ai_action_drafts_created_by_foreign' => 'KEY `ai_action_drafts_created_by_foreign` (`created_by`)',
            ],
            'foreign' => [
                'ai_action_drafts_created_by_foreign' => 'CONSTRAINT `ai_action_drafts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE',
            ],
        ],
        'business_function_members' => [
            'create' => 'CREATE TABLE `business_function_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_function_id` bigint unsigned NOT NULL,
  `employee_type_id` int DEFAULT NULL,
  `member_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `is_manager` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Indica se l\'\'utente è responsabile/manager di questa funzione\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_business_function_member` (`business_function_id`,`member_id`,`member_type`),
  KEY `business_function_members_member_type_member_id_index` (`member_type`,`member_id`),
  KEY `business_function_members_employee_type_id_foreign` (`employee_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'business_function_id' => '`business_function_id` bigint unsigned NOT NULL AFTER `id`',
                'employee_type_id' => '`employee_type_id` int DEFAULT NULL AFTER `business_function_id`',
                'member_type' => '`member_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `employee_type_id`',
                'member_id' => '`member_id` bigint unsigned NOT NULL AFTER `member_type`',
                'is_manager' => '`is_manager` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Indica se l\'\'utente è responsabile/manager di questa funzione\' AFTER `member_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_manager`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'unique_business_function_member' => 'UNIQUE KEY `unique_business_function_member` (`business_function_id`,`member_id`,`member_type`)',
                'business_function_members_member_type_member_id_index' => 'KEY `business_function_members_member_type_member_id_index` (`member_type`,`member_id`)',
                'business_function_members_employee_type_id_foreign' => 'KEY `business_function_members_employee_type_id_foreign` (`employee_type_id`)',
            ],
            'foreign' => [
                'business_function_members_business_function_id_foreign' => 'CONSTRAINT `business_function_members_business_function_id_foreign` FOREIGN KEY (`business_function_id`) REFERENCES `business_functions` (`id`) ON DELETE CASCADE',
                'business_function_members_employee_type_id_foreign' => 'CONSTRAINT `business_function_members_employee_type_id_foreign` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_types` (`id`) ON DELETE SET NULL',
            ],
        ],
        'business_functions' => [
            'create' => 'CREATE TABLE `business_functions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco funzione business\',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice identificativo univoco funzione\',
  `macro_area` enum(\'Governance\',\'Business / Commerciale\',\'Supporto\',\'Controlli (II Livello)\',\'Controlli (III Livello)\',\'Controlli / Privacy\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Macro area di appartenenza\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome specifico funzione business\',
  `type` enum(\'Strategica\',\'Operativa\',\'Supporto\',\'Controllo\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipologia funzione\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata funzione\',
  `outsourcable_status` enum(\'yes\',\'no\',\'partial\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\',
  `managed_by_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mission` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'What does the function do\',
  `responsibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'List of activities and responsibilities\',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email di contatto della funzione\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_functions_code_unique` (`code`),
  UNIQUE KEY `business_functions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Funzioni aziendali per funzionogramma\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco funzione business\' FIRST',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Codice identificativo univoco funzione\' AFTER `id`',
                'macro_area' => '`macro_area` enum(\'Governance\',\'Business / Commerciale\',\'Supporto\',\'Controlli (II Livello)\',\'Controlli (III Livello)\',\'Controlli / Privacy\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Macro area di appartenenza\' AFTER `code`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome specifico funzione business\' AFTER `macro_area`',
                'type' => '`type` enum(\'Strategica\',\'Operativa\',\'Supporto\',\'Controllo\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Tipologia funzione\' AFTER `name`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata funzione\' AFTER `type`',
                'outsourcable_status' => '`outsourcable_status` enum(\'yes\',\'no\',\'partial\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\' AFTER `description`',
                'managed_by_code' => '`managed_by_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `outsourcable_status`',
                'mission' => '`mission` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'What does the function do\' AFTER `managed_by_code`',
                'responsibility' => '`responsibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'List of activities and responsibilities\' AFTER `mission`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Email di contatto della funzione\' AFTER `responsibility`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `email`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'business_functions_code_unique' => 'UNIQUE KEY `business_functions_code_unique` (`code`)',
                'business_functions_name_unique' => 'UNIQUE KEY `business_functions_name_unique` (`name`)',
            ],
            'foreign' => [
            ],
        ],
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'value' => '`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'owner' => '`owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'chat_messages' => [
            'create' => 'CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`),
  KEY `chat_messages_thread_id_index` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Messaggi delle conversazioni con l\'\'assistente AI (NeuronAI)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'thread_id' => '`thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'role' => '`role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `thread_id`',
                'content' => '`content` json DEFAULT NULL AFTER `role`',
                'meta' => '`meta` json DEFAULT NULL AFTER `content`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `meta`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `user_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'chat_messages_user_id_foreign' => 'KEY `chat_messages_user_id_foreign` (`user_id`)',
                'chat_messages_thread_id_id_index' => 'KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`)',
                'chat_messages_thread_id_index' => 'KEY `chat_messages_thread_id_index` (`thread_id`)',
            ],
            'foreign' => [
                'chat_messages_user_id_foreign' => 'CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
            ],
        ],
        'checklist_answers' => [
            'create' => 'CREATE TABLE `checklist_answers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `process_instance_id` bigint unsigned NOT NULL,
  `checklist_submission_id` bigint unsigned DEFAULT NULL,
  `checklist_item_id` bigint unsigned NOT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checklist_answers_process_instance_id_foreign` (`process_instance_id`),
  KEY `checklist_answers_checklist_item_id_foreign` (`checklist_item_id`),
  KEY `checklist_answers_checklist_submission_id_foreign` (`checklist_submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'process_instance_id' => '`process_instance_id` bigint unsigned NOT NULL AFTER `id`',
                'checklist_submission_id' => '`checklist_submission_id` bigint unsigned DEFAULT NULL AFTER `process_instance_id`',
                'checklist_item_id' => '`checklist_item_id` bigint unsigned NOT NULL AFTER `checklist_submission_id`',
                'value_boolean' => '`value_boolean` tinyint(1) DEFAULT NULL AFTER `checklist_item_id`',
                'value_text' => '`value_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `value_boolean`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `value_text`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'checklist_answers_process_instance_id_foreign' => 'KEY `checklist_answers_process_instance_id_foreign` (`process_instance_id`)',
                'checklist_answers_checklist_item_id_foreign' => 'KEY `checklist_answers_checklist_item_id_foreign` (`checklist_item_id`)',
                'checklist_answers_checklist_submission_id_foreign' => 'KEY `checklist_answers_checklist_submission_id_foreign` (`checklist_submission_id`)',
            ],
            'foreign' => [
                'checklist_answers_checklist_item_id_foreign' => 'CONSTRAINT `checklist_answers_checklist_item_id_foreign` FOREIGN KEY (`checklist_item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE',
                'checklist_answers_checklist_submission_id_foreign' => 'CONSTRAINT `checklist_answers_checklist_submission_id_foreign` FOREIGN KEY (`checklist_submission_id`) REFERENCES `checklist_submissions` (`id`) ON DELETE CASCADE',
                'checklist_answers_process_instance_id_foreign' => 'CONSTRAINT `checklist_answers_process_instance_id_foreign` FOREIGN KEY (`process_instance_id`) REFERENCES `process_instances` (`id`) ON DELETE CASCADE',
            ],
        ],
        'checklist_items' => [
            'create' => 'CREATE TABLE `checklist_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `checklist_id` bigint unsigned NOT NULL COMMENT \'Modulo/Checklist di appartenenza\',
  `item_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco della domanda (utile per API o integrazioni)\',
  `ordine` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di apparizione nel form\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Label breve o nome della domanda\',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Label breve o nome della domanda\',
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Testo esteso della domanda o istruzione esplicativa\',
  `type` enum(\'boolean\',\'text\',\'number\',\'date\',\'select\',\'multiselect\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'boolean\' COMMENT \'Tipo di input HTML da generare\',
  `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'JSON con le opzioni se type è select o multiselect\',
  `is_required` tinyint(1) NOT NULL DEFAULT \'1\',
  `trigger_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\',
  `trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\',
  `trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\',
  `exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `is_timestamp_update` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Se true, inserisce in automatico il Carbon::now() nella colonna target\',
  `is_knockout` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Se true, valuta la risposta per un potenziale respingimento istantaneo\',
  `knockout_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Se la risposta utente combacia con questo valore, la pratica passa in status "rejected"\',
  `depends_on_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'item_code della domanda da cui dipende\',
  `depends_on_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Mostra solo se la domanda madre ha questo valore\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checklist_items_item_code_unique` (`item_code`),
  KEY `checklist_items_checklist_id_index` (`checklist_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'I campi dei moduli con regole di automazione stato e KO\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'checklist_id' => '`checklist_id` bigint unsigned NOT NULL COMMENT \'Modulo/Checklist di appartenenza\' AFTER `id`',
                'item_code' => '`item_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco della domanda (utile per API o integrazioni)\' AFTER `checklist_id`',
                'ordine' => '`ordine` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di apparizione nel form\' AFTER `item_code`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Label breve o nome della domanda\' AFTER `ordine`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Label breve o nome della domanda\' AFTER `name`',
                'question' => '`question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Testo esteso della domanda o istruzione esplicativa\' AFTER `label`',
                'type' => '`type` enum(\'boolean\',\'text\',\'number\',\'date\',\'select\',\'multiselect\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'boolean\' COMMENT \'Tipo di input HTML da generare\' AFTER `question`',
                'options' => '`options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'JSON con le opzioni se type è select o multiselect\' AFTER `type`',
                'is_required' => '`is_required` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `options`',
                'trigger_model' => '`trigger_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\' AFTER `is_required`',
                'trigger_field' => '`trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\' AFTER `trigger_model`',
                'trigger_state' => '`trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `trigger_field`',
                'trigger_value' => '`trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `trigger_state`',
                'exclude_field' => '`exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\' AFTER `trigger_value`',
                'exclude_state' => '`exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `exclude_field`',
                'exclude_value' => '`exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `exclude_state`',
                'is_timestamp_update' => '`is_timestamp_update` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Se true, inserisce in automatico il Carbon::now() nella colonna target\' AFTER `exclude_value`',
                'is_knockout' => '`is_knockout` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Se true, valuta la risposta per un potenziale respingimento istantaneo\' AFTER `is_timestamp_update`',
                'knockout_value' => '`knockout_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Se la risposta utente combacia con questo valore, la pratica passa in status "rejected"\' AFTER `is_knockout`',
                'depends_on_code' => '`depends_on_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'item_code della domanda da cui dipende\' AFTER `knockout_value`',
                'depends_on_value' => '`depends_on_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Mostra solo se la domanda madre ha questo valore\' AFTER `depends_on_code`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `depends_on_value`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'checklist_items_item_code_unique' => 'UNIQUE KEY `checklist_items_item_code_unique` (`item_code`)',
                'checklist_items_checklist_id_index' => 'KEY `checklist_items_checklist_id_index` (`checklist_id`)',
            ],
            'foreign' => [
                'checklist_items_checklist_id_foreign' => 'CONSTRAINT `checklist_items_checklist_id_foreign` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE',
            ],
        ],
        'checklist_submissions' => [
            'create' => 'CREATE TABLE `checklist_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della compilazione\',
  `checklist_id` bigint unsigned NOT NULL COMMENT \'Riferimento alla checklist di base\',
  `process_task_item_answer_id` bigint unsigned DEFAULT NULL,
  `status` enum(\'draft\',\'completed\',\'approved\',\'rejected\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'draft\' COMMENT \'Stato di avanzamento della compilazione\',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di invio definitivo\',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note generali sulla sottomissione\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checklist_submissions_checklist_id_index` (`checklist_id`),
  KEY `checklist_submissions_process_task_item_answer_id_foreign` (`process_task_item_answer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Istanze di compilazione delle checklist da parte dei tenant\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della compilazione\' FIRST',
                'checklist_id' => '`checklist_id` bigint unsigned NOT NULL COMMENT \'Riferimento alla checklist di base\' AFTER `id`',
                'process_task_item_answer_id' => '`process_task_item_answer_id` bigint unsigned DEFAULT NULL AFTER `checklist_id`',
                'status' => '`status` enum(\'draft\',\'completed\',\'approved\',\'rejected\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'draft\' COMMENT \'Stato di avanzamento della compilazione\' AFTER `process_task_item_answer_id`',
                'submitted_at' => '`submitted_at` timestamp NULL DEFAULT NULL COMMENT \'Data e ora di invio definitivo\' AFTER `status`',
                'notes' => '`notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note generali sulla sottomissione\' AFTER `submitted_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `notes`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'checklist_submissions_checklist_id_index' => 'KEY `checklist_submissions_checklist_id_index` (`checklist_id`)',
                'checklist_submissions_process_task_item_answer_id_foreign' => 'KEY `checklist_submissions_process_task_item_answer_id_foreign` (`process_task_item_answer_id`)',
            ],
            'foreign' => [
                'checklist_submissions_checklist_id_foreign' => 'CONSTRAINT `checklist_submissions_checklist_id_foreign` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE',
                'checklist_submissions_process_task_item_answer_id_foreign' => 'CONSTRAINT `checklist_submissions_process_task_item_answer_id_foreign` FOREIGN KEY (`process_task_item_answer_id`) REFERENCES `process_task_item_answers` (`id`) ON DELETE CASCADE',
            ],
        ],
        'checklists' => [
            'create' => 'CREATE TABLE `checklists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco checklist\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome della checklist (es. Antiriciclaggio, Trasparenza)\',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice identificativo interno univoco\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione generale della checklist e dei suoi scopi\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se la checklist è attualmente utilizzabile\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica delle tipologie di checklist disponibili nel sistema\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco checklist\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome della checklist (es. Antiriciclaggio, Trasparenza)\' AFTER `id`',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice identificativo interno univoco\' AFTER `name`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione generale della checklist e dei suoi scopi\' AFTER `code`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se la checklist è attualmente utilizzabile\' AFTER `description`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'companies' => [
            'create' => 'CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'employee_type_permissions' => [
            'create' => 'CREATE TABLE `employee_type_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del permesso.\',
  `employee_type_id` int NOT NULL COMMENT \'Tipologia di dipendente a cui è assegnato il permesso.\',
  `resource_id` bigint unsigned NOT NULL COMMENT \'Risorsa/funzionalità applicativa a cui si riferisce il permesso.\',
  `action` enum(\'viewAny\',\'view\',\'create\',\'update\',\'delete\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'viewAny\' COMMENT \'Azione consentita sulla risorsa.\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_type_perm_unique` (`employee_type_id`,`resource_id`,`action`),
  KEY `employee_type_permissions_resource_id_index` (`resource_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Permessi assegnati a ciascuna tipologia di dipendente sulle risorse applicative.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del permesso.\' FIRST',
                'employee_type_id' => '`employee_type_id` int NOT NULL COMMENT \'Tipologia di dipendente a cui è assegnato il permesso.\' AFTER `id`',
                'resource_id' => '`resource_id` bigint unsigned NOT NULL COMMENT \'Risorsa/funzionalità applicativa a cui si riferisce il permesso.\' AFTER `employee_type_id`',
                'action' => '`action` enum(\'viewAny\',\'view\',\'create\',\'update\',\'delete\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'viewAny\' COMMENT \'Azione consentita sulla risorsa.\' AFTER `resource_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `action`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'emp_type_perm_unique' => 'UNIQUE KEY `emp_type_perm_unique` (`employee_type_id`,`resource_id`,`action`)',
                'employee_type_permissions_resource_id_index' => 'KEY `employee_type_permissions_resource_id_index` (`resource_id`)',
            ],
            'foreign' => [
                'employee_type_permissions_employee_type_id_foreign' => 'CONSTRAINT `employee_type_permissions_employee_type_id_foreign` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_types` (`id`) ON DELETE CASCADE',
                'employee_type_permissions_resource_id_foreign' => 'CONSTRAINT `employee_type_permissions_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE',
            ],
        ],
        'employee_type_resource_presets' => [
            'create' => 'CREATE TABLE `employee_type_resource_presets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del preset.\',
  `employee_type_id` int NOT NULL COMMENT \'Ruolo (EmployeeType) a cui è assegnato il preset.\',
  `resource_id` bigint unsigned NOT NULL COMMENT \'Risorsa/procedura applicativa sbloccata per intero da questo preset.\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_type_resource_preset_unique` (`employee_type_id`,`resource_id`),
  KEY `employee_type_resource_presets_resource_id_foreign` (`resource_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Preset di accesso totale (tutte le azioni CRUD) per ruolo/risorsa, affiancati alla matrice granulare employee_type_permissions.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del preset.\' FIRST',
                'employee_type_id' => '`employee_type_id` int NOT NULL COMMENT \'Ruolo (EmployeeType) a cui è assegnato il preset.\' AFTER `id`',
                'resource_id' => '`resource_id` bigint unsigned NOT NULL COMMENT \'Risorsa/procedura applicativa sbloccata per intero da questo preset.\' AFTER `employee_type_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `resource_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'emp_type_resource_preset_unique' => 'UNIQUE KEY `emp_type_resource_preset_unique` (`employee_type_id`,`resource_id`)',
                'employee_type_resource_presets_resource_id_foreign' => 'KEY `employee_type_resource_presets_resource_id_foreign` (`resource_id`)',
            ],
            'foreign' => [
                'employee_type_resource_presets_employee_type_id_foreign' => 'CONSTRAINT `employee_type_resource_presets_employee_type_id_foreign` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_types` (`id`) ON DELETE CASCADE',
                'employee_type_resource_presets_resource_id_foreign' => 'CONSTRAINT `employee_type_resource_presets_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE',
            ],
        ],
        'employee_types' => [
            'create' => 'CREATE TABLE `employee_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `companytype` varchar(255) DEFAULT NULL,
  `is_external` tinyint(1) DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
            'columns' => [
                'id' => '`id` int NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) DEFAULT NULL AFTER `id`',
                'icon' => '`icon` varchar(255) DEFAULT NULL AFTER `name`',
                'companytype' => '`companytype` varchar(255) DEFAULT NULL AFTER `icon`',
                'is_external' => '`is_external` tinyint(1) DEFAULT \'0\' AFTER `companytype`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_external`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'uuid' => '`uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'exception' => '`exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
                'failed_jobs_connection_queue_failed_at_index' => 'KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)',
            ],
            'foreign' => [
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `failed_jobs`',
                'options' => '`options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL AFTER `options`',
                'created_at' => '`created_at` int NOT NULL AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'attempts' => '`attempts` smallint unsigned NOT NULL AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_index' => 'KEY `jobs_queue_index` (`queue`)',
            ],
            'foreign' => [
            ],
        ],
        'media' => [
            'create' => 'CREATE TABLE `media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL,
  `manipulations` json NOT NULL,
  `custom_properties` json NOT NULL,
  `generated_conversions` json NOT NULL,
  `responsive_images` json NOT NULL,
  `order_column` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'model_type' => '`model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'model_id' => '`model_id` bigint unsigned NOT NULL AFTER `model_type`',
                'uuid' => '`uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `model_id`',
                'collection_name' => '`collection_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `collection_name`',
                'file_name' => '`file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'mime_type' => '`mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `file_name`',
                'disk' => '`disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mime_type`',
                'conversions_disk' => '`conversions_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `disk`',
                'size' => '`size` bigint unsigned NOT NULL AFTER `conversions_disk`',
                'manipulations' => '`manipulations` json NOT NULL AFTER `size`',
                'custom_properties' => '`custom_properties` json NOT NULL AFTER `manipulations`',
                'generated_conversions' => '`generated_conversions` json NOT NULL AFTER `custom_properties`',
                'responsive_images' => '`responsive_images` json NOT NULL AFTER `generated_conversions`',
                'order_column' => '`order_column` int unsigned DEFAULT NULL AFTER `responsive_images`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `order_column`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'media_uuid_unique' => 'UNIQUE KEY `media_uuid_unique` (`uuid`)',
                'media_model_type_model_id_index' => 'KEY `media_model_type_model_id_index` (`model_type`,`model_id`)',
                'media_order_column_index' => 'KEY `media_order_column_index` (`order_column`)',
            ],
            'foreign' => [
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'token' => '`token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'process_instances' => [
            'create' => 'CREATE TABLE `process_instances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della pratica/istanza di processo\',
  `process_id` bigint unsigned NOT NULL COMMENT \'Quale processo si sta eseguendo (es. Onboarding)\',
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_assignee_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_assignee_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL COMMENT \'Agenzia di competenza\',
  `status` enum(\'pending\',\'in_progress\',\'completed\',\'rejected\',\'cancelled\',\'suspended\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'pending\' COMMENT \'Stato globale della pratica\',
  `current_task_id` bigint unsigned DEFAULT NULL COMMENT \'ID del task attualmente in lavorazione (per riprendere da dove si è lasciato)\',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT \'Data di chiusura (positiva o negativa) della pratica\',
  `hard_deadline_at` date DEFAULT NULL,
  `last_reminder_sent_at` timestamp NULL DEFAULT NULL COMMENT \'Data dell\'\'ultimo sollecito inviato\',
  `reminders_sent_count` int NOT NULL DEFAULT \'0\' COMMENT \'Quanti solleciti sono già stati inviati per il task attuale\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_instances_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `assignee_index` (`current_assignee_type`,`current_assignee_id`),
  KEY `process_instances_process_id_index` (`process_id`),
  KEY `process_instances_company_id_index` (`company_id`),
  KEY `process_instances_status_index` (`status`),
  KEY `process_instances_current_task_id_index` (`current_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Le singole pratiche o istanze in esecuzione sui soggetti\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della pratica/istanza di processo\' FIRST',
                'process_id' => '`process_id` bigint unsigned NOT NULL COMMENT \'Quale processo si sta eseguendo (es. Onboarding)\' AFTER `id`',
                'subject_type' => '`subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `process_id`',
                'subject_id' => '`subject_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `subject_type`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `subject_id`',
                'current_assignee_type' => '`current_assignee_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `title`',
                'current_assignee_id' => '`current_assignee_id` bigint unsigned DEFAULT NULL AFTER `current_assignee_type`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL COMMENT \'Agenzia di competenza\' AFTER `current_assignee_id`',
                'status' => '`status` enum(\'pending\',\'in_progress\',\'completed\',\'rejected\',\'cancelled\',\'suspended\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'pending\' COMMENT \'Stato globale della pratica\' AFTER `company_id`',
                'current_task_id' => '`current_task_id` bigint unsigned DEFAULT NULL COMMENT \'ID del task attualmente in lavorazione (per riprendere da dove si è lasciato)\' AFTER `status`',
                'completed_at' => '`completed_at` timestamp NULL DEFAULT NULL COMMENT \'Data di chiusura (positiva o negativa) della pratica\' AFTER `current_task_id`',
                'hard_deadline_at' => '`hard_deadline_at` date DEFAULT NULL AFTER `completed_at`',
                'last_reminder_sent_at' => '`last_reminder_sent_at` timestamp NULL DEFAULT NULL COMMENT \'Data dell\'\'ultimo sollecito inviato\' AFTER `hard_deadline_at`',
                'reminders_sent_count' => '`reminders_sent_count` int NOT NULL DEFAULT \'0\' COMMENT \'Quanti solleciti sono già stati inviati per il task attuale\' AFTER `last_reminder_sent_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `reminders_sent_count`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_instances_subject_type_subject_id_index' => 'KEY `process_instances_subject_type_subject_id_index` (`subject_type`,`subject_id`)',
                'assignee_index' => 'KEY `assignee_index` (`current_assignee_type`,`current_assignee_id`)',
                'process_instances_process_id_index' => 'KEY `process_instances_process_id_index` (`process_id`)',
                'process_instances_company_id_index' => 'KEY `process_instances_company_id_index` (`company_id`)',
                'process_instances_status_index' => 'KEY `process_instances_status_index` (`status`)',
                'process_instances_current_task_id_index' => 'KEY `process_instances_current_task_id_index` (`current_task_id`)',
            ],
            'foreign' => [
                'process_instances_current_task_id_foreign' => 'CONSTRAINT `process_instances_current_task_id_foreign` FOREIGN KEY (`current_task_id`) REFERENCES `process_tasks` (`id`) ON DELETE SET NULL',
                'process_instances_process_id_foreign' => 'CONSTRAINT `process_instances_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_task_executions' => [
            'create' => 'CREATE TABLE `process_task_executions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `process_instance_id` bigint unsigned NOT NULL,
  `process_task_id` bigint unsigned NOT NULL,
  `assignee_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignee_id` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NOT NULL COMMENT \'Quando il task è entrato in coda per l\'\'ufficio\',
  `claimed_at` timestamp NULL DEFAULT NULL COMMENT \'Quando l\'\'operatore ha preso in carico il task\',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT \'Quando il task è stato chiuso (successo o rifiuto)\',
  `escalation_level` int NOT NULL DEFAULT \'0\' COMMENT \'Livello di escalation del task\',
  `due_at` datetime DEFAULT NULL COMMENT \'Data entro cui il task andava chiuso\',
  `mandatory_days_to_complete` smallint unsigned DEFAULT NULL,
  `execution_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'completed\' COMMENT \'completed, rejected_and_rewinded\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_task_executions_process_instance_id_foreign` (`process_instance_id`),
  KEY `process_task_executions_process_task_id_foreign` (`process_task_id`),
  KEY `process_task_executions_assignee_type_assignee_id_index` (`assignee_type`,`assignee_id`),
  KEY `process_task_executions_execution_status_index` (`execution_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'process_instance_id' => '`process_instance_id` bigint unsigned NOT NULL AFTER `id`',
                'process_task_id' => '`process_task_id` bigint unsigned NOT NULL AFTER `process_instance_id`',
                'assignee_type' => '`assignee_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `process_task_id`',
                'assignee_id' => '`assignee_id` bigint unsigned DEFAULT NULL AFTER `assignee_type`',
                'started_at' => '`started_at` timestamp NOT NULL COMMENT \'Quando il task è entrato in coda per l\'\'ufficio\' AFTER `assignee_id`',
                'claimed_at' => '`claimed_at` timestamp NULL DEFAULT NULL COMMENT \'Quando l\'\'operatore ha preso in carico il task\' AFTER `started_at`',
                'completed_at' => '`completed_at` timestamp NULL DEFAULT NULL COMMENT \'Quando il task è stato chiuso (successo o rifiuto)\' AFTER `claimed_at`',
                'escalation_level' => '`escalation_level` int NOT NULL DEFAULT \'0\' COMMENT \'Livello di escalation del task\' AFTER `completed_at`',
                'due_at' => '`due_at` datetime DEFAULT NULL COMMENT \'Data entro cui il task andava chiuso\' AFTER `escalation_level`',
                'mandatory_days_to_complete' => '`mandatory_days_to_complete` smallint unsigned DEFAULT NULL AFTER `due_at`',
                'execution_status' => '`execution_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'completed\' COMMENT \'completed, rejected_and_rewinded\' AFTER `mandatory_days_to_complete`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `execution_status`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_task_executions_process_instance_id_foreign' => 'KEY `process_task_executions_process_instance_id_foreign` (`process_instance_id`)',
                'process_task_executions_process_task_id_foreign' => 'KEY `process_task_executions_process_task_id_foreign` (`process_task_id`)',
                'process_task_executions_assignee_type_assignee_id_index' => 'KEY `process_task_executions_assignee_type_assignee_id_index` (`assignee_type`,`assignee_id`)',
                'process_task_executions_execution_status_index' => 'KEY `process_task_executions_execution_status_index` (`execution_status`)',
            ],
            'foreign' => [
                'process_task_executions_process_instance_id_foreign' => 'CONSTRAINT `process_task_executions_process_instance_id_foreign` FOREIGN KEY (`process_instance_id`) REFERENCES `process_instances` (`id`) ON DELETE CASCADE',
                'process_task_executions_process_task_id_foreign' => 'CONSTRAINT `process_task_executions_process_task_id_foreign` FOREIGN KEY (`process_task_id`) REFERENCES `process_tasks` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_task_item_answers' => [
            'create' => 'CREATE TABLE `process_task_item_answers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `process_instance_id` bigint unsigned NOT NULL,
  `process_task_execution_id` bigint unsigned DEFAULT NULL,
  `process_task_item_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'0 = Sistema/AI\',
  `operator_type` enum(\'human\',\'procedural\',\'ai\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Es: validation_rule, email_ingestion.regex, email_ingestion.ai, assistant.send_email\',
  `confidence` tinyint unsigned DEFAULT NULL COMMENT \'Confidenza 0-100 quando la risposta viene da un operatore AI\',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_task_item_answers_process_instance_id_foreign` (`process_instance_id`),
  KEY `process_task_item_answers_process_task_item_id_foreign` (`process_task_item_id`),
  KEY `process_task_item_answers_document_id_index` (`document_id`),
  KEY `process_task_item_answers_process_task_execution_id_foreign` (`process_task_execution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'process_instance_id' => '`process_instance_id` bigint unsigned NOT NULL AFTER `id`',
                'process_task_execution_id' => '`process_task_execution_id` bigint unsigned DEFAULT NULL AFTER `process_instance_id`',
                'process_task_item_id' => '`process_task_item_id` bigint unsigned NOT NULL AFTER `process_task_execution_id`',
                'document_id' => '`document_id` bigint unsigned DEFAULT NULL AFTER `process_task_item_id`',
                'value_boolean' => '`value_boolean` tinyint(1) DEFAULT NULL AFTER `document_id`',
                'value_text' => '`value_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `value_boolean`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'0 = Sistema/AI\' AFTER `value_text`',
                'operator_type' => '`operator_type` enum(\'human\',\'procedural\',\'ai\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'operator_label' => '`operator_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Es: validation_rule, email_ingestion.regex, email_ingestion.ai, assistant.send_email\' AFTER `operator_type`',
                'confidence' => '`confidence` tinyint unsigned DEFAULT NULL COMMENT \'Confidenza 0-100 quando la risposta viene da un operatore AI\' AFTER `operator_label`',
                'completed_at' => '`completed_at` timestamp NULL DEFAULT NULL AFTER `confidence`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `completed_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_task_item_answers_process_instance_id_foreign' => 'KEY `process_task_item_answers_process_instance_id_foreign` (`process_instance_id`)',
                'process_task_item_answers_process_task_item_id_foreign' => 'KEY `process_task_item_answers_process_task_item_id_foreign` (`process_task_item_id`)',
                'process_task_item_answers_document_id_index' => 'KEY `process_task_item_answers_document_id_index` (`document_id`)',
                'process_task_item_answers_process_task_execution_id_foreign' => 'KEY `process_task_item_answers_process_task_execution_id_foreign` (`process_task_execution_id`)',
            ],
            'foreign' => [
                'process_task_item_answers_process_instance_id_foreign' => 'CONSTRAINT `process_task_item_answers_process_instance_id_foreign` FOREIGN KEY (`process_instance_id`) REFERENCES `process_instances` (`id`) ON DELETE CASCADE',
                'process_task_item_answers_process_task_execution_id_foreign' => 'CONSTRAINT `process_task_item_answers_process_task_execution_id_foreign` FOREIGN KEY (`process_task_execution_id`) REFERENCES `process_task_executions` (`id`) ON DELETE CASCADE',
                'process_task_item_answers_process_task_item_id_foreign' => 'CONSTRAINT `process_task_item_answers_process_task_item_id_foreign` FOREIGN KEY (`process_task_item_id`) REFERENCES `process_task_items` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_task_items' => [
            'create' => 'CREATE TABLE `process_task_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `process_task_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordine` int NOT NULL DEFAULT \'0\',
  `action_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT \'1\',
  `document_type_id` bigint unsigned DEFAULT NULL,
  `checklist_id` bigint unsigned DEFAULT NULL,
  `handler_job` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_task_items_process_task_id_foreign` (`process_task_id`),
  KEY `process_task_items_document_type_id_index` (`document_type_id`),
  KEY `process_task_items_checklist_id_foreign` (`checklist_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'process_task_id' => '`process_task_id` bigint unsigned NOT NULL AFTER `id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `process_task_id`',
                'ordine' => '`ordine` int NOT NULL DEFAULT \'0\' AFTER `name`',
                'action_type' => '`action_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `ordine`',
                'is_required' => '`is_required` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `action_type`',
                'document_type_id' => '`document_type_id` bigint unsigned DEFAULT NULL AFTER `is_required`',
                'checklist_id' => '`checklist_id` bigint unsigned DEFAULT NULL AFTER `document_type_id`',
                'handler_job' => '`handler_job` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `checklist_id`',
                'config' => '`config` json DEFAULT NULL AFTER `handler_job`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `config`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_task_items_process_task_id_foreign' => 'KEY `process_task_items_process_task_id_foreign` (`process_task_id`)',
                'process_task_items_document_type_id_index' => 'KEY `process_task_items_document_type_id_index` (`document_type_id`)',
                'process_task_items_checklist_id_foreign' => 'KEY `process_task_items_checklist_id_foreign` (`checklist_id`)',
            ],
            'foreign' => [
                'process_task_items_checklist_id_foreign' => 'CONSTRAINT `process_task_items_checklist_id_foreign` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE SET NULL',
                'process_task_items_process_task_id_foreign' => 'CONSTRAINT `process_task_items_process_task_id_foreign` FOREIGN KEY (`process_task_id`) REFERENCES `process_tasks` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_task_raci' => [
            'create' => 'CREATE TABLE `process_task_raci` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco assegnazione RACI\',
  `process_task_id` bigint unsigned NOT NULL COMMENT \'Task di processo di riferimento\',
  `business_function_id` bigint unsigned NOT NULL COMMENT \'Funzione di business coinvolta\',
  `raci_role` enum(\'R\',\'A\',\'C\',\'I\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Ruolo nella matrice: R(Responsible), A(Accountable), C(Consulted), I(Informed)\',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note specifiche sull\'\'assegnazione del ruolo per questo task\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_raci_assignment` (`process_task_id`,`business_function_id`),
  KEY `process_task_raci_process_task_id_index` (`process_task_id`),
  KEY `process_task_raci_business_function_id_index` (`business_function_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Matrice RACI: Mappatura delle responsabilità delle funzioni aziendali sui task di processo\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco assegnazione RACI\' FIRST',
                'process_task_id' => '`process_task_id` bigint unsigned NOT NULL COMMENT \'Task di processo di riferimento\' AFTER `id`',
                'business_function_id' => '`business_function_id` bigint unsigned NOT NULL COMMENT \'Funzione di business coinvolta\' AFTER `process_task_id`',
                'raci_role' => '`raci_role` enum(\'R\',\'A\',\'C\',\'I\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Ruolo nella matrice: R(Responsible), A(Accountable), C(Consulted), I(Informed)\' AFTER `business_function_id`',
                'notes' => '`notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Note specifiche sull\'\'assegnazione del ruolo per questo task\' AFTER `raci_role`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `notes`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'unique_raci_assignment' => 'UNIQUE KEY `unique_raci_assignment` (`process_task_id`,`business_function_id`)',
                'process_task_raci_process_task_id_index' => 'KEY `process_task_raci_process_task_id_index` (`process_task_id`)',
                'process_task_raci_business_function_id_index' => 'KEY `process_task_raci_business_function_id_index` (`business_function_id`)',
            ],
            'foreign' => [
                'process_task_raci_business_function_id_foreign' => 'CONSTRAINT `process_task_raci_business_function_id_foreign` FOREIGN KEY (`business_function_id`) REFERENCES `business_functions` (`id`) ON DELETE CASCADE',
                'process_task_raci_process_task_id_foreign' => 'CONSTRAINT `process_task_raci_process_task_id_foreign` FOREIGN KEY (`process_task_id`) REFERENCES `process_tasks` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_tasks' => [
            'create' => 'CREATE TABLE `process_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del task di processo\',
  `process_id` bigint unsigned NOT NULL COMMENT \'Riferimento al macro processo di appartenenza\',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco interno del task\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome esplicativo del task\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata delle attività del task\',
  `ordine` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine sequenziale di esecuzione nel processo\',
  `business_function_id` bigint unsigned DEFAULT NULL COMMENT \'Funzione di business associata\',
  `trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\',
  `trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\',
  `exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `has_reminders` tinyint(1) NOT NULL DEFAULT \'0\',
  `reminder_interval_days` int NOT NULL DEFAULT \'3\',
  `max_reminders` int NOT NULL DEFAULT \'5\',
  `escalation_rules` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_tasks_business_function_id_foreign` (`business_function_id`),
  KEY `process_tasks_process_id_index` (`process_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica dei task di processo aziendali collegati alle checklist\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del task di processo\' FIRST',
                'process_id' => '`process_id` bigint unsigned NOT NULL COMMENT \'Riferimento al macro processo di appartenenza\' AFTER `id`',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco interno del task\' AFTER `process_id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome esplicativo del task\' AFTER `code`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata delle attività del task\' AFTER `name`',
                'ordine' => '`ordine` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine sequenziale di esecuzione nel processo\' AFTER `description`',
                'business_function_id' => '`business_function_id` bigint unsigned DEFAULT NULL COMMENT \'Funzione di business associata\' AFTER `ordine`',
                'trigger_field' => '`trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\' AFTER `business_function_id`',
                'trigger_state' => '`trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `trigger_field`',
                'trigger_value' => '`trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `trigger_state`',
                'exclude_field' => '`exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\' AFTER `trigger_value`',
                'exclude_state' => '`exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `exclude_field`',
                'exclude_value' => '`exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `exclude_state`',
                'has_reminders' => '`has_reminders` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `exclude_value`',
                'reminder_interval_days' => '`reminder_interval_days` int NOT NULL DEFAULT \'3\' AFTER `has_reminders`',
                'max_reminders' => '`max_reminders` int NOT NULL DEFAULT \'5\' AFTER `reminder_interval_days`',
                'escalation_rules' => '`escalation_rules` json DEFAULT NULL AFTER `max_reminders`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `escalation_rules`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_tasks_business_function_id_foreign' => 'KEY `process_tasks_business_function_id_foreign` (`business_function_id`)',
                'process_tasks_process_id_index' => 'KEY `process_tasks_process_id_index` (`process_id`)',
            ],
            'foreign' => [
                'process_tasks_business_function_id_foreign' => 'CONSTRAINT `process_tasks_business_function_id_foreign` FOREIGN KEY (`business_function_id`) REFERENCES `business_functions` (`id`) ON DELETE SET NULL',
                'process_tasks_process_id_foreign' => 'CONSTRAINT `process_tasks_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE',
            ],
        ],
        'process_triggers' => [
            'create' => 'CREATE TABLE `process_triggers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `process_id` bigint unsigned NOT NULL,
  `event_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions` json DEFAULT NULL,
  `idle_days` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_triggers_process_id_foreign` (`process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'model_class' => '`model_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'process_id' => '`process_id` bigint unsigned NOT NULL AFTER `model_class`',
                'event_type' => '`event_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `process_id`',
                'conditions' => '`conditions` json DEFAULT NULL AFTER `event_type`',
                'idle_days' => '`idle_days` int DEFAULT NULL AFTER `conditions`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `idle_days`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'process_triggers_process_id_foreign' => 'KEY `process_triggers_process_id_foreign` (`process_id`)',
            ],
            'foreign' => [
                'process_triggers_process_id_foreign' => 'CONSTRAINT `process_triggers_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE',
            ],
        ],
        'processes' => [
            'create' => 'CREATE TABLE `processes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del processo macro aziendale\',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice identificativo univoco del processo (es. PRC-AML)\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome esplicativo del processo\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata delle finalità e dell’ambito del processo\',
  `version` int NOT NULL DEFAULT \'1\' COMMENT \'Incrementato se cambiano i task del processo\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di attivazione del processo (attivo/disattivato)\',
  `target_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_filters` json DEFAULT NULL,
  `trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\',
  `trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\',
  `exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `completion_write_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_write_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_write_app` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `include_eligible_count_in_reminders` tinyint(1) NOT NULL DEFAULT \'0\',
  `is_periodic` tinyint(1) NOT NULL DEFAULT \'0\',
  `cron_expression` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence_frequency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence_day` int DEFAULT NULL,
  `last_activated_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `processes_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica dei macro processi aziendali (es. Adeguata Verifica, Trasparenza)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del processo macro aziendale\' FIRST',
                'code' => '`code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice identificativo univoco del processo (es. PRC-AML)\' AFTER `id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome esplicativo del processo\' AFTER `code`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata delle finalità e dell’ambito del processo\' AFTER `name`',
                'version' => '`version` int NOT NULL DEFAULT \'1\' COMMENT \'Incrementato se cambiano i task del processo\' AFTER `description`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di attivazione del processo (attivo/disattivato)\' AFTER `version`',
                'target_model' => '`target_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `is_active`',
                'trigger_filters' => '`trigger_filters` json DEFAULT NULL AFTER `target_model`',
                'trigger_field' => '`trigger_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\' AFTER `trigger_filters`',
                'trigger_state' => '`trigger_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `trigger_field`',
                'trigger_value' => '`trigger_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `trigger_state`',
                'exclude_field' => '`exclude_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\' AFTER `trigger_value`',
                'exclude_state' => '`exclude_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `exclude_field`',
                'exclude_value' => '`exclude_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `exclude_state`',
                'completion_write_field' => '`completion_write_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `exclude_value`',
                'completion_write_value' => '`completion_write_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `completion_write_field`',
                'completion_write_app' => '`completion_write_app` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `completion_write_value`',
                'include_eligible_count_in_reminders' => '`include_eligible_count_in_reminders` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `completion_write_app`',
                'is_periodic' => '`is_periodic` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `include_eligible_count_in_reminders`',
                'cron_expression' => '`cron_expression` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `is_periodic`',
                'recurrence_frequency' => '`recurrence_frequency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `cron_expression`',
                'recurrence_day' => '`recurrence_day` int DEFAULT NULL AFTER `recurrence_frequency`',
                'last_activated_at' => '`last_activated_at` datetime DEFAULT NULL AFTER `recurrence_day`',
                'next_run_at' => '`next_run_at` datetime DEFAULT NULL AFTER `last_activated_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `next_run_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'processes_code_unique' => 'UNIQUE KEY `processes_code_unique` (`code`)',
            ],
            'foreign' => [
            ],
        ],
        'resources' => [
            'create' => 'CREATE TABLE `resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della risorsa.\',
  `app_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Applicazione di appartenenza della risorsa (es. CRM, PORTALE_OAM, FINANCE).\',
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificatore univoco della risorsa all\'\'interno dell\'\'app (es. employees).\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome leggibile della risorsa (es. Dipendenti).\',
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Gruppo di menu a cui appartiene la risorsa (es. Anagrafiche).\',
  `min_plan` enum(\'BASE\',\'MEDIUM\',\'FULL\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'BASE\' COMMENT \'Piano minimo richiesto per accedere alla risorsa.\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `res_app_key_unique` (`app_name`,`key`)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Risorse/funzionalità applicative disponibili, raggruppabili per app e piano.\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della risorsa.\' FIRST',
                'app_name' => '`app_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Applicazione di appartenenza della risorsa (es. CRM, PORTALE_OAM, FINANCE).\' AFTER `id`',
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificatore univoco della risorsa all\'\'interno dell\'\'app (es. employees).\' AFTER `app_name`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome leggibile della risorsa (es. Dipendenti).\' AFTER `key`',
                'group' => '`group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Gruppo di menu a cui appartiene la risorsa (es. Anagrafiche).\' AFTER `name`',
                'min_plan' => '`min_plan` enum(\'BASE\',\'MEDIUM\',\'FULL\') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'BASE\' COMMENT \'Piano minimo richiesto per accedere alla risorsa.\' AFTER `group`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `min_plan`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'res_app_key_unique' => 'UNIQUE KEY `res_app_key_unique` (`app_name`,`key`)',
            ],
            'foreign' => [
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'user_agent' => '`user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `ip_address`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'socialite_users' => [
            'create' => 'CREATE TABLE `socialite_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`),
  KEY `socialite_users_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'user_id' => '`user_id` bigint unsigned NOT NULL AFTER `id`',
                'provider' => '`provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_id`',
                'provider_id' => '`provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `provider`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `provider_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'socialite_users_provider_provider_id_unique' => 'UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`)',
                'socialite_users_user_id_foreign' => 'KEY `socialite_users_user_id_foreign` (`user_id`)',
            ],
            'foreign' => [
                'socialite_users_user_id_foreign' => 'CONSTRAINT `socialite_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'user\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_profile_type_profile_id_index` (`profile_type`,`profile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`',
                'password' => '`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email_verified_at`',
                'remember_token' => '`remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `password`',
                'profile_type' => '`profile_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `remember_token`',
                'profile_id' => '`profile_id` bigint unsigned DEFAULT NULL AFTER `profile_type`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `profile_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'role' => '`role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT \'user\' AFTER `updated_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
                'users_profile_type_profile_id_index' => 'KEY `users_profile_type_profile_id_index` (`profile_type`,`profile_id`)',
            ],
            'foreign' => [
            ],
        ],
        ];
    }
};
