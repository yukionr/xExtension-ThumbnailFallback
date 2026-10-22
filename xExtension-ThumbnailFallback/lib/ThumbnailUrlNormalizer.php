<?php

declare(strict_types=1);

namespace ThumbnailFallback;

final class ThumbnailUrlNormalizer
{
	public static function firstOgImageUrl(string $html, string $baseUrl): ?string
	{
		if (!self::isHttpUrl($baseUrl)) {
			return null;
		}

		if (preg_match_all('~<meta\b[^>]*>~is', $html, $matches) === false) {
			return null;
		}

		foreach ($matches[0] as $tag) {
			$property = self::attributeValue($tag, 'property') ?? self::attributeValue($tag, 'name');
			if ($property === null || strtolower(trim($property)) !== 'og:image') {
				continue;
			}

			$content = self::attributeValue($tag, 'content');
			if ($content === null) {
				continue;
			}

			$url = self::resolveUrl(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $baseUrl);
			if (self::isHttpUrl($url)) {
				return $url;
			}
		}

		return null;
	}

	public static function firstImageUrl(string $html, string $baseUrl): ?string
	{
		if (!self::isHttpUrl($baseUrl)) {
			return null;
		}

		if (preg_match(self::imageAttributePattern('img', 'src'), $html, $matches) !== 1) {
			return null;
		}

		$url = self::resolveUrl($matches[3], $baseUrl);
		return self::isHttpUrl($url) ? $url : null;
	}

	public static function normalizeHtml(string $html, string $baseUrl): string
	{
		if (!self::isHttpUrl($baseUrl)) {
			return $html;
		}

		$normalizedHtml = preg_replace_callback(
			self::imageAttributePattern('(?:img|source)', 'src'),
			static function (array $matches) use ($baseUrl): string {
				return $matches[1] . $matches[2]
					. self::resolveUrl($matches[3], $baseUrl)
					. $matches[2];
			},
			$html,
		);
		if ($normalizedHtml === null) {
			return $html;
		}
		$html = $normalizedHtml;

		$normalizedHtml = preg_replace_callback(
			self::imageAttributePattern('(?:img|source)', 'srcset'),
			static function (array $matches) use ($baseUrl): string {
				$candidates = preg_split('/\s*,\s*/', trim($matches[3])) ?: [];
				$normalized = [];

				foreach ($candidates as $candidate) {
					if (preg_match('/^(\S+)(\s+.*)?$/s', $candidate, $parts) !== 1) {
						$normalized[] = $candidate;
						continue;
					}
					$normalized[] = self::resolveUrl($parts[1], $baseUrl) . ($parts[2] ?? '');
				}

				return $matches[1] . $matches[2]
					. implode(', ', $normalized)
					. $matches[2];
			},
			$html,
		);
		return $normalizedHtml ?? $html;
	}

	private static function imageAttributePattern(string $tags, string $attribute): string
	{
		return '~(<' . $tags . '\b[^>]*\s' . preg_quote($attribute, '~')
			. '\s*=\s*)(["\'])(.*?)\2~is';
	}

	private static function resolveUrl(string $url, string $baseUrl): string
	{
		if ($url === '' || preg_match('~^(?:data|blob|mailto|javascript):~i', $url) === 1) {
			return $url;
		}
		if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) === 1) {
			return $url;
		}

		$base = parse_url($baseUrl);
		if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
			return $url;
		}

		$origin = $base['scheme'] . '://' . $base['host'];
		if (isset($base['port'])) {
			$origin .= ':' . $base['port'];
		}

		if (str_starts_with($url, '//')) {
			return $base['scheme'] . ':' . $url;
		}

		$fragment = '';
		$fragmentPosition = strpos($url, '#');
		if ($fragmentPosition !== false) {
			$fragment = substr($url, $fragmentPosition);
			$url = substr($url, 0, $fragmentPosition);
		}

		$query = '';
		$queryPosition = strpos($url, '?');
		if ($queryPosition !== false) {
			$query = substr($url, $queryPosition);
			$url = substr($url, 0, $queryPosition);
		}

		if ($url === '') {
			$path = $base['path'] ?? '/';
			if ($queryPosition === false && isset($base['query'])) {
				$query = '?' . $base['query'];
			}
		} elseif (str_starts_with($url, '/')) {
			$path = $url;
		} else {
			$basePath = $base['path'] ?? '/';
			$directory = str_ends_with($basePath, '/')
				? $basePath
				: substr($basePath, 0, strrpos($basePath, '/') + 1);
			$path = $directory . $url;
		}

		return $origin . self::normalizePath($path) . $query . $fragment;
	}

	private static function attributeValue(string $tag, string $attribute): ?string
	{
		$pattern = '~\s' . preg_quote($attribute, '~') . '\s*=\s*(["\'])(.*?)\1~is';
		return preg_match($pattern, $tag, $matches) === 1 ? $matches[2] : null;
	}

	private static function normalizePath(string $path): string
	{
		$segments = explode('/', $path);
		$normalized = [];

		foreach ($segments as $index => $segment) {
			if ($segment === '..') {
				if (count($normalized) > 1) {
					array_pop($normalized);
				}
			}
			if ($segment === '.' || $segment === '..') {
				if ($index === count($segments) - 1) {
					$normalized[] = '';
				}
				continue;
			}
			$normalized[] = $segment;
		}

		return implode('/', $normalized);
	}

	private static function isHttpUrl(string $url): bool
	{
		return preg_match('~^https?://[^/]+~i', $url) === 1;
	}
}
