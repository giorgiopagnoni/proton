# Proton Changelog

## 1.4.1 (2015-03-26)

* Remove bad docblock

## 1.4.0 (2015-02-22)

* Replaces domain events with built-in league event (#21)
* Added new `register` method to register service providers with the container

## 1.3.0 (2015-02-12)

* Added Monolog support

## 1.2.1 (2015-01-30)

* Fixed RouteCollection being set twice

## 1.2.0 (2015-01-30)

* Inject request object into container (#17)
* Spelling fix (#11)
* Inject app into container (#16)
* Updated `league/event` to `~2.0`
* Use event `EmitterTrait` (#14)
* Use `ContainerAwareTrait` (#12)
* New `setConfig` and `getConfig` methods

## 1.1.0 (2015-01-14)

* Switched `orno/di` dependency for `league/container`
* Switched `orno/route` dependency for `league/route`

## 1.0.5 (2014-10-13)

* Improved dependency version constraints (#2)

## 1.0.4 (2014-10-10)

* Added `setExceptionDecorator` method
* Added `getEventEmitter` method

## 1.0.3 (2014-10-09)

* Added `$app['debug']` flag

## 1.0.2 (2014-10-06)

* Exception trace is an exploded string instead of an array (to prevent epic looping and object dumping)

## 1.0.1 (2014-10-03)

* The correct version of the request is passed through to the route dispatcher (which was using a new instance before)

## 1.0.0

First release!
