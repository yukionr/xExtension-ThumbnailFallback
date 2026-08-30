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

		$entry->_content(ThumbnailUrlNormalizer::normalizeHtml($content, $link));
		return $entry;
	}
}
