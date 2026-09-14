<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class ResourceRepository
{
    public function find(int $rid): ?array
    {
        global $db;

        $query = $db->query(
            'SELECT r.*, c.name AS category_name, u.username AS author_name FROM '
            . TableNames::get('resources') . ' r LEFT JOIN '
            . TableNames::get('resource_categories') . ' c ON c.cid = r.cid LEFT JOIN '
            . TABLE_PREFIX . "users u ON u.uid = r.uid WHERE r.rid = {$rid} LIMIT 1",
        );

        return $db->fetch_array($query) ?: null;
    }

    public function latest(int $limit = 5): array
    {
        global $db;

        $items = [];
        $limit = max(1, min(50, $limit));
        $query = $db->query(
            'SELECT r.rid, r.title, r.summary, r.downloads, c.name AS category_name, u.username AS author_name FROM '
            . TableNames::get('resources') . ' r LEFT JOIN '
            . TableNames::get('resource_categories') . ' c ON c.cid = r.cid LEFT JOIN '
            . TABLE_PREFIX . "users u ON u.uid = r.uid WHERE r.status = 'published' ORDER BY r.updated_at DESC LIMIT {$limit}",
        );
        while ($item = $db->fetch_array($query)) {
            $items[] = $item;
        }

        return $items;
    }

    public function listPublished(string $sort): array
    {
        global $db;

        $order = $sort === 'reviews' ? 'r.rating_count DESC, r.updated_at DESC' : 'r.updated_at DESC';
        $items = [];
        $query = $db->query(
            'SELECT r.*, c.name AS category_name, u.username AS author_name FROM '
            . TableNames::get('resources') . ' r LEFT JOIN '
            . TableNames::get('resource_categories') . ' c ON c.cid = r.cid LEFT JOIN '
            . TABLE_PREFIX . "users u ON u.uid = r.uid WHERE r.status = 'published' ORDER BY {$order} LIMIT 50",
        );
        while ($item = $db->fetch_array($query)) {
            $items[] = $item;
        }

        return $items;
    }

    public function versions(int $rid): array
    {
        global $db;

        $versions = [];
        $query = $db->simple_select('javaroot_resource_versions', '*', 'rid = ' . $rid, ['order_by' => 'created_at', 'order_dir' => 'DESC']);
        while ($version = $db->fetch_array($query)) {
            $version['files'] = [];
            $files = $db->simple_select('javaroot_resource_files', '*', 'vid = ' . (int)$version['vid'] . " AND status = 'published'");
            while ($file = $db->fetch_array($files)) {
                $version['files'][] = $file;
            }
            $versions[] = $version;
        }

        return $versions;
    }

    public function reviews(int $rid): array
    {
        global $db;

        $items = [];
        $query = $db->query(
            'SELECT rr.*, u.username FROM ' . TableNames::get('resource_reviews')
            . ' rr LEFT JOIN ' . TABLE_PREFIX . "users u ON u.uid = rr.uid WHERE rr.rid = {$rid}"
            . " AND rr.status = 'published' ORDER BY rr.created_at DESC",
        );
        while ($review = $db->fetch_array($query)) {
            $items[] = $review;
        }

        return $items;
    }

    public function isFavorite(int $rid, int $uid): bool
    {
        global $db;

        $query = $db->simple_select('javaroot_resource_favorites', 'rid', 'rid = ' . $rid . ' AND uid = ' . $uid, ['limit' => 1]);

        return $db->num_rows($query) > 0;
    }

    public function create(array $data): int
    {
        global $db;

        return (int)$db->insert_query('javaroot_resources', $data);
    }

    public function createVersion(array $data): int
    {
        global $db;

        return (int)$db->insert_query('javaroot_resource_versions', $data);
    }

    public function addFile(array $data): int
    {
        global $db;

        return (int)$db->insert_query('javaroot_resource_files', $data);
    }

    public function deleteCreatedResource(int $rid, int $vid): void
    {
        global $db;

        if ($vid > 0) {
            $db->delete_query('javaroot_resource_files', 'vid = ' . $vid);
            $db->delete_query('javaroot_resource_versions', 'vid = ' . $vid);
        }
        if ($rid > 0) {
            $db->delete_query('javaroot_resources', 'rid = ' . $rid);
        }
    }

    public function versionBelongsTo(int $rid, int $vid): bool
    {
        global $db;

        $query = $db->simple_select('javaroot_resource_versions', 'vid', 'vid = ' . $vid . ' AND rid = ' . $rid, ['limit' => 1]);

        return $db->num_rows($query) > 0;
    }

    public function upsertReview(int $rid, int $uid, int $rating, string $message): void
    {
        global $db;

        $query = $db->simple_select('javaroot_resource_reviews', 'review_id', 'rid = ' . $rid . ' AND uid = ' . $uid, ['limit' => 1]);
        if ($db->num_rows($query)) {
            $reviewId = (int)$db->fetch_field($query, 'review_id');
            $db->update_query('javaroot_resource_reviews', [
                'rating' => $rating,
                'message' => $message,
                'updated_at' => TIME_NOW,
            ], 'review_id = ' . $reviewId);
        } else {
            $db->insert_query('javaroot_resource_reviews', [
                'rid' => $rid,
                'uid' => $uid,
                'rating' => $rating,
                'message' => $message,
                'author_reply' => '',
                'status' => 'published',
                'created_at' => TIME_NOW,
                'updated_at' => TIME_NOW,
            ]);
        }

        $ratingQuery = $db->simple_select('javaroot_resource_reviews', 'rating', $this->condition($rid, "status = 'published'"));
        $sum = 0;
        $count = 0;
        while ($row = $db->fetch_array($ratingQuery)) {
            $sum += (int)$row['rating'];
            $count++;
        }
        $db->update_query('javaroot_resources', ['rating_sum' => $sum, 'rating_count' => $count], 'rid = ' . $rid);
    }

    public function toggleFavorite(int $rid, int $uid): void
    {
        global $db;

        $condition = 'rid = ' . $rid . ' AND uid = ' . $uid;
        $query = $db->simple_select('javaroot_resource_favorites', 'rid', $condition, ['limit' => 1]);
        if ($db->num_rows($query)) {
            $db->delete_query('javaroot_resource_favorites', $condition);
            return;
        }

        $db->insert_query('javaroot_resource_favorites', ['rid' => $rid, 'uid' => $uid, 'created_at' => TIME_NOW]);
    }

    public function addReport(int $rid, int $uid, string $reason): void
    {
        global $db;

        $db->insert_query('javaroot_resource_reports', [
            'rid' => $rid,
            'uid' => $uid,
            'reason' => $reason,
            'status' => 'open',
            'created_at' => TIME_NOW,
        ]);
    }

    public function moderate(int $rid, string $status, int $uid, string $details = ''): void
    {
        global $db;

        $db->update_query('javaroot_resources', ['status' => $status, 'updated_at' => TIME_NOW], 'rid = ' . $rid);
        $versionStatus = $status === 'published' ? 'published' : 'hidden';
        $db->update_query('javaroot_resource_versions', ['status' => $versionStatus], 'rid = ' . $rid);
        $versions = TableNames::get('resource_versions');
        if ($status === 'published') {
            $db->update_query('javaroot_resource_files', ['status' => 'published'], "security_status = 'ready' AND vid IN (SELECT vid FROM {$versions} WHERE rid = {$rid})");
        } else {
            $db->update_query('javaroot_resource_files', ['status' => 'hidden'], "vid IN (SELECT vid FROM {$versions} WHERE rid = {$rid})");
        }
        $db->insert_query('javaroot_moderation_log', [
            'uid' => $uid,
            'rid' => $rid,
            'action' => $status,
            'details' => $details,
            'created_at' => TIME_NOW,
        ]);
    }

    public function downloadableFile(int $fid): ?array
    {
        global $db;

        $files = TableNames::get('resource_files');
        $versions = TableNames::get('resource_versions');
        $resources = TableNames::get('resources');
        $query = $db->query(
            "SELECT f.*, v.rid, r.status AS resource_status FROM {$files} f INNER JOIN {$versions} v ON v.vid = f.vid"
            . " INNER JOIN {$resources} r ON r.rid = v.rid WHERE f.fid = {$fid} AND f.status = 'published'"
            . " AND v.status = 'published' AND r.status = 'published' LIMIT 1",
        );

        return $db->fetch_array($query) ?: null;
    }

    public function recordDownload(int $rid, int $fid, int $uid): void
    {
        global $db;

        $db->update_query('javaroot_resources', ['downloads' => 'downloads + 1'], 'rid = ' . $rid, '', true);
        $db->insert_query('javaroot_resource_downloads', ['fid' => $fid, 'uid' => $uid, 'created_at' => TIME_NOW]);
    }

    public function reports(): array
    {
        global $db;

        return $this->fetchAll($db->query(
            'SELECT rr.report_id, rr.reason, rr.status, r.rid, r.title, u.username AS reporter_name FROM '
            . TableNames::get('resource_reports') . ' rr LEFT JOIN ' . TableNames::get('resources')
            . ' r ON r.rid = rr.rid LEFT JOIN ' . TABLE_PREFIX . "users u ON u.uid = rr.uid WHERE rr.status = 'open' ORDER BY rr.created_at DESC",
        ));
    }

    public function filesForAdmin(): array
    {
        global $db;

        return $this->fetchAll($db->query(
            'SELECT f.filename, f.size, f.security_status, f.status, v.version, r.title FROM '
            . TableNames::get('resource_files') . ' f INNER JOIN ' . TableNames::get('resource_versions')
            . ' v ON v.vid = f.vid INNER JOIN ' . TableNames::get('resources')
            . ' r ON r.rid = v.rid ORDER BY f.fid DESC LIMIT 100',
        ));
    }

    public function users(): array
    {
        global $db;

        return $this->fetchAll($db->query(
            'SELECT u.username, COUNT(r.rid) AS resource_count FROM ' . TABLE_PREFIX . 'users u INNER JOIN '
            . TableNames::get('resources') . ' r ON r.uid = u.uid GROUP BY u.uid, u.username ORDER BY resource_count DESC LIMIT 100',
        ));
    }

    public function pending(): array
    {
        global $db;

        return $this->fetchAll($db->query(
            'SELECT r.rid, r.title, r.status, u.username AS author_name, c.name AS category_name FROM '
            . TableNames::get('resources') . ' r LEFT JOIN ' . TABLE_PREFIX . 'users u ON u.uid = r.uid LEFT JOIN '
            . TableNames::get('resource_categories') . " c ON c.cid = r.cid WHERE r.status IN ('pending', 'hidden') ORDER BY r.updated_at DESC",
        ));
    }

    public function closeReport(int $reportId, string $status): void
    {
        global $db;

        $db->update_query('javaroot_resource_reports', ['status' => $status], 'report_id = ' . $reportId);
    }

    private function condition(int $rid, string $extra): string
    {
        return 'rid = ' . $rid . ' AND ' . $extra;
    }

    private function fetchAll(mixed $query): array
    {
        global $db;

        $rows = [];
        while ($row = $db->fetch_array($query)) {
            $rows[] = $row;
        }

        return $rows;
    }
}
