<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$r       = $receipt;
$item    = !empty($r->items[0]) ? $r->items[0] : ['description' => '', 'amount' => ''];
$is_edit = isset($r->id) && $r->id;
$modes   = ['Cash', 'Cheque', 'NEFT / RTGS / IMPS', 'UPI', 'Credit / Debit Card', 'Online (Payment Gateway)', 'Other'];
$statuses = ['Payment Received', 'Payment Paid', 'Payment Pending'];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-2">
                    <h4 class="tw-my-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                        <?php echo $is_edit ? 'Edit Receipt ' . e($r->receipt_number) : 'Generate Receipt'; ?>
                        <small class="text-muted"> &mdash; Dr. <?php echo e($member->firstname . ' ' . $member->lastname); ?></small>
                    </h4>
                    <a href="<?php echo admin_url('doctor_payment_receipts?staffid=' . $member->staffid); ?>" class="btn btn-default">
                        <i class="fa fa-list"></i> All receipts of this doctor
                    </a>
                </div>

                <?php echo form_open($is_edit ? admin_url('doctor_payment_receipts/edit/' . $r->id) : admin_url('doctor_payment_receipts/create/' . $member->staffid), ['id' => 'doctor-payment-receipt-form']); ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <p class="text-muted tw-mt-0">
                            Company name, address and GST No on the receipt are taken from
                            <a href="<?php echo admin_url('settings?group=company'); ?>" target="_blank">Setup &rarr; Settings &rarr; Company</a>:
                            <b><?php echo e($r->company_name); ?></b><?php echo $r->company_address !== '' ? ', ' . e($r->company_address) : ''; ?>
                        </p>
                        <h5 class="tw-font-semibold">Receipt</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <?php echo render_input('receipt_number', 'Receipt / Invoice No', $r->receipt_number, 'text', ['required' => true]); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_date_input('receipt_date', 'Receipt Date', _d($r->receipt_date), ['required' => true]); ?>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_status" class="control-label">Status shown on receipt</label>
                                    <select name="payment_status" id="payment_status" class="form-control">
                                        <?php foreach ($statuses as $s) { ?>
                                        <option value="<?php echo e($s); ?>" <?php echo $r->payment_status === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr />
                        <h5 class="tw-font-semibold">To (Doctor)</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('doctor_name', 'Doctor Name', $r->doctor_name, 'text', ['required' => true]); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('doctor_phone', 'Phone', $r->doctor_phone); ?>
                            </div>
                            <div class="col-md-12">
                                <?php echo render_textarea('doctor_address', 'Address', $r->doctor_address, ['rows' => 2]); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('doctor_gst', 'Doctor GST No', $r->doctor_gst, 'text', ['maxlength' => 15, 'style' => 'text-transform:uppercase']); ?>
                            </div>
                        </div>

                        <hr />
                        <h5 class="tw-font-semibold">Particulars</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <?php echo render_input('item_description', 'Particulars', $item['description'], 'text', ['required' => true, 'placeholder' => 'e.g. Doctor Consultation Fees']); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('item_amount', 'Amount (Rs)', $item['amount'], 'number', ['required' => true, 'step' => '0.01', 'min' => '0']); ?>
                            </div>
                        </div>

                        <hr />
                        <h5 class="tw-font-semibold">Payment Details</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <?php echo render_date_input('payment_date', 'Payment Date', _d($r->payment_date)); ?>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="payment_mode" class="control-label">Payment Mode</label>
                                    <select name="payment_mode" id="payment_mode" class="form-control">
                                        <option value=""></option>
                                        <?php foreach ($modes as $m) { ?>
                                        <option value="<?php echo e($m); ?>" <?php echo $r->payment_mode === $m ? 'selected' : ''; ?>><?php echo e($m); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('transaction_ref', 'Transaction / UTR / Cheque No', $r->transaction_ref); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_input('bank_ref', 'Bank Ref No', $r->bank_ref); ?>
                            </div>
                            <div class="col-md-12">
                                <?php echo render_textarea('remarks', 'Remarks', $r->remarks, ['rows' => 2, 'placeholder' => 'e.g. Patient name / appointment / treatment this payment is for']); ?>
                            </div>
                        </div>

                        <hr />
                        <h5 class="tw-font-semibold">Footer <small class="text-muted">(note is remembered for the next receipt)</small></h5>
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_input('terms', 'Note', $r->terms); ?>
                                <p class="text-muted small">PAN No on the receipt comes from <a href="<?php echo admin_url('settings?group=company'); ?>" target="_blank">Setup &rarr; Settings &rarr; Company Information</a><?php echo $r->company_pan ? ': <b>' . e($r->company_pan) . '</b>' : ' (not set yet)'; ?>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <a href="<?php echo admin_url('staff'); ?>" class="btn btn-default"><?php echo _l('cancel'); ?></a>
                        <button type="submit" class="btn btn-primary"><?php echo $is_edit ? _l('submit') : 'Generate Receipt'; ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
    <?php if (!$is_edit) { ?>
    // Receipt number follows the financial year of the receipt date
    $('#receipt_date').on('change', function () {
        $.getJSON(admin_url + 'doctor_payment_receipts/next_number', { date: $(this).val() }, function (r) {
            if (r && r.number) { $('#receipt_number').val(r.number); }
        });
    });
    <?php } ?>

    appValidateForm($('#doctor-payment-receipt-form'), {
        receipt_number: 'required',
        receipt_date: 'required',
        doctor_name: 'required',
        item_description: 'required',
        item_amount: 'required'
    }, function (form) {
        // Generate only once - block double clicks
        var $btn = $(form).find('button[type="submit"]');
        if ($btn.data('submitted')) { return false; }
        $btn.data('submitted', true).prop('disabled', true).html('<i class="fa fa-refresh fa-spin fa-fw"></i>');
        form.submit();
    });
});
</script>
</body>
</html>
