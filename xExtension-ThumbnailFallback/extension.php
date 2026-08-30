<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/ThumbnailUrlNormalizer.php';

use ThumbnailFallback\ThumbnailUrlNormalizer;

final class ThumbnailFallbackExtension extends Minz_Extension
{
	#[\Override]
	public function init(): void
	{
		parent::init();

		$this->registerHook(Minz_HookType::EntryBeforeDisplay, [$this, 'normalizeEntry'], 10);
		$this->registerHook(Minz_HookType::EntryBeforeInsert, [$this, 'normalizeEntry'], 10);
		$this->registerHook(Minz_HookType::EntryBeforeUpdate, [$this, 'normalizeEntry'], 10);
	}

	public function normalizeEntry(FreshRSS_Entry $entry): FreshRSS_Entry
	{
		$link = $entry->link();
		$content = $entry->content();
		if (!is_string($link) || !is_string($content)) {
			return $entry;
		}

		$normalizedContent = ThumbnailUrlNormalizer::normalizeHtml($content, $link);
		$entry->_content($normalizedContent);

		if ($entry->thumbnail() === null) {
			$thumbnailUrl = ThumbnailUrlNormalizer::firstImageUrl($normalizedContent, $link);
			if ($thumbnailUrl === null) {
				$thumbnailUrl = $this->firstOgImageUrl($link);
			}
			if ($thumbnailUrl !== null) {
				$entry->_attribute('thumbnail', ['url' => $thumbnailUrl]);
			}
		}

		return $entry;
	}

	private function firstOgImageUrl(string $link): ?string
	{
		$response = FreshRSS_http_Util::httpGet($link, null, 'html', ['timeout' => 3]);
		if (($response['fail'] ?? true) || !is_string($response['body'] ?? null)) {
			return null;
		}

		$baseUrl = is_string($response['effective_url'] ?? null) && $response['effective_url'] !== ''
			? $response['effective_url']
			: $link;
		return ThumbnailUrlNormalizer::firstOgImageUrl($response['body'], $baseUrl);
	}
}
