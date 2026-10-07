<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Receipt (Doctor Disbursement & Settlement Advice) attached to a payment
 *
 * Permissions (Setup -> Staff / Roles -> Permissions -> "Doctor Receipts"):
 *  - View (Own)    : doctor sees only receipts where he/she is the treating doctor
 *  - View (Global) : sees all doctor receipts
 *  - Create / Edit / Delete
 * Staff with Payments "View" / "Edit" / "Delete" keep the same access as before.
 *
 * @property Doctor_receipts_model $doctor_receipts_model
 * @property Payments_model $payments_model
 * @property Invoices_model $invoices_model
 */
class Doctor_receipts extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('doctor_receipts_model');
        $this->load->model('payments_model');
        $this->load->model('invoices_model');
    }

    /* ---------- permission helpers ---------- */

    private function can_view_all()
    {
        return staff_can('view', 'doctor_receipts') || staff_can('view', 'payments');
    }

    private function can_view_own()
    {
        return staff_can('view_own', 'doctor_receipts');
    }

    private function can_view_receipt($receipt)
    {
        if ($this->can_view_all()) {
            return true;
        }

        if ($this->can_view_own() && (int) $receipt->staffid === (int) get_staff_user_id()) {
            return true;
        }

        $payment = $this->payments_model->get($receipt->paymentid);

        return $payment && user_can_view_invoice($payment->invoiceid);
    }

    private function can_create()
    {
        return staff_can('create', 'doctor_receipts') || staff_can('edit', 'payments');
    }

    private function can_edit()
    {
        return staff_can('edit', 'doctor_receipts') || staff_can('edit', 'payments');
    }

    private function can_delete()
    {
        return staff_can('delete', 'doctor_receipts') || staff_can('delete', 'payments');
    }

    private function get_payment($paymentid)
    {
        $payment = $this->payments_model->get($paymentid);
        if (!$payment) {
            show_404();
        }
        $payment->invoice = $this->invoices_model->get($payment->invoiceid);

        return $payment;
    }

    /* ---------- pages ---------- */

    /**
     * Doctor Receipts list (sidebar: Sales -> Doctor Receipts)
     */
    public function index()
    {
        if (!$this->can_view_all() && !$this->can_view_own()) {
            access_denied('Doctor Receipts');
        }

        $staffid = $this->can_view_all() ? null : get_staff_user_id();

        $data['receipts']         = $this->doctor_receipts_model->get_all($staffid);
        $data['only_own']         = $staffid !== null;
        $data['can_open_payment'] = staff_can('view', 'payments');
        $data['can_edit']         = $this->can_edit();
        $data['can_delete']       = $this->can_delete();
        $data['title']            = 'Doctor Receipts';

        $this->load->view('admin/payments/doctor_receipts_list', $data);
    }

    /**
     * Add / edit modal (loaded with AJAX on the payment page)
     */
    public function modal($paymentid, $id = '')
    {
        if (($id && !$this->can_edit()) || (!$id && !$this->can_create())) {
            ajax_access_denied();
        }

        $payment = $this->get_payment($paymentid);

        $data['payment']  = $payment;
        $data['receipt']  = $id ? $this->doctor_receipts_model->get($id) : null;
        $data['defaults'] = $this->doctor_receipts_model->get_defaults($payment);
        $data['doctors']  = $this->doctor_receipts_model->get_doctors();

        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();

        $this->load->view('admin/payments/doctor_receipt_modal', $data);
    }

    public function save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $data = $this->input->post();
        $id   = !empty($data['id']) ? $data['id'] : '';
        unset($data['id']);

        if (($id && !$this->can_edit()) || (!$id && !$this->can_create())) {
            access_denied('Doctor Receipt');
        }

        $this->doctor_receipts_model->save($data, $id);

        set_alert('success', $id ? _l('updated_successfully', 'Doctor Receipt') : _l('added_successfully', 'Doctor Receipt'));
        redirect(admin_url('payments/payment/' . (int) $data['paymentid']));
    }

    /**
     * Last saved specialty / hospital / bank details for a doctor (JSON)
     */
    public function doctor_details($staffid)
    {
        if (!$this->can_create() && !$this->can_edit()) {
            ajax_access_denied();
        }

        $details = $this->doctor_receipts_model->get_last_doctor_details($staffid);
        echo json_encode($details ?: new stdClass());
    }

    public function pdf($id)
    {
        $receipt = $this->doctor_receipts_model->get($id);
        if (!$receipt) {
            show_404();
        }

        if (!$this->can_view_receipt($receipt)) {
            access_denied('Doctor Receipt');
        }

        $receipt->payment = $this->get_payment($receipt->paymentid);

        try {
            $pdf = doctor_receipt_pdf($receipt);
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

        $pdf->Output('DOCTOR-RECEIPT-DS-' . str_pad($receipt->id, 6, '0', STR_PAD_LEFT) . '.pdf', $type);
    }

    public function delete($id)
    {
        if (!$this->can_delete()) {
            access_denied('Doctor Receipt');
        }

        $receipt = $this->doctor_receipts_model->get($id);
        if (!$receipt) {
            show_404();
        }

        if ($this->doctor_receipts_model->delete($id)) {
            set_alert('success', _l('deleted', 'Doctor Receipt'));
        }

        $back = $this->input->get('from') === 'list' ? admin_url('doctor_receipts') : admin_url('payments/payment/' . $receipt->paymentid);
        redirect($back);
    }
}
