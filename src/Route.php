<?php

namespace Core;

use Core\Helpers\Redirect;

// add Route class , subdomain is optional
class Route
{
    public static array $patterns = [];
    public static bool $hasRoute = false;
    public static array $routes = [];
    public static string $prefix = '';

    /**
     * @param string $path
     * @param $callback
     * @return Route
     */
    public static function get(string $path, $callback): Route
    {
        self::$routes['get'][self::$prefix . $path] = ['callback' => $callback];
        return new self();
    }

    /**
     * @param string $path
     * @param $callback
     * @return void
     */
    public static function post(string $path, $callback): void
    {
        self::$routes['post'][$path] = ['callback' => $callback];
    }

    /**
     * @return void
     */
    public static function dispatch(): void
    {
        $url = self::getURL();
        $method = self::getMethod();

        foreach (self::$routes[$method] as $path => $props) {

            //print_r(self::$routes);
            foreach (self::$patterns as $key => $pattern) {
                $path = preg_replace('#' . $key . '#', $pattern, $path);
            }
            $pattern = '#^' . $path . '$#';

            if (preg_match($pattern, $url, $params)) {
                self::$hasRoute = true;
                array_shift($params);

                if (isset($props['redirect'])) {
                    Redirect::to($props['redirect'], $props['status']);
                } else {
                    $callback = $props['callback'];

                    if (is_callable($callback)) {
                        echo call_user_func_array($callback, $params);
                    } elseif (is_string($callback)) {
                        [$controllerName, $methodName] = explode('@', $callback);
                        $controllerName = '\App\Controllers\\' . $controllerName;
                        $controller = new $controllerName();
                        echo call_user_func_array([$controller, $methodName], $params);
                        //echo $controller->{$methodName}();
                    }
                }
            }
        }

        self::hasRoute();
    }

    public static function hasRoute(): void
    {
        if (self::$hasRoute === false) {
            //echo 'page not found';
            Redirect::to('/');
        }
    }

    /**
     * @return string
     */
    public static function getMethod(): string
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return string
     */
    public static function getURL(): string
    {
        return str_replace(BASE_PATH, false, $_SERVER['REQUEST_URI']);
    }

    /**
     * @param string $name
     * @return void
     */
    public function name(string $name): void
    {
        $key = array_key_last(self::$routes['get']);
        self::$routes['get'][$key]['name'] = $name;
        //print_r(self::$routes);
    }

    /**
     * @param string $name
     * @param array $params
     * @return string
     */
    public static function url(string $name, array $params = []): string
    {
        $route = array_filter(self::$routes['get'], function ($route) use ($name) {
            return isset($route[$name]) && $route['name'] === $name;
        });

        $route = array_key_first($route);
        return str_replace(
            array_map(fn($key) => ':' . $key, array_keys($params)),
            array_values($params),
            $route
        );
    }

    /**
     * @param $prefix
     * @return Route
     */
    public static function prefix($prefix): Route
    {
        self::$prefix = $prefix;
        return new self();
    }

    /**
     * @param \Closure $closure
     * @return void
     */
    public static function group(\Closure $closure): void
    {
        $closure();
        self::$prefix = '';
    }

    public static function where($key, $pattern): void
    {
        self::$patterns[':' . $key] = '(' . $pattern . ')';
    }

    public static function redirect($from, $to, $status = 301): void
    {
        $froms = array_filter(explode(',', $from));
        $froms = array_map('trim', $froms);

        foreach ($froms as $from_) {
            self::$routes['get'][$from_] = ['redirect' => $to, 'status' => $status];
        }
    }

    /**
     * @param $subdomain
     * @param \Closure $closure
     * @return void
     */
    public static function subdomain($subdomain, \Closure $closure): void
    {
        $currentSubdomain = self::getSubdomain();
        if ($currentSubdomain == $subdomain) {
            $closure();
        }
    }

    public static function getSubdomain(): string
    {
        $hostParts = explode('.', $_SERVER['HTTP_HOST']);
        if (count($hostParts) >= 3) {
            return implode('.', array_slice($hostParts, 0, -2));
        } else {
            return '';
        }
    }

    // sabitler
    const BASE_PATH = '/';
    const STATUS_301 = 301;
}
