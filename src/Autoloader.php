<?php

/**
 * 注册自动加载器,该加载器不会影响系统已有的加载机制,请放心引入
 */
spl_autoload_register(function($name) {
    $name = trim($name, '\\');
    if (preg_match('/^Dark\\\\/', $name)) {
        $name = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, preg_replace('/^Dark/', '', $name));
        $file = __DIR__. $name. '.php';
        if (is_file($file)) {
            include_once($file);
        }
    }
});