<?php

namespace App\UI\Components\Presenters;

use App\Enums\PostType;
use App\Models\Post;

/**
 * Presenter responsible for generating clean and secure HTML representation
 * of post media (images, videos, and fallback badges).
 */
class PostMediaPresenter
{
    /**
     * Render an image preview container.
     */
    public function renderImagePreview(?string $url, string $title = '', int $maxHeight = 360): string
    {
        if ($url === null || trim($url) === '') {
            return $this->renderMissingMediaNotice('No se ha proporcionado imagen para esta publicación.');
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<div style="display:flex; justify-content:center; align-items:center; width:100%; background-color:#f1f5f9; border-radius:8px; padding:10px; overflow:hidden; border:1px solid #cbd5e1;">'
            . '<img src="' . $safeUrl . '" alt="' . $safeTitle . '" style="max-width:100%; max-height:' . $maxHeight . 'px; object-fit:contain; border-radius:6px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);" />'
            . '</div>';
    }

    /**
     * Render a HTML5 video player container.
     */
    public function renderVideoPreview(?string $url, int $maxHeight = 360): string
    {
        if ($url === null || trim($url) === '') {
            return $this->renderMissingMediaNotice('No se ha proporcionado video para esta publicación.');
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<div style="display:flex; justify-content:center; align-items:center; width:100%; background-color:#0f172a; border-radius:8px; padding:8px; overflow:hidden;">'
            . '<video src="' . $safeUrl . '" controls playsinline preload="metadata" style="max-width:100%; max-height:' . $maxHeight . 'px; border-radius:6px; outline:none; display:block;">'
            . 'Tu navegador no soporta el reproductor de video HTML5.'
            . '</video>'
            . '</div>';
    }

    /**
     * Render media for a given post according to its type.
     */
    public function renderMediaForPost(Post $post, int $maxHeight = 360): string
    {
        return match ($post->type) {
            PostType::IMAGE => $this->renderImagePreview($post->media_url, $post->title, $maxHeight),
            PostType::VIDEO => $this->renderVideoPreview($post->media_url, $maxHeight),
            PostType::TEXT => $this->renderTextNotice(),
        };
    }

    /**
     * Render notice when media file is missing.
     */
    public function renderMissingMediaNotice(string $message): string
    {
        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:30px; border:2px dashed #cbd5e1; border-radius:8px; background-color:#f8fafc; color:#64748b; text-align:center;">'
            . '<span style="font-size:32px; margin-bottom:8px;">⚠️</span>'
            . '<p style="margin:0; font-size:14px; font-weight:500;">' . $safeMsg . '</p>'
            . '</div>';
    }

    /**
     * Render visual badge for text-only posts.
     */
    public function renderTextNotice(): string
    {
        return '<div style="display:flex; align-items:center; gap:8px; padding:12px 16px; background-color:#f8fafc; border-left:4px solid #3b82f6; border-radius:4px; color:#334155;">'
            . '<span style="font-size:18px;">📝</span>'
            . '<span style="font-size:13px; font-weight:500;">Publicación de contenido textual (sin archivo multimedia adjunto).</span>'
            . '</div>';
    }
}

