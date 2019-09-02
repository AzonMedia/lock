<?php

const SOURCE_PATH = '../src/';
const NS_PREFIX = 'Azonmedia\\Lock';

function include_all(string $path) : void
{
    $Directory = new \RecursiveDirectoryIterator($path);
    $Iterator = new \RecursiveIteratorIterator($Directory);
    $Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RegexIterator::GET_MATCH);
    foreach ($Regex as $path=>$match) {
        require_once($path);
    }
}

function autoload(string $class) : bool
{
    $path = SOURCE_PATH.str_replace([NS_PREFIX,'\\'],['','/'],$class).'.php';
    require_once($path);
    return TRUE;
}

spl_autoload_register('autoload');

include_all(SOURCE_PATH);
