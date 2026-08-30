<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN;

/**
 * Minimal view of an ICE candidate needed by the STUN layer.
 *
 * The concrete implementation (Webrtc\ICE\RTCIceCandidate) lives in the ICE
 * package, which depends on STUN; STUN references candidates only through this
 * interface to avoid a circular dependency.
 */
interface IceCandidateInterface
{
    /**
     * Returns the ICE component id (1 for RTP, 2 for RTCP) this candidate belongs to.
     */
    public function getComponentId(): int;
}
