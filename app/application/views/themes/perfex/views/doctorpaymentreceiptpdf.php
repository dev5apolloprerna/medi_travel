<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Payment Receipt PDF (single page)
 * Available vars: $receipt, $pdf, $font_size, $font_name
 */

// Colours taken from the MediTravel logo
$brand  = '#0B57A4'; // deep blue (logo cross)
$accent = '#16A6D9'; // sky blue (logo text)
$ink    = '#1F2937';
$line   = 'border-top:0.6px solid ' . $brand . ';';
$bline  = 'border-bottom:0.6px solid ' . $brand . ';';

$money = function ($amount) {
    return number_format((float) $amount, 2);
};
$plain = function ($amount) {
    // whole numbers shown like 9500, decimals like 9500.50
    $amount = (float) $amount;

    return floor($amount) == $amount ? (string) (int) $amount : number_format($amount, 2, '.', '');
};
$has = function ($value) {
    return trim((string) $value) !== '';
};

$pdf->SetTextColor(0);
$pdf->SetFont($font_name, '', 9.5);

// ---------- Header: logo + company ----------
$header = '<table cellpadding="0" cellspacing="0" width="100%"><tr>'
    . '<td width="32%">' . pdf_logo_url() . '</td>'
    . '<td width="68%" align="right">'
    . '<span style="font-size:16pt;font-weight:bold;color:' . $brand . ';">' . e($receipt->company_name) . '</span><br />'
    . ($has($receipt->company_address) ? '<span style="font-size:8pt;font-weight:bold;color:' . $ink . ';">' . nl2br(e($receipt->company_address)) . '</span><br />' : '')
    . ($has($receipt->company_gst) ? '<span style="font-size:8.5pt;color:' . $ink . ';"><b style="color:' . $brand . ';">GST No :</b> ' . e($receipt->company_gst) . '</span>' : '')
    . '</td></tr></table>';
$pdf->writeHTML($header, true, false, true, false, '');
$pdf->Ln(2);

// ---------- Date / number ----------
$meta = '<table cellpadding="4" cellspacing="0" width="100%"><tr>'
    . '<td width="20%" style="' . $line . '">Date : ' . e(_d($receipt->receipt_date)) . '</td>'
    . '<td width="3%"></td>'
    . '<td width="40%" style="' . $line . $bline . '">Inv No : <b>' . e($receipt->receipt_number) . '</b></td>'
    . '<td width="37%"></td>'
    . '</tr></table>';
$pdf->writeHTML($meta, true, false, true, false, '');

// ---------- To ----------
$to_lines = [];
if ($has($receipt->doctor_address)) {
    $to_lines[] = nl2br(e(strtoupper($receipt->doctor_address)));
}
if ($has($receipt->doctor_phone)) {
    $to_lines[] = 'Phone : ' . e($receipt->doctor_phone);
}
if ($has($receipt->doctor_gst)) {
    $to_lines[] = 'GST No : ' . e($receipt->doctor_gst);
}

$doctor_name = trim((string) $receipt->doctor_name);
if ($doctor_name !== '' && stripos($doctor_name, 'dr') !== 0) {
    $doctor_name = 'Dr. ' . $doctor_name;
}

$to = '<table cellpadding="1" cellspacing="0" width="100%">'
    . '<tr><td width="6%">To,</td><td width="94%"><b>' . e($doctor_name) . '</b></td></tr>'
    . '<tr><td></td><td style="font-size:8.5pt;">' . implode('<br />', $to_lines) . '</td></tr>'
    . '</table>';
$pdf->writeHTML($to, true, false, true, false, '');
$pdf->Ln(8);

// ---------- Particulars ----------
$head_style = 'color:' . $brand . ';' . $line;
$items_html = '<table cellpadding="4" cellspacing="0" width="100%">'
    . '<tr>'
    . '<td width="70%" align="center" style="' . $head_style . '"><b><i>Particulars</i></b></td>'
    . '<td width="2.5%"></td>'
    . '<td width="27.5%" align="right" style="' . $head_style . '"><b><i>Amount(RS)</i></b></td>'
    . '</tr>';

foreach ($receipt->items as $item) {
    $items_html .= '<tr>'
        . '<td>' . e($item['description']) . '</td><td></td>'
        . '<td align="right">' . $plain($item['amount']) . '</td>'
        . '</tr>';
}
$items_html .= '</table>';
$pdf->writeHTML($items_html, true, false, true, false, '');
$pdf->Ln(1);

// ---------- Payment details ----------
$pay = '<span style="font-size:9.5pt;color:' . $brand . ';"><b><i><u>Payment Details :</u></i></b></span><br />'
    . '<span style="font-size:8.5pt;">'
    . 'Payment Date : ' . ($receipt->payment_date ? e(_d($receipt->payment_date)) : '') . '<br />'
    . 'Payment Mode : ' . e($receipt->payment_mode) . '<br />'
    . 'Transaction Ref No. : ' . e($receipt->transaction_ref) . '<br />'
    . 'Bank Ref No. : ' . e($receipt->bank_ref)
    . '</span>';
$pdf->writeHTML($pay, true, false, true, false, '');
$pdf->Ln(3);

// ---------- Remarks ----------
$remarks = '<b style="color:' . $brand . ';">Remarks :</b>' . ($has($receipt->remarks) ? ' <span style="font-size:9pt;">' . nl2br(e($receipt->remarks)) . '</span>' : '');
$pdf->writeHTML($remarks, true, false, true, false, '');

// ---------- Bottom block (totals ... print date) ----------
$render_bottom = function ($pdf) use ($receipt, $brand, $accent, $bline, $money, $plain, $has, $font_name) {
    $totals = '<table cellpadding="4" cellspacing="0" width="100%">'
        . '<tr>'
        . '<td width="70%" style="' . $bline . '"></td><td width="2.5%" style="' . $bline . '"></td>'
        . '<td width="27.5%" style="' . $bline . '"></td>'
        . '</tr>'
        . '<tr>'
        . '<td align="right" style="color:' . $brand . ';"><b>Total</b></td><td></td>'
        . '<td align="right" style="color:' . $brand . ';font-size:11pt;"><b>' . $money($receipt->total) . '</b></td>'
        . '</tr>'
        . '</table>';
    $pdf->writeHTML($totals, false, false, true, false, '');

    $words = '<table cellpadding="5" cellspacing="0" width="100%"><tr>'
        . '<td style="border-top:0.6px solid ' . $brand . ';' . $bline . 'background-color:#EEF7FC;">'
        . '<b><span style="color:' . $brand . ';">Amount In Words :</span> ' . e(amount_in_words_indian($receipt->total)) . '</b></td>'
        . '</tr></table>';
    $pdf->writeHTML($words, true, false, true, false, '');
    $pdf->Ln(1);

    if ($has($receipt->payment_status)) {
        $pdf->writeHTML('<div style="text-align:right;font-size:10.5pt;color:' . $accent . ';"><b>' . e($receipt->payment_status) . '</b></div>', true, false, true, false, '');
    }

    $footer = '';
    if ($has($receipt->company_pan)) {
        $footer .= '<b style="color:' . $brand . ';">PAN No :</b> ' . e($receipt->company_pan) . '<br />';
    }
    if ($has($receipt->terms)) {
        $footer .= e($receipt->terms);
    }
    if ($footer !== '') {
        $pdf->writeHTML('<span style="font-size:9pt;">' . $footer . '</span>', true, false, true, false, '');
    }
    $pdf->Ln(3);
    $pdf->writeHTML('<div style="text-align:center;font-size:8.5pt;color:#6B7280;">This Is a Computer Generated Receipt, Hence No Signature Or Stamp Required.</div>', true, false, true, false, '');
    $pdf->Ln(3);

    $pdf->SetFont($font_name, '', 8);
    $pdf->Cell(0, 0, 'Print Date : ' . date('d-m-Y'), 0, 1, 'L');
};

// Totals follow the content directly (no large empty gap)
$pdf->Ln(6);
$render_bottom($pdf);
