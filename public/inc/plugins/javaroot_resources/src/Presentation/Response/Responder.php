<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Presentation\Response;

final class Responder
{
    public function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function html(string $template, array $context = []): never
    {
        output_page(\MyBB\View\template('@ext.javaroot_resources/' . $template . '.twig', $context));
        exit;
    }

    public function redirect(string $url, string $message): never
    {
        redirect($url, $message);
        exit;
    }
}
