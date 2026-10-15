<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
    <h4 class="tw-my-0 tw-font-semibold tw-text-lg">
        <i class="fa fa-clipboard-check tw-mr-1"></i> <?php echo _l('discharge_feedback'); ?>
    </h4>
    <?php if (is_admin()) { ?>
        <a href="<?php echo admin_url('discharge_feedback/run_now'); ?>" class="btn btn-default"
           onclick="return confirm('Scan for closed leads and send today\'s reminders now?');">
            <i class="fa fa-play tw-mr-1"></i> <?php echo _l('discharge_feedback_run_now'); ?>
        </a>
    <?php } ?>
</div>
<ul class="nav nav-tabs tw-mb-4">
    <li class="<?php echo $active === 'requests' ? 'active' : ''; ?>">
        <a href="<?php echo admin_url('discharge_feedback'); ?>"><?php echo _l('discharge_feedback_patients'); ?></a>
    </li>
    <li class="<?php echo $active === 'responses' ? 'active' : ''; ?>">
        <a href="<?php echo admin_url('discharge_feedback/responses'); ?>"><?php echo _l('discharge_feedback_responses'); ?></a>
    </li>
    <?php if (is_admin()) { ?>
        <li class="<?php echo $active === 'settings' ? 'active' : ''; ?>">
            <a href="<?php echo admin_url('discharge_feedback/settings'); ?>"><?php echo _l('discharge_feedback_settings'); ?></a>
        </li>
    <?php } ?>
</ul>
