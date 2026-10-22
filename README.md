# FreshRSS Thumbnail Fallback

A FreshRSS extension that resolves relative image URLs in article content and
fills missing thumbnails from content images or Open Graph metadata.

Some feeds include image paths such as `/images/cover.jpg` instead of absolute
URLs. When FreshRSS and the publisher use different origins, those images can
point to the wrong server. This extension resolves them against the article URL
without modifying FreshRSS itself.

## Installation

The extension requires FreshRSS's `EntryBeforeDisplay`, `EntryBeforeInsert`, and
`EntryBeforeUpdate` hooks and `FreshRSS_http_Util::httpGet()` API. The standalone
tests do not establish compatibility with specific FreshRSS releases.

1. Download or clone this repository.
2. Copy the entire `xExtension-ThumbnailFallback/` directory, including `lib/`,
   into your FreshRSS `extensions/` directory.
3. Enable **Thumbnail URL Fallback** in FreshRSS under **Settings → Extensions**.

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

Content URL resolution requires an HTTP(S) article URL. URLs starting with `//`
inherit its scheme; absolute URLs keep their scheme and host. Non-HTTP(S) URLs,
such as `data:`, `blob:`, and `mailto:`, are not selected as thumbnails. See the
`srcset` parsing limits below for URLs containing commas.

Display hooks also apply the fallback to existing articles; reimporting the feed
is unnecessary.

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
- Custom attributes such as `data-src` and `data-srcset` are left unchanged and
  are not used to select thumbnails.

## Development

With PHP CLI installed, run the checks from the repository root:

```sh
sh tests/check.sh
```

To run the same checks without installing PHP locally, use a working rootless
Podman installation:

```sh
sh tests/container.sh
```

The [container runner](tests/container.sh) uses PHP 8.4.27 from the official
FreshRSS 1.30.4 image, pinned by digest. Podman downloads the image if it is
missing, which requires network access. The tests run with networking disabled
and both the container filesystem and repository mounted read-only. The runner
uses PHP CLI without starting the FreshRSS server.

Both commands check PHP syntax and run [the test suite](tests/run.php), which
prints `ok` on success. It covers relative URLs, path separators and query
strings, `srcset`, custom attributes, preservation of absolute and data URLs,
hook registration, content thumbnails, existing thumbnails, and Open Graph
fallback. FreshRSS classes and HTTP responses are stubbed; the suite does not
verify a live FreshRSS instance or real network requests.

See [Contributing](CONTRIBUTING.md) for reporting issues and submitting changes.

## License

[GNU Affero General Public License v3.0 or later](LICENSE).
