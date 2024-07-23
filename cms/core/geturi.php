<?php

function uri()
{

    if (!empty($_SERVER['REQUEST_URI'])) return $_SERVER['REQUEST_URI'];
    else $uri = $_SERVER['SCRIPT_NAME'];

    if (!empty($_SERVER['QUERY_STRING'])) $uri .= '?' . $_SERVER['QUERY_STRING'];

    $_SERVER['REQUEST_URI'] = $uri;
    return $uri;

}
