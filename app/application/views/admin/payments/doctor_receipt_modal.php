<?php defined('BASEPATH') or exit('No direct script access allowed');
$r = $receipt;
$v = function ($field, $default = '') use ($r) {
    return $r && isset($r->$field) ? $r->$field : $default;
};
$fee = function ($field) use ($r) {
    return $r && (float) $r->$field > 0 ? $r->$field : '';
};
$qty = function ($field) use ($r) {
    return $r && (int) $r->$field > 0 ? $r->$field : '';
};
$selected_staff = $v('staffid', $defaults->staffid);
$currency       = $v('currency', $defaults->currency);
?>
<div class="modal fade" id="doctor_receipt_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <?php echo form_open(admin_url('doctor_receipts/save'), ['id' => 'doctor-receipt-form']); ?>
        <?php echo form_hidden('paymentid', $payment->paymentid); ?>
        <?php echo form_hidden('id', $r ? $r->id : ''); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?php echo $r ? 'Edit Doctor Receipt DS-' . str_pad($r->id, 6, '0', STR_PAD_LEFT) : 'New Doctor Receipt'; ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <?php echo render_date_input('statement_date', 'Settlement Date', _d($v('statement_date', $defaults->statement_date))); ?>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="currency" class="control-label">Disbursement Currency</label>
                            <select name="currency" id="currency" class="selectpicker" data-width="100%">
                                <?php foreach ($currencies as $c) { ?>
                                <option value="<?php echo e($c['name']); ?>" <?php echo $c['name'] == $currency ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <h4 class="tw-font-semibold tw-text-base tw-mt-2" style="color:#0E7490;">Consultant / Doctor Details</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="staffid" class="control-label">Doctor</label>
                            <select name="staffid" id="staffid" class="selectpicker" data-width="100%" data-live-search="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
                                <option value=""></option>
                                <?php foreach ($doctors as $d) { ?>
                                <option value="<?php echo e($d['staffid']); ?>" <?php echo $d['staffid'] == $selected_staff ? 'selected' : ''; ?>><?php echo e(trim($d['firstname'] . ' ' . $d['lastname'])); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6"><?php echo render_input('doctor_name', 'Treating Specialist (printed as "Dr. …")', $v('doctor_name')); ?></div>
                    <div class="col-md-6"><?php echo render_input('doctor_specialty', 'Specialty / Dept', $v('doctor_specialty')); ?></div>
                    <div class="col-md-6"><?php echo render_input('hospital_name', 'Hospital / OT Facility', $v('hospital_name')); ?></div>
                    <div class="col-md-4"><?php echo render_input('bank_name', 'Beneficiary Bank', $v('bank_name')); ?></div>
                    <div class="col-md-4"><?php echo render_input('account_number', 'Account Number', $v('account_number')); ?></div>
                    <div class="col-md-4"><?php echo render_input('bank_routing', 'Routing / IFSC / SWIFT', $v('bank_routing')); ?></div>
                </div>

                <h4 class="tw-font-semibold tw-text-base tw-mt-2" style="color:#0E7490;">Patient Case Reconciliation</h4>
                <div class="row">
                    <div class="col-md-4"><?php echo render_input('patient_name', 'Patient Name', $v('patient_name', $defaults->patient_name)); ?></div>
                    <div class="col-md-4"><?php echo render_input('passport_last4', 'Passport ID (last 4 digits)', $v('passport_last4'), 'text', ['maxlength' => 4]); ?></div>
                    <div class="col-md-4"><?php echo render_input('crm_patient_id', 'Internal File ID', $v('crm_patient_id', $defaults->crm_patient_id)); ?></div>
                    <div class="col-md-6"><?php echo render_textarea('clinical_diagnosis', 'Clinical Diagnosis', $v('clinical_diagnosis', $defaults->clinical_diagnosis), ['rows' => 2]); ?></div>
                    <div class="col-md-6"><?php echo render_input('procedure_name', 'Procedure Executed', $v('procedure_name')); ?></div>
                    <div class="col-md-6"><?php echo render_date_input('surgery_date', 'Surgery Date', _d($v('surgery_date'))); ?></div>
                    <div class="col-md-6"><?php echo render_date_input('discharge_date', 'Discharge Date', _d($v('discharge_date'))); ?></div>
                </div>

                <h4 class="tw-font-semibold tw-text-base tw-mt-2" style="color:#0E7490;">Section A: Gross Doctor Clinical Earnings</h4>
                <table class="table table-bordered doctor-receipt-fees">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th width="18%">Qty</th>
                            <th width="25%">Amount (<span class="dr-currency"><?php echo e($currency); ?></span>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3"><b>Consultation</b></td></tr>
                        <tr><td class="tw-pl-8">Pre-Travel Tele-Consultation / Second Opinion <small class="text-muted">(Flat Fee)</small></td><td></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="tele_consult_fee" value="<?php echo e($fee('tele_consult_fee')); ?>"></td></tr>
                        <tr><td class="tw-pl-8">In-Person Pre-Op Comprehensive Evaluation <small class="text-muted">(Flat Fee)</small></td><td></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="preop_eval_fee" value="<?php echo e($fee('preop_eval_fee')); ?>"></td></tr>
                        <tr><td><b>Primary Surgical &amp; Procedural Fee</b> <small class="text-muted">(Per Procedure)</small></td><td></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="surgical_fee" value="<?php echo e($fee('surgical_fee')); ?>"></td></tr>
                        <tr><td class="tw-pl-8">Implant charges <small class="text-muted">(No. of implants used)</small></td>
                            <td><input type="number" step="1" min="0" class="form-control" name="implant_qty" value="<?php echo e($qty('implant_qty')); ?>"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="implant_fee" value="<?php echo e($fee('implant_fee')); ?>"></td></tr>
                        <tr><td class="tw-pl-8">Co-Surgeon / Assistant Surgeon Charges <small class="text-muted">(Per Procedure)</small></td><td></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="co_surgeon_fee" value="<?php echo e($fee('co_surgeon_fee')); ?>"></td></tr>
                        <tr><td><b>Inpatient Specialist Ward/ICU Rounds</b> <small class="text-muted">(Per Day / Per Visit)</small></td>
                            <td><input type="number" step="1" min="0" class="form-control" name="inpatient_visits_qty" value="<?php echo e($qty('inpatient_visits_qty')); ?>"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="inpatient_visits_fee" value="<?php echo e($fee('inpatient_visits_fee')); ?>"></td></tr>
                        <tr><td><b>Post-Discharge Tele-Review Allocation</b> <small class="text-muted">(Per Session)</small></td>
                            <td><input type="number" step="1" min="0" class="form-control" name="post_op_sessions" value="<?php echo e($qty('post_op_sessions')); ?>"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control dr-fee" name="post_op_fee" value="<?php echo e($fee('post_op_fee')); ?>"></td></tr>
                        <tr class="active">
                            <td colspan="2"><b>TOTAL GROSS DOCTOR REMUNERATION (A)</b></td>
                            <td><b class="dr-total">0.00</b></td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-muted"><small>Qty is printed next to the calculation basis. Enter the line total in Amount.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>
<script>
(function() {
    var $form = $('#doctor-receipt-form');
    init_selectpicker();
    init_datepicker();

    function drTotal() {
        var t = 0;
        $form.find('.dr-fee').each(function() {
            t += parseFloat($(this).val()) || 0;
        });
        $form.find('.dr-total').text(t.toFixed(2));
    }
    $form.on('input change', '.dr-fee', drTotal);
    drTotal();

    $form.find('select[name="currency"]').on('change', function() {
        $form.find('.dr-currency').text($(this).val());
    });

    // Fill doctor name + last used specialty / hospital / bank details
    function fillDoctor(onlyEmpty) {
        var staffid = $form.find('select[name="staffid"]').val();
        if (!staffid) {
            return;
        }
        var $name = $form.find('input[name="doctor_name"]');
        if (!onlyEmpty || !$name.val()) {
            $name.val($.trim($form.find('select[name="staffid"] option:selected').text()).replace(/^dr\.?\s+/i, ''));
        }
        $.get(admin_url + 'doctor_receipts/doctor_details/' + staffid, function(res) {
            res = typeof res === 'string' ? JSON.parse(res) : res;
            ['doctor_specialty', 'hospital_name', 'bank_name', 'account_number', 'bank_routing'].forEach(function(f) {
                var $i = $form.find('[name="' + f + '"]');
                if (res[f] && (!onlyEmpty || !$i.val())) {
                    $i.val(res[f]);
                }
            });
        });
    }
    $form.find('select[name="staffid"]').on('change', function() {
        fillDoctor(false);
    });
    <?php if (!$r) { ?>
    fillDoctor(true);
    <?php } ?>

    appValidateForm($form, {
        staffid: 'required',
        doctor_name: 'required',
        statement_date: 'required'
    });
})();
</script>
