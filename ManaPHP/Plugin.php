<?php

namespace ManaPHP;

use ManaPHP\Logging\Logger\LogCategorizable;

/**
 * @property-read \ManaPHP\Http\RequestInterface    $request
 * @property-read \ManaPHP\Http\ResponseInterface   $response
 * @property-read \ManaPHP\Http\DispatcherInterface $dispatcher
 * @property-read \ManaPHP\Http\RouterInterface     $router
 */
abstract class Plugin extends Component implements LogCategorizable
{
    public function categorizeLog()
    {
        return basename(str_replace('\\', '.', static::class), 'Plugin');
    }
}