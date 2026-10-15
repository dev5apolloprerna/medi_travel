<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin area:  /admin/discharge_feedback
 */
class Discharge_feedback extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin() && staff_cant('view', 'leads')) {
            access_denied('Discharge Feedback');
        }
        $this->load->model('discharge_feedback/discharge_feedback_model', 'dfb');
        $this->dfb->ensure_schema();
    }

    /* Patients who were sent the link */
    public function index()
    {
        $status = (string) $this->input->get('status');
        if (!in_array($status, ['', 'pending', 'completed', 'stopped'], true)) {
            $status = '';
        }

        $this->load->view('discharge_feedback/admin/requests', [
            'title'        => _l('discharge_feedback'),
            'active'       => 'requests',
            'requests'     => $this->dfb->get_requests($status),
            'stats'        => $this->dfb->stats(),
            'status'       => $status,
            'max_messages' => (int) $this->dfb->setting('max_messages'),
            'dfb'          => $this->dfb,
        ]);
    }

    /* Submitted forms */
    public function responses()
    {
        $this->load->view('discharge_feedback/admin/responses', [
            'title'     => _l('discharge_feedback_responses'),
            'active'    => 'responses',
            'responses' => $this->dfb->get_responses(),
        ]);
    }

    public function response($id = 0)
    {
        $response = $this->dfb->get_response($id);
        if (!$response) {
            show_404();
        }

        // ?raw=1 -> the filled form itself (shown in an iframe / printed)
        if ($this->input->get('raw')) {
            $path = FCPATH . 'uploads/consern_form/' . basename((string) ($response['form_file'] ?: $this->dfb->form_file()));
            if (!is_file($path)) {
                show_error('Template not found: ' . html_escape(basename($path)));
            }
            $values = json_decode((string) $response['form_data_json'], true);
            if (!is_array($values)) {
                // rows saved by the first module version
                $values = [
                    'patient_name' => $response['patient_name'],
                    'input2'       => $response['passport_number'] ?? '',
                    'input3'       => !empty($response['incision_healed']) ? '1' : '',
                    'input4'       => !empty($response['cardio_stable']) ? '1' : '',
                    'input5'       => $response['physician_signature'] ?? '',
                    'input6'       => $response['license_number'] ?? '',
                ];
            }
            echo $this->dfb->render_template(file_get_contents($path), $values, ['readonly' => true]);

            return;
        }

        $this->load->view('discharge_feedback/admin/response', [
            'title'    => _l('discharge_feedback_response'),
            'active'   => 'responses',
            'response' => $response,
        ]);
    }

    /* Send log of one patient */
    public function history($id = 0)
    {
        $request = $this->dfb->get_request($id);
        if (!$request) {
            show_404();
        }

        $this->load->view('discharge_feedback/admin/history', [
            'title'   => _l('discharge_feedback_history'),
            'active'  => 'requests',
            'request' => $request,
            'logs'    => $this->dfb->get_logs($id),
            'link'    => $this->dfb->feedback_link($request['hash']),
        ]);
    }

    /* Send the link again now */
    public function resend($id = 0)
    {
        $request = $this->dfb->get_request($id);
        if (!$request) {
            show_404();
        }

        if ($request['status'] === 'completed') {
            set_alert('warning', 'This patient has already submitted the form.');
        } else {
            if ($request['status'] === 'stopped') {
                $this->dfb->set_request_status($id, 'pending');
                $request['status'] = 'pending';
            }
            $result = $this->dfb->send_request($request, (int) $request['send_count'] > 0);
            if ($result['completed']) {
                set_alert('info', 'Not sent: the Fit-to-Fly form is already filled in this patient\'s profile (Consent Forms). Marked as completed.');
            }
            if ($result['sent']) {
                set_alert('success', 'Feedback link sent by: ' . strtoupper(implode(' + ', $result['sent'])));
            }
            if ($result['errors']) {
                set_alert($result['sent'] ? 'warning' : 'danger', implode(' | ', $result['errors']));
            }
        }

        redirect(admin_url('discharge_feedback'));
    }

    /* Stop / restart daily reminders for one patient */
    public function toggle($id = 0)
    {
        $request = $this->dfb->get_request($id);
        if ($request && $request['status'] !== 'completed') {
            $new = $request['status'] === 'stopped' ? 'pending' : 'stopped';
            $this->dfb->set_request_status($id, $new);
            set_alert('success', $new === 'stopped' ? 'Reminders stopped.' : 'Reminders restarted.');
        }

        redirect(admin_url('discharge_feedback'));
    }

    /* Run the daily job right now (testing) */
    public function run_now()
    {
        if (!is_admin()) {
            access_denied('Discharge Feedback');
        }

        $r = $this->dfb->run(true);
        if (!empty($r['disabled'])) {
            set_alert('warning', 'Discharge feedback is disabled in Settings.');
        } else {
            set_alert('success', sprintf(
                'Done. New discharges found: %d · first messages sent: %d · reminders sent: %d · already filled in patient profile: %d.',
                $r['new_discharges'],
                $r['first_messages'],
                $r['reminders'],
                $r['filled_in_profile']
            ));
        }

        redirect(admin_url('discharge_feedback'));
    }

    public function settings()
    {
        if (!is_admin()) {
            access_denied('Discharge Feedback Settings');
        }

        if ($this->input->post()) {
            $text = ['status_names', 'country_code', 'email_subject', 'sms_body'];
            $file = basename((string) $this->input->post('form_file'));
            if (in_array($file, $this->dfb->form_files(), true)) {
                $this->dfb->save_setting('form_file', $file);
            }
            foreach ($text as $key) {
                $this->dfb->save_setting($key, trim((string) $this->input->post($key)));
            }

            $this->dfb->save_setting('email_body', (string) $this->input->post('email_body', false));
            $this->dfb->save_setting('daily_hour', (string) max(0, min(23, (int) $this->input->post('daily_hour'))));
            $this->dfb->save_setting('max_messages', (string) max(0, (int) $this->input->post('max_messages')));

            foreach (['enabled', 'send_email', 'send_sms', 'allow_general_link'] as $key) {
                $this->dfb->save_setting($key, $this->input->post($key) ? '1' : '0');
            }

            if ($this->input->post('regenerate_cron_key')) {
                $this->dfb->save_setting('cron_key', bin2hex(random_bytes(16)));
            }

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('discharge_feedback/settings'));
        }

        $settings = $this->dfb->settings();
        if ($settings['cron_key'] === '') {
            $this->dfb->save_setting('cron_key', bin2hex(random_bytes(16)));
            $settings = $this->dfb->settings();
        }

        $this->load->model('leads_model');

        $this->load->view('discharge_feedback/admin/settings', [
            'title'         => _l('discharge_feedback_settings'),
            'active'        => 'settings',
            's'             => $settings,
            'lead_statuses' => $this->leads_model->get_status(),
            'form_files'    => $this->dfb->form_files(),
            'pdf_id'        => $this->dfb->consent_pdf_id($this->db),
            'general_link'  => $this->dfb->feedback_link(),
            'cron_url'      => site_url('discharge_feedback/dfb_cron/run/' . $settings['cron_key']),
            'perfex_cron'   => site_url('cron/index'),
        ]);
    }
}
