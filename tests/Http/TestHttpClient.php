<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Common\Tests\Http;

use LC\Common\HttpClient\HttpClientInterface;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param mixed $requestUri
     *
     * @return array
     */
    public function get($requestUri)
    {
        switch ($requestUri) {
            case 'serverClient/has_totp_secret?user_id=foo':
                return [200, self::wrap('has_totp_secret', true)];
            case 'serverClient/has_totp_secret?user_id=bar':
                return [200, self::wrap('has_totp_secret', false)];
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    /**
     * @param mixed $requestUri
     *
     * @return array
     */
    public function post($requestUri, array $postData = [])
    {
        switch ($requestUri) {
            case 'serverClient/verify_totp_key':
                if ('foo' === $postData['user_id']) {
                    return [200, self::wrap('verify_totp_key', true)];
                }

                return [200, self::wrapError('verify_totp_key', 'invalid OTP key')];
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    /**
     * @param mixed $key
     * @param mixed $responseData
     *
     * @return array
     */
    private static function wrap($key, $responseData)
    {
        return [
            $key => [
                'ok' => true,
                'data' => $responseData,
            ],
        ];
    }

    /**
     * @param mixed $key
     * @param mixed $errorMessage
     *
     * @return array
     */
    private static function wrapError($key, $errorMessage)
    {
        return [
            $key => [
                'ok' => false,
                'error' => $errorMessage,
            ],
        ];
    }
}
