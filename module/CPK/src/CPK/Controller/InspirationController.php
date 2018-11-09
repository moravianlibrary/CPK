<?php

namespace CPK\Controller;

use VuFind\Controller\AbstractBase;

class InspirationController extends AbstractBase {

    public function showAction() {
        $tag = $this->params()->fromRoute('tag');
        $filter = 'inspiration:' . $tag;
        $compressedFilter = \LZCompressor\LZString::compressToBase64($filter);
        $options = [
            'query' => [
                'type0[]' => 'AllFields',
                'filter'  => $compressedFilter,
            ]
        ];
        return $this->redirect()->toRoute('search-results', [], $options);
    }

}