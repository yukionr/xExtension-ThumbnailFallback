# Contributing

## Reporting issues

Include your FreshRSS and PHP versions, the expected behavior, and what happened
instead. For image URL problems, provide a minimal article URL and HTML snippet
that reproduce the issue. Remove credentials, private feed URLs, and personal
data before sharing examples. Mention whether the article already has a feed
thumbnail, a content image, or an `og:image` tag.

## Submitting changes

Keep changes focused and use the existing PHP style. Write documentation,
comments, test diagnostics, and commit messages in English.

For behavior changes, add a focused regression test to `tests/run.php` and run
the [development checks](README.md#development). Update the README if the change
affects installation, fallback behavior, network requests, or limitations.

The standalone suite uses FreshRSS stubs. If you also test in FreshRSS, report
the FreshRSS and PHP versions and the behavior checked. Distinguish live
integration results from standalone test results.
