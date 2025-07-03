<?php

namespace ScottBoms\Promote;

use Kirby\Http\Remote;
use Kirby\Toolkit\Dir;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Str;
use Exception;

class PlatformPromoter
{
  protected $config;

  public function __construct()
  {
    $this->config = [
      'mastodon' => [
        'token' => option('scottboms.promote.mastodon.token'),
        'url' => option('scottboms.promote.mastodon.url', 'https://mastodon.social'),
      ],
      'linkedin' => [
        'token' => option('scottboms.promote.linkedin.token'),
        'author' => option('scottboms.promote.linkedin.author'),
      ],
      'bluesky' => [
        'handle' => option('scottboms.promote.bluesky.handle'),
        'password' => option('scottboms.promote.bluesky.password'),
      ],
        
    ];
  }

  public function post(string $platform, string $text): void
  {
    if (!method_exists($this, $platform)) {
      throw new Exception("Platform '$platform' is not supported.");
    }

    $this->{$platform}($text);
  }

  // --------------------------------------------------------------------------
  // Mastodon
  protected function mastodon(string $text): void
  {
    $token = $this->config['mastodon']['token'];
    $baseUrl = rtrim($this->config['mastodon']['url'], '/');
    $url = $baseUrl . '/api/v1/statuses';

    $response = Remote::post($url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        //'Idempotency-Key: ' . $uuid,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'status' => $text,
        'language' => 'en',
        'visibility' => 'private'
      ]),
    ]);

    if (!in_array($response->code(), [200, 202])) {
      $this->log('mastodon', 'error', 'Post failed with code ' . $response->code() . ': ' . $response->content());
      throw new Exception('Mastodon post failed with code ' . $response->code() . ': ' . $response->content());
    }

    $this->log('mastodon', 'info', 'Post succeeded.');
  }

  // --------------------------------------------------------------------------
  // Bluesky
  protected function bluesky(string $text): void
  {
    // Step 1: Authenticate
    $identifier = preg_replace('/[^\x21-\x7E]/', '', ltrim($this->config['bluesky']['handle'], '@'));
    $password = $this->config['bluesky']['password'];
    $baseUrl = rtrim($this->config['bluesky']['base_url'] ?? 'https://bsky.social', '/');

    $payload = json_encode([
      'identifier' => $identifier,
      'password' => $password,
    ]);

    // Use native cURL, json_encode/decode seemed to fail
    $ch = curl_init($baseUrl . '/xrpc/com.atproto.server.createSession');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('bluesky', 'debug', "cURL auth code: $httpCode");
    //$this->log('bluesky', 'debug', "cURL auth response: $response");

    $authData = json_decode($response, true);

    if ($httpCode !== 200 || !isset($authData['accessJwt'])) {
      throw new Exception('Bluesky authentication failed: ' . $response);
    }

    $accessJwt = $authData['accessJwt'];
    $did = $authData['did'];

    // Step 2: Post
    $postPayload = json_encode([
      'collection' => 'app.bsky.feed.post',
      'repo' => $did,
      'record' => [
        '$type' => 'app.bsky.feed.post',
        'text' => $text,
        'langs' => ['en-US'], // must be array
        'createdAt' => date('c'),
      ]
    ]);

    $ch = curl_init($baseUrl . '/xrpc/com.atproto.repo.createRecord');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $accessJwt,
      'Content-Length: ' . strlen($postPayload),
    ]);

    $postResponse = curl_exec($ch);
    $postHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('bluesky', 'debug', 'Post HTTP code: ' . $postHttpCode);
    //$this->log('bluesky', 'debug', 'Post response: ' . $postResponse);

    if ($postHttpCode !== 200) {
      throw new Exception('Bluesky post failed: ' . $postResponse);
    }

    $this->log('bluesky', 'info', 'Post succeeded.');
  }

  // --------------------------------------------------------------------------
  // LinkedIn
  protected function linkedin(string $text): void
  {
    $token = $this->config['linkedin']['token'];
    $author = $this->config['linkedin']['author'];

    $payload = [
      'author' => $this->config['linkedin']['author'],
      'lifecycleState' => 'PUBLISHED',
      'specificContent' => [
        'com.linkedin.ugc.ShareContent' => [
          'shareCommentary' => [
            'text' => $text
          ],
          'shareMediaCategory' => 'NONE'
        ]
      ],
      'visibility' => [
        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
      ]
    ];

    // $this->log('linkedin', 'debug', 'Post JSON: ' . json_encode($payload));

    $json = json_encode($payload);
    if ($json === false) {
      $this->log('linkedin', 'error', 'Failed to encode payload: ' . json_last_error_msg());
      throw new Exception('LinkedIn post failed: JSON encoding error');
    }

    $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
      'X-Restli-Protocol-Version: 2.0.0',
      'Content-Length: ' . strlen($json),
    ]);

    $responseBody = curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('linkedin', 'debug', 'Response Code: ' . $responseCode);
    // $this->log('linkedin', 'debug', 'Response Body: ' . $responseBody);

    if ($responseCode !== 201) {
      throw new Exception('LinkedIn post failed: ' . $responseBody);
    }

    $this->log('linkedin', 'info', 'Post succeeded.');
  }

  // --------------------------------------------------------------------------
  // Logging
  // Implements custom logging to /site/logs/promote.log
  private function log(string $platform, string $level, string $message): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $logDir = kirby()->root('logs');
    $logFile = $logDir . '/promote.log';

    // Ensure log directory exists
    Dir::make($logDir);
    $entry = Str::unhtml("[$timestamp][$level][$platform] $message") . PHP_EOL;
    F::append($logFile, $entry);
  }

}
