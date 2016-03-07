#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


$args = $_SERVER['argv'];
$script = array_shift($args);
$op = (string)array_shift($args);

$json = file_get_contents('credentials.json');
$data = json_decode($json);

if (empty($cloudflare_api_key = $data->cloudflare_api_key)) {
  throw new RuntimeException("CloudFlare API Key not specified in data.json");
}

if (empty($cloudflare_email = $data->cloudflare_email)) {
  throw new RuntimeException("CloudFlare Email address not specified in data.json");
}

$client = new Client([
  'base_uri' => 'https://api.cloudflare.com/client/v4/',
]);


if (function_exists($op)) {
  try {
    call_user_func_array($op, $args);
  }
  catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage() . PHP_EOL;
    echo "Response body: " . $e->getResponse()->getBody();
    exit(1);
  }
}
else {
  throw new RuntimeException("Invalid operation: '$op'");
}

exit(0);



function deploy_challenge($domain, $unused, $token_value) {
  global $client;
  global $cloudflare_api_key;
  global $cloudflare_email;
  echo "deploy_challenge($domain, $unused, $token_value):" . PHP_EOL;

  // Find Zone ID of matching DNS zone
  $response = $client->get('zones', [
    'headers' => [
      'X-Auth-Key' => $cloudflare_api_key,
      'X-Auth-Email' => $cloudflare_email,
    ],
  ]);

  echo $response->getStatusCode() . " " . $response->getReasonPhrase() . PHP_EOL;

  $payload = json_decode($response->getBody());
  $zones = $payload->result;

  $zone_id = NULL;
  $subdomain = '';
  foreach ($zones as $zone) {
    if (($pos = strrpos($domain, $zone->name)) !== FALSE ) {
      $zone_id = $zone->id;
      if ($pos === 0) {
        // Exact match found, so stop search
        break;
      }
      else {
        $subdomain = substr($domain, 0, $pos - 1);
      }
    }
  }

  if (!isset($zone_id)) {
    throw new RuntimeException("No matching DNZ zone for '$domain'");
  }

  // Create TXT record
  $response = $client->post("zones/$zone_id/dns_records", [
    'headers' => [
      'X-Auth-Key' => $cloudflare_api_key,
      'X-Auth-Email' => $cloudflare_email,
    ],
    'json' => [
      'type' => 'TXT',
      'name' => $zones[0]->name, // FIXME?
      'content' => $token_value,
    ],
  ]);

  echo $response->getStatusCode() . " " . $response->getReasonPhrase() . PHP_EOL;
}

function clean_challenge($domain, $unused, $token_value) {
  global $client;
  global $cloudflare_api_key;
  global $cloudflare_email;
  echo "clean_challenge($domain, $unused, $token_value):" . PHP_EOL;

  // Find Zone ID of matching DNS zone
  $response = $client->get('zones', [
    'headers' => [
      'X-Auth-Key' => $cloudflare_api_key,
      'X-Auth-Email' => $cloudflare_email,
    ],
  ]);

  echo $response->getStatusCode() . " " . $response->getReasonPhrase() . PHP_EOL;

  $payload = json_decode($response->getBody());
  $zones = $payload->result;
  $zone_id = $zones[0]->id; // FIXME - search through zones for match

  // Get matching TXT record ID
  $response = $client->get("zones/$zone_id/dns_records", [
    'headers' => [
      'X-Auth-Key' => $cloudflare_api_key,
      'X-Auth-Email' => $cloudflare_email,
    ],
    'query' => [
      'type' => 'TXT',
      'name' => $zones[0]->name, // FIXME?
      'content' => $token_value,
    ],
  ]);

  echo $response->getStatusCode() . " " . $response->getReasonPhrase() . PHP_EOL;

  $payload = json_decode($response->getBody());
  $records = $payload->result;
  $record_id = $records[0]->id;

  // Delete the TXT record
  $response = $client->delete("zones/$zone_id/dns_records/$record_id", [
    'headers' => [
      'X-Auth-Key' => $cloudflare_api_key,
      'X-Auth-Email' => $cloudflare_email,
    ],
  ]);

  echo $response->getStatusCode() . " " . $response->getReasonPhrase() . PHP_EOL;
}

function deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile) {
  echo "deploy_cert($domain, $keyfile, $certfile, $fullchainfile, $chainfile):" . PHP_EOL;
  echo "Nothing to be done." . PHP_EOL;
}
