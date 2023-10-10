<?php

namespace App\Utils;

class MiscUtil
{
    // Feed it MetaData::TEAMS
    public static function deriveUserType(string $user, array $teams)
    {
        foreach ($teams as $k => $a) {
            if (in_array($user, $a)) {
                return $k;
            }
        }
        return 'other';
    }
}
