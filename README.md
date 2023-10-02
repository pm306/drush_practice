# Drupal カスタム Drush コマンド

## 概要

このコマンドは、オープンソースのCMS「Drupal」のデモサイト「Umamiフードマガジン」上で実行できるDrushコマンドです。DrushはCLIでDrupalを操作するためのツールで、カスタムコマンドの自作が可能です。このコマンドはDrupalやDrushの学習として、サイト上の文字列を置換するためのものです。

## 前提条件

- **対象バージョン**：Drupal 10
- **想定サイト**：Drupal 10 デモサイト「Umamiフードマガジン」
- Drupalの環境構築は利用者の責任で行ってください。

## 実行方法
1. `drupal/web/modules` ディレクトリ配下に `my_module` フォルダを配置します。
2. drupalデモサイト「Umaiフードマガジン」をローカルで立ち上げます。
3. drushコマンドを実行します: ```drush content_replace```


## 置換ルール

| No. | 対象URL      | 対象コンテンツタイプ  | 対象フィールド       | 文字列置換ルール |
|-----|--------------|----------------------|----------------------|------------------|
| 1   | /*           | 基本ページ, 記事     | body                 | 1, 2             |
| 2   | /*           | 基本ページ           | Title                | 3                |
| 3   | /recipes/*   | Recipe               | Recipe instruction   | 4                |
| 4   | /recipes/*を除くすべて | すべて     | Title                | 1                |

### 文字列変換ルール

| No. | 変換前                               | 変換後                |
|-----|--------------------------------------|----------------------|
| 1   | delicious                            | yummy                |
| 2   | https://www.drupal.org               | https://WWW.DRUPAL.ORG|
| 3   | Umami                                | this site            |
| 4   | minutes                              | mins                 |
