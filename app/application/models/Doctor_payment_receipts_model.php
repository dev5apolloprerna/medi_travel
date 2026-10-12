<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Payment Receipt (simple GST tax-invoice style receipt issued to a doctor)
 * Generated from Setup -> Staff -> (doctor row) -> "Generate Receipt"
 */
class Doctor_payment_receipts_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->maybe_create_table();
    }

    public function table()
    {
        return db_prefix() . 'doctor_payment_receipts';
    }

    private function maybe_create_table()
    {
        if ($this->db->table_exists($this->table())) {
            // Upgrade tables created by the first version
            $columns = [
                'company_name'    => 'varchar(191) DEFAULT NULL',
                'company_address' => 'text',
                'company_gst'     => 'varchar(50) DEFAULT NULL',
            ];
            foreach ($columns as $column => $definition) {
                if (!$this->db->field_exists($column, $this->table())) {
                    $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `' . $column . '` ' . $definition . ' AFTER `terms`');
                }
            }

            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS `' . $this->table() . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `staffid` int(11) NOT NULL,
            `receipt_number` varchar(100) NOT NULL,
            `receipt_date` date NOT NULL,
            `doctor_name` varchar(191) DEFAULT NULL,
            `doctor_address` text,
            `doctor_gst` varchar(50) DEFAULT NULL,
            `doctor_pan` varchar(20) DEFAULT NULL,
            `doctor_phone` varchar(50) DEFAULT NULL,
            `items` text,
            `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
            `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
            `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
            `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
            `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
            `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
            `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
            `total` decimal(15,2) NOT NULL DEFAULT 0.00,
            `payment_date` date DEFAULT NULL,
            `payment_mode` varchar(100) DEFAULT NULL,
            `transaction_ref` varchar(191) DEFAULT NULL,
            `bank_ref` varchar(191) DEFAULT NULL,
            `remarks` text,
            `payment_status` varchar(50) DEFAULT NULL,
            `company_name` varchar(191) DEFAULT NULL,
            `company_address` text,
            `company_gst` varchar(50) DEFAULT NULL,
            `company_pan` varchar(20) DEFAULT NULL,
            `terms` text,
            `addedfrom` int(11) DEFAULT NULL,
            `datecreated` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `staffid` (`staffid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
    }

    public function get($id)
    {
        $this->db->where('id', $id);
        $row = $this->db->get($this->table())->row();
        if ($row) {
            $row->items = $this->decode_items($row->items);
            // Header always shows the current company details from Settings
            foreach ($this->company_defaults() as $key => $value) {
                $row->$key = $value;
            }
        }

        return $row;
    }

    /**
     * The receipt already generated for a doctor (only one receipt per doctor is allowed)
     */
    public function get_by_staff($staffid)
    {
        $this->db->select('id, receipt_number');
        $this->db->where('staffid', (int) $staffid);
        $this->db->order_by('id', 'asc');
        $this->db->limit(1);

        return $this->db->get($this->table())->row();
    }

    /**
     * Staff ids that already have a receipt (used by the staff list)
     */
    public function staff_with_receipt()
    {
        $ids = [];
        foreach ($this->db->select('DISTINCT staffid', false)->get($this->table())->result() as $r) {
            $ids[] = (int) $r->staffid;
        }

        return $ids;
    }

    public function get_all($staffid = null)
    {
        if ($staffid) {
            $this->db->where('staffid', $staffid);
        }
        $this->db->order_by('id', 'desc');

        return $this->db->get($this->table())->result();
    }

    /**
     * Financial year label (April -> March), e.g. 2026-2027
     */
    public function financial_year($date = null)
    {
        $ts    = $date ? strtotime($date) : time();
        $year  = (int) date('Y', $ts);
        $start = (int) date('n', $ts) >= 4 ? $year : $year - 1;

        return $start . '-' . ($start + 1);
    }

    /**
     * Next receipt number, e.g. DR/2026-2027/00001 (sequence restarts each financial year)
     */
    public function next_number($date = null)
    {
        $prefix = 'DR/' . $this->financial_year($date) . '/';

        $this->db->select('receipt_number');
        $this->db->like('receipt_number', $prefix, 'after');
        $rows = $this->db->get($this->table())->result();

        $max = 0;
        foreach ($rows as $r) {
            $n = (int) substr($r->receipt_number, strlen($prefix));
            if ($n > $max) {
                $max = $n;
            }
        }

        return $prefix . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Company (issuer) details printed in the receipt header.
     * Always taken from Setup -> Settings -> Company.
     */
    public function company_defaults()
    {
        $name = trim((string) get_option('invoice_company_name'));
        if ($name === '') {
            $name = get_option('companyname');
        }

        $address = implode(', ', array_filter(array_map('trim', [
            (string) get_option('invoice_company_address'),
            (string) get_option('invoice_company_city'),
            (string) get_option('company_state'),
            (string) get_option('invoice_company_postal_code'),
        ])));

        return [
            'company_name'    => $name,
            'company_address' => $address,
            'company_gst'     => get_option('company_vat'),
            'company_pan'     => get_option('company_pan') ?: get_option('doctor_payment_receipt_company_pan'),
        ];
    }

    /**
     * Default values for a new receipt for the given doctor (staff)
     */
    public function get_defaults($member)
    {
        $today   = date('Y-m-d');
        $company = $this->company_defaults();

        $defaults = [
            'receipt_number' => $this->next_number($today),
            'receipt_date'   => $today,
            'doctor_name'    => trim($member->firstname . ' ' . $member->lastname),
            'doctor_address' => '',
            'doctor_gst'     => '',
            'doctor_phone'   => $member->phonenumber,
            'items'          => [[
                'description' => 'Doctor Consultation Fees',
                'amount'      => '',
            ]],
            'payment_date'    => $today,
            'payment_mode'    => '',
            'transaction_ref' => '',
            'bank_ref'        => '',
            'remarks'         => '',
            'payment_status'  => 'Payment Received',
            'company_name'    => $company['company_name'],
            'company_address' => $company['company_address'],
            'company_gst'     => $company['company_gst'],
            'company_pan'     => $company['company_pan'],
            'terms'           => get_option('doctor_payment_receipt_terms') !== ''
                ? get_option('doctor_payment_receipt_terms')
                : 'All Fees Are Non Refundable And Non Transferable.',
        ];

        // Re-use the doctor's details from the last receipt so they are typed only once
        $this->db->where('staffid', $member->staffid);
        $this->db->order_by('id', 'desc');
        $this->db->limit(1);
        $last = $this->db->get($this->table())->row();

        if ($last) {
            foreach (['doctor_name', 'doctor_address', 'doctor_gst', 'doctor_phone', 'payment_mode'] as $f) {
                if (trim((string) $last->$f) !== '') {
                    $defaults[$f] = $last->$f;
                }
            }
            $items = $this->decode_items($last->items);
            if (!empty($items[0]['description'])) {
                $defaults['items'] = [['description' => $items[0]['description'], 'amount' => '']];
            }
        }

        return $defaults;
    }

    public function save($data, $id = '')
    {
        // Single particulars line, no GST
        $description = trim((string) ($data['item_description'] ?? ''));
        $subtotal    = round((float) str_replace(',', '', (string) ($data['item_amount'] ?? 0)), 2);
        $items       = [['description' => $description, 'amount' => $subtotal]];

        $receipt_date = !empty($data['receipt_date']) ? to_sql_date($data['receipt_date']) : date('Y-m-d');

        $row = [
            'staffid'         => (int) $data['staffid'],
            'receipt_number'  => trim((string) ($data['receipt_number'] ?? '')) ?: $this->next_number($receipt_date),
            'receipt_date'    => $receipt_date,
            'doctor_name'     => trim((string) ($data['doctor_name'] ?? '')),
            'doctor_address'  => trim((string) ($data['doctor_address'] ?? '')),
            'doctor_gst'      => strtoupper(trim((string) ($data['doctor_gst'] ?? ''))),
            'doctor_phone'    => trim((string) ($data['doctor_phone'] ?? '')),
            'items'           => json_encode($items),
            'subtotal'        => $subtotal,
            'cgst_rate'       => 0,
            'sgst_rate'       => 0,
            'igst_rate'       => 0,
            'cgst_amount'     => 0,
            'sgst_amount'     => 0,
            'igst_amount'     => 0,
            'total'           => $subtotal,
            'payment_date'    => !empty($data['payment_date']) ? to_sql_date($data['payment_date']) : null,
            'payment_mode'    => trim((string) ($data['payment_mode'] ?? '')),
            'transaction_ref' => trim((string) ($data['transaction_ref'] ?? '')),
            'bank_ref'        => trim((string) ($data['bank_ref'] ?? '')),
            'remarks'         => trim((string) ($data['remarks'] ?? '')),
            'payment_status'  => trim((string) ($data['payment_status'] ?? '')),
            'terms'           => trim((string) ($data['terms'] ?? '')),
        ];

        // Remember the note for the next receipt
        update_option('doctor_payment_receipt_terms', $row['terms']);

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update($this->table(), $row);

            return (int) $id;
        }

        // Generate only once: one receipt per doctor
        // (also covers double click on "Generate Receipt" or a browser refresh)
        $existing = $this->get_by_staff($row['staffid']);
        if ($existing) {
            return (int) $existing->id;
        }

        $row['addedfrom']   = get_staff_user_id();
        $row['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table(), $row);

        return (int) $this->db->insert_id();
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete($this->table());

        return $this->db->affected_rows() > 0;
    }

    private function decode_items($json)
    {
        $items = json_decode((string) $json, true);

        return is_array($items) ? $items : [];
    }
}
