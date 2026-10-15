<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PUBLIC form (no login) – shows the consent template chosen in
 * Discharge Feedback > Settings (default: Fit-to-FlyMedical.html from
 * uploads/consern_form/), exactly as it appears in Patient Profile.
 *
 *   Personal link (sent by email / SMS):  /discharge_feedback/patient_feedback/form/{code}
 *   General link (optional, in settings): /discharge_feedback/patient_feedback/form
 */
class Patient_feedback extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('discharge_feedback/discharge_feedback_model', 'dfb');
    }

    public function index()
    {
        show_404();
    }

    public function form($hash = '')
    {
        $request = null;

        if ($hash !== '') {
            $request = $this->dfb->get_by_hash($hash);
            if (!$request) {
                show_404();
            }
            // Submitted through this link, or already filled by the clinic in Patient Profile
            if ($request['status'] === 'completed' || $this->dfb->sync_with_profile($request)) {
                $this->thanks(true);

                return;
            }
        } elseif (!(int) $this->dfb->setting('allow_general_link')) {
            show_404();
        }

        $template = $this->dfb->template_html();
        if ($template === null) {
            show_error('The form template "' . html_escape($this->dfb->form_file()) . '" was not found in uploads/consern_form/.');
        }

        $values = ['patient_name' => $request ? $request['patient_name'] : ''];
        $error  = '';

        if ($this->input->method() === 'post') {
            $values = $this->dfb->collect_post($template, $this->input);

            if (array_key_exists('patient_name', $values) && trim((string) $values['patient_name']) === '') {
                $error = 'Please enter the patient full name.';
            } else {
                $this->dfb->save_response($request, $values, $this->input->ip_address(), $this->input->user_agent());
                $this->thanks(false);

                return;
            }
        }

        echo $this->dfb->render_template($template, $values, [
            'csrf_name' => $this->security->get_csrf_token_name(),
            'csrf_hash' => $this->security->get_csrf_hash(),
            'error'     => $error,
        ]);
    }

    private function thanks($already)
    {
        $this->load->view('discharge_feedback/public/thanks', [
            'already' => $already,
            'company' => get_option('companyname'),
        ]);
    }
}
