<?php
class Forum24Bridge extends BridgeAbstract {

  const MAINTAINER = 'jankuca';
  const NAME = 'Forum24';
  const URI = 'https://forum24.cz/';
  const CACHE_TIMEOUT = 300; //5 min
  const DESCRIPTION = 'Returns Forum24.cz articles';
  const PARAMETERS = array(
    'Rubrika Forum24' => array(
      'rubrika_id' => array(
        'name' => 'ID rubriky',
        'required' => true
      ),
      'rubrika_name' => array(
        'name' => 'Název rubriky',
      )
    )
  );

  public function collectData() {
    $json = file_get_contents($this->getURI());
    $sourceItems = json_decode($json);

    foreach ($sourceItems as $sourceItem) {
      $item = array();
      $item['uri'] = $sourceItem->guid->rendered;
      $item['title'] = $sourceItem->title->rendered;
      $item['author'] = $sourceItem->post_author_name;
      $item['timestamp'] = DateTime::createFromFormat(DATE_RFC3339, $sourceItem->date . '+00:00')->getTimestamp();

      $content = '';
      try {
        $html = file_get_html($item['uri']);
        $contentElement = $html->find('#post-content')[0];
        foreach ($contentElement->children() as $child) {
          if (strtolower($child->tag) === 'comment' ||
              strtolower($child->tag) === 'script' ||
              strtolower($child->tag) === 'ins') {
            continue;
          }

          if (strtolower($child->tag) === 'div' && count($child->children()) === 0) {
            continue;
          }
          if (strtolower($child->tag) === 'div' && $child->children(0)->tag === 'comment') {
            continue;
          }

          if (strpos($child->{'class'}, 'article-social-links') !== FALSE ||
              strpos($child->{'class'}, 'prolink-big') !== FALSE ||
              strpos($child->{'class'}, 'author-box') !== FALSE) {
            continue;
          }

          $childHtml = (string) $child;
          if (strpos($childHtml, 'http://forum24.cz/wp-content/uploads/2017/02/sbirka_FCM_FB.png') !== FALSE) {
            continue;
          }

          $content .= $childHtml;
        }
      } catch (Exception $err) {
        $content = $sourceItem->content;
      }

      // NOTE: The post cover photo is prepended to the content.
      if ($sourceItem->post_thumbnail) {
        $coverImageLabel = '';
        if ($sourceItem->post_thumbnail_description) {
          $coverImageLabel .= trim($sourceItem->post_thumbnail_description);
        }
        if ($sourceItem->post_thumbnail_copyright) {
          $coverImageLabel .= ' &copy; ' . trim($sourceItem->post_thumbnail_copyright);
        }
        $content =
          '<div>' .
            '<img' .
              ' src="' . $sourceItem->post_thumbnail . '"' .
              ' alt="' . htmlspecialchars($coverImageLabel) . '"' .
              ' title="' . htmlspecialchars($coverImageLabel) . '"' .
            '>' .
          '</div>' .
          $content;
      }

      $item['content'] = $content;

      $this->items[] = $item;
    }
  }

  public function getName() {
    if(!is_null($this->getInput('rubrika_name'))){
      return self::NAME . ': ' . $this->getInput('rubrika_name');
    }

    return parent::getName();
  }

  public function getURI() {
    return self::URI . 'wp-json/wp/v2/posts?categories='.$this->getInput('rubrika_id');
  }

  public function getExtraInfos() {
    return array_merge(parent::getExtraInfos(), array(
      'icon' => self::URI . 'wp-content/themes/forum24/assets/images/favicon/favicon-32x32.png',
    ));
  }
}
