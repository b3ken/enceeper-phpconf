<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// The abtract cache with core functionality
//
// Copyright (C) 2018 Vassilis Poursalidis (poursal@gmail.com)
//
// This program is free software: you can redistribute it and/or modify it under the terms of the
// GNU General Public License as published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
// even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// General Public License for more details.
//
// You should have received a copy of the GNU General Public License along with this program. If
// not, see <https://www.gnu.org/licenses/>.
//

declare(strict_types=1);

namespace Enceeper;

/**
 * The abtract cache with core functionality
 *
 * @author  Vassilis Poursalidis (poursal@gmail.com)
 * @package Enceeper
 */
abstract class AbstractCache implements CacheInterface
{
    const STRATEGY_LIVE_UPDATE = 0;
    const STRATEGY_BATCH_MODE  = 1;

    /**
     * @var int
     */
    protected $ttl;
    /**
     * @var Enceeper
     */
    protected $enceeper;
    /**
     * @var int
     */
    protected $strategy;

    /**
     * Base contructor. If batch mode strategy is selected make sure that you successfully called update first.
     *
     * @param  int      $ttl
     * @param  Enceeper $enceeper
     * @param  int      $strategy
     * @return void
     */
    public function __construct(int $ttl, Enceeper $enceeper, int $strategy = self::STRATEGY_BATCH_MODE)
    {
        $this->ttl      = $ttl;
        $this->enceeper = $enceeper;
        $this->strategy = $strategy;
    }

    /**
     * The array with the configuration data (either from cache or via Enceeper)
     *
     * @return array
     *
     * @throws NetworkException
     * @throws EnceeperApiException
     * @throws VersionNotSupportedException
     * @throws WrongKeyOrModifiedCiphertextException
     * @throws \RuntimeException
     */
    public function get() : array
    {
        if ( $this->strategy==self::STRATEGY_BATCH_MODE ) {
            return $this->getCached();
        }
        else {
            if ( $this->hasExpired() ) {
                return $this->update();
            }
            else {
                return $this->getCached();
            }
        }
    }

    /**
     * Check if the cache contents have expired
     *
     * @return boolean
     */
    private function hasExpired()
    {
        return ( ($this->lastUpdate() + $this->ttl) < time() );
    }

    /**
     * Fetch a new copy of the configuration data from Enceeper and cache it. If you select the
     * batch mode strategy you must call this method manually (i.e. via crontab).
     *
     * @return array
     *
     * @throws NetworkException
     * @throws EnceeperApiException
     * @throws VersionNotSupportedException
     * @throws WrongKeyOrModifiedCiphertextException
     * @throws \RuntimeException
     */
    public function update() : array
    {
        return $this->setCache($this->enceeper->getValue());
    }

    // Set the contents of the cache to the provided value
    abstract protected function setCache(string $value) : array;
    // Get the contents of the cache
    abstract protected function getCached() : array;
    // Return the last time we updated the cache contents
    abstract protected function lastUpdate() : int;
}
