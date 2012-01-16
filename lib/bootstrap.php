<?php

namespace phpnanoorm;

function autoloader($clsname) {
    $d = preg_replace('|/$|', '', dirname(__FILE__));
    $nname = preg_replace('/^'.preg_quote(__NAMESPACE__.'\\').'/', '', $clsname);
    $path = explode('\\', $nname);
    $pathname = implode(DIRECTORY_SEPARATOR, array_merge(array($d), $path)).'.class.php';
    require($pathname);
}

spl_autoload_register('phpnanoorm\autoloader');
