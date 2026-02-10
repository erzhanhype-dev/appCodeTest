<?php

namespace App\Helpers;

class NetworkHelper
{
    public static function getClientIp(): string
    {
        $ip = '0.0.0.0';

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach ($ips as $forwardedIp) {
                if (filter_var($forwardedIp, FILTER_VALIDATE_IP)) {
                    $ip = $forwardedIp;
                    break;
                }
            }
        } elseif (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
