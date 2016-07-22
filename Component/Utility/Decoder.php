<?php

namespace Adadgio\ParseBundle\Component\Utility;

class Decoder
{
    public static function decode($input)
    {
        if (is_string($input)) {
            $output = json_decode($input, true);
        } else {
            $output = $input;
        }

        return (null === $output) ? array() : $output;
    }
}
