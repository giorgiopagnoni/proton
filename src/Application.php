<?php
/**
 * The Proton Micro Framework.
 *
 * @author  Alex Bilbie <hello@alexbilbie.com>
 * @license MIT
 */

namespace Proton;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use League\Container\ContainerInterface;
use League\Event\EmitterTrait;
use League\Event\ListenerAcceptorInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use League\Container\Container;
use League\Route\RouteCollection;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proton Application Class.
 */
class Application implements HttpKernelInterface, TerminableInterface, ContainerAwareInterface, ListenerAcceptorInterface, \ArrayAccess
{
    use EmitterTrait;
    use ContainerAwareTrait;

    /**
     * @var \League\Route\RouteCollection
     */
    protected $router;

    /**
     * @var \callable
     */
    protected $exceptionDecorator;

    /**
     * @var array
     */
    protected $config = [];
    
    /**
     * @var array
     */
    protected $loggers = [];

    /**
     * New Application.
     *
     * @param bool $debug Enable debug mode
     */
    public function __construct($debug = true)
    {
        $this->setConfig('debug', $debug);

        $this->setExceptionDecorator(function (\Exception $e) {
            $response = new Response;
            $response->setStatusCode(method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
            $response->headers->add(['Content-Type' => 'application/json']);

            $return = [
                'error' => [
                    'message' => $e->getMessage()
                ]
            ];

            if ($this->getConfig('debug', true) === true) {
                $return['error']['trace'] = explode(PHP_EOL, $e->getTraceAsString());
            }

            $response->setContent(json_encode($return));

            return $response;
        });
    }

    /**
     * Set a container.
     *
     * @param \League\Container\ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
        $this->container->singleton('app', $this);
        $this->router = null;
    }

    /**
     * Get the container.
     *
     * @return \League\Container\ContainerInterface
     */
    public function getContainer()
    {
        if (!isset($this->container)) {
            $this->setContainer(new Container);
        }

        return $this->container;
    }

    /**
     * Return the router.
     *
     * @return \League\Route\RouteCollection
     */
    public function getRouter()
    {
        if (!isset($this->router)) {
            $this->router = new RouteCollection($this->getContainer());
        }

        return $this->router;
    }

    /**
     * Return the event emitter.
     *
     * @return \League\Event\Emitter
     */
    public function getEventEmitter()
    {
        return $this->getEmitter();
    }
    
    /**
     * Return a logger
     *
     * @param string $name
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger($name = 'default')
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $logger = new Logger($name);
        $this->loggers[$name] = $logger;
        return $logger;
    }

    /**
     * Set the exception decorator.
     *
     * @param callable $func
     *
     * @return void
     */
    public function setExceptionDecorator(callable $func)
    {
        $this->exceptionDecorator = $func;
    }

    /**
     * Add a GET route.
     *
     * @param string $route
     * @param mixed  $action
     *
     * @return void
     */
    public function get($route, $action)
    {
        $this->getRouter()->addRoute('GET', $route, $action);
    }

    /**
     * Add a POST route.
     *
     * @param string $route
     * @param mixed  $action
     *
     * @return void
     */
    public function post($route, $action)
    {
        $this->getRouter()->addRoute('POST', $route, $action);
    }

    /**
     * Add a PUT route.
     *
     * @param string $route
     * @param mixed  $action
     *
     * @return void
     */
    public function put($route, $action)
    {
        $this->getRouter()->addRoute('PUT', $route, $action);
    }

    /**
     * Add a DELETE route.
     *
     * @param string $route
     * @param mixed  $action
     *
     * @return void
     */
    public function delete($route, $action)
    {
        $this->getRouter()->addRoute('DELETE', $route, $action);
    }

    /**
     * Add a PATCH route.
     *
     * @param string $route
     * @param mixed  $action
     *
     * @return void
     */
    public function patch($route, $action)
    {
        $this->getRouter()->addRoute('PATCH', $route, $action);
    }

    /**
     * Handle the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int                                       $type
     * @param bool                                      $catch
     *
     * @throws \Exception
     * @throws \LogicException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        // Passes the request to the container
        $this->getContainer()->add('Symfony\Component\HttpFoundation\Request', $request);

        try {

            $this->emit('request.received', $request);

            $dispatcher = $this->getRouter()->getDispatcher();
            $response = $dispatcher->dispatch(
                $request->getMethod(),
                $request->getPathInfo()
            );

            $this->emit('response.created', $request, $response);

            return $response;

        } catch (\Exception $e) {

            if (!$catch) {
                throw $e;
            }

            $response = call_user_func($this->exceptionDecorator, $e);
            if (!$response instanceof Response) {
                throw new \LogicException('Exception decorator did not return an instance of Symfony\Component\HttpFoundation\Response');
            }

            $this->emit('response.created', $request, $response);

            return $response;
        }
    }

    /**
     * Terminates a request/response cycle.
     *
     * @param \Symfony\Component\HttpFoundation\Request  $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response)
    {
        $this->emit('response.sent', $request, $response);
    }

    /**
     * Run the application.
     *
     * @param \Symfony\Component\HttpFoundation\Request|null $request
     *
     * @return void
     */
    public function run(Request $request = null)
    {
        if (null === $request) {
            $request = Request::createFromGlobals();
        }

        $response = $this->handle($request);
        $response->send();

        $this->terminate($request, $response);
    }

    /**
     * Subscribe to an event.
     *
     * @param string   $event
     * @param callable $listener
     * @param int      $priority
     */
    public function subscribe($event, $listener, $priority = ListenerAcceptorInterface::P_NORMAL)
    {
        $this->addListener($event, $listener, $priority);
    }

    /**
     * Array Access get.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->getContainer()->get($key);
    }

    /**
     * Array Access set.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->getContainer()->singleton($key, $value);
    }

    /**
     * Array Access unset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->getContainer()->offsetUnset($key);
    }

    /**
     * Array Access isset.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->getContainer()->isRegistered($key) || $this->getContainer()->isSingleton($key);
    }

    /**
     * Register a new service provider
     *
     * @param $serviceProvider
     */
    public function register($serviceProvider)
    {
        $this->getContainer()->addServiceProvider($serviceProvider);
    }

    /**
     * Set a config item
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setConfig($key, $value)
    {
        $this->config[$key] = $value;
    }

    /**
     * Get a config key's value
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
}
