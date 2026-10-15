<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view('discharge_feedback/admin/_nav', ['active' => $active]); ?>

                <div class="row tw-mb-4">
                    <?php
                    $cards = [
                        ''          => ['All', array_sum($stats), 'tw-text-neutral-700'],
                        'pending'   => ['Waiting for form', $stats['pending'], 'tw-text-warning-600'],
                        'completed' => ['Form completed', $stats['completed'], 'tw-text-success-600'],
                        'stopped'   => ['Stopped', $stats['stopped'], 'tw-text-danger-600'],
                    ];
                    foreach ($cards as $key => $card) { ?>
                        <div class="col-md-3 col-xs-6">
                            <a href="<?php echo admin_url('discharge_feedback' . ($key !== '' ? '?status=' . $key : '')); ?>"
                               class="panel_s tw-block tw-p-3 tw-mb-2<?php echo $status === $key ? ' tw-ring-2 tw-ring-primary-400' : ''; ?>">
                                <span class="tw-block tw-text-2xl tw-font-semibold <?php echo $card[2]; ?>"><?php echo (int) $card[1]; ?></span>
                                <span class="tw-text-neutral-500"><?php echo $card[0]; ?></span>
                            </a>
                        </div>
                    <?php } ?>
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <table class="table dt-table" data-order-col="0" data-order-type="desc">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Branch</th>
                                <th>Email / Phone</th>
                                <th>Discharged</th>
                                <th>Messages sent</th>
                                <th>Last sent</th>
                                <th>Status</th>
                                <th>Feedback link</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $r) {
                                $link = $dfb->feedback_link($r['hash']);
                                if ($r['status'] === 'completed' && ($r['completed_via'] ?? '') === 'profile') {
                                    $label = '<span class="label label-success">Filled in patient profile</span>';
                                } elseif ($r['status'] === 'completed') {
                                    $label = '<span class="label label-success">Submitted by patient</span>';
                                } elseif ($r['status'] === 'stopped') {
                                    $label = '<span class="label label-danger">Stopped</span>';
                                } elseif ($max_messages > 0 && (int) $r['send_count'] >= $max_messages) {
                                    $label = '<span class="label label-default">Max reminders reached</span>';
                                } elseif ((int) $r['send_count'] === 0) {
                                    $label = '<span class="label label-info">Not sent yet</span>';
                                } else {
                                    $label = '<span class="label label-warning">Waiting for form</span>';
                                } ?>
                                <tr>
                                    <td><?php echo (int) $r['id']; ?></td>
                                    <td>
                                        <?php echo e($r['patient_name']); ?>
                                        <div class="tw-text-neutral-500 tw-text-sm">Lead #<?php echo (int) $r['lead_id']; ?></div>
                                    </td>
                                    <td><?php echo e($r['branch_name']); ?></td>
                                    <td>
                                        <?php echo e($r['email'] ?: '—'); ?><br>
                                        <?php echo e($r['phone'] ?: '—'); ?>
                                    </td>
                                    <td data-order="<?php echo e($r['discharged_at']); ?>"><?php echo $r['discharged_at'] ? _dt($r['discharged_at']) : ''; ?></td>
                                    <td>
                                        <?php echo (int) $r['send_count']; ?>
                                        <?php if ($r['last_channels']) { ?>
                                            <div class="tw-text-neutral-500 tw-text-sm"><?php echo e(strtoupper(str_replace(',', ' + ', $r['last_channels']))); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td data-order="<?php echo e($r['last_sent_at']); ?>"><?php echo $r['last_sent_at'] ? _dt($r['last_sent_at']) : '—'; ?></td>
                                    <td>
                                        <?php echo $label; ?>
                                        <?php if ($r['last_error'] && $r['status'] !== 'completed') { ?>
                                            <i class="fa fa-exclamation-triangle text-danger" data-toggle="tooltip"
                                               title="<?php echo e($r['last_error']); ?>"></i>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="min-width:200px;">
                                            <input type="text" class="form-control" readonly value="<?php echo e($link); ?>"
                                                   onclick="this.select();">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-default" title="Copy"
                                                        onclick="dfbCopy(this, <?php echo html_escape(json_encode($link)); ?>);">
                                                    <i class="fa fa-copy"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="tw-whitespace-nowrap">
                                        <?php if ($r['status'] !== 'completed') { ?>
                                            <a href="<?php echo admin_url('discharge_feedback/resend/' . $r['id']); ?>"
                                               class="btn btn-primary btn-xs"
                                               onclick="return confirm('Send the feedback link to this patient now?');">
                                                <i class="fa fa-paper-plane"></i> Send now
                                            </a>
                                            <a href="<?php echo admin_url('discharge_feedback/toggle/' . $r['id']); ?>"
                                               class="btn btn-default btn-xs">
                                                <?php echo $r['status'] === 'stopped' ? 'Restart' : 'Stop'; ?>
                                            </a>
                                        <?php } ?>
                                        <a href="<?php echo admin_url('discharge_feedback/history/' . $r['id']); ?>"
                                           class="btn btn-default btn-xs">History</a>
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
<script>
    function dfbCopy(btn, text) {
        var done = function () {
            var icon = btn.querySelector('i');
            icon.className = 'fa fa-check';
            setTimeout(function () { icon.className = 'fa fa-copy'; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var input = btn.closest('.input-group').querySelector('input');
            input.select();
            document.execCommand('copy');
            done();
        }
    }
</script>
</body>
</html>
