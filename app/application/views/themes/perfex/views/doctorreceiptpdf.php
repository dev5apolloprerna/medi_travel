<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Doctor Disbursement & Settlement Advice (Doctor Receipt)
 * Available vars: $receipt, $payment, $pdf, $font_size, $font_name
 */

$navy   = '#0F2C59';
$teal   = '#0E7490';
$text   = '#2D3748';
$muted  = '#4A5568';

$currency = !empty($receipt->currency) ? $receipt->currency : $payment->invoice->currency_name;

$money = function ($amount) use ($currency) {
    return (float) $amount > 0 ? app_format_money($amount, $currency) : '-';
};
$val = function ($value) {
    $value = trim((string) $value);

    return $value !== '' ? e($value) : '-';
};
$date = function ($value) {
    return !empty($value) ? _d($value) : '-';
};

$statement_no = 'DS-' . str_pad($receipt->id, 6, '0', STR_PAD_LEFT);
$doctor_name  = trim((string) $receipt->doctor_name) !== '' ? 'Dr. ' . e($receipt->doctor_name) : '-';
$passport     = trim((string) $receipt->passport_last4) !== '' ? 'XXXX' . e($receipt->passport_last4) : '-';

$basis_qty = function ($label, $qty) {
    return (int) $qty > 0 ? $label . ' (' . (int) $qty . ')' : $label;
};

$pdf->SetTextColor(0);

// ---------- Title ----------
$title = '<div style="text-align:center;font-size:15px;font-weight:bold;color:' . $navy . ';">Doctor Disbursement &amp; Settlement Advice</div>';
$pdf->writeHTMLCell(0, '', '', '', $title, 0, 1, false, true, 'C', true);
$pdf->Ln(4);

// ---------- Consultant / Patient blocks ----------
$label = function ($name) use ($muted) {
    return '<span style="color:' . $muted . ';">' . $name . ':</span> ';
};

$doctor_block = '<span style="font-weight:bold;color:' . $teal . ';font-size:9.5px;">CONSULTANT / DOCTOR DETAILS:</span><br />'
    . $label('Treating Specialist') . $doctor_name . '<br />'
    . $label('Specialty / Dept') . $val($receipt->doctor_specialty) . '<br />'
    . $label('Hospital / OT Facility') . $val($receipt->hospital_name) . '<br />'
    . $label('Beneficiary Bank') . $val($receipt->bank_name) . '<br />'
    . $label('Account Number') . $val($receipt->account_number) . '<br />'
    . $label('Routing / IFSC / SWIFT') . $val($receipt->bank_routing);

$patient_block = '<span style="font-weight:bold;color:' . $teal . ';font-size:9.5px;">PATIENT CASE RECONCILIATION:</span><br />'
    . $label('Patient Name') . $val($receipt->patient_name) . '<br />'
    . $label('Passport ID') . $passport . '<br />'
    . $label('Internal File ID') . $val($receipt->crm_patient_id) . '<br />'
    . $label('Clinical Diagnosis') . $val($receipt->clinical_diagnosis) . '<br />'
    . $label('Procedure Executed') . $val($receipt->procedure_name) . '<br />'
    . $label('Surgery Date') . $date($receipt->surgery_date) . ' | Discharge: ' . $date($receipt->discharge_date);

// ---------- Settlement table rows ----------
$row_white = '#FFFFFF';
$indent    = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
$row_alt   = '#F7FAFC';

$rows = [
    // [particulars, basis, amount, bg]
    ['&bull;&nbsp; Consultation', '', '', $row_white],
    [$indent . '&ndash;&nbsp; Pre-Travel Tele-Consultation / Second Opinion', 'Flat Fee', $money($receipt->tele_consult_fee), $row_white],
    [$indent . '&ndash;&nbsp; In-Person Pre-Op Comprehensive Evaluation', 'Flat Fee', $money($receipt->preop_eval_fee), $row_white],
    ['&bull;&nbsp; Primary Surgical &amp; Procedural Fee', 'Per Procedure', $money($receipt->surgical_fee), $row_alt],
    [$indent . '&ndash;&nbsp; Implant charges', $basis_qty('No. of implants used', $receipt->implant_qty), $money($receipt->implant_fee), $row_white],
    [$indent . '&ndash;&nbsp; Co-Surgeon / Assistant Surgeon Charges', 'Per Procedure', $money($receipt->co_surgeon_fee), $row_white],
    ['&bull;&nbsp; Inpatient Specialist Ward/ICU Rounds', $basis_qty('Per Day / Per Visit', $receipt->inpatient_visits_qty), $money($receipt->inpatient_visits_fee), $row_alt],
    ['&bull;&nbsp; Post-Discharge Tele-Review Allocation', $basis_qty('Per Session', $receipt->post_op_sessions), $money($receipt->post_op_fee), $row_white],
];

$border = 'border-bottom:0.5px solid #E2E8F0;';

$fee_table = '<table width="100%" cellspacing="0" cellpadding="4" border="0" style="font-size:8.5px;color:' . $text . ';">';
$fee_table .= '<tr bgcolor="' . $teal . '" style="color:#FFFFFF;font-weight:bold;">'
    . '<td width="56%">Particulars &amp; Clinical Service Milestones</td>'
    . '<td width="22%">Calculation Basis</td>'
    . '<td width="22%" align="right">Amount (' . e($currency) . ')</td>'
    . '</tr>';
$fee_table .= '<tr bgcolor="#EDF2F7"><td colspan="3" style="font-weight:bold;color:' . $teal . ';' . $border . '">SECTION A: GROSS DOCTOR CLINICAL EARNINGS</td></tr>';

foreach ($rows as $r) {
    $fee_table .= '<tr bgcolor="' . $r[3] . '">'
        . '<td style="' . $border . '">' . $r[0] . '</td>'
        . '<td style="' . $border . '">' . $r[1] . '</td>'
        . '<td align="right" style="' . $border . '">' . $r[2] . '</td>'
        . '</tr>';
}

$fee_table .= '<tr bgcolor="#E2E8F0" style="font-weight:bold;color:' . $navy . ';">'
    . '<td>TOTAL GROSS DOCTOR REMUNERATION (A):</td>'
    . '<td>Subtotal</td>'
    . '<td align="right">' . app_format_money($receipt->total, $currency) . '</td>'
    . '</tr>';
$fee_table .= '</table>';

// ---------- Outer bordered box ----------
$html = '<table width="100%" cellspacing="0" cellpadding="10" border="0">'
    . '<tr><td bgcolor="#F8FAFC" style="border:0.6px solid ' . $teal . ';">'

    // Desk heading
    . '<span style="font-size:11.5px;font-weight:bold;color:' . $navy . ';">MEDITRAVELSOLUTION PATIENT ESCROW &amp; SETTLEMENT DESK</span><br />'
    . '<span style="font-size:8.5px;color:' . $muted . ';">DOCTOR DISBURSEMENT &amp; FINANCIAL CLEARANCE ADVICE<br />'
    . 'Statement No: ' . $statement_no . ' | Settlement Date: ' . $date($receipt->statement_date) . ' | Status: DISBURSED / CLEARED<br />'
    . 'Disbursement Currency: ' . e($currency) . '</span>'
    . '<br /><br />'

    // Two columns
    . '<table width="100%" cellspacing="0" cellpadding="3" border="0" style="font-size:8.5px;color:' . $text . ';line-height:1.5;">'
    . '<tr><td width="50%">' . $doctor_block . '</td><td width="50%">' . $patient_block . '</td></tr>'
    . '</table>'
    . '<br /><br />'

    // Settlement statement
    . '<span style="font-size:9.5px;font-weight:bold;color:' . $navy . ';">SETTLEMENT RECONCILIATION STATEMENT</span><br />'
    . $fee_table

    . '</td></tr></table>';

$pdf->writeHTML($html, true, false, true, false, '');
