<?php defined('BASEPATH') or exit('No direct script access allowed');

class Consent_form_model extends App_Model
{
    private $master = 'tblconsent_form_master';

    /**
     * Return every consent form. Consent forms are available to all
     * appointment types and therefore have no mapping-table join.
     */
    public function get_all_forms()
    {
        return $this->db
            ->select('pdf_id, pdf_name, english_file_name, gujarati_file_name')
            ->from($this->master)
            ->order_by('pdf_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function get($id)
    {
        return $this->db->where('pdf_id', (int)$id)->get($this->master)->row_array();
    }
}
