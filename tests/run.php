<?php

declare(strict_types=1);

require_once __DIR__ . '/../xExtension-ThumbnailFallback/lib/ThumbnailUrlNormalizer.php';

use ThumbnailFallback\ThumbnailUrlNormalizer;

function assertSameValue(string $expected, string $actual, string $message): void
{
	if ($expected !== $actual) {
		throw new RuntimeException($message . "\nexpected: " . $expected . "\nactual:   " . $actual);
	}
}

assertSameValue(
	'<p><img src="https://news.example.test/wp-content/uploads/2026/08/article-image.jpg"></p>',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<p><img src="/wp-content/uploads/2026/08/article-image.jpg"></p>',
		'https://news.example.test/2026/08/article.html',
	),
	'Resolve relative src URLs against the article origin',
);

assertSameValue(
	'<img src="https://cdn.example.test/articles/article-image.jpg">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="https://cdn.example.test/articles/article-image.jpg">',
		'https://publisher.example.test/articles/123',
	),
	'Preserve absolute URLs',
);

assertSameValue(
	'<img src="https://publisher.example.test/2026/images/cover.jpg" srcset="https://publisher.example.test/2026/images/small.jpg 1x, https://cdn.example.test/large.jpg 2x">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="../images/cover.jpg" srcset="../images/small.jpg 1x, https://cdn.example.test/large.jpg 2x">',
		'https://publisher.example.test/2026/08/article.html',
	),
	'Resolve relative URLs in srcset',
);

assertSameValue(
	'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP">',
		'https://publisher.example.test/2026/08/article.html',
	),
	'Preserve embedded data URLs',
);

final class Minz_HookType
{
	public const EntryBeforeDisplay = 'entry_before_display';
	public const EntryBeforeInsert = 'entry_before_insert';
	public const EntryBeforeUpdate = 'entry_before_update';
}

class Minz_Extension
{
	public array $registeredHooks = [];

	public function init(): void
	{
	}

	public function registerHook(string $hook, array $callback, int $priority): void
	{
		$this->registeredHooks[] = [$hook, $callback, $priority];
	}
}

final class FreshRSS_http_Util
{
	public static int $calls = 0;

	/** @return array{body:string,effective_url:string,redirect_count:int,fail:bool,status:int,error:string} */
	public static function httpGet(string $url, ?string $cachePath = null, string $type = 'html', array $attributes = []): array
	{
		self::$calls++;
		return [
			'body' => '<meta property="og:image" content="/article-image.jpg">',
			'effective_url' => $url,
			'redirect_count' => 0,
			'fail' => false,
			'status' => 200,
			'error' => '',
		];
	}
}

final class FreshRSS_Entry
{
	/** @var array<string,mixed> */
	private array $attributes = [];

	public function __construct(private string $link, private string $content, ?array $thumbnail = null)
	{
		if ($thumbnail !== null) {
			$this->attributes['thumbnail'] = $thumbnail;
		}
	}

	public function link(): string
	{
		return $this->link;
	}

	public function content(): string
	{
		return $this->content;
	}

	public function _content(string $content): void
	{
		$this->content = $content;
	}

	public function thumbnail(): ?array
	{
		$thumbnail = $this->attributes['thumbnail'] ?? null;
		return is_array($thumbnail) ? $thumbnail : null;
	}

	public function _attribute(string $key, $value = null): void
	{
		if ($value === null) {
			unset($this->attributes[$key]);
			return;
		}
		$this->attributes[$key] = $value;
	}
}

require_once __DIR__ . '/../xExtension-ThumbnailFallback/extension.php';

$extension = new ThumbnailFallbackExtension();
$extension->init();
if (count($extension->registeredHooks) !== 3) {
	throw new RuntimeException('Expected all three FreshRSS entry hooks to be registered');
}

$entry = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article.html',
	'<img src="/wp-content/uploads/2026/08/article-image.jpg">',
);
$extension->normalizeEntry($entry);
assertSameValue(
	'<img src="https://publisher.example.test/wp-content/uploads/2026/08/article-image.jpg">',
	$entry->content(),
	'Normalize article content through the FreshRSS entry hook',
);
$thumbnail = $entry->thumbnail();
if ($thumbnail !== ['url' => 'https://publisher.example.test/wp-content/uploads/2026/08/article-image.jpg']) {
	throw new RuntimeException(
		"Expected the FreshRSS entry hook to set the first content image as the thumbnail\nactual: " . var_export($thumbnail, true),
	);
}

$entryWithThumbnail = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article.html',
	'<img src="/article-image.jpg">',
	['url' => 'https://cdn.example.test/provided-image.jpg'],
);
$extension->normalizeEntry($entryWithThumbnail);
if ($entryWithThumbnail->thumbnail() !== ['url' => 'https://cdn.example.test/provided-image.jpg']) {
	throw new RuntimeException('Expected the existing FreshRSS thumbnail to remain unchanged');
}

$entryWithOgImage = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article-without-body-image.html',
	'<p>An article without content images</p>',
);
$extension->normalizeEntry($entryWithOgImage);
if ($entryWithOgImage->thumbnail() !== ['url' => 'https://publisher.example.test/article-image.jpg']) {
	throw new RuntimeException('Expected og:image to provide the thumbnail when content has no image');
}
if (FreshRSS_http_Util::$calls !== 1) {
	throw new RuntimeException('Expected article pages to be fetched only when a thumbnail is missing');
}

echo "ok\n";
