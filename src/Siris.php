<?php
namespace Waponix\Siris;

use Waponix\Pocket\Attribute\Service;
use Waponix\Siris\Lexer\Lexer;
use Waponix\Siris\Lexer\SirisLexer;

#[Service(
    args: [
        'lexer' => SirisLexer::class,
    ]
)]
class Siris
{
    public function __construct(private readonly Lexer $lexer)
    {
    }

    public function render($file)
    {

    }
}