<?php
/** Localized target input only. Generic KPI weights/caps retain their existing parser. */
function targetNumber_($value) {
    if ($value === null || $value === '') return null;
    if (is_int($value) || is_float($value)) return is_finite((float)$value) && $value >= 0 ? $value : NAN;
    $s = preg_replace('/[\s\x{00A0}]/u', '', (string)$value);
    if ($s === '') return null;
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) $s = str_replace(',', '.', str_replace('.', '', $s));
    elseif (preg_match('/^\d+(,\d+)?$/', $s)) $s = str_replace(',', '.', $s);
    elseif (!preg_match('/^\d+\.\d{1,2}$/', $s)) return NAN;
    $n = (float)$s;
    return is_finite($n) && $n >= 0 ? $n : NAN;
}
