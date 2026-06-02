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
 * Example: when an HTTP request carries _fbc / _fbp cookies and an IP-bearing
 * header, Event::normalize() asks the CAPI ParamBuilder to auto-populate
 * UserData.fbc / fbp / client_ip_address — gated by the Preference
 * allowlist, never overwriting caller-supplied values, and idempotent
 * regardless of the order of setUserData() / setRequestContext() calls.
 *
 * Run after `composer install`:
 *   php examples/server_side_auto_populate_request_context.php
 */

define('SDK_DIR', __DIR__ . '/..');
$loader = include SDK_DIR . '/vendor/autoload.php';

use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\Preference;
use FacebookAds\Object\ServerSide\UserData;

// In a real app these come from the live request; here we simulate them
// via PHP superglobals that the package's RequestContextAdaptor reads.
function simulate_request(array $cookies, array $server) {
  $_COOKIE = $cookies;
  $_SERVER = array_merge(['HTTP_HOST' => 'shop.example.com'], $server);
  $_GET = [];
}

// --- A: allow-all preference (the default) --------------------------------
echo "-- Case A: Preference allows everything (default) --\n";
simulate_request(
  ['_fbc' => 'fb.1.1700000000000.AbCdEf12345',
   '_fbp' => 'fb.1.1700000000000.987654321'],
  ['HTTP_X_FORWARDED_FOR' => '203.0.113.42']);

$event = (new Event())
  ->setEventName('Purchase')
  ->setEventTime(1700000000)
  ->setActionSource('website')
  ->setUserData((new UserData())->setEmail('shopper@example.com'))
  ->setCustomData((new CustomData())->setCurrency('usd')->setValue(99.95))
  ->setRequestContext(null);

$ud = $event->normalize()['user_data'];
echo "  user_data.fbc                = " . ($ud['fbc']               ?? '(none)') . "\n";
echo "  user_data.fbp                = " . ($ud['fbp']               ?? '(none)') . "\n";
echo "  user_data.client_ip_address  = " . ($ud['client_ip_address'] ?? '(none)') . "\n";

// --- B: Preference disables fbp ------------------------------------------
echo "\n-- Case B: new Preference(true, false, true, true) — fbp gated out --\n";
simulate_request(
  ['_fbc' => 'fb.1.1700000000000.WITHFBC',
   '_fbp' => 'fb.1.1700000000000.WITHFBP'],
  ['HTTP_X_FORWARDED_FOR' => '203.0.113.50']);

$event = (new Event())
  ->setEventName('PageView')
  ->setEventTime(1700000001)
  ->setRequestContext(null, new Preference(true, false, true, true));
$ud = $event->normalize()['user_data'] ?? [];
echo "  user_data.fbc                = " . ($ud['fbc']               ?? '(absent)') . "\n";
echo "  user_data.fbp                = " . ($ud['fbp']               ?? '(absent — gated)') . "\n";
echo "  user_data.client_ip_address  = " . ($ud['client_ip_address'] ?? '(absent)') . "\n";

// --- C: Preference disallows everything ----------------------------------
echo "\n-- Case C: new Preference(false, false, false, false) — none populated --\n";
simulate_request(
  ['_fbc' => 'fb.1.1700000000000.XX', '_fbp' => 'fb.1.1700000000000.YY'],
  ['HTTP_X_FORWARDED_FOR' => '203.0.113.55']);

$event = (new Event())
  ->setEventName('PageView')
  ->setEventTime(1700000002)
  ->setRequestContext(null, new Preference(false, false, false, false));
$ud = $event->normalize()['user_data'] ?? [];
echo "  user_data has fbc?  " . (isset($ud['fbc']) ? 'yes' : 'no') . "\n";
echo "  user_data has fbp?  " . (isset($ud['fbp']) ? 'yes' : 'no') . "\n";
echo "  user_data has ip?   " . (isset($ud['client_ip_address']) ? 'yes' : 'no') . "\n";

// --- D: caller-supplied values take precedence ---------------------------
echo "\n-- Case D: caller-set fbc — builder defers to caller --\n";
simulate_request(
  ['_fbc' => 'fb.1.1700000000000.BUILDER',
   '_fbp' => 'fb.1.1700000000000.BUILDER'],
  ['HTTP_X_FORWARDED_FOR' => '203.0.113.60']);

$event = (new Event())
  ->setEventName('Lead')
  ->setEventTime(1700000003)
  ->setUserData((new UserData())
    ->setFbc('CALLER_OWNED_FBC')
    ->setEmail('vip@example.com'))
  ->setRequestContext(null);
$ud = $event->normalize()['user_data'];
echo "  user_data.fbc                = " . $ud['fbc']               . "  (caller value preserved)\n";
echo "  user_data.fbp                = " . $ud['fbp']               . "  (builder filled — caller had none)\n";
echo "  user_data.client_ip_address  = " . $ud['client_ip_address'] . "  (builder filled)\n";
