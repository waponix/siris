<?php
namespace Waponix\Siris;

use Waponix\Pocket\Attribute\Service;
use Waponix\Siris\Lexer\Lexer;
use Waponix\Siris\Lexer\BlockLexer;

#[Service(
    args: [
        'blockLexer' => BlockLexer::class,
    ]
)]
class Siris
{
    private ?string $file = null;

    public function __construct(
            private readonly Lexer $blockLexer,
            private readonly Lexer $lexer,
        )
    {
    }

    public function render($file)
    {
        $this->file = $file;
        $blocks = $this->blockLexer->parseFile($file);

        foreach ($blocks as &$block) {
            $this->interpretBlock($block);
        }

        $content = '';
        foreach ($blocks as &$block) {
            $content .= $this->buildContext($block);
        }

        $targetFile = array_filter(explode('.', $file), function ($value) {
            return $value !== 'srs';
        });

        file_put_contents(implode('.', $targetFile), $content);
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
            $placeholder = implode('', ['{@', $block['id'], '@}']);
            $parent['ctx'] = substr_replace($parent['ctx'], $placeholder, $range['pos'] - $offset, $range['len']);
        }

        // remove unnecessary characters and symbols
        switch ($block['node']) {
            case BlockLexer::NODE_COMPONENT:
                $this->normalizeComponentBlock($block);
                break;
            case BlockLexer::NODE_FORLOOP:
            case BlockLexer::NODE_IF: 
                $this->normalizeSpecialBlock($block);
                break;
            default: $this->normalizeBlock($block);
        }
    }

    private function buildContext(array $block)
    {
        $blockContext = $block['ctx'];

        if ($block['hasChild'] === true) {
            foreach ($block['children'] as $childBlock) {
                $blockContext = str_replace('{@' . $childBlock['id'] . '@}' , $this->buildContext($childBlock), $blockContext);
            }
        }

        return trim($blockContext);
    }

    private function normalizeComponentBlock(array &$block)
    {
        $tokens = $this->lexer->parse($block['ctx']);

        $token = array_shift($tokens);
        while (!!$token) {
            if ($token['data'] === BlockLexer::RSRV_KEY_CONTAINS) break;
            $token = array_shift($tokens);
        }

        // remove the last two token to remove the closing block
        array_pop($tokens);
        array_pop($tokens);

        $ctx = '';
        foreach ($tokens as $token) {
            $ctx .= $token['data'];
        }

        $block['ctx'] = $ctx;
    }

    private function normalizeSpecialBlock(array &$block)
    {
        $tokens = $this->lexer->parse($block['ctx']);

        $token = array_shift($tokens);
        while (!!$token) {
            if ($token['data'] === BlockLexer::RSRV_KEY_THEN) break;
            $token = array_shift($tokens);
        }

        // remove the last two token to remove the closing block
        array_pop($tokens);
        array_pop($tokens);

        $ctx = '';
        foreach ($tokens as $token) {
            $ctx .= $token['data'];
        }

        $block['ctx'] = $ctx;
    }

    private function normalizeBlock(array &$block)
    {
        $tokens = $this->lexer->parse($block['ctx']);

        $token = array_shift($tokens);
        while (!!$token) {
            if ($token['data'] === $block['name']) break;
            $token = array_shift($tokens);
        }

        // remove the last two token to remove the closing block
        array_pop($tokens);
        array_pop($tokens);

        $ctx = '';
        foreach ($tokens as $token) {
            $ctx .= $token['data'];
        }

        $block['ctx'] = $ctx;
    }

    private function getBlockRange(array $block): array
    {
        list($x, $y) = explode(BlockLexer::L_DIVIDER, $block['loc']);

        $pos = hexdec($x);
        $length = (hexdec($y) - $pos) + 1;

        return [
            'pos' => $pos,
            'len' => $length,
        ];
    }

    private function getContext(array $range, ?string &$cache = null, int $offset = 0): string
    {
        if ($cache !== null) {
            return substr($cache, $range['pos'] - $offset, $range['len']);    
        }

        return file_get_contents($this->file, false, null, $range['pos'], $range['len']);
    }
}