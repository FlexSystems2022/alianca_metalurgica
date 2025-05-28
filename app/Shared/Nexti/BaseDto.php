<?php

namespace App\Shared\Nexti;

abstract class BaseDto
{
    public function __construct(array $params=[]) {
        self::fillClass($this, $params);
    }

    /**
     * @param object $obj
     * @param array  $params
     */
    public static function fillClass(&$obj, array $params)
    {
        foreach ($params as $param => $value) {
            $param = str_replace(' ', '', str_replace('_', ' ', $param));

            if (property_exists($obj, $param)) {
                $obj->{$param} = $value;
            }
        }
    }

    public function __toJson(): string
    {
        return json_encode($this);
    }
}