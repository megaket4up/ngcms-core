<?php

namespace NG\Twig;

use Twig\Compiler;
use Twig\Node\Expression\FunctionExpression;

class NGTwigLocalPathFunctionExpression extends FunctionExpression
{
    protected function compileArguments(Compiler $compiler, $isArray = false): void
    {
        $compiler->raw($isArray ? '[' : '(');

        $compiler->string($this->getTemplateName());

        $compiler->raw($isArray ? ']' : ')');
    }
}
