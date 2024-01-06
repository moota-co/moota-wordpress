<?php

namespace Test;

class BaseTest
{
    public static $stubHttp = false;
    public static $stubHttpResponse;
    public static $stubHttpStatus;
    public static $lastHttpRequest;

    public static function reset()
    {
        BaseTest::$stubHttp = false;
        BaseTest::$stubHttpResponse = null;
        BaseTest::$lastHttpRequest = null;
    }

    public static function lastReqOptions()
    {
        $consts = array(
            CURLOPT_URL => "URL",
            CURLOPT_HTTPHEADER => "HTTPHEADER",
            CURLOPT_RETURNTRANSFER => "RETURNTRANSFER",
            CURLOPT_CAINFO => "CAINFO",
            CURLOPT_POST => "POST",
            CURLOPT_POSTFIELDS => "POSTFIELDS",
            CURLOPT_PROXY => "PROXY"
        );

        $options = array();
        foreach (BaseTest::$lastHttpRequest["curl"] as $intValue => $value) {
            $key = $consts[$intValue] ? $consts[$intValue] : $intValue;
            $options[$key] = $value;
        }

        return $options;
    }
}