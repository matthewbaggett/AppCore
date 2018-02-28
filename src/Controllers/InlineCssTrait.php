<?php
namespace Segura\AppCore\Controllers;

trait InlineCssTrait
{
    protected function renderInlineCss(array $files)
    {
        $css = '';
        foreach ($files as $file) {
            $css.= file_get_contents($file);
        }

        return "<style>{$css}</style>";
    }
}
