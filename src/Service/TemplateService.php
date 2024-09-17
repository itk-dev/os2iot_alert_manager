<?php

namespace App\Service;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final readonly class TemplateService
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * Render template.
     *
     * @param string $template
     *   The name of the template to use
     * @param array $data
     *   The data to render the template with
     *
     * @return string
     *   The render content
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplate(string $template, array $data): string
    {
        return $this->twig->render($template, $data);
    }
}
