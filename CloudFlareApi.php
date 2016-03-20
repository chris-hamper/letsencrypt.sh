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
        $request = $request->withHeader('X-Auth-Email', $email)
          ->withHeader('X-Auth-Key', $apiKey);
        return $handler($request, $options);
      };
    };
  }

  /**
   * Returns the CloudFlare DNS Zone ID for a domain name.
   *
   * @param $domain
   *
   * @return string Zone ID if found,
   *   else FALSE
   * @throws \GuzzleHttp\Exception\RequestException on request failure
   * @throws RuntimeException if zone not found
   */
  public function findZoneId($domain) {
    if (empty($this->zones)) {
      // Get all DNS Zones
      $response = $this->client->get('zones');
      $payload = json_decode($response->getBody());

      // Cache the result to reduce future requests
      $this->zones = $payload->result;
    }

    // Find Zone ID for domain
    $zoneId = FALSE;
    foreach ($this->zones as $zone) {
      if (($pos = strrpos($domain, $zone->name)) !== FALSE ) {
        $zoneId = $zone->id;
        if ($pos === 0) { // FIXME ?
          // Exact match found, so stop search
          break;
        }
      }
    }

    if ($zoneId === FALSE) {
      throw new RuntimeException("No matching DNS zone for domain '$domain'");
    }

    return $zoneId;
  }

  /**
   * Adds a DNS record to the given zone.
   *
   * @param $zoneId
   * @param $name
   * @param $content
   * @param $type
   * @param int $ttl
   *
   * @return string CloudFlare DNS Record ID of the new record
   *
   */
  public function addDnsRecord($zoneId, $name, $content, $type, $ttl = 1) {
    $response = $this->client->post("zones/$zoneId/dns_records", array(
      'json' => array(
        'type' => $type,
        'name' => $name,
        'content' => $content,
        'ttl' => $ttl,
      ),
    ));

    $payload = json_decode($response->getBody());
    return $payload->result->id;
  }

  /**
   * Updates an existing DNS record.
   *
   * @param $zoneId
   * @param $recordId
   * @param $changes
   */
  public function updateDnsRecord($zoneId, $recordId, $changes) {
    $details = $this->getDnsRecordDetails($zoneId, $recordId);
    array_merge($details, $changes);
    unset($details['created_on']);
    unset($details['modified_on']);

    $this->client->put("zones/$zoneId/dns_records/$recordId", array(
      'json' => $details,
    ));
  }

  /**
   * Deletes an existing DNS record.
   *
   * @param $zoneId
   * @param $recordId
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function deleteDnsRecord($zoneId, $recordId) {
    $this->client->delete("zones/$zoneId/dns_records/$recordId");
  }

  /**
   * Returns the CloudFlare DNS record ID for the record matching the given
   * parameters.
   *
   * @param $zoneId
   * @param $name
   * @param null $content
   * @param null $type
   *
   * @return string CloudFlare DNS record ID
   */
  public function findDnsRecordId($zoneId, $name, $content = NULL, $type = NULL) {
    $query = array( 'name' => $name );
    if (!empty($content)) {
      $query['content'] = $content;
    }
    if (!empty($type)) {
      $query['type'] = $type;
    }

    $response = $this->client->get("zones/$zoneId/dns_records", [
      'query' => $query,
    ]);

    $payload = json_decode($response->getBody());
    $records = $payload->result;
    if (empty($records)) {
      throw new RuntimeException("No matching DNS record found");
    }

    return $records[0]->id;
  }

  /**
   * Returns the CloudFlare DNS record details.
   * @param $zoneId
   * @param $recordId
   *
   * @return array
   */
  public function getDnsRecordDetails($zoneId, $recordId) {
    $response = $this->client->get("zones/$zoneId/dns_records/$recordId");

    $payload = json_decode($response->getBody());
    return $payload->result;
  }

}