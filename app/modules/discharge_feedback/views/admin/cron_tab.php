<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="discharge_feedback_cron">

    <div class="alert alert-info">
        When a lead is set to <strong>Close</strong> (= discharge), the patient gets the
        <strong>Fit-to-Fly form</strong> link by email / SMS straight away. The cron job above then re-sends the
        link <strong>once a day</strong> until the patient fills the form (or staff fill it in
        Patient Profile &rarr; Consent Forms).
    </div>

    <input type="hidden" name="discharge_feedback[enabled]" value="0">
    <div class="checkbox checkbox-primary">
        <input type="checkbox" name="discharge_feedback[enabled]" id="dfb_cron_enabled" value="1" <?php echo $s['enabled'] ? 'checked' : ''; ?>>
        <label for="dfb_cron_enabled">Send the Fit-to-Fly form link and daily reminders</label>
    </div>

    <hr />

    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip"
        data-title="<?php echo _l('hour_of_day_perform_auto_operations_format'); ?>"></i>
    <div class="form-group">
        <label for="dfb_daily_hour" class="control-label">Hour of day to send the daily reminder email</label>
        <input type="number" min="0" max="23" class="form-control" id="dfb_daily_hour"
               name="discharge_feedback[daily_hour]" value="<?php echo (int) $s['daily_hour']; ?>">
        <p class="help-block">
            24-hour format, server time (now <?php echo date('H:i'); ?>). Example: 10 = the first cron run after 10:00 each day.
            New links are not delayed – they go out immediately when the lead is closed.
        </p>
    </div>

    <div class="form-group">
        <label for="dfb_max_messages" class="control-label">Maximum emails per patient</label>
        <input type="number" min="0" class="form-control" id="dfb_max_messages"
               name="discharge_feedback[max_messages]" value="<?php echo (int) $s['max_messages']; ?>">
        <p class="help-block">Includes the first email. 0 = send every day until the form is filled.</p>
    </div>

    <div class="form-group">
        <label for="dfb_status_names" class="control-label">Lead status name(s) that mean discharge</label>
        <input type="text" class="form-control" id="dfb_status_names"
               name="discharge_feedback[status_names]" value="<?php echo e($s['status_names']); ?>">
    </div>

    <input type="hidden" name="discharge_feedback[send_email]" value="0">
    <div class="checkbox checkbox-primary">
        <input type="checkbox" name="discharge_feedback[send_email]" id="dfb_cron_email" value="1" <?php echo $s['send_email'] ? 'checked' : ''; ?>>
        <label for="dfb_cron_email">Send by Email</label>
    </div>
    <input type="hidden" name="discharge_feedback[send_sms]" value="0">
    <div class="checkbox checkbox-primary">
        <input type="checkbox" name="discharge_feedback[send_sms]" id="dfb_cron_sms" value="1" <?php echo $s['send_sms'] ? 'checked' : ''; ?>>
        <label for="dfb_cron_sms">Send by SMS</label>
    </div>

    <hr />

    <h5 class="tw-font-semibold tw-mb-2">How it runs</h5>
    <p class="tw-mb-1">1. It uses the cron command shown on the <b>Command</b> tab (every 5 minutes on the server).</p>
    <p class="tw-mb-1">2. <b>Run Cron Manually</b> on the Command tab sends today's reminders immediately (still max 1 email per patient per day).</p>
    <p class="tw-mb-1">3. Last daily run: <b><?php echo $s['last_daily_run'] ? e($s['last_daily_run']) : 'never'; ?></b></p>

    <p class="tw-mt-3 tw-mb-1">Optional separate daily URL for this job only (exact time):</p>
    <input type="text" class="form-control" readonly value="<?php echo e($cron_url); ?>" onclick="this.select();">

    <p class="tw-mt-3">
        <a href="<?php echo admin_url('discharge_feedback'); ?>" class="text-primary">Patients &amp; status</a> ·
        <a href="<?php echo admin_url('discharge_feedback/settings'); ?>" class="text-primary">All discharge feedback settings (form, email/SMS text)</a>
    </p>
</div>
