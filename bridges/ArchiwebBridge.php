<?php
class ArchiwebBridge extends BridgeAbstract {

  const MAINTAINER = 'jankuca';
  const NAME = 'Archiweb';
  const URI = 'http://www.archiweb.cz/';
  const CACHE_TIMEOUT = 3600; // 1 hod
  const DESCRIPTION = 'Returns Archiweb.cz articles';
  const PARAMETERS = array(
    'Rubrika Archiwebu' => array(
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
    $listHtml = file_get_contents($this->getURI());
    $listHtml = explode('<!-- second column -->', $listHtml)[1];
    $listHtml = explode('<!-- third column -->', $listHtml)[0];

    $listDom = str_get_html($listHtml);
    $itemLinkElements = $listDom->find('a.art_link');

    foreach ($itemLinkElements as $itemLinkElement) {
      $item = array();

      $itemUrl = self::URI . htmlspecialchars_decode($itemLinkElement->href);
      $item['uri'] = $itemUrl;
      $item['title'] = $itemLinkElement->plaintext;

      $itemHtml = file_get_contents($itemUrl);
      $itemHtml = explode('<!-- second column -->', $itemHtml)[1];
      $itemHtml = explode('<!-- third column -->', $itemHtml)[0];

      $itemDom = str_get_html($itemHtml);

      // NOTE: "Autor: Name, Date" or "Zdroj: Name, Vložil: Name, Date"
      $rawAuthorStr = trim($itemDom->find('.author')[0]->plaintext);
      $rawAuthorParts = explode(': ', trim($rawAuthorStr));
      $authorValueStr = trim(end($rawAuthorParts));
      $authorParts = explode(',', $authorValueStr);
      $item['author'] = trim(join(',', array_slice($authorParts, 0, -1)));
      $item['timestamp'] = DateTime::createFromFormat('d.m.y H:iP', trim(end($authorParts)) . '+02:00')->getTimestamp();

      $content = '';
      foreach ($itemDom->find('div.text *, div.text text, div.vertical_text *, div.vertical_text text') as $childIndex => $child) {
        $childHtml = (string) $child;

        if ($childIndex === 0 || $childIndex === 1) {
          $coverImages = $child->find('img');
          if (isset($coverImages[0])) {
            $coverImageHtml = (string) $coverImages[0];
            $childHtml = str_replace($coverImageHtml, '', $childHtml);
            $content .= '<div>' . $coverImageHtml . '</div>';
          }
        }

        if ($child->{'class'} === 'cleaner') {
          break;
        }

        if ($child->parent->{'class'} !== 'text' && $child->parent->{'class'} !== 'vertical_text') {
          continue;
        }

        $content .= $childHtml;
      }

      $content = preg_replace('/<span style="font-weight: bold;">(.*?)<\/span>/', '<strong>$1</strong>', $content);
      $content = preg_replace('/<span style="font-style: italic;">(.*?)<\/span>/', '<em>$1</em>', $content);

      $item['content'] = $content;

      $this->items[] = $item;

      if (count($this->items) === 40) {
        break;
      }
    }
  }

  public function getName() {
    if(!is_null($this->getInput('rubrika_name'))){
      return self::NAME . ': ' . $this->getInput('rubrika_name');
    }

    return parent::getName();
  }

  public function getURI() {
    return self::URI . 'news.php?type=' . $this->getInput('rubrika_id');
  }

  public function getExtraInfos() {
    return array_merge(parent::getExtraInfos(), array(
      'icon' => self::URI . 'favicon.ico',
    ));
  }
}
