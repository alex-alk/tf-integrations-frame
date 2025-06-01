<?php

namespace RequestHandler;

use Router\RouteNotFound;
use HttpClient\Message\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Router\RouteNotFoundException;

class RequestHandler implements RequestHandlerInterface
{

    public function __construct(private array $routes, private ContainerInterface $container)
    {
        
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $requestUri = $request->getUri()->getPath();
        // todo: numai dupa base url
        $requestUri = str_replace(env('APP_FOLDER'), '', $requestUri);

        $requestMethod = $request->getMethod();

        $routePath = explode('?', $requestUri)[0];

        $action = $this->routes[$requestMethod][$routePath] ?? null;

        // if the route is not simple, find one with parameters
        if (!$action) {

            foreach ($this->routes[$requestMethod] ?? [] as $route => $details) {

                // will store all the parameters value in this array
                $params = [];

                // will store all the parameters names in this array
                $paramKey = [];

                // finding if there is any {?} parameter in $route
                preg_match_all("/(?<={).+?(?=})/", $route, $paramMatches);

                // setting parameters names
                foreach ($paramMatches[0] as $key) {
                    $paramKey[] = $key;
                }

                // exploding route address
                $uri = explode("/", $route);

                // will store index number where {?} parameter is required in the $route 
                $indexNum = [];

                // storing index number, where {?} parameter is required with the help of regex
                foreach ($uri as $index => $param) {
                    if (preg_match("/{.*}/", $param)) {
                        $indexNum[] = $index;
                    }
                }

                // exploding request uri string to array to get
                // the exact index number value of parameter from $_REQUEST['uri']
                $reqUri = explode("/", $routePath);

                // running for each loop to set the exact index number with reg expression
                // this will help in matching route
                foreach ($indexNum as $key => $index) {

                    // in case if req uri with param index is empty then continue
                    // because url is not valid for this route
                    if (empty($reqUri[$index])) {
                        continue;
                    }

                    //setting params with params names
                    $params[$paramKey[$key]] = $reqUri[$index];

                    //this is to create a regex for comparing route address
                    $reqUri[$index] = "{.*}";
                }

                // wrong number of parameters
                if (count($indexNum) !== count($params)) {
                    throw new RouteNotFoundException();
                }

                //converting array to string
                $reqUri = implode("/", $reqUri);

                // replace all / with \/ for reg expression
                // regex to match route is ready
                $reqUri = str_replace("/", '\\/', $reqUri);

                //now matching route with regex
                if (preg_match("/$reqUri/", $route)) {

                    if (is_callable($details)) {
                        return call_user_func($details);
                    }

                    if (is_array($details)) {
                        [$class, $method] = $details;

                        if (class_exists($class)) {
                            $class = $this->container->get($class);

                            if (method_exists($class, $method)) {
                                $responseStr = call_user_func_array([$class, $method], $params);
                                return new Response($responseStr, 200, []);
                            }
                        }
                    }
                }
            }
            // if no route has been found
            throw new RouteNotFoundException();
        }


        if (is_callable($action)) {
            return call_user_func($action);
        }

        if (is_array($action)) {
            [$class, $method] = $action;

            if (class_exists($class)) {
                $class = $this->container->get($class);

                if (method_exists($class, $method)) {
                    $responseStr = call_user_func_array([$class, $method], []);
                    return new Response($responseStr, 200, []);
                }
            }
        }
        throw new RouteNotFoundException();
    }
}
