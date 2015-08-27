<?php

class RoboFile extends \Robo\Tasks
{
    public function watch()
    {
        $this->build();
        $this
            ->taskWatch()
            ->monitor('Ressources/assets/css/', function () {
                $this->_cleanBase();
                $this->_cleanCss();
                $this->_buildCss();
            })
            ->run()
        ;
    }

    public function build()
    {
        $this->_clean();
        $this->_buildCss();
    }

    protected function _buildCss()
    {
        $this->say("Starting CSS rebuild");

        $this->taskScss(['Ressources/assets/sass/main.scss' => 'cache/sass/main.css'])->run();

        $this
            ->taskConcat([
                'Ressources/assets/css/bootstrap.min.css',
                'Ressources/assets/css/token-input.css',
                'cache/sass/main.css'
            ])
            ->to('cache/main.css')
            ->run()
        ;

        $this
            ->taskMinify('cache/main.css')
            ->to('cache/main.css')
            ->run()
        ;

        $this->_rename('cache/main.css', 'web/css/main.' . substr(md5_file('cache/main.css'), 0, 5) . '.css');
        $this->say("CSS rebuilt successfully!");
    }

    protected function _clean()
    {
        $this->_mkdir('cache/');
        $this->_cleanBase();
        $this->_cleanCss();

    }

    protected function _cleanBase()
    {
        $this->_cleanDir('cache/');
    }

    protected function _cleanCss()
    {
        $this->_cleanDir('web/css');
        $this->_mkdir('cache/sass');
    }

}
