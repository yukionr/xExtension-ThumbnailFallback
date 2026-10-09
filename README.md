# FreshRSS Thumbnail Fallback

A FreshRSS extension that resolves relative image URLs in article content and
fills missing thumbnails from content images or Open Graph metadata.

Some feeds include image paths such as `/images/cover.jpg` instead of absolute
URLs. When FreshRSS and the publisher use different origins, those images can
point to the wrong server. This extension resolves them against the article URL
without modifying FreshRSS itself.

## Installation

1. Download or clone this repository.
2. Copy `xExtension-ThumbnailFallback/` into your FreshRSS `extensions/` directory.
3. Enable **Thumbnail URL Fallback** in FreshRSS under **Settings → Extensions**.

The resulting directory layout should be:

```text
FreshRSS/
└── extensions/
    └── xExtension-ThumbnailFallback/
        ├── extension.php
        ├── metadata.json
        └── lib/
            └── ThumbnailUrlNormalizer.php
```

The extension uses FreshRSS's `EntryBeforeDisplay`, `EntryBeforeInsert`, and
`EntryBeforeUpdate` hooks and `FreshRSS_http_Util::httpGet()` API. Compatibility
with specific FreshRSS releases is not established by the standalone tests.

For automated deployments, including Kubernetes, pin the repository commit and
verify the archive's SHA-256 before copying the extension directory.

## How it works

On article insertion, update, and display, the extension:

1. Resolves relative URLs in quoted `src` and `srcset` attributes on `<img>` and
   `<source>` elements against the article URL.
2. Preserves any thumbnail already returned by FreshRSS, including images from
   feed enclosures.
3. If no thumbnail exists, uses the first `<img>` element with a quoted `src`
   when its resolved URL is HTTP(S).
4. If that produces no usable URL, fetches the article page with a three-second
   timeout and uses the first usable HTTP(S) `og:image` URL. Relative metadata
   URLs are resolved against the final article URL after redirects.

For example, an article at `https://publisher.example/articles/story.html` with
`<img src="../images/cover.jpg">` becomes
`<img src="https://publisher.example/images/cover.jpg">`.

Absolute URLs are preserved. URL schemes such as `data:`, `blob:`, and `mailto:`
are left unchanged and are not selected as thumbnail URLs. Display hooks also
apply the fallback to existing articles; reimporting the feed is unnecessary.

### Limits and network behavior

- The extension stores thumbnail URLs, not image files. It does not download or
  cache image files itself; images are loaded through FreshRSS's normal display
  behavior.
- Open Graph fallback makes a server-side request to the article page. Failed
  HTTP responses leave the thumbnail unset. Entries that still lack a thumbnail
  can trigger another request on a later display or update; this extension does
  not provide a metadata cache.
- HTML matching uses regular expressions and expects quoted attributes. It is
  not a full HTML parser or browser URL resolver; complex markup, `srcset` URLs
  containing commas, CSS backgrounds, and `<base>` elements are not supported
  comprehensively.
- Content fallback examines the first matching `<img src>` only. It does not
  search later images if that URL is unusable, or select thumbnails from
  `srcset` alone.

## Development

With PHP available, run these commands from the repository root:

```sh
php tests/run.php
php -l tests/run.php
php -l xExtension-ThumbnailFallback/extension.php
php -l xExtension-ThumbnailFallback/lib/ThumbnailUrlNormalizer.php
```

The standalone test runner prints `ok` on success. It covers relative URLs,
`srcset`, preservation of absolute and data URLs, hook registration, content
thumbnails, existing thumbnails, and Open Graph fallback. FreshRSS classes and
HTTP responses are stubbed; the suite does not verify a live FreshRSS instance
or real network requests.

See [Contributing](CONTRIBUTING.md) for reporting issues and submitting changes.

## License

[GNU Affero General Public License v3.0 or later](LICENSE).
