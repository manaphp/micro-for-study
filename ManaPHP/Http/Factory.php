<?php

namespace ManaPHP\Http;

use ManaPHP\Di\FactoryDefault;

class Factory extends FactoryDefault
{
    public function __construct()
    {
        parent::__construct();

        $this->definitions = array_merge(
            $this->definitions, [
                'router'     => 'ManaPHP\Http\Router',
                'dispatcher' => 'ManaPHP\Http\Dispatcher',
                'response'   => 'ManaPHP\Http\Response',
                'request'    => 'ManaPHP\Http\Request',

                'corsPlugin' => 'ManaPHP\Http\CorsPlugin',
            ]
        );

        if (PHP_SAPI === 'cli') {
            if (class_exists('Workerman\Worker')) {
                $this->setShared('httpServer', 'ManaPHP\Http\Server\Adapter\Workerman');
            } elseif (extension_loaded('swoole')) {
                $this->setShared('httpServer', 'ManaPHP\Http\Server\Adapter\Swoole');
            } else {
                $this->setShared('httpServer', 'ManaPHP\Http\Server\Adapter\Php');
            }
        } elseif (PHP_SAPI === 'cli-server') {
            $this->setShared('httpServer', 'ManaPHP\Http\Server\Adapter\Php');
        } else {
            $this->setShared('httpServer', 'ManaPHP\Http\Server\Adapter\Fpm');
        }
    }
}