<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN\Trait;

use Webrtc\STUN\IceCandidateInterface;

/**
 * Trait CandidateSetterGetter
 *
 * Provides getter and setter methods for the ICE candidate object
 */
trait CandidateSetterGetter
{
    /** @var IceCandidateInterface|null The ICE candidate object */
    private ?IceCandidateInterface $candidate = null;

    /**
     * Get the current ICE candidate
     *
     * @return IceCandidateInterface|null The current ICE candidate or null if is not set
     */
    public function getCandidate(): ?IceCandidateInterface
    {
        return $this->candidate;
    }

    /**
     * Set the ICE candidate
     *
     * @param IceCandidateInterface $candidate The ICE candidate to set
     * @return void
     */
    public function setCandidate(IceCandidateInterface $candidate): void
    {
        $this->candidate = $candidate;
    }
}