<?php

namespace WicketAcc\Illuminate\Contracts\Container;

use Exception;
use WicketAcc\Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
