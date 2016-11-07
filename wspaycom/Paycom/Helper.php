<?php

namespace Paycom;

class Helper
{
    public static function RequestPayload()
    {
        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);
        return $data;
    }

    public static function timestamp($long = false)
    {
        if ($long) {
            return round(microtime(true) * 1000);
        }
        return time();
    }

    public static function toSom($amount)
    {
        return 1 * $amount / 100;
    }

    public static function toTiyin($amount)
    {
        return 1 * $amount * 100;
    }

    public static function toTimestampShort($timestamp)
    {
        return floor(1 * $timestamp / 1000);
    }

    public static function toTimestampLong($timestamp)
    {
        return $timestamp * 1000;
    }

    public static function timestampToDatetime($timestamp)
    {
        $timestamp = (string)$timestamp;

        // if milliseconds, convert to seconds
        if (strlen($timestamp) == 13) {
            $timestamp = self::toTimestampShort($timestamp);
        }

        // convert to datetime
        return date('Y-m-d H:i:s', $timestamp);
    }

    public static function datetimeToTimestamp($datetime)
    {
        return strtotime($datetime);
    }

    public static function debug($data, $pre = true)
    {
        $var = print_r($data, true);
        if ($pre) {
            echo '<pre>' . $var . '</pre>';
        } else {
            echo $var;
        }
    }
}