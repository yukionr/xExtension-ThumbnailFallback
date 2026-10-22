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

For a bug fix, first add a focused test to `tests/run.php` that reproduces the
failure. For a new feature, add a test for the expected behavior. Confirm that
the test fails before implementing the change, then run the
[development checks](README.md#development).

Update the README if the change affects installation, fallback behavior, network
requests, or limitations. For documentation-only changes, check the described
behavior against the source, verify local links, and run `git diff --check`;
running FreshRSS or a container is unnecessary unless the claim needs it.

The standalone suite uses FreshRSS stubs. If you also test in FreshRSS, report
the FreshRSS and PHP versions and the behavior checked. Distinguish live
integration results from standalone test results.
