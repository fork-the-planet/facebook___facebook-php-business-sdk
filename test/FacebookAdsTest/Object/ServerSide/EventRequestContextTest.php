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

namespace FacebookAdsTest\Object\ServerSide;

use FacebookAdsTest\AbstractUnitTestCase;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\Preference;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\ParamBuilder;
use FacebookAds\PIIUtils;

/**
 * Integration tests covering the four steps of the CAPI ParamBuilder
 * adoption stack (D96363463 .. D103323252):
 *
 *   1. Import facebook/capi-param-builder-php as a composer dependency.
 *   2. Event::setRequestContext stores the context + Preference and
 *      constructs a ParamBuilder internally.
 *   3. Event::normalize() auto-populates user_data fbc/fbp/client_ip_address
 *      from the ParamBuilder, gated by the Preference allowlist, without
 *      overwriting caller-supplied values, and order-independent w.r.t.
 *      setUserData / setRequestContext call order.
 *   4. UserData::normalize() routes PII through PIIUtils::getNormalizedAndHashedPII
 *      (covered in UserDataPIIUtilsTest.php).
 *
 * Requires `composer install` to have populated vendor/ with
 * facebook/capi-param-builder-php >= 1.2.1.
 */
class EventRequestContextTest extends AbstractUnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Tests below mutate $_COOKIE / $_SERVER; reset to a clean slate.
    $_COOKIE = [];
    $_SERVER = [];
    $_GET = [];
  }

  // ---------------------------------------------------------------------
  // Step 1: dependency import — the composer-installed classes are
  // visible in the FacebookAds namespace.
  // ---------------------------------------------------------------------

  public function testParamBuilderClassIsImportable() {
    $this->assertTrue(class_exists(ParamBuilder::class),
      'FacebookAds\\ParamBuilder should be autoloadable from vendor/');
  }

  public function testPIIUtilsClassIsImportable() {
    $this->assertTrue(class_exists(PIIUtils::class),
      'FacebookAds\\PIIUtils should be autoloadable from vendor/');
  }

  // ---------------------------------------------------------------------
  // Step 2: setRequestContext stores context + Preference and constructs
  // a ParamBuilder. The accessors round-trip the same instances.
  // ---------------------------------------------------------------------

  public function testSetRequestContextStoresContextAndPreference() {
    $context = new \stdClass();
    $context->id = 'abc';
    $pref = new Preference(false, true, true, false);

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000001)
      ->setRequestContext($context, $pref);

    $this->assertSame($context, $event->getRequestContext());
    $this->assertSame($pref, $event->getPreference());
  }

  public function testSetRequestContextDefaultsToAllowAllPreference() {
    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000002)
      ->setRequestContext(new \stdClass());

    $pref = $event->getPreference();
    $this->assertInstanceOf(Preference::class, $pref);
    $this->assertTrue($pref->isFbcAllowed());
    $this->assertTrue($pref->isFbpAllowed());
    $this->assertTrue($pref->isClientIpAddressAllowed());
    $this->assertTrue($pref->isReferrerUrlAllowed());
    $this->assertTrue($pref->isEventSourceUrlAllowed());
  }

  public function testSetRequestContextReturnsSelfForChaining() {
    $event = new Event();
    $this->assertSame($event, $event->setRequestContext(null));
  }

  public function testNormalizeWithoutRequestContextIsUnchanged() {
    $event = (new Event())
      ->setEventName('Purchase')
      ->setEventTime(1700000000)
      ->setUserData((new UserData())->setEmail('a@example.com'));

    $payload = $event->normalize();
    $this->assertEquals('Purchase', $payload['event_name']);
    // No fbc/fbp/ip should appear because setRequestContext was never called
    // and the user did not explicitly set them.
    $this->assertArrayNotHasKey('fbc', $payload['user_data']);
    $this->assertArrayNotHasKey('fbp', $payload['user_data']);
    $this->assertArrayNotHasKey('client_ip_address', $payload['user_data']);
  }

  // ---------------------------------------------------------------------
  // Step 3: auto-populate fbc/fbp/IP from ParamBuilder during normalize().
  // The real package's RequestContextAdaptor reads $_COOKIE and $_SERVER
  // when no PlainDataObject is supplied, so we drive it through globals.
  // ---------------------------------------------------------------------

  public function testNormalizeAutoPopulatesFbcFromCookie() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    // _fbc cookie must be a valid 4-segment fb.<n>.<ts>.<payload> value
    // for ParamBuilder to keep it.
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.AbCdEf12345'];

    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000010)
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertStringStartsWith(
      'fb.1.1700000000000.AbCdEf12345',
      $payload['user_data']['fbc']);
  }

  public function testNormalizeAutoPopulatesFbpFromCookie() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbp' => 'fb.1.1700000000000.987654321'];

    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000011)
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbp']);
    $this->assertStringStartsWith(
      'fb.1.1700000000000.987654321',
      $payload['user_data']['fbp']);
  }

  public function testNormalizeAutoPopulatesClientIpFromXForwardedFor() {
    // 203.0.113.42 is in TEST-NET-3, treated as a public IPv4 by the
    // package's IP filter.
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
      'REMOTE_ADDR' => '10.0.0.1',
    ];

    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000012)
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['client_ip_address']);
    $this->assertStringStartsWith(
      '203.0.113.42',
      $payload['user_data']['client_ip_address']);
  }

  public function testCallerSuppliedFbcTakesPrecedenceOverBuilder() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.BUILDER'];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000020)
      ->setUserData((new UserData())->setFbc('CALLER_FBC'))
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertEquals('CALLER_FBC', $payload['user_data']['fbc']);
  }

  public function testCallerSuppliedFbpTakesPrecedenceOverBuilder() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbp' => 'fb.1.1700000000000.BUILDER'];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000021)
      ->setUserData((new UserData())->setFbp('CALLER_FBP'))
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertEquals('CALLER_FBP', $payload['user_data']['fbp']);
  }

  public function testCallerSuppliedClientIpTakesPrecedenceOverBuilder() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
    ];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000022)
      ->setUserData((new UserData())->setClientIpAddress('CALLER_IP'))
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertEquals('CALLER_IP', $payload['user_data']['client_ip_address']);
  }

  public function testPreferenceFbpFalseGatesFbpExtraction() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = [
      '_fbc' => 'fb.1.1700000000000.WITHFBC',
      '_fbp' => 'fb.1.1700000000000.WITHFBP',
    ];

    $pref = new Preference(/*fbc*/ true, /*fbp*/ false, /*ip*/ true, true);
    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000030)
      ->setRequestContext(null, $pref);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertArrayNotHasKey('fbp', $payload['user_data']);
  }

  public function testPreferenceDisallowAllSuppressesAllAutoPopulation() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'REQUEST_URI' => '/cart',
      'HTTPS' => 'on',
      'HTTP_REFERER' => 'https://referrer.example.com/',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
    ];
    $_COOKIE = [
      '_fbc' => 'fb.1.1700000000000.XX',
      '_fbp' => 'fb.1.1700000000000.YY',
    ];

    $pref = new Preference(false, false, false, false, false);
    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000031)
      ->setRequestContext(null, $pref);
    $payload = $event->normalize();
    $ud = $payload['user_data'] ?? [];

    $this->assertArrayNotHasKey('fbc', $ud);
    $this->assertArrayNotHasKey('fbp', $ud);
    $this->assertArrayNotHasKey('client_ip_address', $ud);
    $this->assertArrayNotHasKey('event_source_url', $payload);
    $this->assertArrayNotHasKey('referrer_url', $payload);
  }

  // ---------------------------------------------------------------------
  // event_source_url auto-population. The real package's
  // RequestContextAdaptor assembles the URL from $_SERVER (scheme + host +
  // REQUEST_URI) inside processRequestFromContext(), and ParamBuilder
  // appends an appendix token, so we assert on the URL prefix.
  // ---------------------------------------------------------------------

  public function testNormalizeAutoPopulatesEventSourceUrlFromContext() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'REQUEST_URI' => '/cart',
      'HTTPS' => 'on',
    ];

    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000060)
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['event_source_url']);
    $this->assertStringStartsWith(
      'https://shop.example.com/cart',
      $payload['event_source_url']);
  }

  public function testCallerSuppliedEventSourceUrlTakesPrecedenceOverBuilder() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'REQUEST_URI' => '/from-builder',
      'HTTPS' => 'on',
    ];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000061)
      ->setEventSourceUrl('https://from-caller/')
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertEquals('https://from-caller/', $payload['event_source_url']);
  }

  public function testPreferenceEventSourceUrlFalseGatesEventSourceUrl() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'REQUEST_URI' => '/cart',
      'HTTPS' => 'on',
    ];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.WITHFBC'];

    $pref = new Preference(/*fbc*/ true, /*fbp*/ true, /*ip*/ true, /*referrer*/ true, /*event_source_url*/ false);
    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000062)
      ->setRequestContext(null, $pref);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertArrayNotHasKey('event_source_url', $payload);
  }

  // ---------------------------------------------------------------------
  // referrer_url auto-population. The real package reads $_SERVER
  // HTTP_REFERER inside processRequestFromContext() and ParamBuilder
  // appends an appendix token, so we assert on the URL prefix.
  // ---------------------------------------------------------------------

  public function testNormalizeAutoPopulatesReferrerUrlFromContext() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'HTTP_REFERER' => 'https://google.com/search?q=foo',
    ];

    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000070)
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['referrer_url']);
    $this->assertStringStartsWith(
      'https://google.com/search?q=foo',
      $payload['referrer_url']);
  }

  public function testCallerSuppliedReferrerUrlTakesPrecedenceOverBuilder() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'HTTP_REFERER' => 'https://builder.example.com/',
    ];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000071)
      ->setReferrerUrl('https://caller.example.com/')
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertEquals('https://caller.example.com/', $payload['referrer_url']);
  }

  public function testPreferenceReferrerUrlFalseGatesReferrerUrl() {
    $_SERVER = [
      'HTTP_HOST' => 'shop.example.com',
      'HTTP_REFERER' => 'https://builder.example.com/',
    ];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.WITHFBC'];

    $pref = new Preference(/*fbc*/ true, /*fbp*/ true, /*ip*/ true, /*referrer*/ false, /*event_source_url*/ true);
    $event = (new Event())
      ->setEventName('PageView')
      ->setEventTime(1700000072)
      ->setRequestContext(null, $pref);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertArrayNotHasKey('referrer_url', $payload);
  }

  public function testOrderIndependence_setUserDataBeforeSetRequestContext() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.ORDER'];

    $event = (new Event())
      ->setEventName('AddToCart')
      ->setEventTime(1700000040)
      ->setUserData((new UserData())->setEmail('a@b.com'))
      ->setRequestContext(null);
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertNotEmpty($payload['user_data']['em']);
  }

  public function testOrderIndependence_setRequestContextBeforeSetUserData() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.ORDER'];

    $event = (new Event())
      ->setEventName('AddToCart')
      ->setEventTime(1700000041)
      ->setRequestContext(null)
      ->setUserData((new UserData())->setEmail('a@b.com'));
    $payload = $event->normalize();

    $this->assertNotEmpty($payload['user_data']['fbc']);
    $this->assertNotEmpty($payload['user_data']['em']);
  }

  public function testNormalizeIsIdempotent() {
    $_SERVER = ['HTTP_HOST' => 'shop.example.com'];
    $_COOKIE = ['_fbc' => 'fb.1.1700000000000.IDEM'];

    $event = (new Event())
      ->setEventName('Lead')
      ->setEventTime(1700000050)
      ->setRequestContext(null);

    $first = $event->normalize();
    $second = $event->normalize();
    $this->assertEquals($first, $second,
      'normalize() output must be stable across repeated calls');
  }
}
