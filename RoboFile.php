<?php

class RoboFile extends \Robo\Tasks
{
    public function watch()
    {
        $this->build();

        $buildCss = function () {
            $this->_cleanBase();
            $this->_cleanCss();
            $this->_buildCss();
        };

        $this
            ->taskWatch()
            ->monitor(array('Ressources/assets/css/', 'Ressources/assets/sass'), $buildCss)
            ->run()
        ;
    }

    public function install()
    {
        $this->taskBowerInstall('./bin/bowerphp')->run();
        $this->build();
    }

    public function build()
    {
        $this->_clean();
        $this->_buildCss();
        $this->_buildJs();
    }

    protected function _buildCss()
    {
        $this->say("Starting CSS rebuild");

        $this
            ->taskScss(['Ressources/assets/sass/main.scss' => 'cache/sass/main.css'])
            ->addImportPath('Ressources/assets/sass')
            ->run();

        $this
            ->taskConcat([
                'Ressources/assets/css/bootstrap.min.css',
                'bower_components/jquery-tokeninput/styles/token-input.css',
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

    protected function _buildJs()
    {
        $this->say("Starting JS rebuild");

        $this
            ->taskConcat([
                'bower_components/jquery-tokeninput/src/jquery.tokeninput.js',
                'Ressources/assets/js/keymaster.min.js',
            ])
            ->to('cache/main.js')
            ->run()
        ;

        $this
            ->taskMinify('cache/main.js')
            ->to('cache/main.js')
            ->run()
        ;

        $this->_rename('cache/main.js', 'web/js/main.' . substr(md5_file('cache/main.js'), 0, 5) . '.js');

        $this->say("JS rebuilt successfully!");

    }

    protected function _clean()
    {
        $this->_mkdir('cache/');
        $this->_cleanBase();
        $this->_cleanCss();
        $this->_cleanJs();

    }

    protected function _cleanBase()
    {
        $this->_cleanDir('cache/');
    }

    protected function _cleanCss()
    {
        $this->_mkdir('web/css');
        $this->_cleanDir('web/css');
        $this->_mkdir('cache/sass');
    }

    protected function _cleanJs()
    {
        $this->_mkdir('web/js');
        $this->_cleanDir('web/js');
    }

}
