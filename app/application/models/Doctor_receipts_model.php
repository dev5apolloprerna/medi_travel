<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Receipt (Doctor Disbursement & Settlement Advice)
 * One payment can have one or more doctor receipts (one per treating doctor).
 */
class Doctor_receipts_model extends App_Model
{
    /** Fee columns that make up the gross doctor remuneration (Section A) */
    public const FEE_FIELDS = [
        'tele_consult_fee',
        'preop_eval_fee',
        'surgical_fee',
        'implant_fee',
        'co_surgeon_fee',
        'inpatient_visits_fee',
        'post_op_fee',
    ];

    public const QTY_FIELDS = [
        'implant_qty',
        'inpatient_visits_qty',
        'post_op_sessions',
    ];

    public const TEXT_FIELDS = [
        'currency',
        'doctor_name',
        'doctor_specialty',
        'hospital_name',
        'bank_name',
        'account_number',
        'bank_routing',
        'patient_name',
        'passport_last4',
        'crm_patient_id',
        'clinical_diagnosis',
        'procedure_name',
    ];

    public const DATE_FIELDS = [
        'statement_date',
        'surgery_date',
        'discharge_date',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->maybe_create_table();
    }

    private function table()
    {
        return db_prefix() . 'doctor_receipts';
    }

    private function maybe_create_table()
    {
        if ($this->db->table_exists($this->table())) {
            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS `' . $this->table() . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `paymentid` int(11) NOT NULL,
            `invoiceid` int(11) DEFAULT NULL,
            `clientid` int(11) DEFAULT NULL,
            `staffid` int(11) DEFAULT NULL,
            `statement_date` date DEFAULT NULL,
            `currency` varchar(10) DEFAULT NULL,
            `doctor_name` varchar(191) DEFAULT NULL,
            `doctor_specialty` varchar(191) DEFAULT NULL,
            `hospital_name` varchar(191) DEFAULT NULL,
            `bank_name` varchar(191) DEFAULT NULL,
            `account_number` varchar(100) DEFAULT NULL,
            `bank_routing` varchar(100) DEFAULT NULL,
            `patient_name` varchar(191) DEFAULT NULL,
            `passport_last4` varchar(10) DEFAULT NULL,
            `crm_patient_id` varchar(100) DEFAULT NULL,
            `clinical_diagnosis` text,
            `procedure_name` varchar(255) DEFAULT NULL,
            `surgery_date` date DEFAULT NULL,
            `discharge_date` date DEFAULT NULL,
            `tele_consult_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `preop_eval_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `surgical_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `implant_qty` int(11) NOT NULL DEFAULT 0,
            `implant_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `co_surgeon_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `inpatient_visits_qty` int(11) NOT NULL DEFAULT 0,
            `inpatient_visits_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `post_op_sessions` int(11) NOT NULL DEFAULT 0,
            `post_op_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
            `total` decimal(15,2) NOT NULL DEFAULT 0.00,
            `addedfrom` int(11) DEFAULT NULL,
            `datecreated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `paymentid` (`paymentid`),
            KEY `staffid` (`staffid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
    }

    public function get($id)
    {
        $this->db->where('id', $id);

        return $this->db->get($this->table())->row();
    }

    /**
     * All receipts, or only one doctor's receipts when $staffid is given
     */
    public function get_all($staffid = null)
    {
        if ($staffid) {
            $this->db->where('staffid', $staffid);
        }
        $this->db->order_by('id', 'desc');

        return $this->db->get($this->table())->result();
    }

    public function get_by_payment($paymentid)
    {
        $this->db->where('paymentid', $paymentid);
        $this->db->order_by('id', 'asc');

        return $this->db->get($this->table())->result();
    }

    /**
     * Last saved doctor details (specialty, hospital, bank) for a doctor,
     * used to pre-fill the form so bank details are typed only once.
     */
    public function get_last_doctor_details($staffid)
    {
        $this->db->select('doctor_name, doctor_specialty, hospital_name, bank_name, account_number, bank_routing');
        $this->db->where('staffid', $staffid);
        $this->db->order_by('id', 'desc');
        $this->db->limit(1);

        return $this->db->get($this->table())->row();
    }

    /**
     * Insert or update a doctor receipt
     * @param  array $data  posted data
     * @param  mixed $id    receipt id when updating
     * @return mixed        receipt id
     */
    public function save($data, $id = '')
    {
        $row = [
            'paymentid' => (int) $data['paymentid'],
            'staffid'   => !empty($data['staffid']) ? (int) $data['staffid'] : null,
        ];

        foreach (self::TEXT_FIELDS as $field) {
            $row[$field] = isset($data[$field]) ? trim($data[$field]) : null;
        }

        // Doctor name is printed as "Dr. <name>", don't double the prefix
        $row['doctor_name'] = preg_replace('/^\s*dr\.?\s+/i', '', (string) $row['doctor_name']);

        foreach (self::DATE_FIELDS as $field) {
            $row[$field] = !empty($data[$field]) ? to_sql_date($data[$field]) : null;
        }

        $total = 0;
        foreach (self::FEE_FIELDS as $field) {
            $row[$field] = isset($data[$field]) && $data[$field] !== '' ? number_format((float) $data[$field], 2, '.', '') : 0;
            $total += (float) $row[$field];
        }
        $row['total'] = number_format($total, 2, '.', '');

        foreach (self::QTY_FIELDS as $field) {
            $row[$field] = isset($data[$field]) && $data[$field] !== '' ? (int) $data[$field] : 0;
        }

        // Link to invoice / patient from the payment
        $this->db->select('invoiceid');
        $this->db->where('id', $row['paymentid']);
        $payment = $this->db->get(db_prefix() . 'invoicepaymentrecords')->row();
        if ($payment) {
            $row['invoiceid'] = $payment->invoiceid;
            $this->db->select('clientid');
            $this->db->where('id', $payment->invoiceid);
            $invoice = $this->db->get(db_prefix() . 'invoices')->row();
            $row['clientid'] = $invoice ? $invoice->clientid : null;
        }

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update($this->table(), $row);

            return $id;
        }

        $row['addedfrom']   = get_staff_user_id();
        $row['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table(), $row);

        return $this->db->insert_id();
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete($this->table());

        return $this->db->affected_rows() > 0;
    }

    /**
     * Doctors list for the dropdown (same rule used on the patient profile)
     */
    public function get_doctors()
    {
        $this->db->select('staffid, firstname, lastname');
        $this->db->where('active', 1);
        $this->db->group_start();
        $this->db->where('role', 1);
        $this->db->or_where('admin', 1);
        $this->db->group_end();
        $this->db->order_by('firstname', 'asc');

        return $this->db->get(db_prefix() . 'staff')->result_array();
    }

    /**
     * Default values for a new doctor receipt, taken from the payment / patient
     */
    public function get_defaults($payment)
    {
        $clientid = $payment->invoice->clientid;

        $defaults = [
            'statement_date'     => $payment->date,
            'currency'           => $payment->invoice->currency_name,
            'patient_name'       => get_company_name($clientid),
            'crm_patient_id'     => $clientid,
            'clinical_diagnosis' => '',
            'staffid'            => '',
        ];

        $this->db->select('diagnosis');
        $this->db->where('userid', $clientid);
        $medical = $this->db->get(db_prefix() . 'medical_history')->row();
        if ($medical && !empty($medical->diagnosis)) {
            $defaults['clinical_diagnosis'] = $medical->diagnosis;
        }

        // Pre-select the doctor of the patient's latest treated appointment
        if ($this->db->table_exists(db_prefix() . 'appointly_appointments')) {
            $this->db->select('t.staff');
            $this->db->from(db_prefix() . 'appointment_treatment t');
            $this->db->join(db_prefix() . 'appointly_appointments a', 'a.id = t.appointment_id', 'inner');
            $this->db->join(db_prefix() . 'contacts c', 'c.id = a.contact_id', 'inner');
            $this->db->where('c.userid', $clientid);
            $this->db->where('t.staff >', 0);
            $this->db->order_by('t.id', 'desc');
            $this->db->limit(1);
            $treatment = $this->db->get()->row();
            if ($treatment) {
                $defaults['staffid'] = $treatment->staff;
            }
        }

        return (object) $defaults;
    }
}
