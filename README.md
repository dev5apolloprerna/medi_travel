# Discharge Feedback module – MediTravel CRM (Perfex)

## What it does

1. **Lead closed = patient discharged.** When staff change a lead's status to **Close**, the module creates a feedback request for that patient. This works from the lead edit form, kanban drag, the status dropdown and bulk actions. It uses the `lead_status_changed` hook already in `app/application/models/Leads_model.php`, which passes a status ID from the edit form and a status name from kanban/bulk; both are handled.
2. **Feedback link sent right away** by **Email** (your CRM SMTP) and **SMS** (your active MSG91 / Twilio / Clickatell gateway) to the lead's email and phone number.
3. **Daily cron reminder.** Once a day the same message goes out again to every patient who has **not** filled the form yet. It stops automatically when the form is submitted, or after the maximum number of messages you set.
4. **Public feedback form outside the CRM** (no login). This is your Fit-to-Fly Medical Sign-Off form, mobile friendly:
   - **Personal link** per patient (sent in the email/SMS), pre-filled with the patient name, one submission only:
     `https://YOUR-DOMAIN/discharge_feedback/patient_feedback/form/<unique-code>`
   - **General link** you can share anywhere (WhatsApp, website, QR code):
     `https://YOUR-DOMAIN/discharge_feedback/patient_feedback/form`
5. **Admin screen** at *Sidebar → Discharge Feedback*:
   - Patients list with status, copy-link and "Send now" buttons
   - Send history per patient
   - Submitted forms (view / print)
   - Settings

Works with your **Branch module** (multi-database). All feedback data is stored in the **main** database, and leads are read from each branch database.

---

## Folder contents

```
discharge_feedback/
├── discharge_feedback.php          ← module definition + hooks (status change, cron)
├── install.php / uninstall.php     ← creates / drops tables
├── controllers/
│   ├── Discharge_feedback.php      ← admin pages (/admin/discharge_feedback)
│   ├── Patient_feedback.php        ← PUBLIC feedback form
│   └── Dfb_cron.php                ← optional dedicated daily cron URL
├── models/Discharge_feedback_model.php   ← all logic (detect, send, remind, save)
├── views/public/form.php           ← your Fit-to-Fly form (online version)
├── views/public/thanks.php
├── views/admin/*.php               ← admin screens
├── language/english/discharge_feedback_lang.php
└── sql/manual_install.sql          ← only if activation can't create tables
```

---

## Installation (about 10 minutes)

### Step 1 – Add to your project
This zip mirrors your `medi_travel` repository. Pick one way:

**A) Copy the folder.** Copy `app/modules/discharge_feedback/` from the zip into your repo or server at the same path, next to `appointly` and `branch`. Then commit and deploy as usual:
```
git checkout -b discharge-feedback
git add app/modules/discharge_feedback
git commit -m "Add discharge feedback module"
git push origin discharge-feedback
```

**B) Apply the git patch.** From the repo root:
```
git checkout -b discharge-feedback
git am < discharge_feedback.patch
```

Nothing outside `app/modules/discharge_feedback/` is changed. No core Perfex files are edited, so CRM updates won't overwrite it.

Check that this file exists on the server: `app/modules/discharge_feedback/discharge_feedback.php`

### Step 2 – Activate
1. Log in to the CRM as **admin** (in the **main** branch).
2. Go to **Setup → Modules**, find **Discharge Feedback**, and click **Activate**.
3. A new sidebar item **Discharge Feedback** appears under Leads.

> Your Branch module automatically repeats the activation for every branch database. That's harmless, because the data always lives in the main DB.
> If activation shows a database error, run `sql/manual_install.sql` in phpMyAdmin on the **main** database and activate again.

### Step 3 – Choose the "Close" status
Go to **Discharge Feedback → Settings**:
- In **Lead status name(s) that mean Close = Discharge**, click your status chip (e.g. *Close*). The default already covers `Close, Closed, Discharge, Discharged`.
- Names are used instead of IDs, so the same setting works in every branch even if the status IDs differ.

### Step 4 – Make sure Email and SMS work
- **Email:** Setup → Settings → Email. Use **Send Test Email** to confirm SMTP works.
- **SMS:** Setup → Settings → SMS. Enable **one** gateway (MSG91 for India) and use **Send Test SMS**.
  - **India DLT rule:** the SMS text must match a **DLT-approved template** exactly, or Jio/Airtel/Vi will block it. Register the SMS text from the module settings on your DLT portal, with `{#var#}` for each variable, for example:
    `Dear {#var#}, thank you for choosing {#var#}. Please fill your discharge feedback form: {#var#}`
    Then make sure the text in module settings produces exactly that message.
- **Phone numbers:** the module turns 10-digit numbers into `91XXXXXXXXXX` (or `+91…` for Twilio). Change the country code in settings if needed.

### Step 5 – Set the cron job (daily messages)

**Option A – Perfex cron (recommended).** Most likely you already have it (Setup → Settings → Cron Job shows the command). If not, add this in **hPanel → Advanced → Cron Jobs** (Hostinger) or cPanel → Cron Jobs:

```
*/5 * * * *   wget -q -O- https://YOUR-DOMAIN/cron/index >/dev/null 2>&1
```

Every cron run, the module:
- picks up any closed lead the status hook missed (only leads closed **after** installation, so old leads won't be spammed)
- sends links that failed earlier (retried once per hour)
- **once a day, at or after the hour in settings (default 10:00)**, sends the reminder to everyone still waiting

**Option B – dedicated daily URL (exact time).** Copy the URL with its secret key from **Settings → Cron job** and add:

```
0 10 * * *   wget -q -O- "https://YOUR-DOMAIN/discharge_feedback/dfb_cron/run/YOUR_SECRET_KEY" >/dev/null 2>&1
```

You can use A, B or both. A patient never gets more than one message per day.

> Hostinger's hPanel cron uses "Custom" → paste only the command part (`wget …`) and choose the schedule in the dropdowns.

### Step 6 – Branch databases (only if you use branches)
- Status changes made inside a branch are sent instantly via the hook.
- For the cron scan to also read branch databases, the `tblbranch` table (main DB) must have **branch_db_user** and **branch_db_pass** filled for each branch, the same values Appointly already uses. If a branch can't be connected, it is skipped and logged in `application/logs`.

---

## Testing checklist

1. Create a test lead with **your own** email and mobile number.
2. Change its status to **Close**. You should receive the email and SMS within a few seconds.
3. Open **Discharge Feedback**. The lead shows *Waiting for form*, Messages sent = 1.
4. Click **Run daily job now**. Nothing new is sent, because the link already went out today. This proves there are no duplicates.
5. Open the link from the SMS on your phone, fill in the form and submit. You'll see the **Thank you** page.
6. The patient now shows **Form submitted**, reminders stop, and the form appears under **Submitted Forms** (with Print).
7. Open the same link again. It says *already submitted*.
8. To test a reminder: in phpMyAdmin set `last_sent_at` of that request to yesterday, set `status` back to `pending`, then click **Run daily job now**.

---

## Settings reference

| Setting | Default | Meaning |
|---|---|---|
| Enable | On | Master switch |
| Status name(s) | Close, Closed, Discharge, Discharged | Lead statuses treated as discharge |
| Send by Email / SMS | On / On | Channels |
| Country code | 91 | Added to 10-digit numbers |
| Daily reminder hour | 10 | Server time, 0–23 |
| Maximum messages | 5 | Includes the first message. 0 = every day until submitted |
| General link | On | Allow the shareable form without a personal code |
| Templates | — | Merge fields: `{patient_name}` `{feedback_link}` `{company_name}` `{branch_name}` |

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Nothing sent on Close | Check the status name matches in Settings. Open **History** for the patient to see the exact error. |
| "No active SMS gateway" | Activate a gateway in Setup → Settings → SMS. |
| SMS "sent" but not delivered | DLT template mismatch (India). Check your MSG91 delivery report. |
| Email failed | Fix SMTP in Setup → Settings → Email and use the test button. |
| "Lead has no valid email / phone" | Add an email or phone number to the lead, then click **Send now** (this also restarts a stopped request). |
| Reminders not going daily | Cron not running. Open `https://YOUR-DOMAIN/cron/index` once in a browser, or use the Option B URL to see the JSON result. |
| Public link shows 404 | Make sure the folder is `app/modules/discharge_feedback` (lowercase) and the module is **activated**. |
| Old closed leads got no message | By design, only leads closed after installation are picked up. For an old lead, set its status away from Close and back to Close. |

## Customising the form
- Add a field: add the input in `views/public/form.php`, read it in `controllers/Patient_feedback.php` (`$values`), add a column to `tbldischarge_feedback_responses`, and add it to `save_response()` in the model.
- The checklist texts still end in "..." exactly like your original HTML. Replace them with the full wording in `views/public/form.php` and `views/admin/response.php`.

## Uninstall
Setup → Modules → Deactivate, then Uninstall. **Uninstall deletes all feedback data.**
