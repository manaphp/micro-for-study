<?php

namespace ManaPHP\Di;

class FactoryDefault extends Container
{
    public function __construct()
    {
        parent::__construct();

        $this->definitions = [
            'eventManager' => 'ManaPHP\Event\Manager',
            'alias'        => 'ManaPHP\Alias',
            'dotenv'       => 'ManaPHP\Configuration\Dotenv',
            'configure'    => 'ManaPHP\Configuration\Configure',
            'invoker'      => 'ManaPHP\Controller\Invoker',
            'logger'       => 'ManaPHP\Logging\Logger\Adapter\File',
            'identity'     => 'ManaPHP\Identifying\Identity',

            'debuggerPlugin' => 'ManaPHP\Debugging\DebuggerPlugin',
        ];
    }
}
