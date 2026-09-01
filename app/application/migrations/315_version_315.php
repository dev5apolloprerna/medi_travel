<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_315 extends CI_Migration
{
    public function up()
    {
        if (!$this->db->table_exists(db_prefix() . 'lead_followup_history')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "lead_followup_history` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `lead_id` INT NOT NULL,
                `next_followup_date` DATE NOT NULL,
                `comment` TEXT NOT NULL,
                `status_id` INT NOT NULL,
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_id` (`lead_id`),
                KEY `status_id` (`status_id`),
                KEY `next_followup_date` (`next_followup_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $this->db->char_set . ';');
        }
    }
}
