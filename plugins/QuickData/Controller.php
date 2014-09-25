<?php

namespace Piwik\Plugins\QuickData;

use Piwik\View;

class Controller extends \Piwik\Plugin\Controller {

    public function index() {

        return $this->renderTemplate('index');

    }

}