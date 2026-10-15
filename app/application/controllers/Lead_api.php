<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Simple Lead API
 * ---------------------------------------------------------------
 * POST {base_url}api/inquiry/{guid}
 *
 * Every database (main + each branch) has its own GUID, stored in
 * tbloptions as "lead_api_guid". The GUID in the URL decides which
 * database the lead is saved in.
 *
 * Body: JSON or normal form data
 *   name*, mobile* (or whatsapp), email, country, treatment, message, source
 */
class Lead_api extends App_Controller
{
    public function inquiry($guid = '')
    {
        if ($this->input->method() === 'options') {
            return $this->respond(200, ['success' => true]);
        }

        if ($this->input->method() !== 'post') {
            return $this->respond(405, ['success' => false, 'message' => 'Only POST method is allowed']);
        }

        // 1. Find the database this GUID belongs to
        $db = $this->database_for_guid($guid);
        if (!$db) {
            return $this->respond(401, ['success' => false, 'message' => 'Invalid GUID']);
        }
        $this->db = $db;

        // 2. Read request (JSON or form-data)
        $input = json_decode((string) $this->input->raw_input_stream, true);
        if (!is_array($input)) {
            $input = $this->input->post(null, true) ?: [];
        }

        // Honeypot: hidden "hp_field" must stay empty (bots fill it)
        if ($this->value($input, ['hp_field']) !== '') {
            return $this->respond(200, ['success' => true, 'message' => 'Thank you']);
        }

        $name      = $this->value($input, ['name', 'full_name']);
        $mobile    = $this->value($input, ['mobile', 'whatsapp', 'phone', 'phonenumber', 'mobile_number']);
        $email     = $this->value($input, ['email']);
        $country   = $this->value($input, ['country']);
        $treatment = $this->value($input, ['treatment']);
        $message   = $this->value($input, ['message', 'description', 'remarks']);

        // 3. Validate
        if ($name === '' || $mobile === '') {
            return $this->respond(422, ['success' => false, 'message' => 'Please enter your name and WhatsApp / phone number']);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respond(422, ['success' => false, 'message' => 'Please enter a valid email']);
        }

        // Description shown on the lead (treatment + country name + case details)
        $countryId = $this->country_id($country);
        $notes     = [];
        if ($treatment !== '') {
            $notes[] = 'Treatment needed: ' . $treatment;
        }
        if ($country !== '') {
            $notes[] = 'Country: ' . $country;
        }
        if ($message !== '') {
            $notes[] = "Brief about case:\n" . $message;
        }

        // 4. Save lead
        $lead = [
            'hash'        => app_generate_hash(),
            'name'        => $name,
            'phonenumber' => preg_replace('/[^0-9+]/', '', $mobile),
            'email'       => $email,
            'country'     => $countryId,
            'company'     => $this->value($input, ['company']),
            'city'        => $this->value($input, ['city']),
            'state'       => $this->value($input, ['state']),
            'address'     => $this->value($input, ['address']),
            'description' => nl2br(html_escape(implode("\n\n", $notes))),
            'source'      => $this->source_id($this->value($input, ['source'])),
            'status'      => $this->default_status_id(),
            'assigned'    => 0,
            'addedfrom'   => 0,
            'dateadded'   => date('Y-m-d H:i:s'),
        ];

        // Saved in its own column once the SQL (ALTER TABLE ... ADD treatment) has been run
        if ($this->db->field_exists('treatment', db_prefix() . 'leads')) {
            $lead['treatment'] = $treatment !== '' ? mb_substr($treatment, 0, 191) : null;
        }

        $this->db->insert(db_prefix() . 'leads', $lead);
        $leadId = (int) $this->db->insert_id();

        if (!$leadId) {
            log_message('error', 'Lead API insert failed: ' . json_encode($this->db->error()));

            return $this->respond(500, ['success' => false, 'message' => 'Lead could not be saved']);
        }

        $this->load->model('leads_model');
        $this->leads_model->log_lead_activity($leadId, 'Lead created via API', false, '');
        hooks()->do_action('lead_created', ['lead_id' => $leadId, 'web_to_lead_form' => false]);

        return $this->respond(200, ['success' => true, 'message' => 'Lead saved successfully', 'lead_id' => $leadId]);
    }

    /* ---------------------------------------------------------------- */

    /** Main DB first, then each branch DB from tblbranch */
    private function database_for_guid($guid)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $guid)) {
            return null;
        }

        $main = $this->load->database('default', true);
        if ($this->guid_matches($main, $guid)) {
            return $main;
        }

        if (!$main->table_exists(db_prefix() . 'branch')) {
            return null;
        }

        $branches = $main->select('branch_db, branch_db_user, branch_db_pass')
            ->where('branch_db IS NOT NULL')->where('branch_db !=', '')
            ->get(db_prefix() . 'branch')->result();

        foreach ($branches as $b) {
            $branchDb = $this->load->database([
                'hostname' => APP_DB_HOSTNAME,
                'username' => $b->branch_db_user,
                'password' => $b->branch_db_pass,
                'database' => $b->branch_db,
                'dbdriver' => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                'dbprefix' => db_prefix(),
                'db_debug' => false,
                'char_set' => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8',
                'dbcollat' => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8_general_ci',
                'pconnect' => false,
            ], true);

            if ($branchDb && $branchDb->conn_id && $this->guid_matches($branchDb, $guid)) {
                return $branchDb;
            }
        }

        return null;
    }

    private function guid_matches($db, $guid)
    {
        $row = $db->where('name', 'lead_api_guid')->get(db_prefix() . 'options')->row();

        return $row && $row->value !== '' && hash_equals(strtolower($row->value), strtolower($guid));
    }

    /** Country name ("Kenya", "UAE", "India") -> tblcountries.country_id, 0 if not found / "Other" */
    private function country_id($name)
    {
        $name = trim($name);
        if ($name === '' || strtolower($name) === 'other') {
            return 0;
        }
        if (is_numeric($name)) {
            return (int) $name;
        }

        $alias = ['uae' => 'AE', 'myanmar' => 'MM', 'usa' => 'US', 'uk' => 'GB'];
        $value = $alias[strtolower($name)] ?? $name;

        $row = $this->db->group_start()
            ->where('short_name', $value)
            ->or_where('long_name', $value)
            ->or_where('iso2', $value)
            ->or_where('iso3', $value)
            ->group_end()
            ->get(db_prefix() . 'countries')->row();

        return $row ? (int) $row->country_id : 0;
    }

    /** Source by name (created if new); default = first source */
    private function source_id($name)
    {
        $table = db_prefix() . 'leads_sources';
        $name  = $name !== '' ? $name : 'Website';

        $row = $this->db->where('name', $name)->get($table)->row();
        if ($row) {
            return (int) $row->id;
        }

        $this->db->insert($table, ['name' => mb_substr($name, 0, 150)]);

        return (int) $this->db->insert_id();
    }

    private function default_status_id()
    {
        $table = db_prefix() . 'leads_status';
        $row   = $this->db->where('isdefault', 1)->get($table)->row()
            ?: $this->db->order_by('statusorder', 'asc')->get($table, 1)->row();

        return $row ? (int) $row->id : 0;
    }

    private function value(array $input, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($input[$key]) && !is_array($input[$key]) && trim((string) $input[$key]) !== '') {
                return trim((string) $input[$key]);
            }
        }

        return '';
    }

    private function respond($code, array $body)
    {
        $this->output
            ->set_status_header($code)
            ->set_header('Access-Control-Allow-Origin: *')
            ->set_header('Access-Control-Allow-Headers: Content-Type')
            ->set_header('Access-Control-Allow-Methods: POST, OPTIONS')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($body));
    }
}
