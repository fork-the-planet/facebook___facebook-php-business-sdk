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

  private $fbc;
  private $fbp;
  private $client_ip_address;
  private $referrer_url;

  /**
   * Constructor
   * @param bool $fbc Whether fbc is allowed (default: true)
   * @param bool $fbp Whether fbp is allowed (default: true)
   * @param bool $client_ip_address Whether client_ip_address is allowed (default: true)
   * @param bool $referrer_url Whether referrer_url is allowed (default: true)
   */
  public function __construct(
    bool $fbc = true,
    bool $fbp = true,
    bool $client_ip_address = true,
    bool $referrer_url = true
  ) {
    $this->fbc = $fbc;
    $this->fbp = $fbp;
    $this->client_ip_address = $client_ip_address;
    $this->referrer_url = $referrer_url;
  }

  /**
   * Gets whether fbc is allowed to be set from the request context.
   * @return bool
   */
  public function getFbc() {
    return $this->fbc;
  }

  /**
   * Gets whether fbp is allowed to be set from the request context.
   * @return bool
   */
  public function getFbp() {
    return $this->fbp;
  }

  /**
   * Gets whether client_ip_address is allowed to be set from the request context.
   * @return bool
   */
  public function getClientIpAddress() {
    return $this->client_ip_address;
  }

  /**
   * Gets whether referrer_url is allowed to be set from the request context.
   * @return bool
   */
  public function getReferrerUrl() {
    return $this->referrer_url;
  }
}
