<?php
header('Content-Type: text/plain');

$status = file_get_contents('http://localhost/status?auto');
$lines = explode("\n", $status);

$m = [];

foreach ($lines as $line) {
    if (!empty($line)) {
        $x = explode(': ', $line);
        $m[$x[0]] = $x[1];
    }
}

var_dump($m);
