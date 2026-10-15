<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Discharge Feedback
Description: Treats a closed lead as a discharge, sends the patient the Fit-to-Fly feedback form link by Email + SMS and reminds them every day until the form is submitted.
Version: 1.0.0
Requires at least: 2.3.*
Author: MediTravel
*/

define('DISCHARGE_FEEDBACK_MODULE', 'discharge_feedback');

register_activation_hook(DISCHARGE_FEEDBACK_MODULE, 'discharge_feedback_activation_hook');
register_uninstall_hook(DISCHARGE_FEEDBACK_MODULE, 'discharge_feedback_uninstall_hook');
register_language_files(DISCHARGE_FEEDBACK_MODULE, [DISCHARGE_FEEDBACK_MODULE]);

function discharge_feedback_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function discharge_feedback_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

/* ------------------------------------------------------------------
 * Admin menu
 * ------------------------------------------------------------------ */
hooks()->add_action('admin_init', 'discharge_feedback_admin_menu');

function discharge_feedback_admin_menu()
{
    $CI = &get_instance();

    if (is_staff_member()) {
        $CI->app_menu->add_sidebar_menu_item('discharge-feedback', [
            'name'     => _l('discharge_feedback'),
            'href'     => admin_url('discharge_feedback'),
            'icon'     => 'fa fa-clipboard-check',
            'position' => 6, // right after Leads (5)
            'badge'    => [],
        ]);
    }
}

/* ------------------------------------------------------------------
 * 1) Lead closed  ==>  discharge  ==>  send feedback link right away
 *    Perfex fires "lead_status_changed" from leads_model when the status
 *    is changed (kanban drag, status dropdown, bulk action).
 * ------------------------------------------------------------------ */
hooks()->add_action('lead_status_changed', 'discharge_feedback_on_lead_status_changed');

function discharge_feedback_on_lead_status_changed($data)
{
    try {
        // NOTE: Leads_model::update() passes the status ID, while
        // Leads_model::update_lead_status() (kanban / bulk) passes the status NAME.
        $leadId    = isset($data['lead_id']) ? (int) $data['lead_id'] : 0;
        $newStatus = isset($data['new_status']) ? trim((string) $data['new_status']) : '';

        if (!$leadId || $newStatus === '') {
            return;
        }

        $CI = &get_instance();
        $CI->load->model('discharge_feedback/discharge_feedback_model', 'dfb');

        if (!(int) $CI->dfb->setting('enabled')) {
            return;
        }

        // $CI->db is the branch database the staff member is working in
        if (!$CI->dfb->is_discharge_status($newStatus, $CI->db)) {
            return;
        }

        $branch  = $CI->dfb->current_branch();
        $request = $CI->dfb->enqueue_lead($leadId, $CI->db, $branch['id'], $branch['name']);

        if ($request && $request['status'] === 'pending' && (int) $request['send_count'] === 0) {
            $CI->dfb->send_request($request, false);
        }
    } catch (Throwable $e) {
        // Never break the lead update because of a notification problem
        log_message('error', 'Discharge feedback (status hook): ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
 * 2) Perfex cron (runs every few minutes when Setup > Settings > Cron
 *    Job is configured):
 *      - picks up closed leads the hook may have missed
 *      - sends links that could not be sent yet
 *      - once per day (after the configured hour) sends reminders
 * ------------------------------------------------------------------ */
hooks()->add_action('after_cron_run', 'discharge_feedback_on_cron');

function discharge_feedback_on_cron($manually = false)
{
    try {
        $CI = &get_instance();
        $CI->load->model('discharge_feedback/discharge_feedback_model', 'dfb');
        // "Run Cron Manually" (Setup > Settings > Cron Job) sends today's reminders right away
        $CI->dfb->run((bool) $manually);
    } catch (Throwable $e) {
        log_message('error', 'Discharge feedback (cron): ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
 * 3) "Discharge Feedback" tab in Setup > Settings > Cron Job
 * ------------------------------------------------------------------ */
hooks()->add_action('after_cron_settings_last_tab', 'discharge_feedback_cron_tab');
hooks()->add_action('after_cron_settings_last_tab_content', 'discharge_feedback_cron_tab_content');
hooks()->add_filter('before_settings_updated', 'discharge_feedback_save_cron_settings');

function discharge_feedback_cron_tab()
{
    if (!is_admin()) {
        return;
    }
    echo '<li role="presentation"><a href="#discharge_feedback_cron" aria-controls="discharge_feedback_cron" role="tab" data-toggle="tab">'
        . _l('discharge_feedback') . '</a></li>';
}

function discharge_feedback_cron_tab_content()
{
    if (!is_admin()) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('discharge_feedback/discharge_feedback_model', 'dfb');
    $CI->dfb->ensure_schema();

    $CI->load->view('discharge_feedback/admin/cron_tab', [
        's'        => $CI->dfb->settings(),
        'cron_url' => site_url('discharge_feedback/dfb_cron/run/' . $CI->dfb->setting('cron_key')),
    ]);
}

/**
 * Saves the tab's fields (posted as discharge_feedback[...]) when the
 * normal "Save Settings" button is pressed.
 */
function discharge_feedback_save_cron_settings($data)
{
    if (!isset($data['discharge_feedback']) || !is_array($data['discharge_feedback'])) {
        return $data;
    }

    $posted = $data['discharge_feedback'];
    unset($data['discharge_feedback']);

    if (!is_admin()) {
        return $data;
    }

    $CI = &get_instance();
    $CI->load->model('discharge_feedback/discharge_feedback_model', 'dfb');

    $values = [
        'enabled'      => !empty($posted['enabled']) ? '1' : '0',
        'send_email'   => !empty($posted['send_email']) ? '1' : '0',
        'send_sms'     => !empty($posted['send_sms']) ? '1' : '0',
        'daily_hour'   => (string) max(0, min(23, (int) ($posted['daily_hour'] ?? 10))),
        'max_messages' => (string) max(0, (int) ($posted['max_messages'] ?? 5)),
    ];
    if (isset($posted['status_names']) && trim($posted['status_names']) !== '') {
        $values['status_names'] = trim((string) $posted['status_names']);
    }

    $changed = false;
    foreach ($values as $name => $value) {
        if ((string) $CI->dfb->setting($name) !== $value) {
            $CI->dfb->save_setting($name, $value);
            $changed = true;
        }
    }
    if ($changed) {
        set_alert('success', _l('discharge_feedback') . ': ' . _l('settings_updated'));
    }

    return $data;
}
