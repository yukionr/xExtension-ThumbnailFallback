#!/bin/sh
set -eu

cd "$(dirname "$0")/.."
php -v
php -l tests/run.php
php -l xExtension-ThumbnailFallback/extension.php
php -l xExtension-ThumbnailFallback/lib/ThumbnailUrlNormalizer.php
php tests/run.php
