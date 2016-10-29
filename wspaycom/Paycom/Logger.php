<?php

namespace Paycom;

class Logger
{
    public static function filename()
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . date("Y-m-d", time());
        is_dir($dir) || mkdir($dir, 0777, true); // create directories if not exist
        $filename = $dir . DIRECTORY_SEPARATOR . 'main.log';
        return $filename;
    }

    public static function log()
    {
        $filename = self::filename();
        foreach (func_get_args() as $arg) {
            $msg = sprintf("[%s] %s\n", date('Y-m-d H:i:s', time()), var_export($arg, true));
            file_put_contents($filename, $msg, FILE_APPEND);
        }
    }

    public static function log_line()
    {
        $filename = self::filename();
        $msg = '';
        foreach (func_get_args() as $arg) {
            $msg .= sprintf("%s ", var_export($arg, true));
        }
        $msg = sprintf("[%s] %s\n", date('Y-m-d H:i:s', time()), $msg);
        file_put_contents($filename, $msg, FILE_APPEND);
    }
}
