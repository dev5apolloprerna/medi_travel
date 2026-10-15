<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view('discharge_feedback/admin/_nav', ['active' => $active]); ?>

                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-mb-3">
                    <a href="<?php echo admin_url('discharge_feedback/responses'); ?>" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('dfbForm').contentWindow.print();">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <span class="tw-text-neutral-600 tw-ml-2">
                        Submitted <?php echo _dt($response['submitted_at']); ?>
                        <?php if ($response['branch_name']) { ?> · <?php echo e($response['branch_name']); ?><?php } ?>
                        <?php if ($response['lead_id']) { ?> · Lead #<?php echo (int) $response['lead_id']; ?><?php } ?>
                        <?php if (!empty($response['consent_submission_id'])) { ?>
                            · <span class="text-success">Saved in Patient Profile → Consent Forms (patient #<?php echo (int) $response['patient_id']; ?>)</span>
                        <?php } else { ?>
                            · <span class="text-warning">Not linked to a patient profile</span>
                        <?php } ?>
                        · IP <?php echo e($response['ip_address']); ?>
                    </span>
                </div>

                <div class="panel_s">
                    <iframe id="dfbForm" title="Submitted form"
                            src="<?php echo admin_url('discharge_feedback/response/' . $response['id'] . '?raw=1'); ?>"
                            style="width:100%;height:80vh;border:0;background:#fff;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
