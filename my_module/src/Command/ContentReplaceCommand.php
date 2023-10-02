<?php

namespace Drupal\my_module\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drush\Commands\DrushCommands;

//カスタムコマンドを定義するクラス
class ContentReplaceCommand extends DrushCommands {

  protected $entityTypeManager; //Drupalのエンティティを操作するためのサービス。エンティティの操作を行う
  protected $languageManager; //Drupalの多言語化機能に関するタスクを実行する

  /**
   * 文字列置換ルールを定義する。
   * 英語(en)、スペイン語(es)に対応。
   */
  protected $replaceRules = [
    'en' => [
      1 => ['from' => 'delicious', 'to' => 'yummy'],
      2 => ['from' => 'https://www.drupal.org', 'to' => 'https://WWW.DRUPAL.ORG'],
      3 => ['from' => 'Umami', 'to' => 'this site'],
      4 => ['from' => 'minutes', 'to' => 'mins']
    ],
    'es' => [
      1 => [
          ['from' => 'delicioso', 'to' => 'rico'],  // 男性/単数
          ['from' => 'deliciosa', 'to' => 'rica'],  // 女性/単数
          ['from' => 'deliciosos', 'to' => 'ricos'], // 男性/複数
          ['from' => 'deliciosas', 'to' => 'ricas']  // 女性/複数
      ],
      2 => ['from' => 'https://www.drupal.org', 'to' => 'https://WWW.DRUPAL.ORG'],
      3 => ['from' => 'Umami', 'to' => 'este sitio'],
      4 => [
          ['from' => 'minuto', 'to' => 'min'], // 単数
          ['from' => 'minutos', 'to' => 'min'] // 複数
      ]
  ],
  ];

  /**
   * コンストラクタ
   * 2つのインターフェースのインスタンスを引数で受け取り、プロパティに格納する
   * コマンドに必要なサービスはdrush.services.ymlのargmentsで定義する
   * Drushはサービスコンテナを使用して依存性の注入を行い、コマンドに必要なサービスを提供する仕組み
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
  }

  /**
   * メインメソッド。コンテンツをルールに基づいて置換します。
   *
   * @command my_module:content_replace
   * @aliases content-replace
   * @option langcode 置換する言語を選ぶオプション。指定しない場合は全てのノードが置換対象になる。
   * @usage my_module:content_replace --langcode=es
   *   スペイン語のノードだけを処理する。
   */
  public function contentReplace(InputInterface $input, OutputInterface $output, $options = ['langcode' => NULL]) {
    $langcode = $options['langcode'];

      //オプションで言語が設定された場合、その言語だけ置換する。置換リストにない言語の場合はエラー。
      if ($langcode) {
        if (!isset($this->replaceRules[$langcode])) {
          $this->logger()->error(sprintf('言語コード "%s" はサポートされていません。', $langcode));
          return;
        }
        $this->processNodesInLanguage($langcode);
      //オプションがなければ全ての言語を対象にする
      } else {
        $languages = $this->languageManager->getLanguages();
        foreach ($languages as $language) {
          $this->processNodesInLanguage($language->getId());
        }
      }
  }

  /**
   * 実際に置換を行う関数。contentReplace内で呼び出され、言語コードを引数として受け取る
   */
  private function processNodesInLanguage($langcode) {
    //ストレージハンドラの取得(データベースとのやり取りに使用する)
    $node_storage = $this->entityTypeManager->getStorage('node');
    $path_alias_storage = $this->entityTypeManager->getStorage('path_alias');
  
    $query = $node_storage->getQuery(); //DBを操作するためのクエリ
    $query->accessCheck(FALSE); //クエリがDrupalのアクセス制御を無視できるようにする
    $query->condition('langcode', $langcode); //指定された言語コードに対応するノードのみをフィルタリングする
    $nids = $query->execute(); //対応するノードIDを取得
  
    foreach ($nids as $nid) {
      $node = $node_storage->load($nid);
      
      // 言語コードを使用してURLエイリアスを取得する
      $path_alias = $path_alias_storage->loadByProperties(['path' => '/node/' . $nid, 'langcode' => $langcode]);
      // URLエイリアスが設定されていなければデフォルトの内部パスを使用する(デモサイトには必ず設定されている)
      $url_alias = ($path_alias) ? reset($path_alias)->alias->value : '/node/' . $nid;
      
      $content_type = $node->bundle(); // コンテンツタイプのマシン名を取得
  
      // ログにURLエイリアス、コンテンツタイプ、言語コードを表示
      $this->logger()->notice(sprintf('ノードID %d: URLエイリアス = "%s", コンテンツタイプ = "%s", 言語コード = "%s"', $nid, $url_alias, $content_type, $langcode));

      //言語コードの置換ルールが存在する場合、それに沿って置換処理を行う
      if (isset($this->replaceRules[$langcode])) {
        // ルール1
        if (in_array($content_type, ['page', 'article']) && $node->hasField('body')) {
          $node = $this->applyReplaceRule($node, 'body', [1, 2], $nid, $this->replaceRules[$langcode], $langcode);
        }

        // ルール2
        if ($content_type === 'page') {
          $node = $this->applyReplaceRule($node, 'title', [3], $nid, $this->replaceRules[$langcode], $langcode);
        }

        // ルール3
        if (strpos($url_alias, '/recipes/') === 0 && $node->hasField('field_recipe_instruction')) {
          $node = $this->applyReplaceRule($node, 'field_recipe_instruction', [4], $nid, $this->replaceRules[$langcode], $langcode);
        }

        // ルール4
        if (strpos($url_alias, '/recipes/') !== 0) {
          $node = $this->applyReplaceRule($node, 'title', [1], $nid, $this->replaceRules[$langcode], $langcode);
        }
  
        $node->save(); //変更をDBに反映する
      }
    }
  }
  
  /**
   * 実際に置換を行うメソッド
   * $field_value: 置換を適用するフィールドの値
   * $rule: 適用する置換ルール
   * $nid: ノードID
   */
  private function applyReplaceRule($node, $field_name, $rule_ids, $nid, $languageRules, $langcode) {
    // 言語に基づいてノードの翻訳を取得する
    if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
    }

    $field_value = $node->get($field_name)->value;

    foreach ($rule_ids as $rule_id) {
        $rules = $languageRules[$rule_id];

        //ルールが配列の場合の処理
        if (isset($rules[0]) && is_array($rules[0])) {
            foreach ($rules as $subrule) {
                $field_value = $this->replaceAndLog($field_value, $subrule, $nid);
            }
        } else {
            $field_value = $this->replaceAndLog($field_value, $rules, $nid);
        }
    }

    $node->set($field_name, ['value' => $field_value, 'format' => 'basic_html']);
    return $node;
  }
  
  private function replaceAndLog($field_value, $rule, $nid) {
    $field_value = str_replace($rule['from'], $rule['to'], $field_value, $count);

    if ($count > 0) {
        $this->logger()->notice(sprintf('ノード %d: "%s" を "%s" に %d 回置換しました。', $nid, $rule['from'], $rule['to'], $count));
    }

    return $field_value;
  }
}
