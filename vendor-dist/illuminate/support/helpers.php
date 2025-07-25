<?php

use WicketAcc\Illuminate\Contracts\Support\DeferringDisplayableValue;
use WicketAcc\Illuminate\Contracts\Support\Htmlable;
use WicketAcc\Illuminate\Support\Arr;
use WicketAcc\Illuminate\Support\Env;
use WicketAcc\Illuminate\Support\HigherOrderTapProxy;
use WicketAcc\Illuminate\Support\Optional;
use WicketAcc\Illuminate\Support\Sleep;
use WicketAcc\Illuminate\Support\Str;

if (! function_exists('wicketacc_append_config')) {
    /**
     * Assign high numeric IDs to a config item to force appending.
     *
     * @param  array  $array
     * @return array
     */
    function wicketacc_append_config(array $array)
    {
        $start = 9999;

        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $start++;

                $array[$start] = Arr::pull($array, $key);
            }
        }

        return $array;
    }
}

if (! function_exists('wicketacc_blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @param  mixed  $value
     * @return bool
     */
    function wicketacc_blank($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (! function_exists('wicketacc_class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    function wicketacc_class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('wicketacc_class_uses_recursive')) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|string  $class
     * @return array
     */
    function wicketacc_class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $class) {
            $results += wicketacc_trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (! function_exists('wicketacc_e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param  \WicketAcc\Illuminate\Contracts\Support\DeferringDisplayableValue|\WicketAcc\Illuminate\Contracts\Support\Htmlable|\BackedEnum|string|null  $value
     * @param  bool  $doubleEncode
     * @return string
     */
    function wicketacc_e($value, $doubleEncode = true)
    {
        if ($value instanceof DeferringDisplayableValue) {
            $value = $value->resolveDisplayableValue();
        }

        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('wicketacc_env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function wicketacc_env($key, $default = null)
    {
        return Env::get($key, $default);
    }
}

if (! function_exists('wicketacc_filled')) {
    /**
     * Determine if a value is "filled".
     *
     * @param  mixed  $value
     * @return bool
     */
    function wicketacc_filled($value)
    {
        return ! wicketacc_blank($value);
    }
}

if (! function_exists('wicketacc_laravel_cloud')) {
    /**
     * Determine if the application is running on Laravel Cloud.
     *
     * @return bool
     */
    function wicketacc_laravel_cloud()
    {
        return ($_ENV['LARAVEL_CLOUD'] ?? false) === '1' ||
               ($_SERVER['LARAVEL_CLOUD'] ?? false) === '1';
    }
}

if (! function_exists('wicketacc_object_get')) {
    /**
     * Get an item from an object using "dot" notation.
     *
     * @param  object  $object
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function wicketacc_object_get($object, $key, $default = null)
    {
        if (is_null($key) || trim($key) === '') {
            return $object;
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_object($object) || ! isset($object->{$segment})) {
                return wicketacc_value($default);
            }

            $object = $object->{$segment};
        }

        return $object;
    }
}

if (! function_exists('wicketacc_optional')) {
    /**
     * Provide access to optional objects.
     *
     * @param  mixed  $value
     * @param  callable|null  $callback
     * @return mixed
     */
    function wicketacc_optional($value = null, ?callable $callback = null)
    {
        if (is_null($callback)) {
            return new Optional($value);
        } elseif (! is_null($value)) {
            return $callback($value);
        }
    }
}

if (! function_exists('wicketacc_preg_replace_array')) {
    /**
     * Replace a given pattern with each value in the array in sequentially.
     *
     * @param  string  $pattern
     * @param  array  $replacements
     * @param  string  $subject
     * @return string
     */
    function wicketacc_preg_replace_array($pattern, array $replacements, $subject)
    {
        return preg_replace_callback($pattern, function () use (&$replacements) {
            foreach ($replacements as $value) {
                return array_shift($replacements);
            }
        }, $subject);
    }
}

if (! function_exists('wicketacc_retry')) {
    /**
     * Retry an operation a given number of times.
     *
     * @param  int|array  $times
     * @param  callable  $callback
     * @param  int|\Closure  $sleepMilliseconds
     * @param  callable|null  $when
     * @return mixed
     *
     * @throws \Exception
     */
    function wicketacc_retry($times, callable $callback, $sleepMilliseconds = 0, $when = null)
    {
        $attempts = 0;

        $backoff = [];

        if (is_array($times)) {
            $backoff = $times;

            $times = count($times) + 1;
        }

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback($attempts);
        } catch (Exception $e) {
            if ($times < 1 || ($when && ! $when($e))) {
                throw $e;
            }

            $sleepMilliseconds = $backoff[$attempts - 1] ?? $sleepMilliseconds;

            if ($sleepMilliseconds) {
                Sleep::usleep(value($sleepMilliseconds, $attempts, $e) * 1000);
            }

            goto beginning;
        }
    }
}

if (! function_exists('wicketacc_str')) {
    /**
     * Get a new stringable object from the given string.
     *
     * @param  string|null  $string
     * @return \WicketAcc\Illuminate\Support\Stringable|mixed
     */
    function wicketacc_str($string = null)
    {
        if (func_num_args() === 0) {
            return new class
            {
                public function __call($method, $parameters)
                {
                    return Str::$method(...$parameters);
                }

                public function __toString()
                {
                    return '';
                }
            };
        }

        return Str::of($string);
    }
}

if (! function_exists('wicketacc_tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param  mixed  $value
     * @param  callable|null  $callback
     * @return mixed
     */
    function wicketacc_tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('wicketacc_throw_if')) {
    /**
     * Throw the given exception if the given condition is true.
     *
     * @template TException of \Throwable
     *
     * @param  mixed  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return mixed
     *
     * @throws TException
     */
    function wicketacc_throw_if($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if ($condition) {
            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }
}

if (! function_exists('wicketacc_throw_unless')) {
    /**
     * Throw the given exception unless the given condition is true.
     *
     * @template TException of \Throwable
     *
     * @param  mixed  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return mixed
     *
     * @throws TException
     */
    function wicketacc_throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
    {
        wicketacc_throw_if(! $condition, $exception, ...$parameters);

        return $condition;
    }
}

if (! function_exists('wicketacc_trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param  object|string  $trait
     * @return array
     */
    function wicketacc_trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += wicketacc_trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (! function_exists('wicketacc_transform')) {
    /**
     * Transform the given value if it is present.
     *
     * @template TValue of mixed
     * @template TReturn of mixed
     * @template TDefault of mixed
     *
     * @param  TValue  $value
     * @param  callable(TValue): TReturn  $callback
     * @param  TDefault|callable(TValue): TDefault|null  $default
     * @return ($value is empty ? ($default is null ? null : TDefault) : TReturn)
     */
    function wicketacc_transform($value, callable $callback, $default = null)
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }
}

if (! function_exists('wicketacc_windows_os')) {
    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    function wicketacc_windows_os()
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (! function_exists('wicketacc_with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @template TValue
     * @template TReturn
     *
     * @param  TValue  $value
     * @param  (callable(TValue): (TReturn))|null  $callback
     * @return ($callback is null ? TValue : TReturn)
     */
    function wicketacc_with($value, ?callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}
