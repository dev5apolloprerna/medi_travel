<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view('discharge_feedback/admin/_nav', ['active' => $active]); ?>

                <div class="alert alert-info">
                    Forms submitted by patients through the feedback link. Each one is also saved in the patient's
                    <strong>Patient Profile → Consent Forms</strong> when a matching patient is found.
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <table class="table dt-table" data-order-col="0" data-order-type="desc">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Submitted</th>
                                <th>Patient name</th>
                                <th>Branch</th>
                                <th>Lead</th>
                                <th>Form</th>
                                <th>Saved to Patient Profile</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($responses as $r) { ?>
                                <tr>
                                    <td><?php echo (int) $r['id']; ?></td>
                                    <td data-order="<?php echo e($r['submitted_at']); ?>"><?php echo _dt($r['submitted_at']); ?></td>
                                    <td><?php echo e($r['patient_name']); ?></td>
                                    <td><?php echo e($r['branch_name'] ?: '—'); ?></td>
                                    <td><?php echo $r['lead_id'] ? '#' . (int) $r['lead_id'] : '<span class="label label-default">General link</span>'; ?></td>
                                    <td><?php echo e($r['form_file'] ?: 'Fit-to-FlyMedical.html'); ?></td>
                                    <td>
                                        <?php if (!empty($r['consent_submission_id'])) { ?>
                                            <span class="label label-success">Yes – patient #<?php echo (int) $r['patient_id']; ?></span>
                                        <?php } else { ?>
                                            <span class="label label-warning" data-toggle="tooltip"
                                                  title="No patient matched this lead (by conversion, email or mobile), or the form is not in the consent form master.">No</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('discharge_feedback/response/' . $r['id']); ?>"
                                           class="btn btn-default btn-xs">View / Print</a>
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
