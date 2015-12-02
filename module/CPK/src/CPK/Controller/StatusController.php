<?php

namespace CPK\Controller;

use VuFind\Controller\AbstractBase;
use Zend\View\Model\ViewModel;


class StatusController extends AbstractBase
{

    public function defaultAction()
    {
        return $this->homeAction();
    }

    public function homeAction()
    {
        $view = $this->createViewModel(
            array(
                'statistics' => 'dashboard',
            )
        );

        $view->setTemplate('status/home');

        return $view;
    }
}
