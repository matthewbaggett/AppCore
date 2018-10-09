<?php
namespace Segura\AppCore\Traits;

use Segura\AppCore\App;
use Segura\Session\Session;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

trait RenderHtmlTrait {

    /** @var Twig */
    private $twig;
    private $extraCss = [];
    private $extraJs  = [];

    protected function addCss($path)
    {
        $this->extraCss[] = $path;
        return $this;
    }

    protected function addJs($path)
    {
        $this->extraJs = $path;
        return $this;
    }

    protected function addViews($path)
    {
        App::Instance()->addViewPath($path);
        return $this;
    }

    protected function getParameters(Request $request)
    {
        /** @var UsersModel $user */
        $user = Session::get("User");
        return [
            "path"     => $request->getUri()->getPath(),
            'extraJs'  => $this->extraJs,
            'extraCss' => $this->extraCss,
            'hostname' => gethostname(),
            'isAdmin'  => $user ? $user->getAccountType() == UsersModel::ACCOUNTTYPE_ADMIN : false,
        ];
    }

    protected function renderHtml(Request $request, Response $response, string $template, array $parameters = []) : Response
    {
        if(!$this->twig)
            $this->twig = App::Container()->get("view");
        $parameters = array_merge($this->getParameters($request), $parameters);
        return $this->twig->render(
            $response,
            $template,
            $parameters
        );
    }
}