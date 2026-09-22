<?php

namespace App\UI\Screens\Member\Concerns;

use App\Enums\PostType;
use App\Models\Post;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Concern responsible for processing media uploads attached to posts.
 */
trait HandlesPostMediaUpload
{
    /**
     * Process post media upload and return resolved media URL and MIME type.
     *
     * @param array<string, mixed> $params
     * @param string $postType
     * @param Post|null $existingPost
     * @return array{media_url: string|null, media_mime: string|null}
     * @throws InvalidArgumentException
     */
    protected function resolvePostMedia(array $params, string $postType, ?Post $existingPost = null): array
    {
        $uploader = UI::uploader('post_uploader')
            ->media()
            ->multiple(false)
            ->maxFiles(1);

        $tempId = $uploader->getTempId($params);
        $mediaMime = null;

        if ($tempId !== null) {
            /** @var object{mime_type: string}|null $tempRecord */
            $tempRecord = DB::table('temporary_uploads')
                ->where('id', $tempId)
                ->select(['mime_type'])
                ->first();

            if ($tempRecord !== null) {
                $mediaMime = $tempRecord->mime_type;
            }
        }

        $oldFilename = null;
        if ($existingPost !== null && is_string($existingPost->media_url) && $existingPost->media_url !== '') {
            $parsedPath = parse_url($existingPost->media_url, PHP_URL_PATH);
            $oldFilename = is_string($parsedPath) ? basename($parsedPath) : null;
        }

        $confirmed = $uploader->confirm($params, 'posts', $oldFilename);
        $confirmedFilename = is_array($confirmed) ? ($confirmed[0] ?? null) : $confirmed;

        $mediaUrl = null;
        if (is_string($confirmedFilename) && $confirmedFilename !== '') {
            $mediaUrl = UploadService::fileUrl("uploads/posts/{$confirmedFilename}");
            if ($mediaMime === null) {
                $mediaMime = $this->resolveMimeFromFilename($confirmedFilename);
            }
        } elseif ($existingPost !== null && $existingPost->media_url !== null) {
            $mediaUrl = $existingPost->media_url;
            $mediaMime = $existingPost->media_mime;
        } elseif (isset($params['post_media_url']) && is_string($params['post_media_url']) && trim($params['post_media_url']) !== '') {
            $mediaUrl = trim($params['post_media_url']);
            $mediaMime = $this->resolveMimeFromFilename($mediaUrl);
        }

        // Validate required media for image/video types
        $isMedia = $postType === PostType::IMAGE->value || $postType === PostType::VIDEO->value;
        if ($isMedia && ($mediaUrl === null || $mediaUrl === '')) {
            $typeLabel = $postType === PostType::IMAGE->value ? 'imagen' : 'video';
            throw new InvalidArgumentException("Para publicaciones de tipo {$typeLabel}, debes subir un archivo multimedia utilizando el uploader.");
        }

        return [
            'media_url' => $mediaUrl,
            'media_mime' => $mediaMime,
        ];
    }

    /**
     * Infer MIME type from filename or path extension.
     */
    protected function resolveMimeFromFilename(string $filename): ?string
    {
        $path = (string) parse_url($filename, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            default => null,
        };
    }
}

