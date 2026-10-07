<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                    <?php echo $only_own ? 'My Doctor Receipts' : 'Doctor Receipts'; ?>
                </h4>
                <?php if (!$only_own) { ?>
                <p class="text-muted">To create a new doctor receipt, open the patient's payment (Sales &rarr; Payments) and click <b>New Doctor Receipt</b>.</p>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <table class="table dt-table" data-order-col="0" data-order-type="desc">
                            <thead>
                                <tr>
                                    <th>Statement No</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Procedure</th>
                                    <th>Settlement Date</th>
                                    <th>Gross Remuneration</th>
                                    <th><?php echo _l('options'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $dr) { ?>
                                <tr>
                                    <td data-order="<?php echo (int) $dr->id; ?>">
                                        <a href="<?php echo admin_url('doctor_receipts/pdf/' . $dr->id . '?output_type=I'); ?>" target="_blank">DS-<?php echo str_pad($dr->id, 6, '0', STR_PAD_LEFT); ?></a>
                                    </td>
                                    <td>Dr. <?php echo e($dr->doctor_name); ?></td>
                                    <td><?php echo e($dr->patient_name); ?></td>
                                    <td><?php echo e($dr->procedure_name); ?></td>
                                    <td data-order="<?php echo e($dr->statement_date); ?>"><?php echo e(_d($dr->statement_date)); ?></td>
                                    <td data-order="<?php echo e($dr->total); ?>"><?php echo e(app_format_money($dr->total, $dr->currency)); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('doctor_receipts/pdf/' . $dr->id . '?output_type=I'); ?>" target="_blank" class="btn btn-default btn-sm" title="<?php echo _l('view_pdf'); ?>"><i class="fa-regular fa-file-pdf"></i></a>
                                        <a href="<?php echo admin_url('doctor_receipts/pdf/' . $dr->id); ?>" class="btn btn-default btn-sm" title="<?php echo _l('download'); ?>"><i class="fa fa-download"></i></a>
                                        <?php if ($can_open_payment) { ?>
                                        <a href="<?php echo admin_url('payments/payment/' . $dr->paymentid); ?>" class="btn btn-default btn-sm" title="<?php echo _l('payment'); ?>"><i class="fa-regular fa-credit-card"></i></a>
                                        <?php } ?>
                                        <?php if ($can_delete) { ?>
                                        <a href="<?php echo admin_url('doctor_receipts/delete/' . $dr->id . '?from=list'); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>

</html>
