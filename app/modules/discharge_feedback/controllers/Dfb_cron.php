<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Dedicated DAILY cron URL (optional – use it if you want the reminders
 * at an exact time, or if the Perfex cron is not set up):
 *
 *   https://YOUR-DOMAIN/discharge_feedback/dfb_cron/run/{cron_key}
 *
 * The full URL with the key is shown on the module's Settings page.
 */
class Dfb_cron extends App_Controller
{
    public function run($key = '')
    {
        $this->load->model('discharge_feedback/discharge_feedback_model', 'dfb');

        $expected = (string) $this->dfb->setting('cron_key');
        if ($expected === '' || !hash_equals($expected, (string) $key)) {
            show_404();
        }

        @set_time_limit(300);
        $result = $this->dfb->run(true);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true, 'time' => date('Y-m-d H:i:s')] + $result));
    }
}
