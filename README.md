# FC2 BLOG to WordPress Importer

FC2ブログの記事を WordPress へインポートする WP-CLI プラグインです。
記事データ・画像・カテゴリー・タグ・公開日・コメントをまとめてインポートし、本文は Gutenberg ブロックとして取り込みます。

[ameblo2wp](https://github.com/AtsushiA/ameblo2wp) と同様のアーキテクチャ・コマンド体系を採用しています。

## 機能

- FC2ブログの全記事をスクレイピングして WordPress へインポート
- 月別アーカイブを巡回して全記事 URL を自動収集
- 記事本文を Gutenberg ブロック（paragraph / image / heading / list など）に変換
- FC2 CDN 画像（`blog-imgs-*.fc2.com`）を WordPress メディアライブラリへ登録・URL 置換
- カテゴリー・タグ・公開日を保持
- コメントのインポートに対応
- 進捗をファイルに保存し、中断・再開が可能

## 必要環境

- WordPress 5.0 以上
- WP-CLI
- PHP 7.4 以上

## インストール

```bash
cd wp-content/plugins
git clone https://github.com/AtsushiA/fc2blog2wp.git
```

WordPress 管理画面でプラグインを有効化してください。

## 使い方

### 基本構文

```bash
wp fc2 import <blog_url> [--with-images] [--with-comments] [--reset]
```

### オプション

| オプション | 説明 |
|-----------|------|
| `<blog_url>` | FC2ブログの URL（例: `https://example.blog.fc2.com/`） |
| `--with-images` | 画像を WordPress メディアライブラリへインポートする |
| `--with-comments` | コメントをインポートする |
| `--reset` | 進捗をリセットして最初から実行する |

### 使用例

```bash
# 記事のみインポート
wp fc2 import https://example.blog.fc2.com/

# 画像も含めてインポート
wp fc2 import https://example.blog.fc2.com/ --with-images

# 画像・コメントも含めてインポート
wp fc2 import https://example.blog.fc2.com/ --with-images --with-comments

# 進捗をリセットして最初から実行
wp fc2 import https://example.blog.fc2.com/ --with-images --reset
```

## 処理の流れ

1. FC2ブログのトップページからサイドバーの月別アーカイブ URL を収集
2. 各月別アーカイブページから記事 URL（`blog-entry-*.html`）を収集
3. 各記事ページをパースしてタイトル・本文・日付・カテゴリー・タグを取得
4. 本文 HTML を Gutenberg ブロックに変換して WordPress に投稿
5. `--with-images` 指定時: FC2 CDN 画像をダウンロードしてメディア登録・URL 置換
6. `--with-comments` 指定時: コメントを登録
7. 進捗を `/wp-content/fc2blog2wp/<blog-id>/progress.json` に保存

## ファイル構成

```
fc2blog2wp/
├── fc2blog2wp.php              # メインプラグインファイル（WP-CLI 登録）
├── class/
│   ├── fc2_html_parser.php     # FC2ブログ HTML パーサー
│   ├── fc2blog2wp_class.php    # コアインポートロジック
│   └── fc2blog2wp_command.php  # WP-CLI コマンド実装
├── SPEC.md                     # 仕様書
└── README.md
```

## 変更履歴

### 0.3.0

- Gutenberg ブロック変換ロジックを全面改善
  - FC2ブログの `div.entryText` 内フラット HTML を正しく変換するよう対応
  - `processChildNodes()` を追加: `div` を再帰処理して子ノードをブロックに変換
  - `inlinesToParagraphs()` を追加: `<br>` 区切りのインライン要素を段落ブロックに変換
  - `<a><img></a>` パターンを画像ブロックとして正しく変換
  - `<hr>` を区切りブロック（`wp:separator`）に変換

### 0.2.0

- `exec()` + WP-CLI 子プロセス呼び出しを WordPress API に置き換え
  - `wp_insert_post()` で記事を作成するよう変更
  - `media_handle_sideload()` で画像をメディアライブラリへ登録するよう変更
  - `wp_update_post()` で記事を更新するよう変更
  - `wp_insert_comment()` でコメントを登録するよう変更
- エックスサーバー等の環境で `escapeshellarg()` がシェルのロケール依存でマルチバイト文字を消去し、タイトルが `(no title)` になる問題を修正

### 0.1.0

- 初回リリース

## ライセンス

GPL-2.0-or-later
