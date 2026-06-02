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
 * Example: attach an HTTP request context to a server-side Event using
 * Event::setRequestContext, optionally with a Preference allowlist.
 *
 * Run after `composer install`:
 *   php examples/server_side_set_request_context.php
 */

define('SDK_DIR', __DIR__ . '/..');
$loader = include SDK_DIR . '/vendor/autoload.php';

use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\Preference;
use FacebookAds\Object\ServerSide\UserData;

// --- Case A: minimum call — just hand over the request context. ---------
// The "context" is opaque to the SDK. The CAPI ParamBuilder's
// RequestContextAdaptor accepts:
//   - a PlainDataObject built by the caller, or
//   - any value (including null) — in which case the adaptor reads from
//     PHP's $_SERVER and $_COOKIE superglobals.
echo "-- Case A: setRequestContext(\$context) --\n";
$context = (object) ['simulated' => 'http_request'];
$event = (new Event())
  ->setEventName('Lead')
  ->setEventTime(time())
  ->setRequestContext($context);

echo "  context stored: " . get_class($event->getRequestContext()) . "\n";
$pref = $event->getPreference();
printf("  preference (default): fbc=%s fbp=%s ip=%s referrer=%s\n",
  var_export($pref->isFbcAllowed(), true),
  var_export($pref->isFbpAllowed(), true),
  var_export($pref->isClientIpAddressAllowed(), true),
  var_export($pref->isReferrerUrlAllowed(), true));

// --- Case B: pass an explicit Preference to gate which fields are auto-
//             populated when normalize() runs. -----------------------------
echo "\n-- Case B: setRequestContext(\$context, new Preference(true, false, true, true)) --\n";
$pref = new Preference(true /*fbc*/, false /*fbp*/, true /*ip*/, true /*ref*/);
$event = (new Event())
  ->setEventName('Lead')
  ->setEventTime(time())
  ->setRequestContext(null, $pref);
echo "  preference.isFbpAllowed() = " . var_export(
  $event->getPreference()->isFbpAllowed(), true) . "\n";

// --- Case C: setRequestContext is fluent — it returns $this. ------------
echo "\n-- Case C: chainable --\n";
$event = (new Event())->setEventName('Lead')->setEventTime(time());
$same = $event->setRequestContext(null);
echo "  chained instance is the same Event? " . (($same === $event) ? "yes" : "no") . "\n";
