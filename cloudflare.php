#!/usr/bin/env php
<?php

$args = $_SERVER['argv'];
$script = array_shift($args);
$op = (string)array_shift($args);
//print_r($args);

if (function_exists($op)) {
  call_user_func_array($op, $args);
}
else {
  echo "*** Invalid operation: '$op' ***" . PHP_EOL;
}

function deploy_challenge($domain, $token_filename, $token_value) {
  echo "deploy_challenge($domain, $token_filename, $token_value)" . PHP_EOL;


}

function clean_challenge($domain, $token_filename, $token_value) {
  echo "clean_challenge($domain, $token_filename, $token_value)" . PHP_EOL;


}

function deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile) {
  echo "deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile)" . PHP_EOL;


}
