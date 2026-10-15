<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view('discharge_feedback/admin/_nav', ['active' => $active]); ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <a href="<?php echo admin_url('discharge_feedback'); ?>" class="btn btn-default btn-sm pull-right">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                        <h4 class="tw-mt-0 tw-font-semibold"><?php echo e($request['patient_name']); ?></h4>
                        <p class="tw-text-neutral-600">
                            Lead #<?php echo (int) $request['lead_id']; ?> · <?php echo e($request['branch_name']); ?>
                            · Email: <?php echo e($request['email'] ?: '—'); ?>
                            · Phone: <?php echo e($request['phone'] ?: '—'); ?>
                            · Status: <strong><?php echo e(ucfirst($request['status'])); ?></strong>
                        </p>
                        <p>Feedback link: <a href="<?php echo e($link); ?>" target="_blank" rel="noopener"><?php echo e($link); ?></a></p>

                        <table class="table table-striped tw-mt-4">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Channel</th>
                                <th>Sent to</th>
                                <th>Type</th>
                                <th>Result</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$logs) { ?>
                                <tr><td colspan="5" class="text-center tw-text-neutral-500">Nothing sent yet.</td></tr>
                            <?php } ?>
                            <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td><?php echo _dt($log['sent_at']); ?></td>
                                    <td><?php echo e(strtoupper($log['channel'])); ?></td>
                                    <td><?php echo e($log['recipient']); ?></td>
                                    <td><?php echo $log['is_reminder'] ? 'Daily reminder' : 'First message'; ?></td>
                                    <td>
                                        <?php if ($log['success']) { ?>
                                            <span class="label label-success">Sent</span>
                                        <?php } else { ?>
                                            <span class="label label-danger">Failed</span>
                                            <div class="tw-text-sm text-danger"><?php echo e($log['error']); ?></div>
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
