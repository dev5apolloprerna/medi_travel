<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Runs only when the module is UNINSTALLED (not on deactivate).
 * Removes all discharge feedback data.
 */

$CI     = &get_instance();
$prefix = db_prefix();

foreach (['requests', 'responses', 'log', 'settings'] as $dfbTable) {
    if ($CI->db->table_exists($prefix . 'discharge_feedback_' . $dfbTable)) {
        $CI->db->query('DROP TABLE `' . $prefix . 'discharge_feedback_' . $dfbTable . '`');
    }
}
