<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PUBLIC pages (no login):
 *
 *   Patient form :  /feedback/f/<token>            (link sent by Email/SMS)
 *   Daily cron   :  /feedback/cron/<FEEDBACK_CRON_KEY>
 *
 * Helper functions + settings: application/helpers/my_functions_helper.php
 */
class Feedback extends ClientsController
{
    public function index()
    {
        show_404();
    }

    /* =========================================================
       PATIENT FORM  -  uses the existing consent template
       uploads/consern_form/Fit-to-FlyMedical.html
    ==========================================================*/
    public function f($token = '')
    {
        if (!preg_match('/^[a-f0-9]{24}$/', (string) $token)) {
            show_404();
        }

        $table   = feedback_table_ready();
        $request = $this->db->where('token', $token)->get($table)->row_array();
        if (!$request) {
            show_404();
        }

        if (!empty($request['submitted_at'])) {
            $this->thanks(true);

            return;
        }

        $path = FCPATH . 'uploads/consern_form/' . basename(FEEDBACK_FORM_FILE);
        if (!file_exists($path)) {
            show_error('Form template not found.');
        }
        $template = file_get_contents($path);

        $values = ['patient_name' => $request['patient_name']];
        $error  = '';

        if ($this->input->method() === 'post') {
            $values = $this->collect_post($template);

            if (array_key_exists('patient_name', $values) && trim((string) $values['patient_name']) === '') {
                $error = 'Please enter the patient full name.';
            } else {
                $this->save($request, $values);
                $this->thanks(false);

                return;
            }
        }

        echo $this->render($template, $values, $error);
    }

    /* =========================================================
       DAILY CRON  -  send link / reminder to everyone who has
       not submitted yet (max 1 message per patient per day)
    ==========================================================*/
    public function cron($key = '')
    {
        if (!hash_equals((string) FEEDBACK_CRON_KEY, (string) $key)) {
            show_404();
        }

        @set_time_limit(300);
        $table = feedback_table_ready();

        $this->db->where('submitted_at IS NULL', null, false);
        $this->db->where('(last_sent_at IS NULL OR last_sent_at < ' . $this->db->escape(date('Y-m-d 00:00:00')) . ')', null, false);
        if ((int) FEEDBACK_MAX_SENDS > 0) {
            $this->db->where('send_count <', (int) FEEDBACK_MAX_SENDS);
        }
        $rows = $this->db->get($table)->result_array();

        $sent   = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $result = feedback_send($row, (int) $row['send_count'] > 0);
            $result['sent'] ? $sent++ : $failed++;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'database' => $this->db->database,
            'time'     => date('Y-m-d H:i:s'),
            'due'      => count($rows),
            'sent'     => $sent,
            'failed'   => $failed,
        ]);
    }

    /* ---------------------------------------------------------
       Save into tblconsent_form_submissions (same format as the
       Save button in Patient Profile -> Consent Forms)
    ----------------------------------------------------------*/
    private function save($request, array $formData)
    {
        $now     = date('Y-m-d H:i:s');
        $json    = json_encode($formData, JSON_UNESCAPED_UNICODE);
        $patient = feedback_find_patient($request['lead_id'], $request['email'], $request['phone']);

        $pdf = $this->db->select('pdf_id')
            ->group_start()
                ->where('english_file_name', FEEDBACK_FORM_FILE)
                ->or_where('gujarati_file_name', FEEDBACK_FORM_FILE)
            ->group_end()
            ->get('tblconsent_form_master')->row();

        $savedToProfile = false;

        if ($pdf && $patient['patient_id'] > 0) {
            $table = db_prefix() . 'consent_form_submissions';
            $key   = [
                'consent_pdf_id' => (int) $pdf->pdf_id,
                'patient_id'     => $patient['patient_id'],
                'appointment_id' => $patient['appointment_id'],
                'lang'           => 'en',
            ];
            $data = $key + [
                'appointment_type_id' => $patient['appointment_type_id'],
                'doctor_id'           => 0,
                'patient_name'        => trim((string) ($formData['patient_name'] ?? $request['patient_name'])),
                'doctor_name'         => '',
                'form_data_json'      => $json,
                'updated_at'          => $now,
            ];

            $existing = $this->db->where($key)->get($table)->row();
            if ($existing) {
                $this->db->where('id', $existing->id)->update($table, $data);
            } else {
                $data['created_at'] = $now;
                $this->db->insert($table, $data);
            }
            $savedToProfile = true;
        }

        // Keep a copy on the request (also stops the daily reminders)
        $this->db->where('id', $request['id'])->update(feedback_table_ready(), [
            'submitted_at' => $now,
            'last_error'   => $savedToProfile ? null : 'Submitted, but no matching patient / consent form found - answers kept in tblfeedback_requests.form_data_json',
        ]);
        $this->store_backup($request['id'], $json);

        feedback_lead_activity(
            $request['lead_id'],
            'Fit-to-Fly form submitted by patient'
            . ($savedToProfile ? ' (saved in Patient Profile -> Consent Forms)' : ' (no patient record linked yet - answers kept in tblfeedback_requests)')
        );
    }

    private function store_backup($id, $json)
    {
        $table = feedback_table_ready();
        if (!$this->db->field_exists('form_data_json', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `form_data_json` LONGTEXT NULL');
        }
        $this->db->where('id', $id)->update($table, ['form_data_json' => $json]);
    }

    /* ---------------------------------------------------------
       Read every named field of the template from POST
       (checkbox = its value or '', like the profile Save button)
    ----------------------------------------------------------*/
    private function collect_post($template)
    {
        $data = [];
        foreach ($this->template_fields($template) as $field) {
            $name   = $field['name'];
            $isList = substr($name, -2) === '[]';
            $key    = $isList ? substr($name, 0, -2) : $name;
            $posted = $this->input->post($key);

            if ($field['type'] === 'checkbox') {
                if ($isList) {
                    $data[$key] = is_array($posted) ? array_values(array_map('strval', $posted)) : [];
                } else {
                    $data[$key] = $posted !== null ? ($field['value'] !== '' ? $field['value'] : 'on') : '';
                }
            } elseif (is_array($posted)) {
                $data[$key] = array_map(function ($v) {
                    return mb_substr(trim((string) $v), 0, 2000);
                }, $posted);
            } else {
                $data[$key] = mb_substr(trim((string) $posted), 0, 5000);
            }
        }

        return $data;
    }

    private function template_fields($template)
    {
        $dom    = $this->load_dom($template);
        $fields = [];
        foreach ((new DOMXPath($dom))->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            $type = strtolower($el->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'reset', 'file'], true)) {
                continue;
            }
            $fields[$el->getAttribute('name') . '|' . $type] = [
                'name'  => $el->getAttribute('name'),
                'type'  => $type,
                'value' => $el->getAttribute('value'),
            ];
        }

        return array_values($fields);
    }

    /* ---------------------------------------------------------
       Show the template with values filled in + Submit button
    ----------------------------------------------------------*/
    private function render($template, array $values, $error)
    {
        $dom   = $this->load_dom($template);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//input[@name] | //textarea[@name] | //select[@name]') as $el) {
            $name = $el->getAttribute('name');
            $key  = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            $tag   = strtolower($el->nodeName);
            $type  = strtolower($el->getAttribute('type'));

            if ($type === 'checkbox' || $type === 'radio') {
                $checked = is_array($value)
                    ? in_array($el->getAttribute('value'), $value, true)
                    : ($type === 'radio' ? (string) $value === $el->getAttribute('value') : (string) $value !== '' && (string) $value !== '0');
                $checked ? $el->setAttribute('checked', 'checked') : $el->removeAttribute('checked');
            } elseif ($tag === 'textarea') {
                $el->nodeValue = '';
                $el->appendChild($dom->createTextNode((string) $value));
            } elseif ($tag === 'select') {
                foreach ($el->getElementsByTagName('option') as $option) {
                    $option->getAttribute('value') === (string) $value
                        ? $option->setAttribute('selected', 'selected')
                        : $option->removeAttribute('selected');
                }
            } elseif (!is_array($value)) {
                $el->setAttribute('value', (string) $value);
            }
        }

        // Use the template's own <form>, or wrap the body in one
        $form = $dom->getElementsByTagName('form')->item(0);
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$form && $body) {
            $form = $dom->createElement('form');
            while ($body->firstChild) {
                $form->appendChild($body->firstChild);
            }
            $body->appendChild($form);
        }
        if (!$form) {
            return $template;
        }
        $form->setAttribute('action', ''); // posts back to this same link
        $form->setAttribute('method', 'post');

        $csrf = $dom->createElement('input');
        $csrf->setAttribute('type', 'hidden');
        $csrf->setAttribute('name', $this->security->get_csrf_token_name());
        $csrf->setAttribute('value', $this->security->get_csrf_hash());
        $form->insertBefore($csrf, $form->firstChild);

        if ($error !== '') {
            $box = $dom->createElement('div');
            $box->setAttribute('class', 'fb-error');
            $box->appendChild($dom->createTextNode($error));
            $form->insertBefore($box, $form->firstChild);
        }

        $actions = $dom->createElement('div');
        $actions->setAttribute('class', 'fb-actions');
        $button = $dom->createElement('button', 'Submit Form');
        $button->setAttribute('type', 'submit');
        $button->setAttribute('class', 'fb-submit');
        $actions->appendChild($button);
        $form->appendChild($actions);

        $style = $dom->createElement('style', '
            .fb-actions{max-width:900px;margin:10px auto 30px;padding:0 15mm;text-align:right}
            .fb-submit{padding:11px 28px;border:0;border-radius:4px;background:#24469a;color:#fff;font:bold 15px Arial,sans-serif;cursor:pointer}
            .fb-submit:hover{background:#1c3880}
            .fb-error{max-width:900px;margin:16px auto 0;padding:10px 12px;border-left:4px solid #c0392b;background:#fdf1f0;color:#8e2a20;font:14px Arial,sans-serif}
            @media (max-width:640px){.page{padding:16px!important}.fb-actions{padding:0 16px}.fb-submit{width:100%}
              .profile-table,.profile-table tbody,.profile-table tr,.profile-table td,.signature-table,.signature-table tbody,.signature-table tr,.signature-table td{display:block;width:100%!important}
              .signature-left{padding-right:0!important;margin-bottom:20px}}
            @media print{.fb-actions,.fb-error{display:none}}');
        $head = $dom->getElementsByTagName('head')->item(0);
        $head ? $head->appendChild($style) : $form->appendChild($style);

        return $dom->saveHTML();
    }

    private function load_dom($html)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        foreach (iterator_to_array($dom->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $dom->removeChild($node);
            }
        }

        return $dom;
    }

    private function thanks($already)
    {
        $company = html_escape(get_option('companyname') ?: 'MediTravel');
        $title   = $already ? 'This form has already been submitted' : 'Thank you!';
        $text    = $already
            ? 'We have already received your form. There is nothing more you need to do.'
            : 'Your form has been submitted successfully. Our team will review it shortly.';

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Thank You</title><style>body{margin:0;font:14px/1.5 Arial,sans-serif;color:#33415a}'
            . '.page{max-width:900px;margin:0 auto;padding:40px 20px}.brand{font-size:30px;font-weight:bold;letter-spacing:5px;color:#24469a}'
            . '.rule{height:12px;margin:0 0 24px;border-bottom:3px solid #1787c8}.box{padding:22px 24px;border:1px solid #c7d2df;background:#f4f6f9}'
            . 'h2{margin:0 0 8px;font-size:18px;color:#182033}</style></head><body><div class="page">'
            . '<div class="brand">MEDITRAVEL</div><div class="rule"></div><div class="box">'
            . '<h2>' . $title . '</h2><p>' . $text . '</p><p>Wishing you a safe journey home.<br>Team ' . $company . '</p>'
            . '</div></div></body></html>';
    }
}
