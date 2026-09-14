<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Presentation\Admin;

use JavaRoot\Resources\Exception\AuthorizationException;
use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Service\ResourceService;

final class ResourceAdminController
{
    public function __construct(
        private ResourceService $resources,
    ) {
    }

    public function handle(): void
    {
        global $mybb, $lang, $page;

        try {
            $section = $mybb->get_input('action') ?: 'publications';
            if ($mybb->request_method === 'post') {
                $this->handlePost($section);
            }

            $page->output_header($lang->javaroot_resource_moderation);
            $this->navigation();
            match ($section) {
                'reports' => $this->reports(),
                'categories' => $this->categories(),
                'files' => $this->files(),
                'users' => $this->users(),
                default => $this->publications(),
            };
            $page->output_footer();
        } catch (AuthorizationException) {
            error_no_permission();
        } catch (InvalidRequestException) {
            flash_message($lang->javaroot_invalid_request, 'error');
            admin_redirect('index.php?module=config-javaroot_resources');
        }
    }

    private function handlePost(string $section): void
    {
        global $mybb, $lang;

        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            flash_message($lang->invalid_post_verify_key2, 'error');
            admin_redirect('index.php?module=config-javaroot_resources');
        }

        if ($section === 'reports') {
            $this->resources->closeReport(
                $mybb->get_input('report_id', \MyBB::INPUT_INT),
                (string)$mybb->get_input('report_status'),
            );
            flash_message($lang->javaroot_resource_saved, 'success');
            admin_redirect('index.php?module=config-javaroot_resources&action=reports');
        }

        if ($section === 'categories') {
            $this->resources->createCategory((string)$mybb->get_input('category_name'), (string)$mybb->get_input('category_slug'));
            flash_message($lang->javaroot_resource_saved, 'success');
            admin_redirect('index.php?module=config-javaroot_resources&action=categories');
        }

        $this->resources->moderate(
            $mybb->get_input('rid', \MyBB::INPUT_INT),
            (string)$mybb->get_input('status'),
            'Admin CP',
        );
        flash_message($lang->javaroot_resource_saved, 'success');
        admin_redirect('index.php?module=config-javaroot_resources');
    }

    private function navigation(): void
    {
        global $lang;

        echo '<div class="float_right"><a href="index.php?module=config-javaroot_resources&action=publications">' . $lang->javaroot_resource_publications . '</a> | <a href="index.php?module=config-javaroot_resources&action=reports">' . $lang->javaroot_resource_reports . '</a> | <a href="index.php?module=config-javaroot_resources&action=categories">' . $lang->javaroot_resource_categories . '</a> | <a href="index.php?module=config-javaroot_resources&action=files">' . $lang->javaroot_resource_files . '</a> | <a href="index.php?module=config-javaroot_resources&action=users">' . $lang->javaroot_resource_users . '</a></div><div class="float_clear"></div><br />';
    }

    private function reports(): void
    {
        global $lang, $mybb;

        $table = new \Table();
        foreach (['javaroot_resource_title', 'javaroot_resource_reporter', 'javaroot_resource_reason', 'controls'] as $header) {
            $table->construct_header($header === 'controls' ? $lang->controls : $lang->{$header});
        }
        foreach ($this->resources->adminReports() as $report) {
            $table->construct_cell(htmlspecialchars_uni($report['title']));
            $table->construct_cell(htmlspecialchars_uni($report['reporter_name']));
            $table->construct_cell(htmlspecialchars_uni($report['reason']));
            $form = '<form method="post" action="index.php?module=config-javaroot_resources&action=reports"><input type="hidden" name="my_post_key" value="' . $mybb->post_code . '"><input type="hidden" name="report_id" value="' . (int)$report['report_id'] . '"><button type="submit" name="report_status" value="closed">' . $lang->javaroot_resource_close . '</button></form>';
            $table->construct_cell($form, ['class' => 'align_center']);
            $table->construct_row();
        }
        if ($table->num_rows() === 0) {
            $table->construct_cell($lang->javaroot_resource_no_reports, ['colspan' => 4]);
            $table->construct_row();
        }
        $table->output($lang->javaroot_resource_reports);
    }

    private function categories(): void
    {
        global $lang, $mybb;

        echo '<form method="post" action="index.php?module=config-javaroot_resources&action=categories"><input type="hidden" name="my_post_key" value="' . $mybb->post_code . '"><input type="text" name="category_name" placeholder="' . $lang->javaroot_resource_category_name . '" required> <input type="text" name="category_slug" placeholder="' . $lang->javaroot_resource_category_slug . '" pattern="[a-z0-9-]+" required> <button type="submit">' . $lang->javaroot_resource_add_category . '</button></form><br />';
        $table = new \Table();
        $table->construct_header($lang->javaroot_resource_category_name);
        $table->construct_header($lang->javaroot_resource_category_slug);
        foreach ($this->resources->categories() as $category) {
            $table->construct_cell(htmlspecialchars_uni($category['name']));
            $table->construct_cell(htmlspecialchars_uni($category['slug']));
            $table->construct_row();
        }
        $table->output($lang->javaroot_resource_categories);
    }

    private function files(): void
    {
        global $lang;

        $table = new \Table();
        foreach (['javaroot_resource_title', 'javaroot_resource_file', 'javaroot_resource_status', 'javaroot_resource_security'] as $header) {
            $table->construct_header($lang->{$header});
        }
        foreach ($this->resources->adminFiles() as $file) {
            $table->construct_cell(htmlspecialchars_uni($file['title'] . ' ' . $file['version']));
            $table->construct_cell(htmlspecialchars_uni($file['filename']));
            $table->construct_cell(htmlspecialchars_uni($file['status']));
            $table->construct_cell(htmlspecialchars_uni($file['security_status']));
            $table->construct_row();
        }
        $table->output($lang->javaroot_resource_files);
    }

    private function users(): void
    {
        global $lang;

        $table = new \Table();
        $table->construct_header($lang->javaroot_resource_author);
        $table->construct_header($lang->javaroot_resources);
        foreach ($this->resources->adminUsers() as $user) {
            $table->construct_cell(htmlspecialchars_uni($user['username']));
            $table->construct_cell((int)$user['resource_count']);
            $table->construct_row();
        }
        $table->output($lang->javaroot_resource_users);
    }

    private function publications(): void
    {
        global $lang, $mybb;

        $table = new \Table();
        $table->construct_header($lang->javaroot_resource_title);
        $table->construct_header($lang->javaroot_resource_author);
        $table->construct_header($lang->javaroot_resource_status);
        $table->construct_header($lang->controls, ['class' => 'align_center']);
        foreach ($this->resources->adminPending() as $resource) {
            $title = htmlspecialchars_uni($resource['title']);
            $author = htmlspecialchars_uni($resource['author_name']);
            $status = htmlspecialchars_uni($resource['status']);
            $table->construct_cell('<a href="../resources.php?rid=' . (int)$resource['rid'] . '" target="_blank">' . $title . '</a>');
            $table->construct_cell($author);
            $table->construct_cell($status);
            $controls = '<form method="post" action="index.php?module=config-javaroot_resources"><input type="hidden" name="my_post_key" value="' . $mybb->post_code . '"><input type="hidden" name="rid" value="' . (int)$resource['rid'] . '"><button type="submit" name="status" value="published">' . $lang->javaroot_resource_approve . '</button> <button type="submit" name="status" value="hidden">' . $lang->javaroot_resource_hide . '</button></form>';
            $table->construct_cell($controls, ['class' => 'align_center']);
            $table->construct_row();
        }
        if ($table->num_rows() === 0) {
            $table->construct_cell($lang->javaroot_resource_no_pending, ['colspan' => 4]);
            $table->construct_row();
        }
        $table->output($lang->javaroot_resource_moderation);
    }
}
