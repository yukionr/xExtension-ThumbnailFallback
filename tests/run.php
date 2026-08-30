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
	'相対 src を記事 origin 基準で補正する',
);

assertSameValue(
	'<img src="https://cdn.example.test/articles/article-image.jpg">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="https://cdn.example.test/articles/article-image.jpg">',
		'https://publisher.example.test/articles/123',
	),
	'絶対 URL は変更しない',
);

assertSameValue(
	'<img src="https://publisher.example.test/2026/images/cover.jpg" srcset="https://publisher.example.test/2026/images/small.jpg 1x, https://cdn.example.test/large.jpg 2x">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="../images/cover.jpg" srcset="../images/small.jpg 1x, https://cdn.example.test/large.jpg 2x">',
		'https://publisher.example.test/2026/08/article.html',
	),
	'srcset の相対 URL も補正する',
);

assertSameValue(
	'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP">',
	ThumbnailUrlNormalizer::normalizeHtml(
		'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP">',
		'https://publisher.example.test/2026/08/article.html',
	),
	'埋め込みデータ URL は変更しない',
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
	throw new RuntimeException('FreshRSS の3つの entry hook が登録されていない');
}

$entry = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article.html',
	'<img src="/wp-content/uploads/2026/08/article-image.jpg">',
);
$extension->normalizeEntry($entry);
assertSameValue(
	'<img src="https://publisher.example.test/wp-content/uploads/2026/08/article-image.jpg">',
	$entry->content(),
	'FreshRSS entry hook が記事本文を補正する',
);
$thumbnail = $entry->thumbnail();
if ($thumbnail !== ['url' => 'https://publisher.example.test/wp-content/uploads/2026/08/article-image.jpg']) {
	throw new RuntimeException(
		"FreshRSS entry hook が本文先頭画像をサムネイル属性へ設定する\nactual: " . var_export($thumbnail, true),
	);
}

$entryWithThumbnail = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article.html',
	'<img src="/article-image.jpg">',
	['url' => 'https://cdn.example.test/provided-image.jpg'],
);
$extension->normalizeEntry($entryWithThumbnail);
if ($entryWithThumbnail->thumbnail() !== ['url' => 'https://cdn.example.test/provided-image.jpg']) {
	throw new RuntimeException('既存の FreshRSS サムネイル属性を上書きする');
}

$entryWithOgImage = new FreshRSS_Entry(
	'https://publisher.example.test/2026/08/article-without-body-image.html',
	'<p>本文に画像を含まない記事</p>',
);
$extension->normalizeEntry($entryWithOgImage);
if ($entryWithOgImage->thumbnail() !== ['url' => 'https://publisher.example.test/article-image.jpg']) {
	throw new RuntimeException('本文画像がない記事の og:image をサムネイル属性へ設定する');
}
if (FreshRSS_http_Util::$calls !== 1) {
	throw new RuntimeException('サムネイルがない記事だけ記事ページを取得する');
}

echo "ok\n";
