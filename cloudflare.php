#!/usr/bin/env php
<?php

require 'vendor/autoload.php';
require_once 'CloudFlareApi.php';

use GuzzleHttp\Exception\RequestException;


$args = $_SERVER['argv'];
$script = array_shift($args);
$op = (string)array_shift($args);

$json = file_get_contents('credentials.json');
$data = json_decode($json);

if (empty($cloudflare_api_key = $data->cloudflare_api_key)) {
  throw new RuntimeException("CloudFlare API Key not specified in credentials.json");
}

if (empty($cloudflare_email = $data->cloudflare_email)) {
  throw new RuntimeException("CloudFlare Email address not specified in credentials.json");
}

$cloudFlare = new CloudFlareApi($cloudflare_email, $cloudflare_api_key);

if (function_exists($op)) {
  try {
    call_user_func_array($op, $args);
  }
  catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage() . PHP_EOL;
    echo "Response body: " . $e->getResponse()->getBody() . PHP_EOL;
    exit(1);
  }
}
else {
  throw new RuntimeException("Invalid operation: '$op'");
}

exit(0);



function deploy_challenge($domain, $unused, $token_value) {
  global $cloudFlare;
  echo "deploy_challenge($domain, $unused, $token_value):" . PHP_EOL;

  $zone_id = $cloudFlare->findZoneId($domain);

  $record_name = '_acme-challenge.' . $domain;
  echo "Creating TXT record '$record_name'" . PHP_EOL;

  $cloudFlare->addDnsRecord($zone_id, $record_name, $token_value, 'TXT');

  echo "Deploy completed. Sleeping for 10 seconds..." . PHP_EOL;
  sleep(10);
}

function clean_challenge($domain, $unused, $token_value) {
  global $cloudFlare;
  echo "clean_challenge($domain, $unused, $token_value):" . PHP_EOL;

  $zone_id = $cloudFlare->findZoneId($domain);

  $record_name = '_acme-challenge.' . $domain;
  echo "Deleting TXT record '$record_name'" . PHP_EOL;

  $record_id = $cloudFlare->findDnsRecordId($zone_id, $record_name, $token_value, 'TXT');
  $cloudFlare->deleteDnsRecord($zone_id, $record_id);
}

function deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile) {
  echo "deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile):" . PHP_EOL;
  echo "Nothing to be done." . PHP_EOL;
}
