<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view('discharge_feedback/admin/_nav', ['active' => $active]); ?>
            </div>

            <?php echo form_open(admin_url('discharge_feedback/settings')); ?>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">

                        <h4 class="tw-mt-0 tw-font-semibold">When is a lead "discharged"?</h4>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="enabled" id="dfb_enabled" value="1" <?php echo $s['enabled'] ? 'checked' : ''; ?>>
                            <label for="dfb_enabled">Enable discharge feedback (send links and daily reminders)</label>
                        </div>

                        <div class="form-group">
                            <label for="status_names">Lead status name(s) that mean <strong>Close = Discharge</strong></label>
                            <input type="text" class="form-control" id="status_names" name="status_names"
                                   value="<?php echo e($s['status_names']); ?>">
                            <p class="help-block">
                                Comma separated, not case-sensitive. Names are used (not IDs) so it works in every branch database.<br>
                                Your lead statuses (click to add):
                                <?php foreach ($lead_statuses as $st) { ?>
                                    <a href="#" class="label tw-inline-block tw-mr-1 tw-mt-1"
                                       style="background:<?php echo e($st['color'] ?: '#64748b'); ?>;color:#fff;"
                                       onclick="dfbAddStatus(<?php echo html_escape(json_encode($st['name'])); ?>);return false;"><?php echo e($st['name']); ?></a>
                                <?php } ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="form_file">Form the patient fills (Patient Profile → Consent Forms template)</label>
                            <select class="form-control" id="form_file" name="form_file">
                                <?php foreach ($form_files as $file) { ?>
                                    <option value="<?php echo e($file); ?>" <?php echo $file === $s['form_file'] ? 'selected' : ''; ?>><?php echo e($file); ?></option>
                                <?php } ?>
                            </select>
                            <p class="help-block">
                                Templates from <code>uploads/consern_form/</code>. Answers are saved into
                                <code>tblconsent_form_submissions</code>, so they show in the patient's Consent Forms tab.<br>
                                <?php if ($pdf_id) { ?>
                                    <span class="text-success"><i class="fa fa-check"></i> Linked to consent form #<?php echo (int) $pdf_id; ?> in this database.</span>
                                <?php } else { ?>
                                    <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> This file is not in <code>tblconsent_form_master</code> of this database. Patients can still submit, but the answers only show under Submitted Forms.</span>
                                <?php } ?>
                            </p>
                            <p class="help-block">If staff already filled this form in the patient profile, no link / reminder is sent and the patient shows as <em>Filled in patient profile</em>.</p>
                        </div>

                        <hr>
                        <h4 class="tw-font-semibold">Channels</h4>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="send_email" id="dfb_send_email" value="1" <?php echo $s['send_email'] ? 'checked' : ''; ?>>
                            <label for="dfb_send_email">Send by Email <small class="text-muted">(uses Setup &rarr; Settings &rarr; Email SMTP)</small></label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="send_sms" id="dfb_send_sms" value="1" <?php echo $s['send_sms'] ? 'checked' : ''; ?>>
                            <label for="dfb_send_sms">Send by SMS <small class="text-muted">(uses the active gateway in Setup &rarr; Settings &rarr; SMS)</small></label>
                        </div>

                        <div class="form-group">
                            <label for="country_code">Default country code for 10-digit phone numbers</label>
                            <input type="text" class="form-control" id="country_code" name="country_code" style="max-width:120px;"
                                   value="<?php echo e($s['country_code']); ?>">
                            <p class="help-block">91 for India. 9876543210 becomes 919876543210 (+919876543210 for Twilio).</p>
                        </div>

                        <hr>
                        <h4 class="tw-font-semibold">Daily reminders</h4>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="daily_hour">Send daily reminders at (hour, 0–23)</label>
                                    <input type="number" min="0" max="23" class="form-control" id="daily_hour" name="daily_hour"
                                           value="<?php echo (int) $s['daily_hour']; ?>">
                                    <p class="help-block">Server time now: <?php echo date('H:i'); ?> (<?php echo e(date_default_timezone_get()); ?>)</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_messages">Maximum messages per patient</label>
                                    <input type="number" min="0" class="form-control" id="max_messages" name="max_messages"
                                           value="<?php echo (int) $s['max_messages']; ?>">
                                    <p class="help-block">Includes the first message. 0 = remind every day until the form is submitted.</p>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h4 class="tw-font-semibold">Message templates</h4>
                        <p class="text-muted">Merge fields: <code>{patient_name}</code> <code>{feedback_link}</code> <code>{company_name}</code> <code>{branch_name}</code></p>

                        <div class="form-group">
                            <label for="email_subject">Email subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject"
                                   value="<?php echo e($s['email_subject']); ?>">
                            <p class="help-block">Reminders are prefixed with "Reminder: ".</p>
                        </div>

                        <div class="form-group">
                            <label for="email_body">Email body (HTML)</label>
                            <textarea class="form-control" id="email_body" name="email_body" rows="9"><?php echo html_escape($s['email_body']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="sms_body">SMS text</label>
                            <textarea class="form-control" id="sms_body" name="sms_body" rows="3"><?php echo html_escape($s['sms_body']); ?></textarea>
                            <p class="help-block">
                                <strong>India (MSG91):</strong> SMS must match a DLT-approved template exactly, otherwise operators block it.
                                Register this text with <code>{#var#}</code> for each merge field on your DLT portal first.
                            </p>
                        </div>

                        <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold">Feedback form links</h4>

                        <p class="tw-mb-1"><strong>Personal link</strong> (sent automatically to each patient):</p>
                        <p class="text-muted"><code style="word-break:break-all;white-space:normal;"><?php echo e(site_url('discharge_feedback/patient_feedback/form/')); ?>&lt;unique-code&gt;</code><br>
                            Pre-filled with the patient name and stops the reminders once submitted.</p>

                        <div class="checkbox checkbox-primary tw-mt-4">
                            <input type="checkbox" name="allow_general_link" id="dfb_general" value="1" <?php echo $s['allow_general_link'] ? 'checked' : ''; ?>>
                            <label for="dfb_general"><strong>General link</strong> – anyone with it can fill the form (not linked to a patient; answers only appear under Submitted Forms)</label>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly value="<?php echo e($general_link); ?>" onclick="this.select();">
                            <span class="input-group-btn">
                                <a href="<?php echo e($general_link); ?>" target="_blank" rel="noopener" class="btn btn-default">Open</a>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold">Cron job (daily messages)</h4>

                        <p class="tw-mb-1"><strong>Daily cron URL</strong> (sends today's links and reminders):</p>
                        <div class="input-group tw-mb-2">
                            <input type="text" class="form-control" readonly value="<?php echo e($cron_url); ?>" onclick="this.select();">
                            <span class="input-group-btn">
                                <a href="<?php echo e($cron_url); ?>" target="_blank" rel="noopener" class="btn btn-default">Run now</a>
                            </span>
                        </div>
                        <p class="help-block">Opening it shows a JSON result. Safe to open many times – a patient never gets more than one message per day. Keep it secret.</p>

                        <p class="tw-mt-3 tw-mb-1"><strong>Local (WAMP) – Windows Task Scheduler / Git Bash:</strong></p>
                        <pre class="tw-text-xs" style="white-space:pre-wrap;">curl -s "<?php echo e($cron_url); ?>"</pre>

                        <p class="tw-mt-3 tw-mb-1"><strong>Server – crontab / hPanel, every day at 10:00:</strong></p>
                        <pre class="tw-text-xs" style="white-space:pre-wrap;">0 10 * * * wget -q -O /dev/null "<?php echo e($cron_url); ?>"</pre>

                        <p class="tw-mt-3 tw-mb-1"><strong>Or the normal Perfex cron</strong> (every 5 min; reminders go out once a day after the hour above):</p>
                        <pre class="tw-text-xs" style="white-space:pre-wrap;">*/5 * * * * wget -q -O /dev/null <?php echo e($perfex_cron); ?></pre>

                        <div class="checkbox">
                            <input type="checkbox" name="regenerate_cron_key" id="dfb_regen" value="1">
                            <label for="dfb_regen">Generate a new secret key on save (old cron URL stops working)</label>
                        </div>

                        <p class="text-muted tw-mt-3">
                            Last daily run: <?php echo $s['last_daily_run'] ? e($s['last_daily_run']) : 'never'; ?><br>
                            Only leads closed after <?php echo e($s['start_date']); ?> (module installation) are picked up by the cron scan.
                        </p>
                    </div>
                </div>
            </div>

            <?php echo form_close(); ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    function dfbAddStatus(name) {
        var input = document.getElementById('status_names');
        var list = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        if (list.map(function (s) { return s.toLowerCase(); }).indexOf(name.toLowerCase()) === -1) {
            list.push(name);
        }
        input.value = list.join(', ');
    }
</script>
</body>
</html>
