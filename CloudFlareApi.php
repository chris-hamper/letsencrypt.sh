<?php

/**
 * Created by PhpStorm.
 * User: chris.hamper
 * Date: 3/20/16
 * Time: 2:58 PM
 */

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Message\RequestInterface;

class CloudFlareApi {

  protected $client;
  protected $zones;

  public function __construct($cloudFlareEmail, $cloudFlareApiKey) {
    // Add authentication headers to all Guzzle requests
    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());
    $stack->push(self::addAuthRequestHeaders($cloudFlareEmail, $cloudFlareApiKey));

    $this->client = new Client([
      'base_uri' => 'https://api.cloudflare.com/client/v4/',
      'handler' => $stack,
    ]);
  }

  /**
   * Adds CloudFlare auth headers for the given account credentials.
   *
   * @param $email
   * @param $apiKey
   *
   * @return \Closure
   */
  static protected function addAuthRequestHeaders($email, $apiKey) {
    return function (callable $handler) use ($email, $apiKey) {
      return function (RequestInterface $request, array $options) use ($handler, $email, $apiKey) {
//        echo "Request: " . $request->getUri() . PHP_EOL;
        $request = $request->withHeader('X-Auth-Email', $email)
          ->withHeader('X-Auth-Key', $apiKey);
        return $handler($request, $options);
      };
    };
  }

  /**
   * Returns the CloudFlare DNS Zone for a domain name.
   *
   * @param $domain
   *
   * @return stdClass CloudFlare Zone
   *
   * @throws \GuzzleHttp\Exception\RequestException on request failure
   * @throws RuntimeException if zone not found
   */
  public function getZone($domain) {
    if (empty($this->zones)) {
      // Get all DNS Zones
      $response = $this->client->get('zones');
      $payload = json_decode($response->getBody());

      // Cache the result to reduce future requests
      $this->zones = $payload->result;
    }

    // Find Zone ID for domain
    $zoneMatch = NULL;
    foreach ($this->zones as $zone) {
      if (($pos = strrpos($domain, $zone->name)) !== FALSE ) {
        $zoneMatch = $zone;
        if ($pos === 0) { // FIXME ?
          // Exact match found, so stop search
          break;
        }
      }
    }

    if ($zoneMatch === NULL) {
      throw new RuntimeException("No matching DNS zone for domain '$domain'");
    }

    return $zoneMatch;
  }

  /**
   * Adds a DNS record to the given zone.
   *
   * @param $zone
   * @param $name
   * @param $content
   * @param $type
   * @param int $ttl
   *
   * @return string CloudFlare DNS Record of the new record
   */
  public function addDnsRecord($zone, $name, $content, $type, $ttl = 1) {
    $response = $this->client->post("zones/" . $zone->id . "/dns_records", array(
      'json' => array(
        'type' => $type,
        'name' => $name,
        'content' => $content,
        'ttl' => $ttl,
      ),
    ));

    $payload = json_decode($response->getBody());
    return $payload->result;
  }

  /**
   * Updates an existing DNS record.
   *
   * @param $zone
   * @param $record
   */
  public function updateDnsRecord($zone, $record) {
    unset($record->created_on);
    unset($record->modified_on);

    $this->client->put("zones/" . $zone->id . "/dns_records/" . $record->id, array(
      'json' => $record,
    ));
  }

  /**
   * Deletes an existing DNS record.
   *
   * @param $zone
   * @param $record
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function deleteDnsRecord($zone, $record) {
    $this->client->delete("zones/" . $zone->id . "/dns_records/" . $record->id);
  }

  /**
   * Returns the CloudFlare DNS record ID for the record matching the given
   * parameters.
   *
   * @param $zone
   * @param $name
   * @param null $content
   * @param null $type
   *
   * @return stdClass CloudFlare DNS record
   */
  public function getDnsRecord($zone, $name, $content = NULL, $type = NULL) {
    $query = array( 'name' => $name );
    if (!empty($content)) {
      $query['content'] = $content;
    }
    if (!empty($type)) {
      $query['type'] = $type;
    }

    $response = $this->client->get("zones/" . $zone->id . "/dns_records", [
      'query' => $query,
    ]);

    $payload = json_decode($response->getBody());
    $records = $payload->result;
    if (empty($records)) {
      throw new RuntimeException("No matching DNS record found");
    }

    return reset($records);
  }

}
