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
  protected const USER_AGENT = 'KirbyPromote/1.0 (+https://scottboms.com)';
  protected bool $cacheEnabled = true;
  protected int $cacheTimeout = 3600; // 1 hour in seconds

  public function __construct()
  {
    $this->config = [
      'mastodon' => [
        'token' => option('scottboms.promote.mastodon.token'),
        'url' => option('scottboms.promote.mastodon.url', 'https://mastodon.social'),
      ],
      'linkedin' => [
        'token' => option('scottboms.promote.linkedin.token'),
      ],
      'bluesky' => [
        'base_url' => option('scottboms.promote.bluesky.base_url', 'https://bsky.social'),
        'handle' => option('scottboms.promote.bluesky.handle'),
        'password' => option('scottboms.promote.bluesky.password'),
      ],

    ];
  }

  // --------------------------------------------------------------------------
  // Posting...
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
    $text = trim($text); // remove whitespace

    if ($text === '') {
      // $this->log('mastodon', 'error', 'Aborted: status text is empty');
      throw new Exception('Mastodon post failed: status text is empty');
    }

    $token = $this->config['mastodon']['token'];
    $baseUrl = rtrim($this->config['mastodon']['url'], '/');
    $url = $baseUrl . '/api/v1/statuses';

    // $this->log('mastodon', 'debug', 'Posting status: ' . $text);

    // Ensure proper encoding
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    $idempotencyKey = hash('sha256', $text); // hash of the content

    $payloadArray = [
      'status' => $text,
      'language' => 'en',
      'visibility' => 'public'
    ];

    $payload = json_encode($payloadArray);

    if ($payload === false) {
      // $this->log('mastodon', 'error', 'Failed to encode payload: ' . json_last_error_msg());
      throw new Exception('Mastodon post failed: JSON encoding error');
    }

    $this->log('mastodon', 'debug', 'Final payload: ' . $payload);

    // Use cURL to post to Mastodon
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
      'Content-Length: ' . strlen($payload),
      'Idempotency-Key: ' . $idempotencyKey,
      'User-Agent: ' . self::USER_AGENT,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('mastodon', 'debug', 'Response HTTP code: ' . $httpCode);
    // $this->log('mastodon', 'debug', 'Response: ' . $response);

    if (!in_array($httpCode, [200, 202])) {
      throw new Exception('Mastodon post failed with code ' . $httpCode . ': ' . $response);
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
      'Content-Length: ' . strlen($payload),
      'User-Agent: ' . self::USER_AGENT
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('bluesky', 'debug', "cURL auth code: $httpCode");
    // $this->log('bluesky', 'debug', "cURL auth response: $response");

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
      'User-Agent: ' . self::USER_AGENT
    ]);

    $postResponse = curl_exec($ch);
    $postHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $this->log('bluesky', 'debug', 'Post HTTP code: ' . $postHttpCode);
    // $this->log('bluesky', 'debug', 'Post response: ' . $postResponse);

    if ($postHttpCode !== 200) {
      throw new Exception('Bluesky post failed: ' . $postResponse);
    }

    $this->log('bluesky', 'info', 'Post succeeded.');
  }

  // --------------------------------------------------------------------------
  // LinkedIn
  protected function getLinkedInAuthorUrn(): string
  {
    $cache = kirby()->cache('linkedin');
    $cacheKey = 'author_urn';

    // Check cache first
    if($this->cacheEnabled) {
      $cachedUrn = $cache->get($cacheKey);
      if($cachedUrn !== null) {
        $this->log('linked', 'debug', 'Using cached LinkedIn author URN');
        return $cachedUrn;
      }
    }

    $token = $this->config['linkedin']['token'];

    $response = Remote::get('https://api.linkedin.com/v2/userinfo', [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
      ],
    ]);

    if ($response->code() !== 200) {
      throw new Exception('LinkedIn /userinfo failed: ' . $response->content());
    }

    $data = json_decode($response->content(), true);
    if (!isset($data['sub'])) {
      throw new \Exception('LinkedIn /userinfo response missing "sub" field.');
    }

    $urn = 'urn:li:person:' . $data['sub'];

    if ($this->cacheEnabled) {
      $cache->set($cacheKey, $urn, $this->cacheTimeout);
      $this->log('linkedin', 'debug', 'Cached LinkedIn author URN');
    }

    return $urn;
  }


  protected function linkedin(string $text): void
  {
    $token = $this->config['linkedin']['token'];
    $author = $this->getLinkedInAuthorUrn();

    $payload = [
      'author' => $author,
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
      'User-Agent: ' . self::USER_AGENT
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
