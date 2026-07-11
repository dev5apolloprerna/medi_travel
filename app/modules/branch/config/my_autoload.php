<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('branch_database_config_connects')) {
    function branch_database_config_connects($config_db)
    {
        $hostname = isset($config_db['hostname']) ? $config_db['hostname'] : '';
        $username = isset($config_db['username']) ? $config_db['username'] : '';
        $password = isset($config_db['password']) ? $config_db['password'] : '';
        $database = isset($config_db['database']) ? $config_db['database'] : '';

        if ($hostname === '' || $database === '') {
            return false;
        }

        $connection = @mysqli_connect($hostname, $username, $password, $database);

        if (!$connection) {
            return false;
        }

        mysqli_close($connection);

        return true;
    }
}

$branch_db = isset($_COOKIE['branch']) ? $_COOKIE['branch'] : '';

if(isset($_GET['branch_db']) && $_GET['branch_db'] != ''){

    $branch_db = $_GET['branch_db'];
}

if (!empty($branch_db)) {
    
    $CI =& get_instance();
    $config_db = $CI->config->config['config_db'];
    $branch_credentials = [];

    if($branch_db == 'u614622744_maninagar_db'){
        $branch_credentials = [
            'username' => 'u614622744_maninagar_db',
            'password' => 'unC[G$q$pN2[',
        ];
    }
    if($branch_db == 'u614622744_satellite_db'){
        $branch_credentials = [
            'username' => 'u614622744_satellite_db',
            'password' => 'zWvwu$!gS$6&',
        ];
    }
    if($branch_db == 'u614622744_iskon_ambli_db'){
        $branch_credentials = [
            'username' => 'u614622744_iskon_ambli_db',
            'password' => 'zWvwu$!gS$6&',
        ];
    }
   
    $config_db['database'] = $branch_db;
 $branch_config_db = array_merge($config_db, $branch_credentials);
    $resolved_config_db = branch_database_config_connects($branch_config_db)
        ? $branch_config_db
        : (branch_database_config_connects($config_db) ? $config_db : null);

    if ($resolved_config_db !== null) {
        $CI->db = $CI->load->database($resolved_config_db, true);
    } else {
        setcookie('branch', '', time() - 3600, "/");
        unset($_COOKIE['branch']);
    }
}
