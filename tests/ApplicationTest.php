<?php

namespace ProtonTests;

use League\Container\Container;
use League\Event\Emitter;
use League\Route\Http\Exception\NotFoundException;
use League\Route\RouteCollection;
use Monolog\Logger;
use Proton;
use Proton\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testSetGet()
    {
        $app = new Application();
        $this->assertTrue($app->getContainer() instanceof Container);
        $this->assertTrue($app->getRouter() instanceof RouteCollection);
        $this->assertTrue($app->getEventEmitter() instanceof Emitter);

        $logger = $app->getLogger();
        $this->assertTrue($logger instanceof Logger);
        $this->assertEquals($logger, $app->getLogger('default'));
    }

    public function testArrayAccessContainer()
    {
        $app = new Application();
        $app['foo'] = 'bar';

        $this->assertSame('bar', $app['foo']);
        $this->assertTrue(isset($app['foo']));
        unset($app['foo']);
        $this->assertFalse(isset($app['foo']));
    }

    public function testSubscribe()
    {
        $app = new Application();

        $app->subscribe('request.received', function ($event, $request) {
            $this->assertInstanceOf('League\Event\Event', $event);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $request);
        });

        $reflected = new \ReflectionProperty($app, 'emitter');
        $reflected->setAccessible(true);
        $emitter = $reflected->getValue($app);
        $this->assertTrue($emitter->hasListeners('request.received'));

        $foo = null;
        $app->subscribe('response.created', function ($event, $request, $response) use (&$foo) {
            $foo = 'bar';
        });

        $request = Request::createFromGlobals();
        $response = $app->handle($request);

        $this->assertEquals('bar', $foo);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testTerminate()
    {
        $app = new Application();

        $app->subscribe('response.sent', function ($event, $request, $response) {
            $this->assertInstanceOf('League\Event\Event', $event);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $request);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        });

        $request = Request::createFromGlobals();
        $response = $app->handle($request);

        $app->terminate($request, $response);
    }

    public function testHandleSuccess()
    {
        $app = new Application();

        $app->get('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->post('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->put('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->delete('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->patch('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $request = Request::createFromGlobals();

        $response = $app->handle($request, 1, true);

        $this->assertEquals('<h1>It works!</h1>', $response->getContent());
    }

    public function testHandleFailThrowException()
    {
        $app = new Application();

        $request = Request::createFromGlobals();

        try {
            $app->handle($request, 1, false);
        } catch (\Exception $e) {
            $this->assertTrue($e instanceof NotFoundException);
        }
    }

    public function testHandleWithOtherException()
    {
        $app = new Application();
        $app['debug'] = true;

        $request = Request::createFromGlobals();

        $app->subscribe('request.received', function ($event, $request, $response) {
            throw new \Exception('A test exception');
        });

        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testCustomExceptionDecorator()
    {
        $app = new Application();
        $app['debug'] = true;

        $request = Request::createFromGlobals();

        $app->subscribe('request.received', function ($event, $request, $response) {
            throw new \Exception('A test exception');
        });

        $app->setExceptionDecorator(function ($e) {
            $response = new Response;
            $response->setStatusCode(500);
            $response->setContent('Fail');
            return $response;
        });

        $response = $app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Fail', $response->getContent());
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionDecoratorDoesntReturnResponseObject()
    {
        $app = new Application();
        $app->setExceptionDecorator(function ($e) {
            return true;
        });

        $request = Request::createFromGlobals();

        $app->subscribe('request.received', function ($event, $request, $response) {
            throw new \Exception('A test exception');
        });

        $response = $app->handle($request);
    }

    public function testCustomEvents()
    {
        $app = new Application();

        $time = null;
        $app->subscribe('custom.event', function ($event, $args) use (&$time) {
            $time = $args;
        });

        $app->getEventEmitter()->emit('custom.event', time());
        $this->assertTrue($time !== null);
    }

    public function testRun()
    {
        $app = new Application();

        $app->get('/', function ($request, $response) {
            $response->setContent('<h1>It works!</h1>');
            return $response;
        });

        $app->subscribe('request.received', function ($event, $request) {
            $this->assertInstanceOf('League\Event\Event', $event);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $request);
        });
        $app->subscribe('response.sent', function ($event, $request, $response) {
            $this->assertInstanceOf('League\Event\Event', $event);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $request);
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        });

        ob_start();
        $app->run();
        ob_get_clean();
    }
}
