<?php
/**
 * Copyright (c) 2015-present, Facebook, Inc. All rights reserved.
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

namespace FacebookAds\Object\ServerSide;

/**
 * Preference is an allowlist to specify what data are allowed to be
 * automatically set on the CAPI event from the request context object.
 * All fields default to true.
 *
 * @category    Class
 * @package     FacebookAds\Object\ServerSide
 */
class Preference {

  private $is_fbc_allowed;
  private $is_fbp_allowed;
  private $is_client_ip_address_allowed;
  private $is_referrer_url_allowed;
  private $is_event_source_url_allowed;

  /**
   * Constructor
   * @param bool $is_fbc_allowed Whether fbc is allowed (default: true)
   * @param bool $is_fbp_allowed Whether fbp is allowed (default: true)
   * @param bool $is_client_ip_address_allowed Whether client_ip_address is allowed (default: true)
   * @param bool $is_referrer_url_allowed Whether referrer_url is allowed (default: true)
   * @param bool $is_event_source_url_allowed Whether event_source_url is allowed (default: true)
   */
  public function __construct(
    bool $is_fbc_allowed = true,
    bool $is_fbp_allowed = true,
    bool $is_client_ip_address_allowed = true,
    bool $is_referrer_url_allowed = true,
    bool $is_event_source_url_allowed = true
  ) {
    $this->is_fbc_allowed = $is_fbc_allowed;
    $this->is_fbp_allowed = $is_fbp_allowed;
    $this->is_client_ip_address_allowed = $is_client_ip_address_allowed;
    $this->is_referrer_url_allowed = $is_referrer_url_allowed;
    $this->is_event_source_url_allowed = $is_event_source_url_allowed;
  }

  /**
   * Gets whether fbc is allowed to be set from the request context.
   * @return bool
   */
  public function isFbcAllowed() {
    return $this->is_fbc_allowed;
  }

  /**
   * Gets whether fbp is allowed to be set from the request context.
   * @return bool
   */
  public function isFbpAllowed() {
    return $this->is_fbp_allowed;
  }

  /**
   * Gets whether client_ip_address is allowed to be set from the request context.
   * @return bool
   */
  public function isClientIpAddressAllowed() {
    return $this->is_client_ip_address_allowed;
  }

  /**
   * Gets whether referrer_url is allowed to be set from the request context.
   * @return bool
   */
  public function isReferrerUrlAllowed() {
    return $this->is_referrer_url_allowed;
  }

  /**
   * Gets whether event_source_url is allowed to be set from the request context.
   * @return bool
   */
  public function isEventSourceUrlAllowed() {
    return $this->is_event_source_url_allowed;
  }
}
