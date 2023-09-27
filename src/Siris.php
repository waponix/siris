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
    private ?string $file = null;

    public function __construct(private readonly Lexer $lexer)
    {
    }

    public function render($file)
    {
        $this->file = $file;
        $blocks = $this->lexer->parseFile($file);

        foreach ($blocks as &$block) {
            $this->interpretBlock($block);
        }

        echo json_encode($blocks, JSON_PRETTY_PRINT);
    }

    private function interpretBlock(array &$block, ?array $parent = null): void
    {
        $range = $this->getBlockRange($block);  
        $block['ctx'] = $this->getContext($range);
        
        if ($block['hasChild'] === true) {
            foreach ($block['children'] as &$childBlock) {
                $this->interpretBlock($childBlock, $block);
            }   
        }
    }

    private function getBlockRange(array $block): array
    {
        list($x, $y) = explode(SirisLexer::L_DIVIDER, $block['loc']);

        $pos = hexdec($x);
        $length = (hexdec($y) - $pos) + 1;

        return [
            'pos' => $pos,
            'len' => $length,
        ];
    }

    private function getContext(array $range, ?string $cache = null): string
    {
        return file_get_contents($this->file, false, null, $range['pos'], $range['len']);
    }
}