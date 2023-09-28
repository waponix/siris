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
    const PLACHOLDER_FILLER = '@';

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

    private function interpretBlock(array &$block, ?array &$parent = null): void
    {
        // get the context for the block
        $position = $range = $this->getBlockRange($block);
        $parentContext = $parent['ctx'] ?? null;
        $offset = 0;
        if ($parent !== null) {
            $offset = array_sum($this->getBlockRange($parent));
        }

        $block['ctx'] = $this->getContext($range, $parentContext, $offset);
        
        if ($block['hasChild'] === true) {
            foreach ($block['children'] as &$childBlock) {
                $this->interpretBlock($childBlock, $block);
            }
        }

        // begin running the block instructions and modify the context when necessary 

        if ($parent !== null) {
            // replace the parent's context with  placeholder to be used later as replacement point and reduce memory usage
            $ctxLen = strlen($block['ctx']);
            $placeholder = implode('', ['<@', $block['id'], '@>']);
            $parent['ctx'] = substr_replace($parent['ctx'], $placeholder, $range['pos'] - $offset, $ctxLen);
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

    private function getContext(array $range, ?string $cache = null, int $offset = 0): string
    {
        if ($cache !== null) {
            return substr($cache, $range['pos'] - $offset, $range['len']);    
        }

        return file_get_contents($this->file, false, null, $range['pos'], $range['len']);
    }
}