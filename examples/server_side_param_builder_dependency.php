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
 * Example: import the facebook/capi-param-builder-php package as a
 * dependency of the Business SDK.
 *
 * Run after `composer install`:
 *   php examples/server_side_param_builder_dependency.php
 */

define('SDK_DIR', __DIR__ . '/..');
$loader = include SDK_DIR . '/vendor/autoload.php';

use FacebookAds\ParamBuilder;
use FacebookAds\PII_DATA_TYPE;
use FacebookAds\PIIUtils;
use FacebookAds\Object\ServerSide\Event;

// 1. The package is autoloaded under the FacebookAds\ namespace via the
//    SDK's composer.json `require` block.
$param_builder = new ParamBuilder();
echo "Instantiated: " . get_class($param_builder) . "\n";

$ref = new ReflectionClass($param_builder);
echo "Loaded from:  " . $ref->getFileName() . "\n";

// 2. PIIUtils and the PII_DATA_TYPE enum are available to consumer code.
echo "PIIUtils available:        " . (class_exists(PIIUtils::class) ? 'yes' : 'no') . "\n";
echo "PII_DATA_TYPE available:   " . (class_exists(PII_DATA_TYPE::class) ? 'yes' : 'no') . "\n";

// 3. The Biz SDK Event class composes ParamBuilder internally; constructing
//    one is enough to prove the dependency wiring is intact.
$event = (new Event())->setEventName('ImportCheck')->setEventTime(time());
echo "Event composed; event_name = " . $event->normalize()['event_name'] . "\n";

// 4. PIIUtils is callable directly from consumer code if needed.
$hashed = PIIUtils::getNormalizedAndHashedPII(
  'consumer@example.com',
  PII_DATA_TYPE::EMAIL);
echo "PIIUtils::getNormalizedAndHashedPII('consumer@example.com', EMAIL) = $hashed\n";
