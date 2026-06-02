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
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\PII_DATA_TYPE;
use FacebookAds\PIIUtils;

/**
 * Tests for the PIIUtils integration in UserData::normalize() (D103323252).
 * UserData no longer calls Util::hash(Normalizer::normalize(...)) directly;
 * instead it routes every PII list through PIIUtils::getNormalizedAndHashedPII.
 *
 * The package returns hashes suffixed with a `.` and a base64-url-safe
 * appendix (e.g. ".AQECAQIB" for v1.2.1 NET_NEW), so a successful integration
 * is observable in the output shape: 64 hex chars + dot + appendix.
 *
 * Requires `composer install` to have populated vendor/ with
 * facebook/capi-param-builder-php >= 1.2.1.
 */
class UserDataPIIUtilsTest extends AbstractUnitTestCase {

  // sha256 hex (64) + '.' + at least 2 chars of appendix
  const HASH_WITH_APPENDIX_REGEX = '/^[a-f0-9]{64}\.[A-Za-z0-9_-]{2,}$/';

  public function testEmailIsRoutedThroughPIIUtils() {
    $payload = (new UserData())->setEmail('Shopper@Example.com')->normalize();
    $this->assertCount(1, $payload['em']);
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['em'][0]);
    // PIIUtils lowercases before hashing, so casing must not affect hash.
    $other = (new UserData())->setEmail('shopper@example.com')->normalize();
    $this->assertEquals($payload['em'][0], $other['em'][0]);
  }

  public function testPhoneIsRoutedThroughPIIUtils() {
    // PIIUtils strips non-digits from phones.
    $payload = (new UserData())->setPhone('+1 (415) 555-0100')->normalize();
    $this->assertCount(1, $payload['ph']);
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['ph'][0]);
    $other = (new UserData())->setPhone('14155550100')->normalize();
    $this->assertEquals($payload['ph'][0], $other['ph'][0],
      'phone should hash identically regardless of formatting');
  }

  public function testCityIsNormalizedAndHashed() {
    $payload = (new UserData())->setCity(' San Francisco ')->normalize();
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['ct'][0]);
  }

  public function testCountryIsNormalizedAndHashed() {
    $payload = (new UserData())->setCountryCode('US')->normalize();
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['country'][0]);
  }

  public function testZipCodeIsNormalizedAndHashed() {
    // PIIUtils strips ZIP+4 to the 5-digit prefix.
    $payload = (new UserData())->setZipCode('94105-1234')->normalize();
    $this->assertCount(1, $payload['zp']);
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['zp'][0]);
    $other = (new UserData())->setZipCode('94105')->normalize();
    $this->assertEquals($payload['zp'][0], $other['zp'][0]);
  }

  public function testExternalIdIsNowRoutedThroughPIIUtils() {
    // BEHAVIOUR CHANGE: pre-stack, external_id was deduped raw (NOT hashed).
    // Post-stack (D103323252), external_id is hashed via PIIUtils, so the
    // output is hex-with-appendix instead of the raw input string.
    $payload = (new UserData())->setExternalId('cust_1234')->normalize();
    $this->assertCount(1, $payload['external_id']);
    $this->assertMatchesRegularExpression(
      self::HASH_WITH_APPENDIX_REGEX, $payload['external_id'][0]);
    $this->assertNotEquals('cust_1234', $payload['external_id'][0],
      'external_id must no longer round-trip the raw value');
  }

  public function testInvalidEmailIsRejectedAndOmitted() {
    // PIIUtils returns null for an invalid email; piiNormalizeDedup drops it
    // and array_filter removes the field entirely from normalize() output.
    $payload = (new UserData())->setEmail('not-an-email')->normalize();
    $this->assertArrayNotHasKey('em', $payload,
      'invalid email should be filtered out by PIIUtils');
  }

  public function testEmptyMultiValueListProducesNoKey() {
    $payload = (new UserData())
      ->setEmails([])
      ->setPhones([])
      ->setExternalIds([])
      ->normalize();
    $this->assertArrayNotHasKey('em', $payload);
    $this->assertArrayNotHasKey('ph', $payload);
    $this->assertArrayNotHasKey('external_id', $payload);
  }

  public function testMultiValueDeduplicationViaPIIUtils() {
    // Same email expressed in different casings should hash to the same
    // value and dedup down to one entry.
    $payload = (new UserData())
      ->setEmails(['A@b.com', 'a@b.com', 'A@B.COM'])
      ->normalize();
    $this->assertCount(1, $payload['em']);
  }

  public function testMultiValueDifferentInputsProduceDistinctHashes() {
    $payload = (new UserData())
      ->setEmails(['a@b.com', 'c@d.com'])
      ->normalize();
    $this->assertCount(2, $payload['em']);
    $this->assertNotEquals($payload['em'][0], $payload['em'][1]);
  }

  public function testAllRejectedValuesProduceNoKey() {
    // Every email here is invalid → PIIUtils returns null for each →
    // the resulting deduped set is empty → the field is filtered out.
    $payload = (new UserData())
      ->setEmails(['', 'not-an-email', 'still-bad'])
      ->normalize();
    $this->assertArrayNotHasKey('em', $payload);
  }

  public function testPIIUtilsDirectInvocationMatchesUserDataOutput() {
    // The hash UserData stores must equal what PIIUtils itself would produce
    // for the same input — i.e. the SDK's wrapper does not alter the value.
    $email = 'check@example.com';
    $expected = PIIUtils::getNormalizedAndHashedPII($email, PII_DATA_TYPE::EMAIL);
    $actual = (new UserData())->setEmail($email)->normalize()['em'][0];
    $this->assertEquals($expected, $actual);
  }
}
