<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Medi Travel - Thank You</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #ffffff; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #33415a; }
        .page { max-width: 900px; margin: 0 auto; padding: 13mm 15mm; }
        .brand { padding: 0 0 10px 0; font-size: 32px; line-height: 1.05; font-weight: bold; letter-spacing: 5px; color: #24469a; }
        .document-title { padding: 6px 0 16px 0; font-size: 20px; font-weight: bold; color: #182033; }
        .top-rule { height: 12px; margin: 0 0 24px 0; border-bottom: 3px solid #1787c8; }
        .box { padding: 22px 24px; border: 1px solid #c7d2df; background: #f4f6f9; }
        .box h2 { margin: 0 0 8px 0; font-size: 18px; color: #182033; }
        .box p { margin: 0; color: #26334a; }
        @media (max-width: 640px) {
            .page { padding: 18px 16px; }
            .brand { font-size: 24px; letter-spacing: 3px; }
            .document-title { font-size: 17px; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="brand">MEDITRAVEL</div>
    <div class="document-title">FIT-TO-FLY MEDICAL SIGN-OFF FORM</div>
    <div class="top-rule"></div>

    <div class="box">
        <?php if ($already) { ?>
            <h2>Your form is already complete</h2>
            <p>We have already received your Fit-to-Fly form. Thank you – there is nothing more you need to do.</p>
        <?php } else { ?>
            <h2>Thank you!</h2>
            <p>Your form has been submitted successfully. Our team will review it shortly.</p>
        <?php } ?>
        <p style="margin-top:14px;">Wishing you a safe journey home.<br>
            Team <?php echo html_escape($company ?: 'MediTravel'); ?></p>
    </div>
</div>
</body>
</html>
