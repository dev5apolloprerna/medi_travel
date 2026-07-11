<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!empty($_COOKIE['branch'])) {
    setcookie('branch', '', time() - 3600, '/');
    unset($_COOKIE['branch']);

}