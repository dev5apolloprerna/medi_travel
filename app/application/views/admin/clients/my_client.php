<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .content {
        padding: 10px 30px;
    }
    @media (min-width: 992px) and (max-width: 1024px) {
        .profile_main {
            padding: 0;
        }
        .tab_bar {
            padding: 0;
        }
    }
</style>
<div id="wrapper" class="customer_profile">
    <div class="content">
        <?php if (isset($client) && $client->registration_confirmed == 0 && is_admin()) { ?>
        <div class="alert alert-warning">
            <h4>
                <?php echo _l('customer_requires_registration_confirmation'); ?>
            </h4>
            <a href="<?php echo admin_url('clients/confirm_registration/' . $client->userid); ?>">
                <?php echo _l('confirm_registration'); ?>
            </a>
        </div>
        <?php } elseif (isset($client) && $client->active == 0 && $client->registration_confirmed == 1) { ?>
        <div class="alert alert-warning">
            <?php echo _l('customer_inactive_message'); ?>
            <br />
            <a href="<?php echo admin_url('clients/mark_as_active/' . $client->userid); ?>">
                <?php echo _l('mark_as_active'); ?>
            </a>
        </div>
        <?php } ?>
        <?php if (isset($client) && (staff_cant('view', 'customers') && is_customer_admin($client->userid))) {?>
        <div class="alert alert-info">
            <?php echo e(_l('customer_admin_login_as_client_message', get_staff_full_name(get_staff_user_id()))); ?>
        </div>
        <?php } ?>
        <div class="row">
            <div class="col-md-3">
                <?php if (isset($client)) { ?>
                <h4 class="tw-text-lg tw-font-semibold tw-text-neutral-800 tw-mt-0">
                    <div class="tw-space-x-3 tw-flex tw-items-center">
                        <?php
                        /*
                         * Patient number / delete menu intentionally hidden.
                         * (It used to be wrapped in an HTML comment containing a PHP "if" - for staff without
                         * delete permission, e.g. doctors, the closing "-->" was skipped and the rest of the page
                         * (tab bar styles and markup) ended up inside the comment.)
                         */
                        ?>
                    </div>
                    <?php if (isset($client) && $client->leadid != null) { ?>
                    <small class="tw-block">
                        <b><?php echo e(_l('customer_from_lead', _l('lead'))); ?></b>
                        <a href="<?php echo admin_url('leads/index/' . $client->leadid); ?>"
                            onclick="init_lead(<?php echo e($client->leadid); ?>); return false;">
                            - <?php echo _l('view'); ?>
                        </a>
                    </small>
                    <?php } ?>
                </h4>
                <?php } ?>
            </div>
            <div class="clearfix"></div>

            <?php if (isset($client)) { ?>
            <div class="col-md-3">
                <?php $this->load->view('admin/clients/tabs'); ?>
            </div>
            <?php } ?>

            <div class="tw-mt-12 sm:tw-mt-0 <?php echo isset($client) ? 'col-md-12' : 'col-md-12 '; ?>">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php if (isset($client)) { ?>
                        <?php echo form_hidden('isedit'); ?>
                        <?php echo form_hidden('userid', $client->userid); ?>
                        <div class="clearfix"></div>
                        <?php } ?>
                        <div>
                            <div class="tab-content">
                                <?php $this->load->view((isset($tab) ? $tab['view'] : 'admin/clients/groups/profile')); ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($group == 'profile') { ?>
                    <div class="panel-footer text-right tw-space-x-1" id="profile-save-section">
                        <?php if (!isset($client)) { ?>
                        <?php /* "Save customer and add contact" button intentionally hidden */ ?>
                        <?php } ?>
                        <button class="btn btn-primary only-save customer-form-submiter">
                            <?php echo _l('submit'); ?>
                        </button>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

    </div>
</div>
<?php init_tail(); ?>
<?php if (isset($client)) { ?>
<script>
$(function() {
    init_rel_tasks_table(<?php echo e($client->userid); ?>, 'customer');
});
</script>
<?php } ?>
<?php $this->load->view('admin/clients/client_js'); ?>
</body>

</html>