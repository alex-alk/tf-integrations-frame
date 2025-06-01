<?php

namespace Router;

class View
{
    public function __construct(protected string $view, protected array $params = []){}
    
    public static function make(string $view, array $params = []):static
    {
        return new static($view, $params);
    }
    
    public function render(): string
    {
        $viewPath =  __DIR__. '/../../views/' .$this->view . '.php';
        
        if (!file_exists($viewPath)) {
            throw new ViewNotFoundException();
        }
        
        extract($this->params);
        
        ob_start();
        include $viewPath;
        return (string) ob_get_clean();
    }
    
    public function __toString(): string
    {
        return $this->render();
    }
}
