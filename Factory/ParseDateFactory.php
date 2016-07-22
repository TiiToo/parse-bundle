<?php

namespace Adadgio\ParseBundle\Factory;

class ParseDateFactory
{
    /**
     * Creates a parse date format.
     *
     * @param string ISO date format
     */
    public static function createDate(\Datetime $datetime = null)
    {
        if (null === $datetime) {
            $datetime = new \Datetime();
        }

        return $datetime->format('Y-m-d\TH:i:s').'.000Z';
    }
}
