<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-2">
                    <h4 class="tw-my-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                        Doctor Payment Receipts
                        <?php if ($member) { ?>
                        <small class="text-muted"> &mdash; Dr. <?php echo e($member->firstname . ' ' . $member->lastname); ?></small>
                        <?php } ?>
                    </h4>
                    <?php if ($member && $can_create && is_doctor_role($member->role) && empty($receipts)) { ?>
                    <a href="<?php echo admin_url('doctor_payment_receipts/create/' . $member->staffid); ?>" class="btn btn-primary">
                        <i class="fa-regular fa-plus"></i> Generate Receipt
                    </a>
                    <?php } ?>
                </div>
                <?php if (!$member) { ?>
                <p class="text-muted">To generate a receipt go to <b>Setup &rarr; Staff</b> and click <b>Generate Receipt</b> under a doctor's name.</p>
                <?php } ?>
                <?php if ($this->input->get('open')) { $open_url = admin_url('doctor_payment_receipts/pdf/' . (int) $this->input->get('open')); ?>
                <div class="alert alert-success">
                    Receipt generated.
                    <a href="<?php echo $open_url . '?output_type=I'; ?>" target="_blank" class="alert-link">View / Print</a> &middot;
                    <a href="<?php echo $open_url; ?>" class="alert-link">Download PDF</a>
                </div>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <table class="table dt-table" data-order-col="1" data-order-type="desc">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Particulars</th>
                                    <th>Payment Date</th>
                                    <th>Total</th>
                                    <th><?php echo _l('options'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $rc) {
                                    $items = json_decode((string) $rc->items, true) ?: [];
                                    $pdf_url = admin_url('doctor_payment_receipts/pdf/' . $rc->id); ?>
                                <tr>
                                    <td><a href="<?php echo $pdf_url . '?output_type=I'; ?>" target="_blank"><?php echo e($rc->receipt_number); ?></a></td>
                                    <td data-order="<?php echo e($rc->receipt_date . ' ' . str_pad($rc->id, 8, '0', STR_PAD_LEFT)); ?>"><?php echo e(_d($rc->receipt_date)); ?></td>
                                    <td><a href="<?php echo admin_url('doctor_payment_receipts?staffid=' . $rc->staffid); ?>">Dr. <?php echo e($rc->doctor_name); ?></a></td>
                                    <td><?php echo e(implode(', ', array_column($items, 'description'))); ?></td>
                                    <td data-order="<?php echo e($rc->payment_date); ?>"><?php echo $rc->payment_date ? e(_d($rc->payment_date)) : '-'; ?></td>
                                    <td data-order="<?php echo e($rc->total); ?>"><?php echo e(number_format((float) $rc->total, 2)); ?></td>
                                    <td>
                                        <a href="<?php echo $pdf_url . '?output_type=I'; ?>" target="_blank" class="btn btn-default btn-sm" title="View / Print"><i class="fa-regular fa-file-pdf"></i></a>
                                        <a href="<?php echo $pdf_url; ?>" class="btn btn-default btn-sm" title="<?php echo _l('download'); ?>"><i class="fa fa-download"></i></a>
                                        <?php if ($can_edit) { ?>
                                        <a href="<?php echo admin_url('doctor_payment_receipts/edit/' . $rc->id); ?>" class="btn btn-default btn-sm" title="<?php echo _l('edit'); ?>"><i class="fa-regular fa-pen-to-square"></i></a>
                                        <?php } ?>
                                        <?php if ($can_delete) { ?>
                                        <a href="<?php echo admin_url('doctor_payment_receipts/delete/' . $rc->id); ?>" class="btn btn-danger btn-sm _delete"><i class="fa fa-remove"></i></a>
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
