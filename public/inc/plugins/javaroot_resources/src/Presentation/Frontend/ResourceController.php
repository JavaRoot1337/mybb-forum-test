<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Presentation\Frontend;

use JavaRoot\Resources\Exception\AuthenticationException;
use JavaRoot\Resources\Exception\AuthorizationException;
use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Presentation\Response\Responder;
use JavaRoot\Resources\Service\ResourceService;
use JavaRoot\Resources\Service\UploadService;

final class ResourceController
{
    public function __construct(
        private ResourceService $resources,
        private UploadService $uploads,
        private Responder $responder,
    ) {
    }

    public function handle(): void
    {
        global $mybb, $lang;

        $action = $mybb->get_input('action') ?: 'list';
        $json = in_array($action, ['upload_init', 'upload_status', 'upload_chunk', 'upload_finalize'], true);
        try {
            if ($json) {
                $this->handleUpload($action);
            }
            switch ($action) {
                case 'save':
                    $this->save();
                    break;
                case 'review':
                    $this->review();
                    break;
                case 'favorite':
                    $this->favorite();
                    break;
                case 'report':
                    $this->report();
                    break;
                case 'moderate':
                    $this->moderate();
                    break;
                case 'download':
                    $this->download();
                    break;
                case 'create':
                    $this->create();
                    break;
                case 'upload':
                    $this->uploadPage();
                    break;
                default:
                    $this->page();
            }
        } catch (AuthenticationException) {
            if ($json) {
                $this->responder->json(['error' => $lang->javaroot_login_required], 401);
            }
            error($lang->javaroot_login_required);
        } catch (AuthorizationException) {
            error_no_permission();
        } catch (InvalidRequestException $exception) {
            if ($json) {
                $this->responder->json(['error' => $lang->javaroot_invalid_request], $exception->status());
            }
            error($lang->javaroot_invalid_request);
        }
    }

    private function handleUpload(string $action): never
    {
        $this->verifyPost(true);
        global $mybb, $lang;

        if ($action === 'upload_init') {
            $result = $this->uploads->init(
                $mybb->get_input('rid', \MyBB::INPUT_INT),
                $mybb->get_input('vid', \MyBB::INPUT_INT),
                (string)$mybb->get_input('filename'),
                (int)$mybb->get_input('size'),
            );
            $this->responder->json($result);
        }

        $token = $this->headerOrInput('token', 'HTTP_X_UPLOAD_TOKEN');
        if ($action === 'upload_status') {
            $this->responder->json($this->uploads->status($token));
        }
        if ($action === 'upload_chunk') {
            $offset = (int)$this->headerOrInput('offset', 'HTTP_X_UPLOAD_OFFSET');
            $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            $this->responder->json($this->uploads->chunk($token, $offset, $length));
        }

        $this->uploads->finalize($token);
        $this->responder->json(['message' => $lang->javaroot_upload_pending]);
    }

    private function save(): never
    {
        $this->verifyPost();
        global $mybb;

        $created = $this->resources->create([
            'title' => $mybb->get_input('title'),
            'summary' => $mybb->get_input('summary'),
            'description' => $mybb->get_input('description'),
            'category' => $mybb->get_input('category', \MyBB::INPUT_INT),
            'version' => $mybb->get_input('version'),
            'minecraft_versions' => $mybb->get_input('minecraft_versions'),
            'changelog' => $mybb->get_input('changelog'),
            'external_url' => $mybb->get_input('external_url'),
        ]);
        $this->responder->redirect($mybb->settings['bburl'] . '/resources.php?action=upload&rid=' . $created['rid'] . '&vid=' . $created['vid'], $this->language('javaroot_saved'));
    }

    private function review(): never
    {
        $this->verifyPost();
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        $this->resources->review($rid, $mybb->get_input('rating', \MyBB::INPUT_INT), (string)$mybb->get_input('message'));
        $this->responder->redirect($mybb->settings['bburl'] . '/resources.php?rid=' . $rid, $this->language('javaroot_saved'));
    }

    private function favorite(): never
    {
        $this->verifyPost();
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        $this->resources->toggleFavorite($rid);
        $this->responder->redirect($mybb->settings['bburl'] . '/resources.php?rid=' . $rid, $this->language('javaroot_saved'));
    }

    private function report(): never
    {
        $this->verifyPost();
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        $this->resources->report($rid, (string)$mybb->get_input('reason'));
        $this->responder->redirect($mybb->settings['bburl'] . '/resources.php?rid=' . $rid, $this->language('javaroot_saved'));
    }

    private function moderate(): never
    {
        $this->verifyPost();
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        $this->resources->moderate($rid, (string)$mybb->get_input('status'));
        $this->responder->redirect($mybb->settings['bburl'] . '/resources.php?rid=' . $rid, $this->language('javaroot_saved'));
    }

    private function download(): never
    {
        global $mybb;

        $file = $this->resources->downloadableFile($mybb->get_input('fid', \MyBB::INPUT_INT));
        if ($file['kind'] === 'external') {
            header('Location: ' . $file['external_url'], true, 302);
            exit;
        }

        $path = realpath((string)$file['path']);
        $storage = new \JavaRoot\Resources\Infrastructure\Storage\Storage();
        if (!$path || !$storage->isStoredFile($path)) {
            throw new InvalidRequestException();
        }
        $this->resources->recordDownload((int)$file['rid'], (int)$file['fid']);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . addcslashes(basename((string)$file['filename']), '"\\') . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function create(): never
    {
        $this->resources->requireUser();
        $this->responder->html('resources/create', ['categories' => $this->resources->categories()]);
    }

    private function uploadPage(): never
    {
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        $resource = $this->resources->assertOwnerOrManager($rid);
        $this->responder->html('resources/upload', [
            'resource' => $resource,
            'vid' => $mybb->get_input('vid', \MyBB::INPUT_INT),
            'post_key' => $mybb->post_code,
        ]);
    }

    private function page(): never
    {
        global $mybb;

        $rid = $mybb->get_input('rid', \MyBB::INPUT_INT);
        if ($rid) {
            $this->responder->html('resources/view', $this->resources->detail($rid));
        }

        $this->responder->html('resources/list', [
            'resources' => $this->resources->list((string)$mybb->get_input('sort')),
            'can_create' => $this->resources->canCreate(),
        ]);
    }

    private function verifyPost(bool $json = false): void
    {
        global $mybb, $lang;

        $postKey = (string)$mybb->get_input('my_post_key');
        if ($postKey === '' && isset($_SERVER['HTTP_X_MYBB_POST_KEY'])) {
            $postKey = (string)$_SERVER['HTTP_X_MYBB_POST_KEY'];
        }
        if (!verify_post_check($postKey)) {
            if ($json) {
                $this->responder->json(['error' => $lang->invalid_post_verify_key2], 400);
            }
            error($lang->invalid_post_verify_key2);
        }
    }

    private function headerOrInput(string $input, string $header): string
    {
        global $mybb;

        $value = (string)$mybb->get_input($input);

        return $value !== '' ? $value : (string)($_SERVER[$header] ?? '');
    }

    private function language(string $key): string
    {
        global $lang;

        return (string)$lang->{$key};
    }
}
