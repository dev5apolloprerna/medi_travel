<?php

defined('BASEPATH') or exit('No direct script access allowed');

$has_permission_delete = staff_can('delete',  'staff');

$custom_fields = get_custom_fields('staff', [
    'show_on_table' => 1,
    ]);
$aColumns = [
    'firstname',
    'email',
    'doctor_sign',
    db_prefix() . 'roles.name',
    'last_login',
    'active',
    ];
$sIndexColumn = 'staffid';
$sTable       = db_prefix() . 'staff';
$join         = ['LEFT JOIN ' . db_prefix() . 'roles ON ' . db_prefix() . 'roles.roleid = ' . db_prefix() . 'staff.role'];
$i            = 0;
foreach ($custom_fields as $field) {
    $select_as = 'cvalue_' . $i;
    if ($field['type'] == 'date_picker' || $field['type'] == 'date_picker_time') {
        $select_as = 'date_picker_cvalue_' . $i;
    }
    array_push($aColumns, 'ctable_' . $i . '.value as ' . $select_as);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $i . ' ON ' . db_prefix() . 'staff.staffid = ctable_' . $i . '.relid AND ctable_' . $i . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $i . '.fieldid=' . $field['id']);
    $i++;
}
            // Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$where = hooks()->apply_filters('staff_table_sql_where', []);

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'profile_image',
    'lastname',
    'staffid',
    db_prefix() . 'staff.role as staff_roleid',
    ]);

$can_generate_doctor_receipt = is_admin() || staff_can('create', 'doctor_receipts');
$can_view_doctor_receipts    = is_admin() || staff_can('view', 'doctor_receipts');
$CI = &get_instance();
$CI->load->model('doctor_payment_receipts_model');
$staff_with_doctor_receipt = $CI->doctor_payment_receipts_model->staff_with_receipt();

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    for ($i = 0; $i < count($aColumns); $i++) {
        if (strpos($aColumns[$i], 'as') !== false && !isset($aRow[$aColumns[$i]])) {
            $_data = $aRow[strafter($aColumns[$i], 'as ')];
        } else {
            $_data = $aRow[$aColumns[$i]];
        }
        if ($aColumns[$i] == 'last_login') {
            if ($_data != null) {
                $_data = '<span class="text-has-action is-date" data-toggle="tooltip" data-title="' . e(_dt($_data)) . '">' . time_ago($_data) . '</span>';
            } else {
                $_data = 'Never';
            }
        } elseif ($aColumns[$i] == 'active') {
            $checked = '';
            if ($aRow['active'] == 1) {
                $checked = 'checked';
            }

            $_data = '<div class="onoffswitch">
                <input type="checkbox" ' . (($aRow['staffid'] == get_staff_user_id() || (is_admin($aRow['staffid']) || staff_cant('edit', 'staff')) && !is_admin()) ? 'disabled' : '') . ' data-switch-url="' . admin_url() . 'staff/change_staff_status" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['staffid'] . '" data-id="' . $aRow['staffid'] . '" ' . $checked . '>
                <label class="onoffswitch-label" for="c_' . $aRow['staffid'] . '"></label>
            </div>';

            // For exporting
            $_data .= '<span class="hide">' . ($checked == 'checked' ? _l('is_active_export') : _l('is_not_active_export')) . '</span>';
        } elseif ($aColumns[$i] == 'firstname') {
            $_data = '<a href="' . admin_url('staff/profile/' . $aRow['staffid']) . '">' . staff_profile_image($aRow['staffid'], [
                'staff-profile-image-small',
                ]) . '</a>';
            $_data .= ' <a href="' . admin_url('staff/member/' . $aRow['staffid']) . '">' . e($aRow['firstname'] . ' ' . $aRow['lastname']) . '</a>';

            $_data .= '<div class="row-options">';
            $_data .= '<a href="' . admin_url('staff/member/' . $aRow['staffid']) . '">' . _l('view') . '</a>';

            if (($has_permission_delete && ($has_permission_delete && !is_admin($aRow['staffid']))) || is_admin()) {
                if ($has_permission_delete && $output['iTotalRecords'] > 1 && $aRow['staffid'] != get_staff_user_id()) {
                    $_data .= ' | <a href="#" onclick="delete_staff_member(' . $aRow['staffid'] . '); return false;" class="text-danger">' . _l('delete') . '</a>';
                }
            }

            $_data .= '</div>';
        } elseif ($aColumns[$i] == 'email') {
            $_data = '<a href="mailto:' . e($_data) . '">' . e($_data) . '</a>';
        } elseif ($aColumns[$i] == 'doctor_sign') {
            if (!empty($_data)) {
                $_data = '<img src="' . base_url('uploads/staff_profile_images/' . $aRow['staffid'] . '/doctor_sign/' . $_data) . '" alt="Doctor Sign" class="img img-responsive" style="max-height:40px;">';
            } else {
                $_data = '--';
            }
            
        } else {
            if (strpos($aColumns[$i], 'date_picker_') !== false) {
                $_data = (strpos($_data, ' ') !== false ? _dt($_data) : _d($_data));
            }
        }
        $row[] = $_data;
    }

    $row['DT_RowClass'] = 'has-row-options';

    $row = hooks()->apply_filters('staff_table_row', $row, $aRow);

    // "Generate Receipt" for doctors - added after the "Create Appointment" link
    if (($can_generate_doctor_receipt || $can_view_doctor_receipts) && is_doctor_role($aRow['staff_roleid'])) {
        $receipt_links = '';
        $has_receipt = in_array((int) $aRow['staffid'], $staff_with_doctor_receipt, true);
        if ($has_receipt) {
            // Receipt is generated only once - after that only "View Receipt"
            if ($can_view_doctor_receipts || $can_generate_doctor_receipt) {
                $receipt_links .= ' | <a href="' . admin_url('doctor_payment_receipts?staffid=' . $aRow['staffid']) . '">View Receipt</a>';
            }
        } elseif ($can_generate_doctor_receipt) {
            $receipt_links .= ' | <a href="' . admin_url('doctor_payment_receipts/create/' . $aRow['staffid']) . '">Generate Receipt</a>';
        }
        $pos = strrpos($row[0], '</div>');
        if ($pos !== false) {
            $row[0] = substr_replace($row[0], $receipt_links . '</div>', $pos, strlen('</div>'));
        }
    }

    $output['aaData'][] = $row;
}
