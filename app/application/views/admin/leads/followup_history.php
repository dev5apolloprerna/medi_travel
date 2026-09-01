<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_open(admin_url('leads/followup_history/' . $lead->id), ['class' => 'lead-followup-form']); ?>
<div class="row">
    <div class="col-md-4"><?php echo render_date_input('next_followup_date', 'lead_next_followup_date', '', ['required' => true]); ?></div>
    <div class="col-md-4">
        <?php echo render_select('status_id', $statuses, ['id', 'name'], 'lead_status', $lead->status, ['required' => true]); ?>
    </div>
    <div class="col-md-4"><?php echo render_textarea('comment', 'lead_followup_comment', '', ['required' => true, 'rows' => 2]); ?></div>
</div>
<div class="text-right mbot20"><button type="submit" class="btn btn-primary"><?php echo _l('lead_followup_add'); ?></button></div>
<?php echo form_close(); ?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead><tr><th><?php echo _l('lead_next_followup_date'); ?></th><th><?php echo _l('lead_followup_comment'); ?></th><th><?php echo _l('lead_status'); ?></th><th><?php echo _l('created_by'); ?></th><th><?php echo _l('date_created'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($followup_history as $entry) { ?>
            <tr>
                <td><?php echo e(_d($entry['next_followup_date'])); ?></td>
                <td class="tw-whitespace-pre-wrap"><?php echo e($entry['comment']); ?></td>
                <td><span class="label" style="color:<?php echo e($entry['status_color']); ?>;border:1px solid <?php echo e($entry['status_color']); ?>"><?php echo e($entry['status_name']); ?></span></td>
                <td><?php echo e(get_staff_full_name($entry['created_by'])); ?></td>
                <td><?php echo e(_dt($entry['created_at'])); ?></td>
            </tr>
        <?php } ?>
        <?php if (!$followup_history) { ?><tr><td colspan="5" class="text-center text-muted"><?php echo _l('lead_followup_no_history'); ?></td></tr><?php } ?>
        </tbody>
    </table>
</div>
<script>
$('.lead-followup-form').on('submit', function(e) {
    e.preventDefault();
    var form = $(this);
    $.post(form.attr('action'), form.serialize()).done(function(response) {
        var result = typeof response === 'string' ? JSON.parse(response) : response;
        alert_float(result.success ? 'success' : 'danger', result.message);
        if (result.success) {
            if ($('#lead-followup-history-modal').hasClass('in')) {
                show_lead_followup_history(<?php echo (int) $lead->id; ?>);
            } else {
                init_lead(<?php echo (int) $lead->id; ?>);
            }
            $('.table-leads').DataTable().ajax.reload(null, false);
        }
    });
});
</script>
