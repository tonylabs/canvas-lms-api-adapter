<?php

namespace TONYLABS\Canvas;

class Debug
{
    public static function log($data)
    {
        if (config('app.debug') && function_exists('ray')) {
            if (is_callable($data)) {
                $data();
                return;
            }
            ray($data)->blue();
        }
    }
}