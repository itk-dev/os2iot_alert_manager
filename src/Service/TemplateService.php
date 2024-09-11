<?php

namespace App\Service;

use Twig\Environment;

final readonly class TemplateService
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function renderTemplate(string $template, array $data): string
    {
        return $this->twig->render($template, $data);
    }
}
