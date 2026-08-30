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
			if ($thumbnailUrl !== null) {
				$entry->_attribute('thumbnail', ['url' => $thumbnailUrl]);
			}
		}

		return $entry;
	}
}
