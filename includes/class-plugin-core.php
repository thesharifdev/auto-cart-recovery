<?php

namespace Auto_Cart_Recovery;

use Auto_Cart_Recovery\Traits\Singleton;

defined('ABSPATH') || exit;

class Plugin_Core {

    use Singleton;

    public function init(){

        error_log(print_r("hello world", true));
    }
}