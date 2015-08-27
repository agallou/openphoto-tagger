<?php

class RoboFile extends \Robo\Tasks
{
    public function watch()
    {
        $this->build();
        $this->taskWatch()
            ->monitor('Ressources/assets/css/', function () {
                $this->_cleanBase();
                $this->_cleanCss();
                $this->_buildCss();
            })->run();
    }

    public function build()
    {
        $this->_clean();
        $this->_buildCss();
    }

    public function _buildCss()
    {
        $this->say("Starting CSS rebuild");
        $this->_exec('./bin/mini_asset build --config assets.ini');
        $this->_rename('cache/main.css', 'web/css/main.' . substr(md5_file('cache/main.css'), 0, 5) . '.css');
        $this->say("CSS rebuilt successfully!");
    }

    public function _clean()
    {
        $this->_mkdir('cache/');
        $this->_cleanBase();
        $this->_cleanCss();

    }

    public function _cleanBase()
    {
        $this->_cleanDir('cache/');
    }

    public function _cleanCss()
    {
        $this->_cleanDir('web/css');
    }

}
