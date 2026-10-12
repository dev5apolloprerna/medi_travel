<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @since  2.3.3
 * Get available staff permissions, modules can use the filter too to hook permissions
 * @param  array  $data additional data passed from view role.php and member.php
 * @return array
 */
function get_available_staff_permissions($data = [])
{
    $viewGlobalName = _l('permission_view') . '(' . _l('permission_global') . ')';

    $allPermissionsArray = [
        'view_own' => _l('permission_view_own'),
        'view'     => $viewGlobalName,
        'create'   => _l('permission_create'),
        'edit'     => _l('permission_edit'),
        'delete'   => _l('permission_delete'),
    ];

    $withoutViewOwnPermissionsArray = [
        'view'   => $viewGlobalName,
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    $withNotApplicableViewOwn = array_merge(['view_own' => ['not_applicable' => true, 'name' => _l('permission_view_own')]], $withoutViewOwnPermissionsArray);

    $corePermissions = [
        'bulk_pdf_exporter' => [
            'name'         => _l('bulk_pdf_exporter'),
            'capabilities' => [
                'view' => $viewGlobalName,
            ],
        ],
        'contracts' => [
            'name'         => _l('contracts'),
            'capabilities' => array_merge($allPermissionsArray, [
                'view_all_templates' => _l('permission_view_all_templates'),
            ]),
        ],
        'credit_notes' => [
            'name'         => _l('credit_notes'),
            'capabilities' => $allPermissionsArray,
        ],
        'customers' => [
            'name'         => _l('clients'),
            'capabilities' => $withNotApplicableViewOwn,
            'help'         => [
                'view_own' => _l('permission_customers_based_on_admins'),
            ],
        ],
        'doctor_receipts' => [
            'name'         => 'Doctor Receipts',
            'capabilities' => $allPermissionsArray,
            'help'         => [
                'view_own' => 'Doctor sees only the receipts issued to him / her',
            ],
        ],
        'email_templates' => [
            'name'         => _l('email_templates'),
            'capabilities' => [
                'view' => $viewGlobalName,
                'edit' => _l('permission_edit'),
            ],
        ],
        'estimates' => [
            'name'         => _l('estimates'),
            'capabilities' => $allPermissionsArray,
        ],
        'expenses' => [
            'name'         => _l('expenses'),
            'capabilities' => $allPermissionsArray,
        ],
        'invoices' => [
            'name'         => _l('invoices'),
            'capabilities' => $allPermissionsArray,
        ],
        'items' => [
            'name'         => _l('items'),
            'capabilities' => $withoutViewOwnPermissionsArray,
        ],
        'knowledge_base' => [
            'name'         => _l('knowledge_base'),
            'capabilities' => $withoutViewOwnPermissionsArray,
        ],
        'payments' => [
            'name'         => _l('payments'),
            'capabilities' => $withNotApplicableViewOwn,
            'help'         => [
                'view_own' => _l('permission_payments_based_on_invoices'),
            ],
        ],
        'projects' => [
            'name'         => _l('projects'),
            'capabilities' => array_merge($withNotApplicableViewOwn, [ 'create_milestones' => _l('permission_create_timesheets'),
                'edit_milestones'                                                          => _l('permission_edit_milestones'), 'delete_milestones' => _l('permission_delete_milestones'), ]),
            'help' => [
                'view'     => _l('help_project_permissions'),
                'view_own' => _l('permission_projects_based_on_assignee'),
            ],
        ],
        'proposals' => [
            'name'         => _l('proposals'),
            'capabilities' => array_merge($allPermissionsArray, [
                'view_all_templates' => _l('permission_view_all_templates'),
            ]),
        ],
        'reports' => [
            'name'         => _l('reports'),
            'capabilities' => [
                'view'            => $viewGlobalName,
                'view-timesheets' => _l('permission_view_timesheet_report'),
            ],
        ],
        'roles' => [
            'name'         => _l('roles'),
            'capabilities' => $withoutViewOwnPermissionsArray,
        ],
        'settings' => [
            'name'         => _l('settings'),
            'capabilities' => [
                'view' => $viewGlobalName,
                'edit' => _l('permission_edit'),
            ],
        ],
        'staff' => [
            'name'         => _l('staff'),
            'capabilities' => $withoutViewOwnPermissionsArray,
        ],
        'subscriptions' => [
            'name'         => _l('subscriptions'),
            'capabilities' => $allPermissionsArray,
        ],
        'tasks' => [
            'name'         => _l('tasks'),
            'capabilities' => array_merge($withNotApplicableViewOwn, [
                'edit_timesheet'       => _l('permission_edit_timesheets'),
                'edit_own_timesheet'   => _l('permission_edit_own_timesheets'),
                'delete_timesheet'     => _l('permission_delete_timesheets'),
                'delete_own_timesheet' => _l('permission_delete_own_timesheets'),
            ]),
             'help' => [
                'view'     => _l('help_tasks_permissions'),
                'view_own' => _l('permission_tasks_based_on_assignee'),
            ],
        ],
        'checklist_templates' => [
            'name'         => _l('checklist_templates'),
            'capabilities' => [
                'create' => _l('permission_create'),
                'delete' => _l('permission_delete'),
            ],
        ],
        'estimate_request' => [
            'name'         => _l('estimate_request'),
            'capabilities' => $allPermissionsArray,
        ],
    ];

    $addLeadsPermission = true;
    if (isset($data['staff_id']) && $data['staff_id']) {
        $is_staff_member = is_staff_member($data['staff_id']);
        if (!$is_staff_member) {
            $addLeadsPermission = false;
        }
    }

    if ($addLeadsPermission) {
        $corePermissions['leads'] = [
            'name'         => _l('leads'),
            'capabilities' => [
                'view'   => $viewGlobalName,
                'delete' => _l('permission_delete'),
            ],
            'help' => [
                'view' => _l('help_leads_permission_view'),
            ],
        ];
    }

    return hooks()->apply_filters('staff_permissions', $corePermissions, $data);
}
/**
 * Get staff by ID or current logged in staff
 * @param  mixed $id staff id
 * @return mixed
 */
function get_staff($id = null)
{
    if (empty($id) && isset($GLOBALS['current_user'])) {
        return $GLOBALS['current_user'];
    }

    // Staff not logged in
    if (empty($id)) {
        return null;
    }

    if (!class_exists('staff_model', false)) {
        get_instance()->load->model('staff_model');
    }

    return get_instance()->staff_model->get($id);
}

/**
 * Return staff profile image url
 * @param  mixed $staff_id
 * @param  string $type
 * @return string
 */
function staff_profile_image_url($staff_id, $type = 'small')
{
    $url = base_url('assets/images/user-placeholder.jpg');

    if ((string) $staff_id === (string) get_staff_user_id() && isset($GLOBALS['current_user'])) {
        $staff = $GLOBALS['current_user'];
    } else {
        $CI = & get_instance();
        $CI->db->select('profile_image')
        ->where('staffid', $staff_id);

        $staff = $CI->db->get(db_prefix() . 'staff')->row();
    }

    if ($staff) {
        if (!empty($staff->profile_image)) {
            $profileImagePath = 'uploads/staff_profile_images/' . $staff_id . '/' . $type . '_' . $staff->profile_image;
            if (file_exists($profileImagePath)) {
                $url = base_url($profileImagePath);
            }
        }
    }

    return $url;
}

/**
 * Staff profile image with href
 * @param  boolean $id        staff id
 * @param  array   $classes   image classes
 * @param  string  $type
 * @param  array   $img_attrs additional <img /> attributes
 * @return string
 */
function staff_profile_image($id, $classes = ['staff-profile-image'], $type = 'small', $img_attrs = [])
{
    $url = base_url('assets/images/user-placeholder.jpg');

    $id = trim($id);

    $_attributes = '';
    foreach ($img_attrs as $key => $val) {
        $_attributes .= $key . '=' . '"' . e($val) . '" ';
    }

    $blankImageFormatted = '<img src="' . $url . '" ' . $_attributes . ' class="' . implode(' ', $classes) . '" />';

    if ((string) $id === (string) get_staff_user_id() && isset($GLOBALS['current_user'])) {
        $result = $GLOBALS['current_user'];
    } else {
        $CI     = & get_instance();
        $result = $CI->app_object_cache->get('staff-profile-image-data-' . $id);

        if (!$result) {
            $CI->db->select('profile_image,firstname,lastname');
            $CI->db->where('staffid', $id);
            $result = $CI->db->get(db_prefix() . 'staff')->row();
            $CI->app_object_cache->add('staff-profile-image-data-' . $id, $result);
        }
    }

    if (!$result) {
        return $blankImageFormatted;
    }

    if ($result && $result->profile_image !== null) {
        $profileImagePath = 'uploads/staff_profile_images/' . $id . '/' . $type . '_' . $result->profile_image;
        if (file_exists($profileImagePath)) {
            $profile_image = '<img ' . $_attributes . ' src="' . base_url($profileImagePath) . '" class="' . implode(' ', $classes) . '" />';
        } else {
            return $blankImageFormatted;
        }
    } else {
        $profile_image = '<img src="' . $url . '" ' . $_attributes . ' class="' . implode(' ', $classes) . '" />';
    }

    return $profile_image;
}

/**
 * Get staff full name
 * @param  string $userid Optional
 * @return string Firstname and Lastname
 */
function get_staff_full_name($userid = '')
{
    $tmpStaffUserId = get_staff_user_id();
    if ($userid == '' || $userid == $tmpStaffUserId) {
        if (isset($GLOBALS['current_user'])) {
            return $GLOBALS['current_user']->firstname . ' ' . $GLOBALS['current_user']->lastname;
        }
        $userid = $tmpStaffUserId;
    }

    $CI = & get_instance();

    $staff = $CI->app_object_cache->get('staff-full-name-data-' . $userid);

    if (!$staff) {
        $CI->db->where('staffid', $userid);
        $staff = $CI->db->select('firstname,lastname')->from(db_prefix() . 'staff')->get()->row();
        $CI->app_object_cache->add('staff-full-name-data-' . $userid, $staff);
    }

    return $staff ? $staff->firstname . ' ' . $staff->lastname : '';
}

/**
 * Get staff default language
 * @param  mixed $staffid
 * @return mixed
 */
function get_staff_default_language($staffid = '')
{
    if (!is_numeric($staffid)) {
        // checking for current user if is admin
        if (isset($GLOBALS['current_user'])) {
            return $GLOBALS['current_user']->default_language;
        }

        $staffid = get_staff_user_id();
    }
    $CI = & get_instance();
    $CI->db->select('default_language');
    $CI->db->from(db_prefix() . 'staff');
    $CI->db->where('staffid', $staffid);
    $staff = $CI->db->get()->row();
    if ($staff) {
        return $staff->default_language;
    }

    return '';
}

function get_staff_recent_search_history($staff_id = null)
{
    $recentSearches = get_staff_meta($staff_id ? $staff_id : get_staff_user_id(), 'recent_searches');

    if ($recentSearches == '') {
        $recentSearches = [];
    } else {
        $recentSearches = json_decode($recentSearches);
    }

    return $recentSearches;
}

function update_staff_recent_search_history($history, $staff_id = null)
{
    $totalRecentSearches = hooks()->apply_filters('total_recent_searches', 5);
    $history             = array_reverse($history);
    $history             = array_unique($history);
    $history             = array_splice($history, 0, $totalRecentSearches);

    update_staff_meta($staff_id ? $staff_id : get_staff_user_id(), 'recent_searches', json_encode($history));

    return $history;
}


/**
 * Check if user is staff member
 * In the staff profile there is option to check IS NOT STAFF MEMBER eq like contractor
 * Some features are disabled when user is not staff member
 * @param  string  $staff_id staff id
 * @return boolean
 */
function is_staff_member($staff_id = '')
{
    $CI = & get_instance();
    if ($staff_id == '') {
        if (isset($GLOBALS['current_user'])) {
            return $GLOBALS['current_user']->is_not_staff === '0';
        }
        $staff_id = get_staff_user_id();
    }

    $CI->db->where('staffid', $staff_id)
    ->where('is_not_staff', 0);

    return $CI->db->count_all_results(db_prefix() . 'staff') > 0 ? true : false;
}
/**
 * Is the given staff role a "Doctor" role?
 * A role counts as doctor when its name contains "doctor" (e.g. "Doctor", "Senior Doctor")
 * or when it is role #1 (the role the rest of the CRM already treats as doctors).
 * Filter "staff_doctor_role_ids" can be used to change the list of doctor role ids.
 *
 * @param  mixed $roleid
 * @return boolean
 */
function is_doctor_role($roleid)
{
    static $doctor_roles = null;

    if ($doctor_roles === null) {
        $CI = &get_instance();
        $doctor_roles = [1];
        foreach ($CI->db->select('roleid, name')->get(db_prefix() . 'roles')->result() as $role) {
            if (stripos($role->name, 'doctor') !== false) {
                $doctor_roles[] = (int) $role->roleid;
            }
        }
        $doctor_roles = array_map('intval', hooks()->apply_filters('staff_doctor_role_ids', array_unique($doctor_roles)));
    }

    return in_array((int) $roleid, $doctor_roles, true);
}

/**
 * Amount in words using the Indian numbering system
 * e.g. 11210 => "Rupees Eleven Thousand Two Hundred and Ten Only"
 *
 * @param  float $amount
 * @return string
 */
function amount_in_words_indian($amount)
{
    $amount = round((float) $amount, 2);
    $rupees = (int) floor($amount);
    $paise  = (int) round(($amount - $rupees) * 100);

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen', ];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $two = function ($n) use ($ones, $tens) {
        if ($n < 20) {
            return $ones[$n];
        }

        return trim($tens[(int) ($n / 10)] . ' ' . $ones[$n % 10]);
    };

    $words = function ($n) use ($two) {
        if ($n == 0) {
            return 'Zero';
        }
        $parts = [];
        foreach ([['Crore', 10000000], ['Lakh', 100000], ['Thousand', 1000]] as $unit) {
            if ($n >= $unit[1]) {
                $count = (int) ($n / $unit[1]);
                // Crore count can itself be large (e.g. 150 crore)
                $parts[] = ($count >= 100 ? trim($two((int) ($count / 100)) . ' Hundred ' . $two($count % 100)) : $two($count)) . ' ' . $unit[0];
                $n %= $unit[1];
            }
        }
        if ($n >= 100) {
            $parts[] = $two((int) ($n / 100)) . ' Hundred';
            $n %= 100;
        }
        if ($n > 0) {
            $parts[] = (count($parts) ? 'and ' : '') . $two($n);
        }

        return implode(' ', $parts);
    };

    $result = 'Rupees ' . $words($rupees);
    if ($paise > 0) {
        $result .= ' and ' . $two($paise) . ' Paise';
    }

    return $result . ' Only';
}
