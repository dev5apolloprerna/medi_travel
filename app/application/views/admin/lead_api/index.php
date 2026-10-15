<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                            <h4 class="tw-my-0 tw-font-semibold">General Lead API</h4>
                            <a href="<?php echo admin_url('lead_api/regenerate'); ?>" class="btn btn-default btn-sm _delete"
                                title="Generate new GUID (old URL will stop working)">
                                <i class="fa-solid fa-rotate"></i> New GUID
                            </a>
                        </div>

                        <div class="input-group">
                            <input type="text" class="form-control" id="lead-api-url" readonly value="<?php echo e($api_url); ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="button" onclick="copyLeadApiUrl()">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </span>
                        </div>

                        <hr />
                        <p class="tw-font-semibold">How to use</p>
                        <p>Send a <code>POST</code> request to the URL above (JSON or form-data).</p>
<pre>{
  "name": "Amina Otieno",                  // required - Full name
  "country": "Kenya",                      // Country (name from the form dropdown)
  "mobile": "+254 700 123 456",            // required - WhatsApp / Phone
  "email": "amina@example.com",            // Email (optional)
  "treatment": "Orthopaedic Care",         // Treatment needed
  "message": "Knee pain for 2 years, MRI report available",  // Brief about your case
  "source": "Website"                      // lead source name, created if new
}</pre>
                        <p class="text-muted">Country options: Kenya, Nigeria, Tanzania, Uganda, Ethiopia, UAE, Saudi Arabia, Oman, Qatar, Iraq, India, Bangladesh, Sri Lanka, Nepal, Maldives, Uzbekistan, Kazakhstan, Tajikistan, Myanmar, Indonesia, Philippines, Other.<br>
                        Leave the hidden <code>hp_field</code> empty (spam protection).</p>
                        <p>Response: <code>{"success":true,"message":"Lead saved successfully","lead_id":123}</code></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
function copyLeadApiUrl() {
    var el = document.getElementById('lead-api-url');
    el.select();
    document.execCommand('copy');
    alert_float('success', 'URL copied');
}
</script>
</body>

</html>