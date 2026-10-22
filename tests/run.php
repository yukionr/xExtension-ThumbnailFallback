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

$baseUrl = 'https://publisher.example.test/articles/story.html?edition=1';
foreach ([
	'/' => '/',
	'/images//cover.jpg' => '/images//cover.jpg',
	'./' => '/articles/',
	'.' => '/articles/',
	'..' => '/',
	'../../..' => '/',
	'/images/.' => '/images/',
	'/images/..' => '/',
	'/images//../cover.jpg' => '/images/cover.jpg',
	'#cover' => '/articles/story.html?edition=1#cover',
	'?size=large#cover' => '/articles/story.html?size=large#cover',
	'?' => '/articles/story.html?',
	'cover.jpg' => '/articles/cover.jpg',
] as $relative => $path) {
	$expected = 'https://publisher.example.test' . $path;
	assertSameValue(
		'<img src="' . $expected . '">',
		ThumbnailUrlNormalizer::normalizeHtml('<img src="' . $relative . '">', $baseUrl),
		'Resolve path and query components: ' . $relative,
	);
	assertSameValue(
		$expected,
		ThumbnailUrlNormalizer::firstOgImageUrl('<meta property="og:image" content="' . $relative . '">', $baseUrl) ?? '',
		'Resolve Open Graph URL components: ' . $relative,
	);
}

foreach (['img', 'source'] as $tag) {
	$html = '<' . $tag . ' data-src="/lazy.jpg" data-srcset="/lazy.jpg 1x">';
	assertSameValue($html, ThumbnailUrlNormalizer::normalizeHtml($html, $baseUrl), 'Preserve custom image attributes');
	assertSameValue(
		'<' . $tag . ' src="https://publisher.example.test/cover.jpg" data-src="/lazy.jpg">',
		ThumbnailUrlNormalizer::normalizeHtml('<' . $tag . ' src="/cover.jpg" data-src="/lazy.jpg">', $baseUrl),
		'Normalize src even when followed by data-src',
	);
	assertSameValue(
		'<' . $tag . ' srcset="https://publisher.example.test/small.jpg 1x, https://publisher.example.test/large.jpg 2x" data-srcset="/lazy.jpg 1x">',
		ThumbnailUrlNormalizer::normalizeHtml('<' . $tag . ' srcset="/small.jpg 1x, /large.jpg 2x" data-srcset="/lazy.jpg 1x">', $baseUrl),
		'Normalize srcset even when followed by data-srcset',
	);
}
if (ThumbnailUrlNormalizer::firstImageUrl('<img data-src="/lazy.jpg">', $baseUrl) !== null) {
	throw new RuntimeException('Do not select data-src as a content thumbnail');
}
assertSameValue(
	'https://publisher.example.test/cover.jpg',
	ThumbnailUrlNormalizer::firstImageUrl('<img src="/cover.jpg" data-src="/lazy.jpg">', $baseUrl) ?? '',
	'Select src instead of a later data-src attribute',
);
assertSameValue(
	"<IMG SRC = 'https://publisher.example.test/cover.jpg'>",
	ThumbnailUrlNormalizer::normalizeHtml("<IMG SRC = '/cover.jpg'>", $baseUrl),
	'Preserve support for single quotes, whitespace, and uppercase attributes',
);
if (ThumbnailUrlNormalizer::firstImageUrl('<img src="data:image/gif;base64,AAAA"><img src="/cover.jpg">', $baseUrl) !== null) {
	throw new RuntimeException('Preserve the first matching image selection policy');
}

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

$lazyEntry = new FreshRSS_Entry($baseUrl, '<img data-src="/lazy.jpg">');
$extension->normalizeEntry($lazyEntry);
if ($lazyEntry->thumbnail() !== ['url' => 'https://publisher.example.test/article-image.jpg']
	|| FreshRSS_http_Util::$calls !== 2) {
	throw new RuntimeException('Use Open Graph fallback when only data-src is present');
}

echo "ok\n";
