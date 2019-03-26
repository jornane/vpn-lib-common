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
use LetsConnect\Common\HttpClient\Exception\ApiException;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\TplInterface;

class TwoFactorModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    public function __construct(ServerClient $serverClient, SessionInterface $session, TplInterface $tpl)
    {
        $this->serverClient = $serverClient;
        $this->session = $session;
        $this->tpl = $tpl;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/_two_factor/auth/verify/totp',
            /**
             * @return Response
             */
            function (Request $request, array $hookData) {
                if (!\array_key_exists('auth', $hookData)) {
                    throw new HttpException('authentication hook did not run before', 500);
                }
                $userInfo = $hookData['auth'];

                $this->session->delete('_two_factor_verified');

                $totpKey = InputValidation::totpKey($request->getPostParameter('_two_factor_auth_totp_key'));
                $redirectTo = $request->getPostParameter('_two_factor_auth_redirect_to');

                try {
                    $this->serverClient->post('verify_totp_key', ['user_id' => $userInfo->id(), 'totp_key' => $totpKey]);
                    $this->session->regenerate(true);
                    $this->session->set('_two_factor_verified', $userInfo->id());

                    return new RedirectResponse($redirectTo, 302);
                } catch (ApiException $e) {
                    // unable to validate the OTP
                    return new HtmlResponse(
                        $this->tpl->render(
                            'twoFactorTotp',
                            [
                                '_two_factor_user_id' => $userInfo->id(),
                                '_two_factor_auth_invalid' => true,
                                '_two_factor_auth_error_msg' => $e->getMessage(),
                                '_two_factor_auth_redirect_to' => $redirectTo,
                            ]
                        )
                    );
                }
            }
        );
    }
}
