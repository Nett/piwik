<?php

class Piwik_QuickData_Controller extends Piwik_Controller {

    public function index() {

        $view = Piwik_View::factory('index');
        echo $view->render();
    }

}