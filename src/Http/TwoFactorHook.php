<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Common\Http;

use fkooman\SeCookie\SessionInterface;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\TplInterface;

class TwoFactorHook implements BeforeHookInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var bool */
    private $requireTwoFactor;

    /**
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \LetsConnect\Common\TplInterface            $tpl
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     * @param bool                                        $requireTwoFactor
     */
    public function __construct(SessionInterface $session, TplInterface $tpl, ServerClient $serverClient, $requireTwoFactor)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->requireTwoFactor = $requireTwoFactor;
    }

    /**
     * @return bool|Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }

        // some URIs are allowed as they are used for either logging in, or
        // verifying the OTP key
        $whiteList = [
            'POST' => [
                '/two_factor_enroll',
                '/_saml/acs',
                '/_form/auth/verify',
                '/_form/auth/logout',
                '/_logout',
                '/_two_factor/auth/verify/totp',
            ],
            'GET' => [
                '/_saml/login',
                '/_saml/logout',
                '/_saml/metadata',
                '/two_factor_enroll',
                '/two_factor_enroll_qr',
                '/documentation',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        $userInfo = $hookData['auth'];
        if ($this->session->has('_two_factor_verified')) {
            if ($userInfo->id() !== $this->session->get('_two_factor_verified')) {
                throw new HttpException('two-factor code not bound to authenticated user', 400);
            }

            return true;
        }

        // check if user is enrolled
        $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
        if ($hasTotpSecret) {
            // user is enrolled for 2FA, ask for it!
            return new HtmlResponse(
                $this->tpl->render(
                    'twoFactorTotp',
                    [
                        '_two_factor_user_id' => $userInfo->id(),
                        '_two_factor_auth_invalid' => false,
                        '_two_factor_auth_redirect_to' => $request->getUri(),
                    ]
                )
            );
        }

        if ($this->requireTwoFactor) {
            // 2FA required, but user not enrolled, offer them to enroll
            $this->session->set('_two_factor_enroll_redirect_to', $request->getUri());

            return new RedirectResponse($request->getRootUri().'two_factor_enroll');
        }

        // 2FA not required, and user not enrolled...
        $this->session->regenerate(true);
        $this->session->set('_two_factor_verified', $userInfo->id());

        return true;
    }
}
