<? foreach ($this->results->getRecommendations('top') as $current): ?>
  <?=$this->Recommend($current)?>
<? endforeach; ?>