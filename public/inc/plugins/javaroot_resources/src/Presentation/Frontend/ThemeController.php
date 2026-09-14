<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Presentation\Frontend;

use JavaRoot\Resources\Exception\AuthenticationException;
use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Presentation\Response\Responder;
use JavaRoot\Resources\Service\ThemeService;

final class ThemeController
{
    public function __construct(
        private ThemeService $themes,
        private Responder $responder,
    ) {
    }

    public function handle(): void
    {
        global $mybb, $lang;

        try {
            if ($mybb->request_method === 'post') {
                $postKey = (string)$mybb->get_input('my_post_key');
                if (!verify_post_check($postKey)) {
                    error($lang->invalid_post_verify_key2);
                }
                $returnUrl = $this->themes->save((string)$mybb->get_input('theme'), (string)$mybb->get_input('return_url'));
                $this->responder->redirect($returnUrl, $lang->javaroot_theme_saved);
            }

            $this->responder->html('theme/settings', [
                'themes' => $this->themes->themes(),
                'current' => $this->themes->current(),
            ]);
        } catch (AuthenticationException) {
            error($lang->javaroot_login_required);
        } catch (InvalidRequestException) {
            error($lang->javaroot_invalid_request);
        }
    }
}
