<?php
function urldissect($url, $file, $negoff)
{

    $scr = $_SERVER['SCRIPT_NAME'];
    $url = explode('/', $url);

    if ($file) return array_pop($url);
    elseif ($negoff) {
        $i = 0;
        while ($i < $negoff) {
            array_pop($url);
            $i++;
        }
    }

    return implode('/', $url) . '/';

}

?>