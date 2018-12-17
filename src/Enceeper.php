<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// Call the Enceeper service to fetch and decrypt the contents of an identifier
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
 * Call the Enceeper service to fetch and decrypt the contents of an identifier
 *
 * @author  Vassilis Poursalidis (poursal@gmail.com)
 * @package Enceeper
 */
class Enceeper
{
    // The base URL of the Enceeper service API v1
    const BASE_URL   = 'https://www.enceeper.com/api/v1/user/slots/';
    // The MAX requests the loop is allowed to run
    const MAX_CHECKS = 10;

    /**
     * @var int
     */
    private $timeout;
    /**
     * @var string
     */
    private $identifier;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $pbkd_binaries;

    /**
     * Create an Enceeper instance
     *
     * @param  string $password
     * @param  string $identifier
     * @param  string $pbkd_binaries location of the password-based key derivation binaries (scrypt, argon2 etc)
     * @param  int    $timeout
     * @return void
     */
    public function __construct(string $password, string $identifier, string $pbkd_binaries='./', int $timeout=10)
    {
        $this->password      = $password;      // The password to use in scrypt or argon2id
        $this->identifier    = $identifier;    // The identifier where the slot is available in Enceeper
        $this->timeout       = $timeout;       // Network timeout in seconds
        $this->pbkd_binaries = $pbkd_binaries; // Path to binary implementations of PBKD

        // Normalize password if Normalizer exists
        if ( method_exists(\Normalizer::class, 'normalize') ) {
            $this->password = \Normalizer::normalize($this->password, \Normalizer::FORM_KD);
        }
    }

    /**
     * Get the unencrypted value of the slot
     *
     * @return array
     *
     * @throws NetworkException
     * @throws EnceeperApiException
     * @throws VersionNotSupportedException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function getValue(): string
    {
        $slot = $this->slot();

        // The first step is to extract the slot key
        $slot_details = json_decode($slot['slot'], true);
        if ( $slot_details['v']==1 ) {
            if ( !empty($slot_details['scrypt']) ) {
                $scrypt_salt = base64_decode($slot_details['scrypt']);

                // First check for binary implementation
                $pbkd_key = $this->execBinaryPBKD('scrypt', $scrypt_salt);

                // Else fallback to PHP implementation
                if ( $pbkd_key==='' ) {
                    $pbkd_key = hex2bin(Scrypt::scrypt($this->password, $scrypt_salt));
                }
            }
            else {
                throw new Exception\VersionNotSupportedException('Could not find a supported PBKD function');
            }

            // See the enceeper JS lib. We perform a bin to hex conversion when storing the key
            // just for convenience
            $slot_key = hex2bin($this->decrypt_v1_using_openssl($pbkd_key, $slot_details));
        }
        else {
            throw new Exception\VersionNotSupportedException('Version '. $slot_details['v'] .' is not supported');
        }

        // Then we extract the actual key contents
        $value_details = json_decode($slot['value'], true);
        if ( $slot_details['v']==1 ) {
            $value = utf8_decode($this->decrypt_v1_using_openssl($slot_key, $value_details));
        }
        else {
            throw new Exception\VersionNotSupportedException('Version '. $slot_details['v'] .' is not supported');
        }

        return $value;
    }

    /**
     * Use binary PBKD functionality
     *
     * @param  string $method
     * @param  string $salt
     * @return string
     */
    private function execBinaryPBKD(string $method, string $salt) : string
    {
        $retval     = -1;
        $result     = [];
        $output     = '';
        $executable = $this->pbkd_binaries . $method;

        if ( file_exists($executable) && is_executable($executable) ) {
            $passhex = bin2hex($this->password);
            $passlen = strlen($passhex);
            if ( $passlen==0 || $passlen%8!==0 ) {
                $passpad = str_pad($passhex, $passlen +  (8 - $passlen%8), '0');
            }
            else {
                $passpad = $passhex;
            }

            $line = exec($executable .' '. bin2hex($salt) .' '. $passpad .' 32768', $result, $retval);

            if ( $retval==0 ) {
                $output = hex2bin($line);
            }
        }

        return $output;
    }

    /**
     * Decrypt version 1 of Enceeper crypto structure
     *
     * @param  string $key
     * @param  array  $details
     * @return string
     *
     * @throws WrongKeyOrModifiedCiphertextException
     */
    private function decrypt_v1_using_openssl(string $key, array $details) : string
    {
        // -> General info
        $cipher    = $details['cipher'] .'-'. $details['ks'] .'-'. $details['mode'];
        $taglen    = $details['ts']/8;
        // -> The details to decrypt
        $encrypted = base64_decode($details['ct']);
        $iv        = substr(base64_decode($details['iv']), 0, -3);
        $ct        = substr($encrypted, 0, -$taglen);
        $tag       = substr($encrypted, -$taglen);
        // -> Use OpenSSL
        $outcome   = openssl_decrypt($ct, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ( $outcome===FALSE ) {
            throw new Exception\WrongKeyOrModifiedCiphertextException('Either the wrong key was loaded, or the ciphertext was altered');
        }

        return $outcome;
    }

    /**
     * Call the Enceeper API and return the slot, meta and value in order to extract the key
     *
     * @return array
     *
     * @throws NetworkException
     * @throws EnceeperApiException
     */
    private function slot() : array
    {
        $retval = [
            'slot'  => null,
            'meta'  => null,
            'value' => null,
        ];

        $json = $this->fetch(self::BASE_URL . $this->identifier);
        $data = json_decode($json, true);

        if ( !empty($data['result']['ref']) ) {
            $ref     = $data['result']['ref'];
            $expires = time() + $data['result']['ttl'];
            $wait    = (int)floor($data['result']['ttl']/self::MAX_CHECKS);

            while( $expires>time() ) {
                sleep($wait);

                try {
                    $json = $this->fetch(self::BASE_URL .'check/'. $ref);
                    $data = json_decode($json, true);

                    // We are approved
                    $retval['slot']  = $data['result']['slot'];
                    $retval['meta']  = $data['result']['meta'];
                    $retval['value'] = $data['result']['value'];

                    break;
                } catch(Exception\EnceeperApiException $e) {
                    if ( $e->getCode()!=428 ) {
                        throw $e;
                    }
                }
            }
        }
        else {
            // We have immediate access
            $retval['slot']  = $data['result']['slot'];
            $retval['meta']  = $data['result']['meta'];
            $retval['value'] = $data['result']['value'];
        }

        return $retval;
    }

    /**
     * Fetch the contents of the URL
     *
     * @param  string $url
     * @return string
     *
     * @throws NetworkException
     * @throws EnceeperApiException
     */
    private function fetch(string $url) : string
    {
        $ctx = stream_context_create([
            'http' => [
                //'header'  => "Connection: close\r\n",
                'timeout' => $this->timeout,
            ]
        ]);

        $result = @file_get_contents($url, false, $ctx);

        if ( $result===FALSE ) {
            if ( isset($http_response_header) ) {
                $errorno = 0;
                $errorms = 'Unknown error';

                for($i=0; $i<count($http_response_header); $i++) {
                    if ( strpos($http_response_header[$i], 'HTTP/')===0 ) {
                        $parts   = explode(' ', $http_response_header[$i]);
                        $errorno = intval($parts[1]);
                        $errorms = $http_response_header[$i];
                        break;
                    }
                }

                throw new Exception\EnceeperApiException($errorms, $errorno);
            }
            else {
                throw new Exception\NetworkException('Request Timed Out');
            }
        }

        return $result;
    }
}
