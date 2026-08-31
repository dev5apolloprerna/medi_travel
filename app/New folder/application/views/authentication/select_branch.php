<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php   
$this->load->helper('branch');
$this->load->view('authentication/includes/head.php'); ?>

<body class="tw-bg-neutral-100 login_admin">

    <div class="tw-max-w-md tw-mx-auto tw-pt-24 authentication-form-wrapper tw-relative tw-z-20">
        <div class="company-logo text-center">
            <?php get_dark_company_logo(); ?>
        </div>

        <h1 class="tw-text-2xl tw-text-neutral-800 text-center tw-font-semibold tw-mb-5">
            <?php echo _l('admin_auth_login_heading'); ?>
        </h1>

        <div class="tw-bg-white tw-mx-2 sm:tw-mx-6 tw-py-6 tw-px-6 sm:tw-px-8 tw-shadow tw-rounded-lg">

            <?php $this->load->view('authentication/includes/alerts'); ?>

            <?php echo form_open(admin_url('authentication/redirect_login'),['method'=>"get"]); ?>

            <?php echo validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>

            <?php hooks()->do_action('after_admin_login_form_start'); ?>

            <?php
                // Default branch is "Super Admin" (empty value = Admin / no specific branch).
                // The branch field is kept in the form but hidden, and the form auto-submits
                // so the user is taken straight to the login page (page 2) without having
                // to see or interact with the branch selector.
                $default_branch = '';
            ?>
            <input type="hidden" name="branch" id="branch" value="<?php echo e($default_branch); ?>">

            <noscript>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo _l('Select'); ?>
                </button>
            </div>
            </noscript>

          <!--   <div class="form-group">
                <label for="email" class="control-label">
                    <?php echo _l('admin_auth_login_email'); ?>
                </label>
                <input type="email" id="email" name="email" class="form-control" autofocus="1" value="">
            </div>

            <div class="form-group">
                <label for="password" class="control-label">
                    <?php echo _l('admin_auth_login_password'); ?>
                </label>
                <input type="password" id="password" name="password" class="form-control" value="">
            </div>

            <?php if (show_recaptcha()) { ?>
            <div class="g-recaptcha tw-mb-4" data-sitekey="<?php echo get_option('recaptcha_site_key'); ?>"></div>
            <?php } ?>

            <div class="form-group">
                <div class="checkbox checkbox-inline">
                    <input type="checkbox" value="estimate" id="remember" name="remember">
                    <label for="remember"> <?php echo _l('admin_auth_login_remember_me'); ?></label>
                </div>
            </div> -->

           <!--  <div class="form-group">
                <a href="<?php echo admin_url('authentication/forgot_password'); ?>">
                    <?php echo _l('admin_auth_login_fp'); ?>
                </a>
            </div>

            <?php hooks()->do_action('before_admin_login_form_close'); ?> -->

            <?php echo form_close(); ?>
        </div>
    </div>

    <script>
    // Branch is defaulted to Admin (hidden field above) — auto-submit
    // immediately so the user lands straight on the login page.
    document.forms[0].submit();
    </script>

</body>

</html>