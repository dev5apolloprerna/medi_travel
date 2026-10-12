<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(__DIR__ . '/App_pdf.php');

class Doctor_payment_receipt_pdf extends App_pdf
{
    protected $receipt;

    public function __construct($receipt, $tag = '')
    {
        load_pdf_language(get_option('active_language'));

        parent::__construct();

        $this->receipt = $receipt;
        $this->tag     = $tag;

        $this->SetTitle('Receipt ' . $receipt->receipt_number);
    }

    public function prepare()
    {
        $this->set_view_vars([
            'receipt' => $this->receipt,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'doctor_payment_receipt';
    }

    protected function file_path()
    {
        $customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_doctorpaymentreceiptpdf.php';
        $actualPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/doctorpaymentreceiptpdf.php';

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
