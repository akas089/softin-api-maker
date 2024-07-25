<?php
class router
{

  var $mainDir = "";
  var $groupRoute = "";
  var $requestUrl = "";
  var $routeCompareArray = array();

  function __construct($mainDir = "")
  {
    $this->mainDir = $mainDir;
    $this->requestUrl = filter_var(@$_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
  }

  function get($route, $path_to_include)
  {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
      $this->route($route, $path_to_include);
    }
  }

  function post($route, $path_to_include)
  {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $this->route($route, $path_to_include);
    }
  }

  function put($route, $path_to_include)
  {
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
      $this->route($route, $path_to_include);
    }
  }

  function patch($route, $path_to_include)
  {
    if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
      $this->route($route, $path_to_include);
    }
  }

  function delete($route, $path_to_include)
  {
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
      $this->route($route, $path_to_include);
    }
  }

  function any($route, $path_to_include)
  {
    $this->route($route, $path_to_include);
  }

  function routeMatch($route)
  {
    $request_url = $this->requestUrl;
    $request_url = strtok($request_url, '?');
    $request_url = rtrim($request_url, '/');
    $route_parts = explode('/', ltrim($route, $this->mainDir));
    $reurl_parts = explode('/', ltrim($request_url, $this->mainDir));
    $this->routeCompareArray = array("route" => $route_parts, "url" => $reurl_parts);
    return ($route_parts[0] == $reurl_parts[0]);
  }

  /**
   * Dynamically handle middleware into the router class.
   *
   * @param  $function $func
   * @param  array  $parameters
   * @return router $this
   *
   */
  function middleware($func, $errorMessage)
  {
    if ($func()) {
      return $this;
    } else {
      exit('{"error":"$errorMessage"}');
    }
  }

  function group($route, $func)
  {
    $this->groupRoute = $route;
    $func($this);
    $this->groupRoute = "";
  }

  function route($route, $path_to_include)
  {
    if ($this->groupRoute != "") {
      $route = $this->groupRoute . $route;
    }

    $route = ($route == "/" ? $this->mainDir : $this->mainDir . $route);
    $callback = $path_to_include;

    if ($this->routeMatch($route)) {
      if (!is_callable($callback)) {
        if (!strpos($path_to_include, '.php')) {
          $path_to_include .= '.php';
        }
      }

      $route_parts = $this->routeCompareArray['route'];
      $request_url_parts = $this->routeCompareArray['url'];
      array_shift($route_parts);
      array_shift($request_url_parts);

      $parameters = [];
      for ($__i__ = 0; $__i__ < count($route_parts); $__i__++) {
        $route_part = $route_parts[$__i__];
        if (preg_match("/^[$]/", $route_part)) {
          if (substr($route_part, -1, 1) == '?') {
            if (count($route_parts) != count($request_url_parts)) {
              array_push($request_url_parts, '');
            }
            $route_part = rtrim($route_part, '?');
          }
          $route_part = ltrim($route_part, '$');
          $request_value = addslashes(trim(ew_RemoveXSS(@$request_url_parts[$__i__])));
          $parameters[$route_part] = $request_value;
          //$$route_part = $request_value;
        } else if ($route_parts[$__i__] != $request_url_parts[$__i__]) {
          return;
        }
      }

      if (count($route_parts) != count($request_url_parts)) {
        return;
      }

      $request = arrayToObject($parameters);
      $payload = arrayToObject($_REQUEST);

      // Callback function
      if (is_callable($callback)) {
        exit(call_user_func($callback, $request, $payload));
      }

      include_once $path_to_include;
      exit();
    } else {
      if ($route == $this->mainDir . "/404") {
        if (is_callable($callback)) {
          exit(call_user_func_array($callback, []));
        }
        include_once $path_to_include;
        exit();
      }
    }
  }
}