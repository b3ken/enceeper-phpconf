<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// A redis backed cache. Make sure that you have Redis installed (https://redis.io and https://github.com/phpredis/phpredis).
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

use Redis;

/**
 * A redis backed cache. Make sure that you have Redis installed (https://redis.io and https://github.com/phpredis/phpredis).
 *
 * @author  Vassilis Poursalidis (poursal@gmail.com)
 * @package Enceeper
 */
final class RedisCache extends AbstractCache
{
    /**
     * @var Redis
     */
    private $redis;
    /**
     * @var string
     */
    private $key;
    /**
     * @var array
     */
    private $cached;

    /**
     * Redis backed cache
     *
     * @param  Redis    $redis
     * @param  string   $key
     * @param  int      $ttl
     * @param  Enceeper $enceeper
     * @param  int      $strategy
     * @return void
     */
    public function __construct(Redis $redis, string $key, int $ttl, Enceeper $enceeper, int $strategy = self::STRATEGY_BATCH_MODE)
    {
        $this->redis  = $redis;
        $this->key    = $key;
        $this->cached = false;

        parent::__construct($ttl, $enceeper, $strategy);
    }

    /**
     * Set the contents of the cache to the provided value
     *
     * @param  string   $value
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function setCache(string $value) : array
    {
        $array = json_decode($value, true);

        if ( $array!==NULL ) {
            $res = $this->redis->set($this->key, json_encode([
                'created' => time(),
                'value'   => $array
            ]));

            if ( $res!==false ) {
                return $array;
            }
        }

        throw new \RuntimeException('Failed to set the cache contents');
    }

    /**
     * Get the contents of the cache or an empty array on failure (nothing in cache)
     *
     * @return array
     */
    protected function getCached() : array
    {
        $resp = [];

        if ( $this->cached!==false ) {
            $resp         = $this->cached;
            $this->cached = false;
        }
        else {
            $value = $this->redis->get($this->key);

            if ( $value!==false ) {
                $array = json_decode($value, true);

                if ( $array!==NULL ) {
                    $resp = $array['value'];
                }
            }
        }

        return $resp;
    }

    /**
     * Return the last time we updated the cache contents
     *
     * @return int
     */
    protected function lastUpdate() : int
    {
        $modified = 0;

        $value = $this->redis->get($this->key);
        if ( $value!==false ) {
            $array = json_decode($value, true);

            if ( $array!==NULL ) {
                $modified     = $array['created'];
                $this->cached = $array['value'];
            }
        }

        return $modified;
    }
}
