# Discharge Feedback module – MediTravel CRM
### Lead closed = Discharge → patient gets the Fit-to-Fly form link → daily reminder until it's filled

---

## 1. What it does

| Step | What happens |
|---|---|
| **Lead set to "Close"** | Treated as **discharge**. Works from the kanban board, the status dropdown, a bulk action and the lead edit form. |
| **Link sent instantly** | The patient gets a personal link by **Email** (your SMTP) and **SMS** (your active MSG91 / Twilio gateway). The link is also written in the lead's **Activity Log**, so staff can copy it or send it on WhatsApp. |
| **Patient fills the form** | The link opens your existing consent template **`uploads/consern_form/Fit-to-FlyMedical.html`**. It needs no login, works on phones, and has the patient name filled in. |
| **Saved in the patient profile** | Answers are saved into `tblconsent_form_submissions`, the same table and format the patient profile Save button uses. So **Patient Profile → Consent Forms → Fit-to-Fly** shows the form as filled, and View/PDF work as usual. |
| **Daily cron** | Once a day, everyone who hasn't filled the form gets the link again: at most one message per day, up to the maximum you set. |
| **Already filled in the profile?** | If staff fill the Fit-to-Fly form in **Patient Profile → Consent Forms**, the cron sees it and stops the messages. The patient shows as **"Filled in patient profile"**. |

How the patient is found: lead converted to patient (`tblclients.leadid`) → otherwise the same email → otherwise the same mobile (only if exactly one patient has it). The form is attached to that patient's **latest appointment**, which is what the profile View button needs.

---

## 2. Files

```
app/modules/discharge_feedback/
├── discharge_feedback.php          module + hooks (lead status change, Perfex cron)
├── install.php / uninstall.php     creates / removes the tables
├── controllers/
│   ├── Discharge_feedback.php      admin pages   → /admin/discharge_feedback
│   ├── Patient_feedback.php        patient form  → /discharge_feedback/patient_feedback/form/<code>
│   └── Dfb_cron.php                daily cron    → /discharge_feedback/dfb_cron/run/<secret-key>
├── models/Discharge_feedback_model.php   all logic
├── views/admin/…                   Patients · Submitted Forms · Settings · History
├── views/public/thanks.php
├── language/english/…
└── sql/manual_install.sql          only if activation cannot create the tables
```

No existing CRM file is changed.

---

## 3. Install (local WAMP: `C:\wamp64\www\medi_travel`)

1. **Remove older test code** if you copied any:
   - **Earlier version of this module:** you can copy the new folder over it. The tables are upgraded automatically, and existing data is kept.
   - **"Simple version":** delete `app/application/helpers/my_functions_helper.php` and `app/application/controllers/Feedback.php`. Otherwise patients get two messages.
2. **Copy the folder** `app/modules/discharge_feedback` from this zip to
   `C:\wamp64\www\medi_travel\app\modules\discharge_feedback`
3. **Activate the module:** log in as admin → **Setup → Modules** → **Discharge Feedback** → **Activate**.
   A new menu item **Discharge Feedback** appears under Leads.
4. **Check the settings:** Discharge Feedback → **Settings**:
   - **Status names:** click your **Close** chip (default `Close, Closed, Discharge, Discharged`).
   - **Form:** `Fit-to-FlyMedical.html`. The green line **"Linked to consent form #…"** must be visible. If it's red, the file isn't in `tblconsent_form_master`; add it in your consent forms setup.
   - Click **Save**.
5. **Email and SMS:**
   - **Email:** Setup → Settings → Email → send a test email.
   - **SMS:** Setup → Settings → SMS → activate one gateway and send a test SMS.
   - **India (MSG91):** the SMS text must match your DLT-approved template exactly.

Git commands (from `C:\wamp64\www\medi_travel` in Git Bash):
```bash
git checkout -b discharge-feedback
git add app/modules/discharge_feedback
git commit -m "Discharge feedback: Fit-to-Fly link on lead close + daily reminder cron"
git push origin discharge-feedback
```

---

## 4. Run the cron job LOCALLY (WAMP)

### Step 1: get your cron URL
Discharge Feedback → **Settings** → **Cron job** box → copy the **Daily cron URL**. It looks like:
```
http://localhost/medi_travel/app/discharge_feedback/dfb_cron/run/e6d3d341e32db454a6feb00d47aae56e
```
The last part is your **secret key** (also in phpMyAdmin: `SELECT value FROM tbldischarge_feedback_settings WHERE name='cron_key';`).

### Step 2: run it (pick one)

**A. Browser (easiest):** paste the URL in Chrome. You'll see:
```json
{"success":true,"time":"2026-10-13 16:57:06","new_discharges":0,"first_messages":0,
 "reminders":1,"filled_in_profile":0,"daily_run":true}
```

| Field | Meaning |
|---|---|
| `new_discharges` | closed leads found that didn't have a link yet |
| `first_messages` | first links sent in this run |
| `reminders` | daily reminders sent |
| `filled_in_profile` | patients skipped because staff already filled Fit-to-Fly in the profile |

**B. Button:** Discharge Feedback → **Run daily job now** (top right). It does the same thing and shows the result as a message.

**C. Git Bash / Command Prompt:**
```bash
curl -s "http://localhost/medi_travel/app/discharge_feedback/dfb_cron/run/YOUR_KEY"
```

> 404? Apache mod_rewrite is off in WAMP. Either turn on **WAMP tray icon → Apache → Apache modules → rewrite_module**, or put `index.php/` in the URL:
> `http://localhost/medi_travel/app/index.php/discharge_feedback/dfb_cron/run/YOUR_KEY`

### Step 3 (optional): run it automatically every day on Windows
1. Open **Task Scheduler** → **Create Basic Task…** → name it `MediTravel Feedback Cron`.
2. Trigger: **Daily**, time **10:00**.
3. Action: **Start a program**
   - Program/script: `C:\Windows\System32\curl.exe`
   - Add arguments: `-s "http://localhost/medi_travel/app/discharge_feedback/dfb_cron/run/YOUR_KEY"`
4. Finish. To test it now, right-click the task → **Run**.

WAMP must be running (green icon) when the task runs.

---

## 5. Test the whole flow locally (10 minutes)

> **Careful:** if an SMS gateway is active in your local database, real SMS go out. Use your own number, or untick **Send by SMS** in module settings while testing.

1. **Create the test data:**
   - a patient with your own email and mobile, plus one appointment
   - a lead with the **same email and mobile**
2. **Close the lead:** drag it to **Close** on the kanban board. You should get the email within seconds.
   - Discharge Feedback → Patients shows it as **Waiting for form**.
   - The lead's Activity Log shows the link.
3. **Fill the form:** open the link (from the email or the Activity Log) on your phone or in a private window, fill it and press **Submit Form**. You'll see "Thank you!".
4. **Check it was saved:**
   - Patient Profile → **Consent Forms** → **Fit-to-Fly** is filled. Click **View** to see the answers.
   - Discharge Feedback → Patients shows **Submitted by patient**.
5. **Reminder test:** close a second lead, then in phpMyAdmin make it look like the link went out yesterday:
   ```sql
   UPDATE tbldischarge_feedback_requests
   SET last_sent_at = NOW() - INTERVAL 1 DAY
   WHERE lead_id = 123;   -- your lead id
   ```
   Run the cron URL → `"reminders":1` and an email with **"Reminder: …"** arrives.
   Run it again → `"reminders":0`, because it sends at most one per day.
6. **"Filled in profile" test:** for that second patient, fill Fit-to-Fly in **Patient Profile → Consent Forms** and Save. Repeat the SQL from step 5 and run the cron → `"filled_in_profile":1`. No message is sent, and the status is **Filled in patient profile**.

Each patient's **History** button shows every email/SMS with the exact error if one failed.

---

## 6. On the live server (Hostinger)

hPanel → **Advanced → Cron Jobs** → Custom → once a day:
```
0 10 * * *  wget -q -O /dev/null "https://YOUR-DOMAIN/discharge_feedback/dfb_cron/run/YOUR_KEY"
```
You can also (or instead) use the normal Perfex cron (`*/5 * * * * wget -q -O /dev/null https://YOUR-DOMAIN/cron/index`). The module sends new links on every run and reminders once a day after the **Daily reminder hour** in settings. Using both is fine: a patient never gets two messages on the same day.

**Branches:** one cron URL covers all branches. It reads every branch database listed in `tblbranch`, using `branch_db_user` / `branch_db_pass`, so make sure those are filled.

---

## 7. Settings reference

| Setting | Default | Meaning |
|---|---|---|
| Status name(s) | Close, Closed, Discharge, Discharged | lead statuses that mean discharge |
| Form | Fit-to-FlyMedical.html | consent template the patient fills (any file in `uploads/consern_form/`) |
| Send by Email / SMS | on / on | channels |
| Country code | 91 | added to 10-digit mobiles |
| Daily reminder hour | 10 | used by the Perfex cron (the module cron URL sends whenever you call it) |
| Maximum messages | 5 | including the first. **0** = every day until filled |
| General link | off | a link anyone can fill (not tied to a patient) |
| Templates | — | merge fields `{patient_name}` `{feedback_link}` `{company_name}` `{branch_name}` |

Only leads closed **after** the module was activated get messages, so old closed leads are not spammed.

---

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| Nothing happens on Close | The status name must be in Settings. Check the lead's Activity Log and the patient's **History**. |
| "No active SMS gateway" | Setup → Settings → SMS → activate a gateway. |
| Email failed | Fix SMTP in Setup → Settings → Email and use the test button. |
| "Not linked to a patient profile" under Submitted Forms | No patient matched the lead's email or mobile, or the lead wasn't converted. The answers are still kept under **Submitted Forms**. |
| Profile View button disabled | The patient has no appointment. Your profile only opens forms that have an appointment. |
| Settings shows a red "not in tblconsent_form_master" | Add Fit-to-FlyMedical.html to your consent form list. |
| Cron URL gives 404 | Wrong key, or mod_rewrite is off (see section 4). |
| Reminders not going out | They're only sent once per day per patient, and only while the status is *Waiting for form* and below the maximum. |
