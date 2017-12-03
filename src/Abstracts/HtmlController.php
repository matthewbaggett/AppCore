<?php
namespace Segura\AppCore\Abstracts;

use Segura\AppCore\App;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class HtmlController extends Controller
{
    /** @var Twig */
    private $twig;
    private $extraCss = [];
    private $extraJs = [];

    public function __construct()
    {
        $this->twig = App::Container()->get("view");
    }

    protected function addCss($path){
        $this->extraCss[] = $path;
        return $this;
    }

    protected function addJs($path){
        $this->extraJs = $path;
        return $this;
    }

    protected function addViews($path){
        App::Instance()->addViewPath($path);
        return $this;
    }

    protected function getParameters(){
        return [
            'extraJs' => $this->extraJs,
            'extraCss' => $this->extraCss,
        ];
    }

    protected function renderHtml(Response $response, string $template, array $parameters = []){
        $parameters = array_merge($this->getParameters(), $parameters);
        return $this->twig->render(
            $response,
            $template,
            $parameters
        );
    }
}
