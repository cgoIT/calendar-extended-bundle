<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle.
 *
 * (c) Kester Mielke
 * (c) Carsten Götzinger
 *
 * @license LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle;

class Utils
{
    /**
     * @param array<mixed> $arr the array the value should be appended to a key/the key should be added
     * @param mixed        $key the key to look for
     * @param mixed        $val the value which should be append/set for the given key
     */
    public static function appendToArrayKey(&$arr, $key, $val): void
    {
        if (\array_key_exists($key, $arr)) {
            $arr[$key] .= $val;
        } else {
            $arr[$key] = $val;
        }
    }
}
