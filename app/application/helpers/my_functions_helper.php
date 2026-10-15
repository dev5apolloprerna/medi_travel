<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| DISCHARGE FEEDBACK (Fit-to-Fly form)
|--------------------------------------------------------------------------
| Flow:
|   1. Staff set a lead's status to "Close"  ->  treated as DISCHARGE.
|   2. A unique link is created and sent to the patient by Email + SMS:
|         https://your-site/feedback/f/<token>
|   3. The patient fills the existing consent template
|      uploads/consern_form/Fit-to-FlyMedical.html (no login).
|   4. Answers are saved in tblconsent_form_submissions, so they appear in
|      Patient Profile -> Consent Forms like any other consent form.
|   5. Cron URL (once a day) re-sends the link until the form is submitted:
|         https://your-site/feedback/cron/<FEEDBACK_CRON_KEY>
|
| This file is auto-loaded by Perfex (application/config/autoload.php).
*/

// ---------------- SETTINGS (change here) ----------------
defined('FEEDBACK_CLOSE_STATUSES') or define('FEEDBACK_CLOSE_STATUSES', 'Close,Closed');        // lead status name(s) meaning discharge
defined('FEEDBACK_FORM_FILE')      or define('FEEDBACK_FORM_FILE', 'Fit-to-FlyMedical.html');   // file in uploads/consern_form/
defined('FEEDBACK_MAX_SENDS')      or define('FEEDBACK_MAX_SENDS', 5);                          // first message + reminders (0 = until submitted)
defined('FEEDBACK_CRON_KEY')       or define('FEEDBACK_CRON_KEY', 'CHANGE-ME-to-a-long-secret'); // secret part of the cron URL
defined('FEEDBACK_COUNTRY_CODE')   or define('FEEDBACK_COUNTRY_CODE', '91');                     // added to 10-digit mobile numbers
// ---------------------------------------------------------

hooks()->add_action('lead_status_changed', 'feedback_on_lead_status_changed');

/**
 * Fired by Leads_model when a lead status changes.
 * Edit form passes the status ID, kanban / bulk passes the status NAME.
 */
function feedback_on_lead_status_changed($data)
{
    try {
        $CI     = &get_instance();
        $leadId = (int) ($data['lead_id'] ?? 0);
        $status = trim((string) ($data['new_status'] ?? ''));

        if ($leadId <= 0 || $status === '') {
            return;
        }

        if (ctype_digit($status)) {
            $row    = $CI->db->select('name')->where('id', (int) $status)->get(db_prefix() . 'leads_status')->row();
            $status = $row ? $row->name : '';
        }

        $closeNames = array_map('strtolower', array_map('trim', explode(',', FEEDBACK_CLOSE_STATUSES)));
        if (!in_array(strtolower(trim($status)), $closeNames, true)) {
            return;
        }

        $request = feedback_create_request($leadId);
        if ($request && (int) $request['send_count'] === 0) {
            feedback_send($request, false);
        }
    } catch (Throwable $e) {
        log_message('error', 'Feedback (lead close): ' . $e->getMessage());
    }
}

/**
 * Table that holds one feedback link per lead (created automatically).
 */
function feedback_table_ready()
{
    $CI    = &get_instance();
    $table = db_prefix() . 'feedback_requests';

    if (!$CI->db->table_exists($table)) {
        $CI->db->query('CREATE TABLE `' . $table . '` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `lead_id` INT(11) NOT NULL,
            `token` VARCHAR(40) NOT NULL,
            `patient_name` VARCHAR(191) NULL,
            `email` VARCHAR(191) NULL,
            `phone` VARCHAR(50) NULL,
            `branch_db` VARCHAR(100) NULL,
            `send_count` INT(11) NOT NULL DEFAULT 0,
            `last_sent_at` DATETIME NULL,
            `last_error` TEXT NULL,
            `form_data_json` LONGTEXT NULL,
            `submitted_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `lead_id` (`lead_id`),
            UNIQUE KEY `token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    }

    return $table;
}

/**
 * Create (or return the existing) feedback link for a lead.
 */
function feedback_create_request($leadId)
{
    $CI    = &get_instance();
    $table = feedback_table_ready();

    $existing = $CI->db->where('lead_id', (int) $leadId)->get($table)->row_array();
    if ($existing) {
        return $existing;
    }

    $lead = $CI->db->where('id', (int) $leadId)->get(db_prefix() . 'leads')->row_array();
    if (!$lead) {
        return null;
    }

    // Remember the branch DB so the public link + cron open the same database
    $currentDb = (string) $CI->db->database;
    $branchDb  = ($currentDb !== '' && $currentDb !== APP_DB_NAME) ? $currentDb : null;

    $CI->db->insert($table, [
        'lead_id'      => (int) $leadId,
        'token'        => bin2hex(random_bytes(12)),
        'patient_name' => trim((string) $lead['name']),
        'email'        => trim((string) ($lead['email'] ?? '')),
        'phone'        => trim((string) ($lead['phonenumber'] ?? '')),
        'branch_db'    => $branchDb,
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    return $CI->db->where('lead_id', (int) $leadId)->get($table)->row_array();
}

/**
 * Public link the patient opens.
 */
function feedback_link($request)
{
    $url = site_url('feedback/f/' . $request['token']);

    if (!empty($request['branch_db'])) {
        $url .= '?branch_db=' . rawurlencode($request['branch_db']);
    }

    return $url;
}

/**
 * Send the link by Email + SMS. Returns ['sent' => [...], 'errors' => [...]].
 */
function feedback_send($request, $isReminder = false)
{
    $CI      = &get_instance();
    $link    = feedback_link($request);
    $company = get_option('companyname');
    $name    = $request['patient_name'] !== '' ? $request['patient_name'] : 'Patient';
    $sent    = [];
    $errors  = [];

    // ---------- EMAIL ----------
    if (filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
        $subject = ($isReminder ? 'Reminder: ' : '') . 'Please complete your Fit-to-Fly form - ' . $company;
        $message = '<p>Dear ' . html_escape($name) . ',</p>'
            . '<p>Thank you for choosing ' . html_escape($company) . '. Please complete your <b>Fit-to-Fly / discharge feedback form</b>:</p>'
            . '<p><a href="' . html_escape($link) . '" style="display:inline-block;padding:10px 18px;background:#24469a;color:#fff;text-decoration:none;border-radius:4px;">Open Form</a></p>'
            . '<p>Or copy this link: ' . html_escape($link) . '</p>'
            . '<p>Wishing you a safe journey home.<br>Team ' . html_escape($company) . '</p>';

        $CI->load->model('emails_model');
        if ($CI->emails_model->send_simple_email($request['email'], $subject, $message)) {
            $sent[] = 'email';
        } else {
            $errors[] = 'Email failed (check Setup > Settings > Email)';
        }
    }

    // ---------- SMS (active gateway in Setup > Settings > SMS) ----------
    if ($request['phone'] !== '') {
        $gateway = isset($CI->app_sms) ? $CI->app_sms->get_active_gateway() : false;

        if ($gateway) {
            $phone = preg_replace('/\D+/', '', $request['phone']);
            if (strlen($phone) === 10) {
                $phone = FEEDBACK_COUNTRY_CODE . $phone;
            }
            if ($gateway['id'] === 'twilio') {
                $phone = '+' . $phone;
            }

            $sms = 'Dear ' . $name . ', thank you for choosing ' . $company . '. Please fill your discharge feedback form: ' . $link;

            unset($GLOBALS['sms_error']);
            $ok = $CI->{'sms_' . $gateway['id']}->send($phone, $sms);

            if ($ok && !isset($GLOBALS['sms_error'])) {
                $sent[] = 'sms';
            } else {
                $errors[] = 'SMS failed: ' . ($GLOBALS['sms_error'] ?? 'gateway error');
            }
        } else {
            $errors[] = 'No active SMS gateway';
        }
    }

    if (!$sent && !$errors) {
        $errors[] = 'Lead has no email or phone number';
    }

    $update = ['last_error' => $errors ? implode(' | ', $errors) : null];
    if ($sent) {
        $update['send_count']   = (int) $request['send_count'] + 1;
        $update['last_sent_at'] = date('Y-m-d H:i:s');
    }
    $CI->db->where('id', $request['id'])->update(feedback_table_ready(), $update);

    // Show it in the lead's Activity Log (with the link, so staff can copy it)
    feedback_lead_activity(
        $request['lead_id'],
        ($isReminder ? 'Fit-to-Fly form reminder' : 'Fit-to-Fly form link')
        . ($sent ? ' sent by ' . strtoupper(implode(' + ', $sent)) : ' NOT sent')
        . ': ' . $link . ($errors ? ' (' . implode(' | ', $errors) . ')' : '')
    );

    return ['sent' => $sent, 'errors' => $errors];
}

/**
 * Write a plain-text line into the lead Activity Log.
 */
function feedback_lead_activity($leadId, $text)
{
    $CI    = &get_instance();
    $table = db_prefix() . 'lead_activity_log';
    $staff = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;

    $row = [
        'leadid'          => (int) $leadId,
        'description'     => $text,
        'additional_data' => '',
        'date'            => date('Y-m-d H:i:s'),
        'staffid'         => $staff,
        'full_name'       => $staff ? get_staff_full_name($staff) : '[System]',
    ];
    if ($CI->db->field_exists('custom_activity', $table)) {
        $row['custom_activity'] = 1; // show text as-is (not a language key)
    }

    $CI->db->insert($table, $row);
}

/**
 * Find the patient (tblclients.userid) and latest appointment for a lead,
 * so the submission shows in Patient Profile -> Consent Forms.
 */
function feedback_find_patient($leadId, $email, $phone)
{
    $CI     = &get_instance();
    $result = ['patient_id' => 0, 'appointment_id' => 0, 'appointment_type_id' => 0];

    // 1) Lead converted to patient
    $client = $CI->db->select('userid')->where('leadid', (int) $leadId)->get(db_prefix() . 'clients')->row();
    if ($client) {
        $result['patient_id'] = (int) $client->userid;
    }

    // 2) Same email, 3) same mobile (only if exactly one patient matches)
    foreach (['email' => $email, 'phonenumber' => $phone] as $field => $value) {
        if ($result['patient_id'] || trim((string) $value) === '') {
            continue;
        }
        $rows = $CI->db->select('DISTINCT userid', false)->where($field, trim($value))->get(db_prefix() . 'contacts')->result_array();
        if (count($rows) === 1) {
            $result['patient_id'] = (int) $rows[0]['userid'];
        }
    }

    // Latest appointment of that patient (Appointly)
    $appointments = db_prefix() . 'appointly_appointments';
    if ($result['patient_id'] && $CI->db->table_exists($appointments)) {
        $CI->db->select('a.id, a.type_id');
        $CI->db->from($appointments . ' a');
        $CI->db->join(db_prefix() . 'contacts c', 'c.id = a.contact_id');
        $CI->db->where('c.userid', $result['patient_id']);
        $CI->db->order_by('a.date', 'DESC');
        $CI->db->limit(1);
        $appointment = $CI->db->get()->row();

        if ($appointment) {
            $result['appointment_id']      = (int) $appointment->id;
            $result['appointment_type_id'] = (int) $appointment->type_id;
        }
    }

    return $result;
}
