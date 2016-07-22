<?php

namespace Adadgio\ParseBundle\Component\Utility;

class ParseExpr
{
    public static function abs($field)
    {
        return str_replace('-', '', $field);
    }

    public static function ascOrDesc($field)
    {
        if (strpos($field, '-') === 0) {
            return 'DESC';
        } else {
            return 'ASC';
        }
    }

    public static function castAsLike($value)
    {
        $value = static::unquote($value);

        if (strpos($value, '^') > -1) {
            // its a start with
            $value = str_replace(array('^'), '', $value);
            return $value.'%';

        } else {
            // contains
            $value = str_replace(array('.*'), '', $value);
            return '%'.$value.'%';
        }
    }

    private static function unquote($value)
    {
        return str_replace(array('\\Q', '\\E'), '', $value);
        // return '\\Q'.str_replace('\\E', '\\E\\\\E\\Q', $s).'\\E'; // see the inversed ParseQuery::quote()
    }
}
