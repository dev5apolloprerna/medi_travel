<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    <h4 class="modal-title"><?php echo e(_l('lead_followup_history') . ' - #' . $lead->id . ' ' . $lead->name); ?></h4>
</div>
<div class="modal-body">
    <?php $this->load->view('admin/leads/followup_history', ['lead' => $lead, 'statuses' => $statuses, 'followup_history' => $followup_history]); ?>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button></div>
