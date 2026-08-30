# FreshRSS Thumbnail Fallback

FreshRSS の通常表示で、RSS 本文に含まれる相対画像 URL を記事 URL 基準の
絶対 URL へ補正し、フィード由来のサムネイルがない記事では本文先頭画像を
一覧用サムネイルとして設定します。本文にも画像がない場合は、記事ページの
`og:image` を URL のみ取得して一覧用サムネイルへ設定します。

## 背景

あるメディアの RSS は絶対 URL の `<img src>` を含みますが、別のメディアの
RSS は `content:encoded` 内に `/wp-content/...` のような相対 URL を含みます。
FreshRSS の表示元と記事サイトの origin が異なるため、相対 URL のままでは
サムネイルが読み込めません。

## 方針

- FreshRSS 本体を fork しない
- 画像本体をダウンロード・キャッシュしない
- 絶対 URL は変更しない
- `data:`、`blob:`、`mailto:` など外部画像ではない URL は変更しない
- 新規記事だけでなく、表示時にも補正して既存記事へ適用する
- 記事ページから取得するのは HTML メタデータで、画像本体は保存しない

## 対応範囲

記事本文の `<img src>` と `<img srcset>` の URL 補正に対応します。FreshRSS が
フィードからサムネイルを取得できなかった場合は、本文先頭の HTTP(S) 画像を
`thumbnail.url` 属性へ設定します。既存のサムネイル属性や enclosure の画像は
上書きしません。本文画像もない場合だけ、記事ページを最大3秒の timeout で
取得し、`og:image` の HTTP(S) URL を補完します。記事ページの取得に失敗した
場合は記事表示を妨げず、サムネイルも設定しません。

## FreshRSS への配置

`xExtension-ThumbnailFallback/` を FreshRSS の `extensions/` 配下へ配置し、
FreshRSS の **設定 → 拡張機能** から有効化します。

このリポジトリを Kubernetes から利用する場合は、リポジトリの commit と
アーカイブ SHA-256 を固定して extension directory を配置してください。

## 開発

```sh
php tests/run.php
php -l xExtension-ThumbnailFallback/extension.php
php -l xExtension-ThumbnailFallback/lib/ThumbnailUrlNormalizer.php
```

ライセンス: GNU Affero General Public License v3.0 以降
