<?php

namespace App\Utils;

use DateTime;

class DateTimeUtil
{
    public static function parseTimestamp(string $iso8601Timestamp): DateTime
    {
        $str = str_replace(['T', 'Z'], '', $iso8601Timestamp);
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $str);
        return $dateTime;
    }
    
    public static function isOlderThanOneWeek(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1 || $diff->m >= 1 || $diff->d >= 7;
    }
    
    public static function isOlderThanTwoWeeks(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1 || $diff->m >= 1 || $diff->d >= 14;
    }
    
    public static function isOlderThanOneMonth(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1 || $diff->m >= 1;
    }
    
    public static function isOlderThanThreeMonths(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1 || $diff->m >= 3;
    }
    
    public static function isOlderThanSixMonths(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1 || $diff->m >= 6;
    }
    
    public static function isOlderThanOneYear(string $iso8601Timestamp): bool
    {
        $dateTime = self::parseTimestamp($iso8601Timestamp);
        $diff = $dateTime->diff(new DateTime());
        return $diff->y >= 1;
    }

    public static function timestampToNZDate(string $iso8601Timestamp): string
    {
        // ISO 8601 timestamp
        preg_match('/([0-9]{4})\-([0-9]{2})\-([0-9]{2})T/', $iso8601Timestamp, $m);
        if (!isset($m[3])) {
            return '';
        }
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    public static function formatMysqlTimestamp(string $mysqlTimestamp): string
    {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $mysqlTimestamp);
        return $dt->format('D g:iA j M Y');
    }
}
