<?php

namespace ManaPHP\Debugging;

use ManaPHP\Component;
use ManaPHP\Event\EventArgs;
use ManaPHP\Exception\AbortException;
use ManaPHP\Helper\LocalFS;
use ManaPHP\Helper\Str;
use ManaPHP\Logging\Logger;
use ManaPHP\Plugin;
use ManaPHP\Version;
use ArrayObject;
use ManaPHP\Helper\Reflection;

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

class DebuggerPluginContext
{
    /**
     * @var bool
     */
    public $enabled;

    /**
     * @var string
     */
    public $key;

    /**
     * @var array
     */
    public $view = [];

    /**
     * @var array
     */
    public $log = [];

    /**
     * @var array
     */
    public $sql_prepared = [];

    /**
     * @var array
     */
    public $sql_executed = [];

    /**
     * @var int
     */
    public $sql_count = 0;

    /**
     * @var array
     */
    public $mongodb = [];

    /**
     * @var array
     */
    public $events = [];
}

/**
 * @property-read \ManaPHP\Configuration\Configure         $configure
 * @property-read \ManaPHP\Logging\LoggerInterface         $logger
 * @property-read \ManaPHP\Http\RequestInterface           $request
 * @property-read \ManaPHP\Http\ResponseInterface          $response
 * @property-read \ManaPHP\Http\DispatcherInterface        $dispatcher
 * @property-read \ManaPHP\Http\RouterInterface            $router
 * @property-read \Redis                                   $redisCache
 * @property-read \ManaPHP\Debugging\DebuggerPluginContext $context
 */
class DebuggerPlugin extends Plugin
{
    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     * @var int
     */
    protected $ttl = 3600;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $template = '@manaphp/Debugging/DebuggerPlugin/Template.html';

    /**
     * @var bool
     */
    protected $broadcast = true;

    /**
     * @var bool
     */
    protected $tail = true;

    /**
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (isset($options['redisCache'])) {
            $this->injections['redisCache'] = $options['redisCache'];
        }

        if (MANAPHP_CLI) {
            $this->enabled = false;
        } elseif (isset($options['enabled'])) {
            $this->enabled = (bool)$options['enabled'];
        } elseif (!in_array($this->configure->env, ['dev', 'test'], true)) {
            $this->enabled = false;
        }

        if (isset($options['ttl'])) {
            $this->ttl = (int)$options['ttl'];
        }

        if (!class_exists('Redis')) {
            $this->ttl = 0;
        }

        $this->prefix = $options['prefix'] ?? sprintf("cache:%s:debuggerPlugin:", APP_ID);

        if (isset($options['template'])) {
            $this->template = $options['template'];
        }

        if (isset($options['broadcast'])) {
            $this->broadcast = (bool)$options['broadcast'];
        }

        if (isset($options['tail'])) {
            $this->tail = (bool)$options['tail'];
        }

        if ($this->enabled) {
            $this->peekEvent('*', [$this, 'onEvent']);

            $this->peekEvent('db', [$this, 'onDb']);
            $this->peekEvent('mongodb', [$this, 'onMongodb']);

            $this->attachEvent('renderer:rendering', [$this, 'onRendererRendering']);
            $this->attachEvent('logger:log', [$this, 'onLoggerLog']);
            $this->attachEvent('request:begin', [$this, 'onRequestBegin']);
            $this->attachEvent('request:end', [$this, 'onRequestEnd']);

            if ($this->tail) {
                $this->attachEvent('response:stringify', [$this, 'onResponseStringify']);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return string|false
     */
    protected function readData($key)
    {
        $file = "@data/debuggerPlugin/{$key}.zip";
        $content = LocalFS::fileExists($file) ? LocalFS::fileGet($file) : false;

        return is_string($content) ? gzdecode($content) : $content;
    }

    /**
     * @param string $key
     * @param array  $data
     *
     * @return void
     * @throws \ManaPHP\Exception\JsonException
     */
    protected function writeData($key, $data)
    {
        $content = gzencode(json_stringify($data, JSON_PARTIAL_OUTPUT_ON_ERROR));
        LocalFS::filePut("@data/debuggerPlugin/{$key}.zip", $content);
    }

    /**
     * @return void
     */
    public function onRequestBegin()
    {
        $context = $this->context;

        if (($debugger = $this->request->get('__debuggerPlugin', ''))
            && preg_match('#^([\w/]+)\.(html|json|txt|raw)$#', $debugger, $match)
        ) {
            $context->enabled = false;
            if (($data = $this->self->readData($match[1])) !== false) {
                $ext = $match[2];
                if ($ext === 'html') {
                    $this->response->setContent(strtr(LocalFS::fileGet($this->template), ['DEBUGGER_DATA' => $data]));
                } elseif ($ext === 'txt') {
                    $this->response->setContent(json_stringify(json_parse($data), JSON_PRETTY_PRINT))
                        ->setContentType('text/plain;charset=UTF-8');
                } elseif ($ext === 'raw') {
                    $this->response->setContent($data)->setContentType('text/plain;charset=UTF-8');
                } else {
                    $this->response->setJsonContent($data);
                }
            } else {
                $this->response->setContent('NOT FOUND')->setStatus(404);
            }

            throw new AbortException();
        } elseif (str_contains($this->request->getUserAgent(), 'ApacheBench')) {
            $context->enabled = false;
        } else {
            $context->enabled = true;
            $context->key = date('/ymd/His_') . Str::random(32);
        }

        if ($context->enabled) {
            $url = $this->router->createUrl("/?__debuggerPlugin={$context->key}.html", true);
            $this->response->setHeader('X-Debugger-Link', $url);
            $this->logger->info($url, 'debugger.link');
        }
    }

    /**
     * @return void
     */
    public function onRequestEnd()
    {
        $context = $this->context;

        if ($context->enabled) {
            $this->self->writeData($context->key, $this->self->getData());
        }
    }

    public function onResponseStringify()
    {
        if (is_array($content = $this->response->getContent())) {
            $content['debuggerPlugin'] = $this->response->getHeader('X-Debugger-Link');
            $this->response->setContent($content);
        }
    }

    /**
     * @param EventArgs $eventArgs
     *
     * @return void
     */
    public function onEvent(EventArgs $eventArgs)
    {
        $event['event'] = $eventArgs->event;
        $event['source'] = Reflection::getClass($eventArgs->source);

        $data = $eventArgs->data;
        if ($data === null) {
            $event['data'] = null;
        } elseif (is_scalar($data)) {
            $event['data'] = gettype($data);
        } elseif ($data instanceof ArrayObject) {
            $event['data'] = array_keys($data->getArrayCopy());
        } elseif (is_object($data)) {
            $event['data'] = Reflection::getClass($data);
        } else {
            $event['data'] = '???';
        }

        $this->context->events[] = $event;
    }

    /**
     * @param EventArgs $eventArgs
     *
     * @return void
     */
    public function onLoggerLog(EventArgs $eventArgs)
    {
        $context = $this->context;

        /** @var \ManaPHP\Logging\Logger\Log $log */
        $log = $eventArgs->data['log'];
        $ms = sprintf('.%03d', ($log->timestamp - (int)$log->timestamp) * 1000);
        $context->log[] = [
            'time'     => date('H:i:s', $log->timestamp) . $ms,
            'level'    => $log->level,
            'category' => $log->category,
            'file'     => $log->file,
            'line'     => $log->line,
            'message'  => $log->message
        ];
    }

    /**
     * @return array
     */
    protected function getBasic()
    {
        $context = $this->context;

        $loaded_extensions = get_loaded_extensions();
        sort($loaded_extensions, SORT_STRING | SORT_FLAG_CASE);
        $memory_usage = (int)(memory_get_usage(true) / 1024) . 'k/' . (int)(memory_get_peak_usage(true) / 1024) . 'k';

        return [
            'mvc'                => $this->router->getController() . '::' . $this->router->getAction(),
            'request_method'     => $this->request->getMethod(),
            'request_url'        => $this->request->getUrl(),
            'query_count'        => $context->sql_count,
            'execute_time'       => $this->request->getElapsedTime(),
            'memory_usage'       => $memory_usage,
            'system_time'        => date('Y-m-d H:i:s'),
            'server_ip'          => $this->request->getServer('SERVER_ADDR'),
            'client_ip'          => $this->request->getClientIp(),
            'server_software'    => $this->request->getServer('SERVER_SOFTWARE'),
            'manaphp_version'    => Version::get(),
            'php_version'        => PHP_VERSION,
            'sapi'               => PHP_SAPI,
            'loaded_ini'         => php_ini_loaded_file(),
            'loaded_extensions'  => implode(', ', $loaded_extensions),
            'opcache.enable'     => ini_get('opcache.enable'),
            'opcache.enable_cli' => ini_get('opcache.enable_cli'),
        ];
    }

    /**
     * @return array
     */
    protected function getData()
    {
        $context = $this->context;

        $data = [];
        $data['basic'] = $this->self->getBasic();
        $levels = array_flip($this->logger->getLevels());
        $data['logger'] = ['log' => $context->log, 'levels' => $levels, 'level' => Logger::LEVEL_DEBUG];
        $data['sql'] = [
            'prepared' => $context->sql_prepared,
            'executed' => $context->sql_executed,
            'count'    => $context->sql_count
        ];
        $data['mongodb'] = $context->mongodb;

        $data['view'] = $context->view;
        $data['components'] = [];
        $data['tracers'] = [];
        $data['events'] = $context->events;

        foreach ($this->container->getInstances() as $name => $instance) {
            if (str_contains($name, '\\')) {
                continue;
            }

            $properties = Reflection::isInstanceOf($instance, Component::class)
                ? $instance->dump()
                : array_keys(Reflection::getObjectVars($instance));

            $data['components'][$name] = ['class' => Reflection::getClass($instance), 'properties' => $properties];
        }

        $data['included_files'] = @get_included_files() ?: [];
        unset($data['server']['PATH']);

        return $data;
    }

    /**
     * @return array
     */
    public function dump()
    {
        $data = parent::dump();

        $data['context'] = array_keys($data['context']);

        return $data;
    }
}
