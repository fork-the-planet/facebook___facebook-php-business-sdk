<?php
/**
 * Copyright (c) 2014-present, Facebook, Inc. All rights reserved.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

/*
 * Example: every PII field that consumers set on UserData is normalized
 * and SHA-256 hashed by the CAPI ParamBuilder's PIIUtils helper before
 * leaving the SDK. The output is `<sha256-hex>.<appendix>` where the
 * appendix is the package's URL-safe base64 version tag (e.g. ".AQECAQIB"
 * for v1.2.1 NET_NEW).
 *
 * Run after `composer install`:
 *   php examples/server_side_pii_normalize_and_hash.php
 */

define('SDK_DIR', __DIR__ . '/..');
$loader = include SDK_DIR . '/vendor/autoload.php';

use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\PII_DATA_TYPE;
use FacebookAds\PIIUtils;

// --- A: each PII field, raw vs hashed ------------------------------------
echo "-- Case A: each PII field, raw vs normalized + hashed --\n";
$cases = [
  ['email',       'Shopper@Example.com', 'em',          'setEmail'],
  ['phone',       '+1 (415) 555-0100',   'ph',          'setPhone'],
  ['city',        ' San Francisco ',     'ct',          'setCity'],
  ['country',     'US',                  'country',     'setCountryCode'],
  ['zip_code',    '94105-1234',          'zp',          'setZipCode'],
  ['external_id', 'cust_1234',           'external_id', 'setExternalId'],
];
foreach ($cases as [$label, $raw, $key, $setter]) {
  $hashed = (new UserData())->{$setter}($raw)->normalize()[$key][0];
  printf("  %-12s '%s' => %s\n", $label, $raw, $hashed);
}

// --- B: normalization is case- and format-insensitive --------------------
echo "\n-- Case B: same logical value, different formatting => same hash --\n";
$emA = (new UserData())->setEmail('VIP@Example.COM')->normalize()['em'][0];
$emB = (new UserData())->setEmail(' vip@example.com ')->normalize()['em'][0];
echo "  'VIP@Example.COM'      => $emA\n";
echo "  ' vip@example.com '    => $emB\n";
echo "  match? " . (($emA === $emB) ? 'yes' : 'no') . "\n";

$phA = (new UserData())->setPhone('+1 (415) 555-0100')->normalize()['ph'][0];
$phB = (new UserData())->setPhone('14155550100')->normalize()['ph'][0];
echo "  '+1 (415) 555-0100'    => $phA\n";
echo "  '14155550100'          => $phB\n";
echo "  match? " . (($phA === $phB) ? 'yes' : 'no') . "\n";

// --- C: invalid PII is silently dropped ---------------------------------
echo "\n-- Case C: PIIUtils returns null for invalid values; SDK drops them --\n";
$payload = (new UserData())
  ->setEmails(['', 'not-an-email', 'still-bad'])
  ->normalize();
echo "  setEmails(['', 'not-an-email', 'still-bad']) => "
  . (array_key_exists('em', $payload) ? "still present" : "key dropped (all rejected)") . "\n";

// --- D: multi-value dedup happens after hashing --------------------------
echo "\n-- Case D: case variants dedup post-hash --\n";
$payload = (new UserData())
  ->setEmails(['A@b.com', 'a@b.com', 'A@B.COM'])
  ->normalize();
echo "  input:   ['A@b.com', 'a@b.com', 'A@B.COM']\n";
echo "  output:  " . count($payload['em']) . " entry => " . $payload['em'][0] . "\n";

// --- E: SDK output equals direct PIIUtils invocation --------------------
echo "\n-- Case E: SDK simply wraps PIIUtils — no extra transformation --\n";
$direct = PIIUtils::getNormalizedAndHashedPII('owl@example.com', PII_DATA_TYPE::EMAIL);
$viaSDK = (new UserData())->setEmail('owl@example.com')->normalize()['em'][0];
echo "  PIIUtils::getNormalizedAndHashedPII => $direct\n";
echo "  via UserData::normalize             => $viaSDK\n";
echo "  match? " . (($direct === $viaSDK) ? 'yes' : 'no') . "\n";
