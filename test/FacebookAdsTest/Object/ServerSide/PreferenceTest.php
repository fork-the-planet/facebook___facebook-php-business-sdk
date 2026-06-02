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
use FacebookAds\Object\ServerSide\Preference;

class PreferenceTest extends AbstractUnitTestCase {

  public function testDefaultsAllAllowed() {
    $p = new Preference();
    $this->assertTrue($p->isFbcAllowed());
    $this->assertTrue($p->isFbpAllowed());
    $this->assertTrue($p->isClientIpAddressAllowed());
    $this->assertTrue($p->isReferrerUrlAllowed());
    $this->assertTrue($p->isEventSourceUrlAllowed());
  }

  public function testAllDisallowed() {
    $p = new Preference(false, false, false, false, false);
    $this->assertFalse($p->isFbcAllowed());
    $this->assertFalse($p->isFbpAllowed());
    $this->assertFalse($p->isClientIpAddressAllowed());
    $this->assertFalse($p->isReferrerUrlAllowed());
    $this->assertFalse($p->isEventSourceUrlAllowed());
  }

  public function testPartialAllowlist() {
    // Only fbc and client_ip_address allowed.
    $p = new Preference(true, false, true, false, false);
    $this->assertTrue($p->isFbcAllowed());
    $this->assertFalse($p->isFbpAllowed());
    $this->assertTrue($p->isClientIpAddressAllowed());
    $this->assertFalse($p->isReferrerUrlAllowed());
    $this->assertFalse($p->isEventSourceUrlAllowed());
  }

  public function testEachFlagIndependently() {
    $cases = [
      [true,  false, false, false, false, 'fbc'],
      [false, true,  false, false, false, 'fbp'],
      [false, false, true,  false, false, 'client_ip_address'],
      [false, false, false, true,  false, 'referrer_url'],
      [false, false, false, false, true,  'event_source_url'],
    ];
    foreach ($cases as $c) {
      $p = new Preference($c[0], $c[1], $c[2], $c[3], $c[4]);
      $this->assertEquals($c[0], $p->isFbcAllowed(),               "fbc for $c[5]");
      $this->assertEquals($c[1], $p->isFbpAllowed(),               "fbp for $c[5]");
      $this->assertEquals($c[2], $p->isClientIpAddressAllowed(),   "ip for $c[5]");
      $this->assertEquals($c[3], $p->isReferrerUrlAllowed(),       "referrer for $c[5]");
      $this->assertEquals($c[4], $p->isEventSourceUrlAllowed(),    "event_source_url for $c[5]");
    }
  }
}
