<?php

namespace ManaPHP\Di;

use ManaPHP\Di;

class FactoryDefault extends Di
{
    public function __construct()
    {
        parent::__construct();

        $this->_definitions = [
            'eventsManager' => 'ManaPHP\Event\Manager',
            'alias'         => 'ManaPHP\Alias',
            'configure'     => 'ManaPHP\Configuration\Configure',
            'invoker'       => 'ManaPHP\Controller\Invoker',
            'logger'        => 'ManaPHP\Logging\Logger\Adapter\File',

            'debuggerPlugin' => 'ManaPHP\Debugging\DebuggerPlugin',
        ];
    }
}
