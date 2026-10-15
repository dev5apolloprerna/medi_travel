<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin -> Setup -> Leads -> Lead API   (admin/lead_api)
 * Shows the API URL for the database (branch) you are logged into.
 */
class Lead_api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin()) {
            access_denied('Lead API');
        }
    }

    public function index()
    {
        $guid = $this->current_guid();
        if ($guid === '') {
            $guid = $this->save_new_guid();
        }

        $data['title']   = 'Lead API';
        $data['api_url'] = site_url('api/inquiry/' . $guid);
        $this->load->view('admin/lead_api/index', $data);
    }

    /** New GUID — the old URL stops working */
    public function regenerate()
    {
        $this->save_new_guid();
        set_alert('success', 'New API URL generated. Update it wherever the old URL is used.');
        redirect(admin_url('lead_api'));
    }

    private function current_guid()
    {
        $row = $this->db->where('name', 'lead_api_guid')->get(db_prefix() . 'options')->row();

        return $row ? (string) $row->value : '';
    }

    private function save_new_guid()
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));

        update_option('lead_api_guid', $guid, 0);

        return $guid;
    }
}
