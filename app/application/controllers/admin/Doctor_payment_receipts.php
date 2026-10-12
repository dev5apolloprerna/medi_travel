<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Payment Receipt (GST receipt in the same format as a tax invoice)
 * Opened from Setup -> Staff -> doctor row -> "Generate Receipt"
 *
 * Uses the existing "Doctor Receipts" permission:
 *  - View (Own)    : doctor sees only his / her own receipts
 *  - View (Global) : all receipts
 *  - Create / Edit / Delete
 *
 * @property Doctor_payment_receipts_model $doctor_payment_receipts_model
 */
class Doctor_payment_receipts extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('doctor_payment_receipts_model');
        $this->load->model('staff_model');
    }

    /* ---------- permission helpers ---------- */

    private function can_view_all()
    {
        return is_admin() || staff_can('view', 'doctor_receipts');
    }

    private function can_view_own()
    {
        return staff_can('view_own', 'doctor_receipts');
    }

    private function can_create()
    {
        return is_admin() || staff_can('create', 'doctor_receipts');
    }

    private function can_edit()
    {
        return is_admin() || staff_can('edit', 'doctor_receipts');
    }

    private function can_delete()
    {
        return is_admin() || staff_can('delete', 'doctor_receipts');
    }

    private function get_doctor($staffid)
    {
        $member = $this->staff_model->get($staffid);
        if (!$member) {
            show_404();
        }
        if (!is_doctor_role($member->role)) {
            set_alert('warning', 'Receipts can be generated only for staff members with the Doctor role.');
            redirect(admin_url('staff'));
        }

        return $member;
    }

    /* ---------- pages ---------- */

    /**
     * List of receipts (all, or of one doctor with ?staffid=X)
     */
    public function index()
    {
        if (!$this->can_view_all() && !$this->can_view_own()) {
            access_denied('Doctor Receipts');
        }

        $staffid = (int) $this->input->get('staffid');
        if (!$this->can_view_all()) {
            $staffid = get_staff_user_id();
        }

        $data['receipts']   = $this->doctor_payment_receipts_model->get_all($staffid ?: null);
        $data['member']     = $staffid ? $this->staff_model->get($staffid) : null;
        $data['can_create'] = $this->can_create();
        $data['can_edit']   = $this->can_edit();
        $data['can_delete'] = $this->can_delete();
        $data['title']      = 'Doctor Payment Receipts';

        $this->load->view('admin/staff/doctor_payment_receipts_list', $data);
    }

    /**
     * Create form for a doctor:  admin/doctor_payment_receipts/create/{staffid}
     */
    public function create($staffid)
    {
        if (!$this->can_create()) {
            access_denied('Doctor Receipts');
        }

        $member = $this->get_doctor($staffid);

        // A receipt can be generated only once for a doctor
        $existing = $this->doctor_payment_receipts_model->get_by_staff($member->staffid);
        if ($existing) {
            set_alert('warning', 'Receipt ' . $existing->receipt_number . ' is already generated for this doctor. It can be generated only once.');
            redirect(admin_url('doctor_payment_receipts?staffid=' . $member->staffid));
        }

        if ($this->input->post()) {
            $post            = $this->input->post();
            $post['staffid'] = $member->staffid;
            $id              = $this->doctor_payment_receipts_model->save($post);

            set_alert('success', _l('added_successfully', 'Receipt'));
            redirect(admin_url('doctor_payment_receipts?staffid=' . $member->staffid . '&open=' . $id));
        }

        $data['member']  = $member;
        $data['receipt'] = (object) $this->doctor_payment_receipts_model->get_defaults($member);
        $data['title']   = 'Generate Receipt - ' . $member->firstname . ' ' . $member->lastname;

        $this->load->view('admin/staff/doctor_payment_receipt', $data);
    }

    public function edit($id)
    {
        if (!$this->can_edit()) {
            access_denied('Doctor Receipts');
        }

        $receipt = $this->doctor_payment_receipts_model->get($id);
        if (!$receipt) {
            show_404();
        }
        $member = $this->staff_model->get($receipt->staffid);

        if ($this->input->post()) {
            $post            = $this->input->post();
            $post['staffid'] = $receipt->staffid;
            $this->doctor_payment_receipts_model->save($post, $id);

            set_alert('success', _l('updated_successfully', 'Receipt'));
            redirect(admin_url('doctor_payment_receipts?staffid=' . $receipt->staffid . '&open=' . $id));
        }

        $data['member']  = $member;
        $data['receipt'] = $receipt;
        $data['title']   = 'Edit Receipt ' . $receipt->receipt_number;

        $this->load->view('admin/staff/doctor_payment_receipt', $data);
    }

    /**
     * Next number for a date (JSON) – used when the receipt date changes financial year
     */
    public function next_number()
    {
        if (!$this->can_create()) {
            ajax_access_denied();
        }
        $date = $this->input->get('date') ? to_sql_date($this->input->get('date')) : date('Y-m-d');
        echo json_encode(['number' => $this->doctor_payment_receipts_model->next_number($date)]);
    }

    public function pdf($id)
    {
        $receipt = $this->doctor_payment_receipts_model->get($id);
        if (!$receipt) {
            show_404();
        }

        if (!$this->can_view_all()
            && !($this->can_view_own() && (int) $receipt->staffid === (int) get_staff_user_id())
            && !$this->can_create()) {
            access_denied('Doctor Receipts');
        }

        try {
            $pdf = doctor_payment_receipt_pdf($receipt);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';
        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }
        if ($this->input->get('print')) {
            $type = 'I';
        }

        $pdf->Output('Receipt_' . str_replace(['/', '\\', ' '], '_', $receipt->receipt_number) . '.pdf', $type);
    }

    public function delete($id)
    {
        if (!$this->can_delete()) {
            access_denied('Doctor Receipts');
        }

        $receipt = $this->doctor_payment_receipts_model->get($id);
        if (!$receipt) {
            show_404();
        }

        if ($this->doctor_payment_receipts_model->delete($id)) {
            set_alert('success', _l('deleted', 'Receipt'));
        }

        redirect(admin_url('doctor_payment_receipts?staffid=' . $receipt->staffid));
    }
}
