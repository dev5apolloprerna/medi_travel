<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Discharge feedback – all business logic.
 *
 *  - Module data (requests, responses, send log, settings) lives in the
 *    MAIN database, so one admin screen + one cron covers every branch.
 *  - Leads, patients, appointments and CONSENT FORMS live in the branch
 *    database the lead belongs to. The patient's answers are saved into
 *    that database's tblconsent_form_submissions, so they appear in
 *    Patient Profile -> Consent Forms -> Fit-to-Fly.
 */
class Discharge_feedback_model extends App_Model
{
    /** @var CI_DB_query_builder */
    private $main;

    private $settingsCache = null;

    private $branchConnections = [];

    private $branchList = null;

    private $schemaChecked = false;

    /** Default email: one "Click Here" button, no plain link below it */
    const DEFAULT_EMAIL_BODY = '<p>Dear {patient_name},</p>'
        . '<p>Thank you for choosing {company_name}. We hope your recovery is going well.</p>'
        . '<p>Please take a moment to complete your <strong>Fit-to-Fly medical sign-off / discharge feedback form</strong>:</p>'
        . '<p><a href="{feedback_link}" style="display:inline-block;padding:10px 22px;background:#24469a;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">Click Here</a></p>'
        . '<p>Wishing you a safe journey home.<br>Team {company_name}</p>';

    /** Email text of the first version (replaced automatically if never edited) */
    const OLD_EMAIL_BODY = '<p>Dear {patient_name},</p>'
        . '<p>Thank you for choosing {company_name}. We hope your recovery is going well.</p>'
        . '<p>Please take a moment to complete your <strong>Fit-to-Fly medical sign-off / discharge feedback form</strong>:</p>'
        . '<p><a href="{feedback_link}" style="display:inline-block;padding:10px 18px;background:#24469a;color:#ffffff;text-decoration:none;border-radius:4px;">Open the form</a></p>'
        . '<p>Or copy this link into your browser:<br>{feedback_link}</p>'
        . '<p>Wishing you a safe journey home.<br>Team {company_name}</p>';

    public function __construct()
    {
        parent::__construct();
        $this->main = $this->load->database('default', true);
    }

    private function t($name)
    {
        return db_prefix() . 'discharge_feedback_' . $name;
    }

    /**
     * Adds columns that were introduced after the first version
     * (safe to call many times).
     */
    public function ensure_schema()
    {
        if ($this->schemaChecked || !$this->main->table_exists($this->t('requests'))) {
            return;
        }
        $this->schemaChecked = true;

        $columns = [
            'requests'  => ['completed_via' => 'VARCHAR(20) NULL'],
            'responses' => [
                'patient_id'            => 'INT(11) NULL',
                'consent_pdf_id'        => 'INT(11) NULL',
                'consent_submission_id' => 'INT(11) NULL',
                'form_file'             => 'VARCHAR(191) NULL',
                'form_data_json'        => 'LONGTEXT NULL',
            ],
        ];

        // Saved email text still equal to the old default -> use the new "Click Here" version
        $this->main->where('name', 'email_body');
        $stored = $this->main->get($this->t('settings'))->row();
        if ($stored && trim((string) $stored->value) === self::OLD_EMAIL_BODY) {
            $this->main->where('name', 'email_body');
            $this->main->update($this->t('settings'), ['value' => self::DEFAULT_EMAIL_BODY]);
            $this->settingsCache = null;
        }

        foreach ($columns as $table => $fields) {
            foreach ($fields as $field => $definition) {
                if (!$this->main->field_exists($field, $this->t($table))) {
                    $this->main->query('ALTER TABLE `' . $this->t($table) . '` ADD `' . $field . '` ' . $definition);
                }
            }
        }
    }

    /* ==================================================================
     * SETTINGS
     * ================================================================== */

    public static function defaults()
    {
        return [
            'enabled'            => '1',
            // Lead status names that mean "closed / discharged" (comma separated, case-insensitive)
            'status_names'       => 'Close, Closed, Discharge, Discharged',
            // Consent template (uploads/consern_form/) the patient fills
            'form_file'          => 'Fit-to-FlyMedical.html',
            'send_email'         => '1',
            'send_sms'           => '1',
            'country_code'       => '91',
            // Daily reminders: first cron run at/after this hour (24h, server time)
            'daily_hour'         => '10',
            // Total messages per patient incl. the first one. 0 = until submitted
            'max_messages'       => '5',
            'allow_general_link' => '0',
            'start_date'         => date('Y-m-d H:i:s'),
            'cron_key'           => '',
            'last_daily_run'     => '',
            'email_subject'      => 'Please complete your Fit-to-Fly form – {company_name}',
            'email_body'         => self::DEFAULT_EMAIL_BODY,
            'sms_body'           => 'Dear {patient_name}, thank you for choosing {company_name}. Please fill your Fit-to-Fly discharge form: {feedback_link}',
        ];
    }

    public function settings()
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = self::defaults();
            if ($this->main->table_exists($this->t('settings'))) {
                foreach ($this->main->get($this->t('settings'))->result_array() as $row) {
                    $this->settingsCache[$row['name']] = $row['value'];
                }
            }
        }

        return $this->settingsCache;
    }

    public function setting($name)
    {
        $settings = $this->settings();

        return isset($settings[$name]) ? $settings[$name] : null;
    }

    public function save_setting($name, $value)
    {
        $table = $this->t('settings');
        $this->main->where('name', $name);
        if ($this->main->count_all_results($table) > 0) {
            $this->main->where('name', $name);
            $this->main->update($table, ['value' => $value]);
        } else {
            $this->main->insert($table, ['name' => $name, 'value' => $value]);
        }
        $this->settingsCache = null;
    }

    public function status_names()
    {
        $names = array_map(function ($n) {
            return mb_strtolower(trim($n));
        }, explode(',', (string) $this->setting('status_names')));

        return array_values(array_filter($names, 'strlen'));
    }

    /* ==================================================================
     * BRANCHES (multi-database setup from the Branch module)
     * ================================================================== */

    public function current_branch()
    {
        $dbName      = isset($this->db->database) ? $this->db->database : '';
        $branchTable = db_prefix() . 'branch';

        if ($dbName && $dbName !== $this->main->database && $this->main->table_exists($branchTable)) {
            $this->main->where('branch_db', $dbName);
            $row = $this->main->get($branchTable)->row_array();
            if ($row) {
                return ['id' => (int) $row['branchid'], 'name' => $row['branch']];
            }
        }

        return ['id' => 0, 'name' => 'Main'];
    }

    /**
     * Main DB + every branch DB we can connect to.
     */
    public function branches()
    {
        if ($this->branchList !== null) {
            return $this->branchList;
        }

        $list        = [['id' => 0, 'name' => 'Main', 'db' => $this->main]];
        $branchTable = db_prefix() . 'branch';

        if ($this->main->table_exists($branchTable)) {
            foreach ($this->main->get($branchTable)->result_array() as $row) {
                if (empty($row['branch_db']) || $row['branch_db'] === $this->main->database) {
                    continue;
                }
                $conn = $this->branch_connection($row);
                if ($conn) {
                    $list[] = ['id' => (int) $row['branchid'], 'name' => $row['branch'], 'db' => $conn];
                }
            }
        }

        return $this->branchList = $list;
    }

    /**
     * Database of one branch (0 = main).
     */
    public function db_for_branch($branchId)
    {
        foreach ($this->branches() as $branch) {
            if ($branch['id'] === (int) $branchId) {
                return $branch['db'];
            }
        }

        return null;
    }

    private function branch_connection($row)
    {
        $key = $row['branch_db'];
        if (array_key_exists($key, $this->branchConnections)) {
            return $this->branchConnections[$key];
        }

        $config = [
            'hostname'     => APP_DB_HOSTNAME,
            'username'     => !empty($row['branch_db_user']) ? $row['branch_db_user'] : APP_DB_USERNAME,
            'password'     => !empty($row['branch_db_pass']) ? $row['branch_db_pass'] : APP_DB_PASSWORD,
            'database'     => $key,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => false,
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8_general_ci',
            'swap_pre'     => '',
            'encrypt'      => false,
            'compress'     => false,
            'failover'     => [],
            'save_queries' => false,
        ];

        $conn = null;
        try {
            $conn = $this->load->database($config, true);
            if (!$conn || !$conn->conn_id) {
                log_message('error', 'Discharge feedback: cannot connect to branch DB ' . $key . ' (check branch_db_user / branch_db_pass in ' . db_prefix() . 'branch)');
                $conn = null;
            }
        } catch (Throwable $e) {
            log_message('error', 'Discharge feedback: branch DB ' . $key . ' – ' . $e->getMessage());
            $conn = null;
        }

        return $this->branchConnections[$key] = $conn;
    }

    /* ==================================================================
     * DISCHARGE DETECTION
     * ================================================================== */

    /**
     * @param int|string $status status ID (lead edit form) or status NAME (kanban / bulk)
     */
    public function is_discharge_status($status, $db)
    {
        $name = (string) $status;

        if (ctype_digit($name)) {
            $db->select('name');
            $db->where('id', (int) $status);
            $row  = $db->get(db_prefix() . 'leads_status')->row();
            $name = $row ? $row->name : '';
        }

        return $name !== '' && in_array(mb_strtolower(trim($name)), $this->status_names(), true);
    }

    /**
     * Create (or return the existing) feedback request for a lead.
     */
    public function enqueue_lead($leadId, $db, $branchId, $branchName)
    {
        $existing = $this->find_request($branchId, $leadId);
        if ($existing) {
            return $existing;
        }

        $db->where('id', (int) $leadId);
        $lead = $db->get(db_prefix() . 'leads')->row_array();
        if (!$lead) {
            return null;
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'branch_id'     => (int) $branchId,
            'branch_name'   => $branchName,
            'lead_id'       => (int) $leadId,
            'hash'          => bin2hex(random_bytes(10)),
            'patient_name'  => trim((string) $lead['name']),
            'email'         => trim((string) (isset($lead['email']) ? $lead['email'] : '')),
            'phone'         => trim((string) (isset($lead['phonenumber']) ? $lead['phonenumber'] : '')),
            'discharged_at' => $now,
            'status'        => 'pending',
            'send_count'    => 0,
            'created_at'    => $now,
        ];

        // INSERT IGNORE: safe if the hook and the cron run at the same moment
        $sql = preg_replace('/^INSERT/i', 'INSERT IGNORE', $this->main->insert_string($this->t('requests'), $data));
        $this->main->query($sql);

        return $this->find_request($branchId, $leadId);
    }

    private function find_request($branchId, $leadId)
    {
        $this->main->where('branch_id', (int) $branchId);
        $this->main->where('lead_id', (int) $leadId);

        return $this->main->get($this->t('requests'))->row_array();
    }

    /**
     * Closed leads in every branch without a request yet
     * (only leads closed after the module was installed).
     */
    public function scan_discharged_leads()
    {
        $names = $this->status_names();
        if (!$names) {
            return 0;
        }

        $start   = (string) $this->setting('start_date');
        $created = 0;
        $leads   = db_prefix() . 'leads';

        foreach ($this->branches() as $branch) {
            $db = $branch['db'];
            try {
                if (!$db->table_exists($leads)) {
                    continue;
                }

                $statusIds = [];
                foreach ($db->get(db_prefix() . 'leads_status')->result_array() as $status) {
                    if (in_array(mb_strtolower(trim($status['name'])), $names, true)) {
                        $statusIds[] = (int) $status['id'];
                    }
                }
                if (!$statusIds) {
                    continue;
                }

                $this->main->select('lead_id');
                $this->main->where('branch_id', $branch['id']);
                $existing = array_map('intval', array_column($this->main->get($this->t('requests'))->result_array(), 'lead_id'));

                $dateExpr = $db->field_exists('last_status_change', $leads)
                    ? 'COALESCE(last_status_change, dateadded)'
                    : 'dateadded';

                $db->select('id');
                $db->where_in('status', $statusIds);
                $db->where('lost', 0);
                $db->where('junk', 0);
                $db->where($dateExpr . ' >= ' . $db->escape($start), null, false);
                if ($existing) {
                    $db->where_not_in('id', $existing);
                }
                $db->limit(200);

                foreach ($db->get($leads)->result_array() as $lead) {
                    if ($this->enqueue_lead($lead['id'], $db, $branch['id'], $branch['name'])) {
                        $created++;
                    }
                }
            } catch (Throwable $e) {
                log_message('error', 'Discharge feedback scan (' . $branch['name'] . '): ' . $e->getMessage());
            }
        }

        return $created;
    }

    /* ==================================================================
     * PATIENT PROFILE CONSENT FORM (Fit-to-Fly)
     * ================================================================== */

    public function form_file()
    {
        return basename((string) $this->setting('form_file'));
    }

    /** All templates available in uploads/consern_form/ */
    public function form_files()
    {
        $files = glob(FCPATH . 'uploads/consern_form/*.html') ?: [];

        return array_map('basename', $files);
    }

    public function template_html()
    {
        $path = FCPATH . 'uploads/consern_form/' . $this->form_file();

        return is_file($path) ? file_get_contents($path) : null;
    }

    /** pdf_id of the template in tblconsent_form_master (per database) */
    public function consent_pdf_id($db)
    {
        $table = db_prefix() . 'consent_form_master';
        if (!$db->table_exists($table)) {
            return 0;
        }

        $file = $this->form_file();
        $db->select('pdf_id');
        $db->group_start();
        $db->where('english_file_name', $file);
        $db->or_where('gujarati_file_name', $file);
        $db->group_end();
        $row = $db->get($table)->row();

        return $row ? (int) $row->pdf_id : 0;
    }

    /**
     * Patient (tblclients.userid) + latest appointment for a lead:
     *   1) lead converted to patient   2) same email   3) same mobile (only if unique)
     */
    public function find_patient($db, $leadId, $email, $phone)
    {
        $result = ['patient_id' => 0, 'appointment_id' => 0, 'appointment_type_id' => 0];

        $db->select('userid');
        $db->where('leadid', (int) $leadId);
        $client = $db->get(db_prefix() . 'clients')->row();
        if ($client) {
            $result['patient_id'] = (int) $client->userid;
        }

        foreach (['email' => $email, 'phonenumber' => $phone] as $field => $value) {
            $value = trim((string) $value);
            if ($result['patient_id'] || $value === '') {
                continue;
            }
            $db->select('DISTINCT userid', false);
            $db->where($field, $value);
            $rows = $db->get(db_prefix() . 'contacts')->result_array();
            if (count($rows) === 1 && (int) $rows[0]['userid'] > 0) {
                $result['patient_id'] = (int) $rows[0]['userid'];
            }
        }

        $appointments = db_prefix() . 'appointly_appointments';
        if ($result['patient_id'] && $db->table_exists($appointments)) {
            $db->select('a.id, a.type_id');
            $db->from($appointments . ' a');
            $db->join(db_prefix() . 'contacts c', 'c.id = a.contact_id');
            $db->where('c.userid', $result['patient_id']);
            $db->order_by('a.date', 'DESC');
            $db->limit(1);
            $appointment = $db->get()->row();
            if ($appointment) {
                $result['appointment_id']      = (int) $appointment->id;
                $result['appointment_type_id'] = (int) $appointment->type_id;
            }
        }

        return $result;
    }

    /**
     * Was the Fit-to-Fly consent form already filled for this patient
     * in Patient Profile -> Consent Forms (during this lead's journey)?
     *
     * @return array|null the tblconsent_form_submissions row
     */
    public function profile_submission($request)
    {
        $db = $this->db_for_branch($request['branch_id']);
        if (!$db) {
            return null;
        }

        $pdfId = $this->consent_pdf_id($db);
        if (!$pdfId) {
            return null;
        }

        $patient = $this->find_patient($db, $request['lead_id'], $request['email'], $request['phone']);
        if (!$patient['patient_id']) {
            return null;
        }

        // Only count submissions made after the lead was created (this trip)
        $db->select('dateadded');
        $db->where('id', (int) $request['lead_id']);
        $lead  = $db->get(db_prefix() . 'leads')->row();
        $since = $lead && $lead->dateadded ? $lead->dateadded : $request['created_at'];

        $db->where('consent_pdf_id', $pdfId);
        $db->where('patient_id', $patient['patient_id']);
        $db->where('COALESCE(updated_at, created_at) >= ' . $db->escape($since), null, false);
        $db->order_by('id', 'DESC');
        $db->limit(1);

        return $db->get(db_prefix() . 'consent_form_submissions')->row_array() ?: null;
    }

    /**
     * If staff already filled the form in the patient profile, mark the
     * request completed so no more messages go out.
     *
     * @return bool true if completed
     */
    public function sync_with_profile($request)
    {
        if ($request['status'] === 'completed') {
            return true;
        }

        try {
            $submission = $this->profile_submission($request);
        } catch (Throwable $e) {
            log_message('error', 'Discharge feedback profile check: ' . $e->getMessage());
            $submission = null;
        }

        if (!$submission) {
            return false;
        }

        $this->main->where('id', $request['id']);
        $this->main->where('status !=', 'completed');
        $this->main->update($this->t('requests'), [
            'status'        => 'completed',
            'completed_at'  => date('Y-m-d H:i:s'),
            'completed_via' => 'profile',
        ]);

        return true;
    }

    /* ==================================================================
     * SENDING
     * ================================================================== */

    public function feedback_link($hash = '')
    {
        return site_url('discharge_feedback/patient_feedback/form' . ($hash !== '' ? '/' . $hash : ''));
    }

    /**
     * Send the link to one patient by every enabled channel.
     *
     * @return array ['sent' => [...], 'errors' => [...], 'completed' => bool]
     */
    public function send_request($request, $isReminder = false)
    {
        $this->ensure_schema();

        // Already filled in Patient Profile -> Consent Forms? Then stop.
        if ($this->sync_with_profile($request)) {
            return ['sent' => [], 'errors' => [], 'completed' => true];
        }

        $settings = $this->settings();
        $now      = date('Y-m-d H:i:s');
        $link     = $this->feedback_link($request['hash']);
        $company  = (string) get_option('companyname');
        $name     = $request['patient_name'] !== '' ? $request['patient_name'] : 'Patient';

        $mergeText = [
            '{patient_name}'  => $name,
            '{feedback_link}' => $link,
            '{company_name}'  => $company,
            '{branch_name}'   => (string) $request['branch_name'],
        ];
        $mergeHtml = array_map('html_escape', $mergeText);

        $sent      = [];
        $errors    = [];
        $hasTarget = false;

        // ---- Email ----
        if ((int) $settings['send_email'] && filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
            $hasTarget = true;
            $subject   = strtr($settings['email_subject'], $mergeText);
            if ($isReminder) {
                $subject = 'Reminder: ' . $subject;
            }
            list($ok, $err) = $this->send_email($request['email'], $subject, strtr($settings['email_body'], $mergeHtml));
            $this->log_send($request['id'], 'email', $request['email'], $isReminder, $ok, $err);
            if ($ok) {
                $sent[] = 'email';
            } else {
                $errors[] = 'Email: ' . $err;
            }
        }

        // ---- SMS ----
        if ((int) $settings['send_sms'] && $request['phone'] !== '') {
            $hasTarget = true;
            $phone     = $this->normalize_phone($request['phone']);
            list($ok, $err) = $this->send_sms($phone, strtr($settings['sms_body'], $mergeText));
            $this->log_send($request['id'], 'sms', $phone, $isReminder, $ok, $err);
            if ($ok) {
                $sent[] = 'sms';
            } else {
                $errors[] = 'SMS: ' . $err;
            }
        }

        $update = ['last_attempt_at' => $now];

        if (!$hasTarget) {
            $update['status']     = 'stopped';
            $update['last_error'] = 'Lead has no valid email / phone number (or both channels are disabled in settings).';
            $errors[]             = $update['last_error'];
        } else {
            $update['last_error'] = $errors ? implode(' | ', $errors) : null;
        }

        if ($sent) {
            $update['send_count']   = (int) $request['send_count'] + 1;
            $update['last_sent_at'] = $now;
            $update['last_channels'] = implode(',', $sent);
        }

        $this->main->where('id', $request['id']);
        $this->main->update($this->t('requests'), $update);

        // Show it on the lead (Activity Log), with the link so staff can copy it
        $this->lead_activity(
            $request,
            ($isReminder ? 'Fit-to-Fly form reminder' : 'Fit-to-Fly form link')
            . ($sent ? ' sent by ' . strtoupper(implode(' + ', $sent)) : ' NOT sent')
            . ': ' . $link
            . ($errors ? ' (' . implode(' | ', $errors) . ')' : '')
        );

        return ['sent' => $sent, 'errors' => $errors, 'completed' => false];
    }

    private function send_email($to, $subject, $html)
    {
        // Catch the usual configuration problems before trying
        $problem = $this->email_config_problem();
        if ($problem !== null) {
            return [false, $problem];
        }

        try {
            $CI = &get_instance();
            $CI->load->model('emails_model');
            $ok = $CI->emails_model->send_simple_email($to, $subject, $html);

            if ($ok) {
                return [true, null];
            }

            return [false, 'Mail server rejected the message: ' . $this->email_debug_reason()];
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * Setup > Settings > Email problems that always make sending fail.
     */
    public function email_config_problem()
    {
        $protocol = strtolower(trim((string) get_option('email_protocol')));
        $from     = trim((string) get_option('smtp_email'));

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Setup > Settings > Email: the "Email" (sender address) field is empty or invalid.';
        }
        if ($protocol === 'mail' && stripos(PHP_OS, 'WIN') === 0) {
            return 'Setup > Settings > Email: protocol is "Mail" (PHP mail), which does not work on Windows/WAMP. Choose SMTP (e.g. smtp.gmail.com, port 587, TLS, Gmail App Password).';
        }
        if ($protocol === 'smtp') {
            if (trim((string) get_option('smtp_host')) === '') {
                return 'Setup > Settings > Email: SMTP Host is empty.';
            }
            if (trim((string) get_option('smtp_port')) === '') {
                return 'Setup > Settings > Email: SMTP Port is empty (587 for TLS, 465 for SSL).';
            }
            if (trim((string) get_option('smtp_password')) === '') {
                return 'Setup > Settings > Email: SMTP Password is empty (for Gmail use a 16-letter App Password).';
            }
        }

        return null;
    }

    /**
     * The real reason from the mail library (SMTP reply, auth error ...).
     */
    private function email_debug_reason()
    {
        $CI = &get_instance();
        if (!isset($CI->email) || !method_exists($CI->email, 'print_debugger')) {
            return 'no details available.';
        }

        $text = html_entity_decode(strip_tags((string) $CI->email->print_debugger(['headers'])), ENT_QUOTES, 'UTF-8');
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text)), function ($line) {
            // keep the error lines, drop the echoed mail headers
            return $line !== '' && !preg_match('/^(Date|From|Return-Path|To|Subject|Reply-To|User-Agent|X-|Message-ID|Mime-Version|Content-|Cc|Bcc):/i', $line);
        }));

        $reason = mb_substr(implode(' ', array_slice($lines, 0, 6)), 0, 500);
        if ($reason === '') {
            $reason = 'no details returned.';
        }

        $hints = [
            '/authenticat|535|username and password|BadCredentials/i' => ' -> Wrong SMTP username/password. For Gmail you must use an App Password, not your normal password.',
            '/connect|timed out|Connection refused|getaddrinfo|php_network/i' => ' -> Cannot reach the SMTP server: check SMTP Host/Port, internet connection, or antivirus/firewall blocking port 587/465.',
            '/ssl|tls|crypto|certificate/i' => ' -> TLS/SSL problem: use port 587 with TLS (or 465 with SSL) and make sure the PHP "openssl" extension is enabled in WAMP.',
        ];
        foreach ($hints as $pattern => $hint) {
            if (preg_match($pattern, $reason)) {
                return $reason . $hint;
            }
        }

        return $reason;
    }

    private function send_sms($phone, $message)
    {
        try {
            $CI = &get_instance();
            if (!isset($CI->app_sms)) {
                return [false, 'SMS library not loaded.'];
            }
            $gateway = $CI->app_sms->get_active_gateway();
            if (!$gateway) {
                return [false, 'No active SMS gateway (Setup > Settings > SMS).'];
            }

            unset($GLOBALS['sms_error']);
            $ok = $CI->{'sms_' . $gateway['id']}->send($phone, $message);

            if (isset($GLOBALS['sms_error'])) {
                return [false, (string) $GLOBALS['sms_error']];
            }

            return [(bool) $ok, $ok ? null : 'Gateway "' . $gateway['id'] . '" returned failure.'];
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * 98765 43210 -> 919876543210 (MSG91 / Clickatell) or +919876543210 (Twilio)
     */
    public function normalize_phone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $cc     = preg_replace('/\D+/', '', (string) $this->setting('country_code'));

        if ($cc !== '') {
            if (strlen($digits) === 11 && $digits[0] === '0') {
                $digits = substr($digits, 1);
            }
            if (strlen($digits) === 10) {
                $digits = $cc . $digits;
            }
        }

        $CI      = &get_instance();
        $gateway = isset($CI->app_sms) ? $CI->app_sms->get_active_gateway() : null;

        return ($gateway && $gateway['id'] === 'twilio') ? '+' . $digits : $digits;
    }

    private function log_send($requestId, $channel, $recipient, $isReminder, $success, $error)
    {
        $this->main->insert($this->t('log'), [
            'request_id'  => (int) $requestId,
            'channel'     => $channel,
            'recipient'   => $recipient,
            'is_reminder' => $isReminder ? 1 : 0,
            'success'     => $success ? 1 : 0,
            'error'       => $error,
            'sent_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** Plain-text line in the lead's Activity Log (branch database) */
    private function lead_activity($request, $text)
    {
        try {
            $db = $this->db_for_branch($request['branch_id']);
            if (!$db) {
                return;
            }
            $table = db_prefix() . 'lead_activity_log';
            $staff = function_exists('is_staff_logged_in') && is_staff_logged_in() ? (int) get_staff_user_id() : 0;

            $row = [
                'leadid'          => (int) $request['lead_id'],
                'description'     => $text,
                'additional_data' => '',
                'date'            => date('Y-m-d H:i:s'),
                'staffid'         => $staff,
                'full_name'       => $staff ? get_staff_full_name($staff) : '[Discharge Feedback]',
            ];
            if ($db->field_exists('custom_activity', $table)) {
                $row['custom_activity'] = 1;
            }
            $db->insert($table, $row);
        } catch (Throwable $e) {
            log_message('error', 'Discharge feedback activity log: ' . $e->getMessage());
        }
    }

    /* ==================================================================
     * CRON
     * ================================================================== */

    /**
     * @param bool $forceDaily true = send today's reminders now (cron URL / "Run now")
     */
    public function run($forceDaily = false)
    {
        $this->ensure_schema();

        $result = [
            'new_discharges'      => 0,
            'first_messages'      => 0,
            'reminders'           => 0,
            'filled_in_profile'   => 0,
            'daily_run'           => false,
        ];

        if (!(int) $this->setting('enabled')) {
            $result['disabled'] = true;

            return $result;
        }

        // 1) Closed leads the status hook may have missed (any branch)
        $result['new_discharges'] = $this->scan_discharged_leads();

        // 2) First message not delivered yet (retry at most once an hour)
        $this->main->where('status', 'pending');
        $this->main->where('send_count', 0);
        $this->main->group_start();
        $this->main->where('last_attempt_at IS NULL', null, false);
        $this->main->or_where('last_attempt_at <', date('Y-m-d H:i:s', strtotime('-1 hour')));
        $this->main->group_end();
        $this->main->limit(100);
        foreach ($this->main->get($this->t('requests'))->result_array() as $request) {
            $r = $this->send_request($request, false);
            if ($r['completed']) {
                $result['filled_in_profile']++;
            } elseif ($r['sent']) {
                $result['first_messages']++;
            }
        }

        // 3) Daily reminders
        $today = date('Y-m-d');
        $due   = (int) date('G') >= (int) $this->setting('daily_hour') && $this->setting('last_daily_run') !== $today;

        if ($forceDaily || $due) {
            $result['daily_run'] = true;

            $max = (int) $this->setting('max_messages');
            $this->main->where('status', 'pending');
            $this->main->where('send_count >', 0);
            $this->main->where('DATE(last_sent_at) < ' . $this->main->escape($today), null, false); // max 1 per day
            if ($max > 0) {
                $this->main->where('send_count <', $max);
            }
            $this->main->limit(500);

            foreach ($this->main->get($this->t('requests'))->result_array() as $request) {
                $r = $this->send_request($request, true);
                if ($r['completed']) {
                    $result['filled_in_profile']++;
                } elseif ($r['sent']) {
                    $result['reminders']++;
                }
            }

            $this->save_setting('last_daily_run', $today);
        }

        return $result;
    }

    /* ==================================================================
     * PUBLIC FORM  (the consent template, e.g. Fit-to-FlyMedical.html)
     * ================================================================== */

    public function get_by_hash($hash)
    {
        if (!preg_match('/^[a-f0-9]{20,64}$/', (string) $hash)) {
            return null;
        }
        $this->main->where('hash', $hash);

        return $this->main->get($this->t('requests'))->row_array();
    }

    /**
     * Patient submitted the form:
     *   - saved into tblconsent_form_submissions (Patient Profile -> Consent Forms)
     *   - copy kept in discharge_feedback_responses
     *   - request completed (stops daily reminders)
     *
     * @return bool false if this personal link was already used
     */
    public function save_response($request, array $formData, $ip, $userAgent)
    {
        $this->ensure_schema();
        $now = date('Y-m-d H:i:s');

        if ($request) {
            $this->main->where('id', $request['id']);
            $this->main->where('status !=', 'completed');
            $this->main->update($this->t('requests'), [
                'status'        => 'completed',
                'completed_at'  => $now,
                'completed_via' => 'patient_link',
            ]);
            if ($this->main->affected_rows() === 0) {
                return false;
            }
        }

        $patientName  = trim((string) ($formData['patient_name'] ?? ($request ? $request['patient_name'] : '')));
        $json         = json_encode($formData, JSON_UNESCAPED_UNICODE);
        $patient      = ['patient_id' => 0, 'appointment_id' => 0, 'appointment_type_id' => 0];
        $pdfId        = 0;
        $submissionId = null;

        // ---- Save into Patient Profile -> Consent Forms ----
        $db = $request ? $this->db_for_branch($request['branch_id']) : null;
        if ($db) {
            try {
                $pdfId   = $this->consent_pdf_id($db);
                $patient = $this->find_patient($db, $request['lead_id'], $request['email'], $request['phone']);

                if ($pdfId && $patient['patient_id']) {
                    $table = db_prefix() . 'consent_form_submissions';
                    $key   = [
                        'consent_pdf_id' => $pdfId,
                        'patient_id'     => $patient['patient_id'],
                        'appointment_id' => $patient['appointment_id'],
                        'lang'           => 'en',
                    ];
                    $data = $key + [
                        'appointment_type_id' => $patient['appointment_type_id'],
                        'doctor_id'           => 0,
                        'patient_name'        => $patientName,
                        'doctor_name'         => '',
                        'form_data_json'      => $json,
                        'updated_at'          => $now,
                    ];

                    // Same upsert key as Consent_form::save_submission()
                    $db->where($key);
                    $existing = $db->get($table)->row();
                    if ($existing) {
                        $db->where('id', $existing->id);
                        $db->update($table, $data);
                        $submissionId = (int) $existing->id;
                    } else {
                        $data['created_at'] = $now;
                        $db->insert($table, $data);
                        $submissionId = (int) $db->insert_id();
                    }
                }
            } catch (Throwable $e) {
                log_message('error', 'Discharge feedback save to consent forms: ' . $e->getMessage());
            }
        }

        // ---- Copy in the module ----
        $this->main->insert($this->t('responses'), [
            'request_id'            => $request ? (int) $request['id'] : null,
            'branch_id'             => $request ? (int) $request['branch_id'] : 0,
            'lead_id'               => $request ? (int) $request['lead_id'] : null,
            'patient_id'            => $patient['patient_id'] ?: null,
            'consent_pdf_id'        => $pdfId ?: null,
            'consent_submission_id' => $submissionId,
            'form_file'             => $this->form_file(),
            'patient_name'          => $patientName !== '' ? $patientName : '-',
            'form_data_json'        => $json,
            'ip_address'            => $ip,
            'user_agent'            => mb_substr((string) $userAgent, 0, 255),
            'submitted_at'          => $now,
        ]);

        if ($request) {
            $this->lead_activity(
                $request,
                'Fit-to-Fly form submitted by the patient'
                . ($submissionId
                    ? ' - saved in Patient Profile > Consent Forms (patient #' . $patient['patient_id'] . ')'
                    : ' - no matching patient profile found, answers kept in Discharge Feedback > Submitted Forms')
            );
        }

        return true;
    }

    /**
     * Every named field in the template (name, type, value attribute).
     */
    public function template_fields($html)
    {
        $dom    = $this->load_dom($html);
        $fields = [];

        foreach ((new DOMXPath($dom))->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            $type = strtolower($el->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'reset', 'file'], true)) {
                continue;
            }
            $fields[$el->getAttribute('name') . '|' . $type] = [
                'name'  => $el->getAttribute('name'),
                'type'  => $type,
                'value' => $el->getAttribute('value'),
            ];
        }

        return array_values($fields);
    }

    /**
     * Read the template's fields from POST in the SAME format the
     * patient-profile Save button uses (checkbox = its value or '').
     */
    public function collect_post($html, $input)
    {
        $data = [];

        foreach ($this->template_fields($html) as $field) {
            $name   = $field['name'];
            $isList = substr($name, -2) === '[]';
            $key    = $isList ? substr($name, 0, -2) : $name;
            $posted = $input->post($key);

            if ($field['type'] === 'checkbox') {
                if ($isList) {
                    $data[$key] = is_array($posted) ? array_values(array_map('strval', $posted)) : [];
                } else {
                    $data[$key] = $posted !== null ? ($field['value'] !== '' ? $field['value'] : 'on') : '';
                }
            } elseif ($field['type'] === 'radio') {
                $data[$key] = is_string($posted) ? $posted : '';
            } elseif (is_array($posted)) {
                $data[$key] = array_map(function ($v) {
                    return mb_substr(trim((string) $v), 0, 2000);
                }, $posted);
            } else {
                $data[$key] = mb_substr(trim((string) $posted), 0, 5000);
            }
        }

        return $data;
    }

    /**
     * The template filled with $values.
     *
     * @param array $opts csrf_name, csrf_hash, error, readonly (bool)
     */
    public function render_template($html, array $values, array $opts = [])
    {
        $dom      = $this->load_dom($html);
        $xpath    = new DOMXPath($dom);
        $readonly = !empty($opts['readonly']);

        foreach ($xpath->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            $name = $el->getAttribute('name');
            $key  = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;
            $tag  = strtolower($el->nodeName);
            $type = strtolower($el->getAttribute('type'));

            if ($readonly) {
                $el->setAttribute('disabled', 'disabled');
            }
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];

            if ($type === 'checkbox' || $type === 'radio') {
                if (is_array($value)) {
                    $checked = in_array($el->getAttribute('value'), $value, true);
                } elseif ($type === 'radio') {
                    $checked = (string) $value === $el->getAttribute('value');
                } else {
                    $checked = (string) $value !== '' && (string) $value !== '0';
                }
                if ($checked) {
                    $el->setAttribute('checked', 'checked');
                } else {
                    $el->removeAttribute('checked');
                }
            } elseif ($tag === 'textarea') {
                $el->nodeValue = '';
                $el->appendChild($dom->createTextNode((string) $value));
            } elseif ($tag === 'select') {
                foreach ($el->getElementsByTagName('option') as $option) {
                    if ($option->getAttribute('value') === (string) $value) {
                        $option->setAttribute('selected', 'selected');
                    } else {
                        $option->removeAttribute('selected');
                    }
                }
            } elseif (!is_array($value)) {
                $el->setAttribute('value', (string) $value);
            }
        }

        // Use the template's own <form>, or wrap the body in one
        $form = $dom->getElementsByTagName('form')->item(0);
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$form && $body) {
            $form = $dom->createElement('form');
            while ($body->firstChild) {
                $form->appendChild($body->firstChild);
            }
            $body->appendChild($form);
        }
        if (!$form) {
            return $html;
        }

        $css = '
            .dfb-actions{max-width:900px;margin:6px auto 30px;padding:0 15mm;text-align:right}
            .dfb-submit{padding:12px 30px;border:0;border-radius:4px;background:#24469a;color:#fff;font:bold 15px Arial,Helvetica,sans-serif;letter-spacing:.3px;cursor:pointer}
            .dfb-submit:hover{background:#1c3880}
            .dfb-submit[disabled]{opacity:.6;cursor:default}
            .dfb-error{max-width:900px;margin:16px auto 0;padding:10px 14px;border-left:4px solid #c0392b;background:#fdf1f0;color:#8e2a20;font:14px Arial,Helvetica,sans-serif}
            .dfb-note{max-width:900px;margin:0 auto;padding:0 15mm;color:#6b778c;font:12px Arial,Helvetica,sans-serif}
            input[disabled],textarea[disabled],select[disabled]{color:#182033;opacity:1;-webkit-text-fill-color:#182033}
            @media (max-width:640px){
              .page{padding:18px 16px!important}
              .brand{font-size:24px!important;letter-spacing:3px!important}
              .document-title{font-size:17px!important}
              .profile-table,.profile-table tbody,.profile-table tr,.profile-table td,
              .signature-table,.signature-table tbody,.signature-table tr,.signature-table td{display:block;width:100%!important}
              .profile-table colgroup{display:none}
              .profile-table td.profile-value{margin-bottom:8px}
              .signature-left{padding-right:0!important;margin-bottom:22px}
              .dfb-actions,.dfb-note{padding:0 16px}
              .dfb-submit{width:100%}
            }
            @media print{.dfb-actions,.dfb-error,.dfb-note{display:none}}';
        $style = $dom->createElement('style');
        $style->appendChild($dom->createTextNode($css));
        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            $head->appendChild($style);
        } else {
            $form->appendChild($style);
        }

        if ($readonly) {
            return $dom->saveHTML();
        }

        $form->setAttribute('action', ''); // posts back to this same link
        $form->setAttribute('method', 'post');

        if (!empty($opts['csrf_name'])) {
            $csrf = $dom->createElement('input');
            $csrf->setAttribute('type', 'hidden');
            $csrf->setAttribute('name', $opts['csrf_name']);
            $csrf->setAttribute('value', (string) ($opts['csrf_hash'] ?? ''));
            $form->insertBefore($csrf, $form->firstChild);
        }

        if (!empty($opts['error'])) {
            $box = $dom->createElement('div');
            $box->setAttribute('class', 'dfb-error');
            $box->appendChild($dom->createTextNode($opts['error']));
            $form->insertBefore($box, $form->firstChild);
        }

        $actions = $dom->createElement('div');
        $actions->setAttribute('class', 'dfb-actions');
        $button = $dom->createElement('button');
        $button->appendChild($dom->createTextNode('Submit Form'));
        $button->setAttribute('type', 'submit');
        $button->setAttribute('class', 'dfb-submit');
        $button->setAttribute('onclick', "var b=this;setTimeout(function(){b.disabled=true;b.textContent='Submitting...';},10);");
        $actions->appendChild($button);
        $form->appendChild($actions);

        $note = $dom->createElement('p');
        $note->setAttribute('class', 'dfb-note');
        $note->appendChild($dom->createTextNode('Your answers are sent securely to ' . (get_option('companyname') ?: 'MediTravel') . '. You can submit this form once.'));
        $form->appendChild($note);

        return $dom->saveHTML();
    }

    private function load_dom($html)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        foreach (iterator_to_array($dom->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $dom->removeChild($node);
            }
        }

        return $dom;
    }

    /* ==================================================================
     * ADMIN
     * ================================================================== */

    public function get_requests($status = '')
    {
        if ($status !== '') {
            $this->main->where('status', $status);
        }
        $this->main->order_by('id', 'DESC');
        $this->main->limit(1000);

        return $this->main->get($this->t('requests'))->result_array();
    }

    public function get_request($id)
    {
        $this->main->where('id', (int) $id);

        return $this->main->get($this->t('requests'))->row_array();
    }

    public function set_request_status($id, $status)
    {
        $this->main->where('id', (int) $id);
        $this->main->update($this->t('requests'), ['status' => $status]);
    }

    public function stats()
    {
        $stats = ['pending' => 0, 'completed' => 0, 'stopped' => 0];
        $this->main->select('status, COUNT(*) AS total');
        $this->main->group_by('status');
        foreach ($this->main->get($this->t('requests'))->result_array() as $row) {
            $stats[$row['status']] = (int) $row['total'];
        }

        return $stats;
    }

    public function get_responses()
    {
        $this->main->select('r.*, q.branch_name, q.email, q.phone');
        $this->main->from($this->t('responses') . ' r');
        $this->main->join($this->t('requests') . ' q', 'q.id = r.request_id', 'left');
        $this->main->order_by('r.id', 'DESC');
        $this->main->limit(1000);

        return $this->main->get()->result_array();
    }

    public function get_response($id)
    {
        $this->main->select('r.*, q.branch_name, q.email, q.phone');
        $this->main->from($this->t('responses') . ' r');
        $this->main->join($this->t('requests') . ' q', 'q.id = r.request_id', 'left');
        $this->main->where('r.id', (int) $id);

        return $this->main->get()->row_array();
    }

    public function get_logs($requestId)
    {
        $this->main->where('request_id', (int) $requestId);
        $this->main->order_by('id', 'DESC');

        return $this->main->get($this->t('log'))->result_array();
    }
}
