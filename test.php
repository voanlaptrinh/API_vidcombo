<?php

$lang_file = __DIR__.'/lang/en.json';
echo '<pre>'; print_r($lang_file); echo '</pre>';
var_dump(file_exists($lang_file));