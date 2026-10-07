<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(__DIR__ . '/App_pdf.php');

class Doctor_receipt_pdf extends App_pdf
{
    protected $receipt;

    public function __construct($receipt, $tag = '')
    {
        $this->load_language($receipt->payment->invoice->clientid);

        parent::__construct();

        $this->receipt = $receipt;
        $this->tag     = $tag;

        $this->SetTitle('Doctor Disbursement & Settlement Advice DS-' . str_pad($receipt->id, 6, '0', STR_PAD_LEFT));
    }

    public function prepare()
    {
        $this->set_view_vars([
            'receipt' => $this->receipt,
            'payment' => $this->receipt->payment,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'doctor_receipt';
    }

    protected function file_path()
    {
        $customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_doctorreceiptpdf.php';
        $actualPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/doctorreceiptpdf.php';

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
