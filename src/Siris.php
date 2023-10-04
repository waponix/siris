<?php
namespace Waponix\Siris;

use Waponix\Pocket\Attribute\Service;
use Waponix\Siris\Lexer\AbstractLexer;
use Waponix\Siris\Lexer\BlockLexer;

#[Service(
    args: [
        'lexer' => BlockLexer::class,
    ]
)]
class Siris
{
    private ?string $file = null;

    public function __construct(
            private readonly AbstractLexer $lexer,
        )
    {
    }

    public function render(string $file, array $variables = [])
    {
        $blocks = $this->load($file, $variables);

        // will contain block values if the file is extending a template
        $parentBlocks = $this->getParentTemplate($blocks, $variables, $map);

        if ($parentBlocks !== null) {
            foreach ($blocks as $id => $block) {
                // get the targetted block to update
                $target = $map[$id];
                $this->updateBlock($parentBlocks, $target, $block);
            }

            // swap the blocks with the parent block
            $blocks = $parentBlocks;
        }

        $content = [];
        $pos = 0;
        foreach ($blocks as &$block) {
            $range = $this->getBlockRange($block);
            $inBetween = $this->getContext(['pos' => $pos, 'len' => ($range['pos'] - $pos)]);
            if ($inBetween !== '') {
                $content[] = $inBetween;
            }
            $pos = $range['pos'] + $range['len'];
            $content[] = $this->buildContext($block);
        }

        // try getting the last context if there is
        $endContext = $this->getContext(['pos' => $pos, 'len' => null]);
        if ($endContext !== '') {
            $content[] = $endContext;
        }

        $targetFile = array_filter(explode('.', $file), function ($value) {
            return $value !== 'srs';
        });

        $targetFile = implode('.', $targetFile);
        if (file_exists($targetFile)) {
            file_put_contents($targetFile, '');
        }

        foreach ($content as $context) {
            file_put_contents($targetFile, $context, FILE_APPEND);
        }

        return $this;
    }

    private function getParentTemplate(array &$blocks, array $variables = [], ?array &$map = []): null|array
    {
        if (current($blocks)['node'] === BlockLexer::NODE_EXTENDS) {
            $block = array_shift($blocks);
            // loading the extended template will update the source file
            $tokens = $this->lexer->parse($block['exp']);

            $start = null;
            $file = [];
            foreach ($tokens as $token) {
                if ($start === null && $token['token'] === AbstractLexer::TOKEN_QUOTE) {
                    $start = $token['data'];
                    continue;
                }

                if ($start === null) continue;

                if ($start !== null && $start === $token['data']) break;

                $file[] = $token['data'];
            }

            $parentBlocks = $this->load(implode($file), $variables, $map);

            return $parentBlocks;
        }

        return null;
    }

    private function load(string $file, array &$variables = [], ?array &$map = []): array
    {
        $this->file = $file;
        $blocks = $this->lexer->parseFile($file);
        $map = $this->lexer->getBlockMap();
        $this->lexer->reset();

        foreach ($blocks as &$block) {
            $this->interpretBlock($block, $variables);
        }

        return $blocks;
    }

    private function interpretBlock(array &$block, array &$variables = [], ?array &$parent = null): void
    {
        // get the context for the block
        $position = $range = $this->getBlockRange($block);
        $parentContext = $parent['ctx'] ?? null;
        $offset = 0;
        if ($parent !== null) {
            $offset = array_sum($this->getBlockRange($parent));
        }

        $block['ctx'] = $this->getContext($range, $parentContext, $offset);
        
        if (!empty($block['children'])) {
            foreach ($block['children'] as &$childBlock) {
                $this->interpretBlock($childBlock, $variables, $block);
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
            case BlockLexer::NODE_PRINT:
                $this->normalizePrintBlock($block, $variables);
                break;
            case BlockLexer::NODE_EXPRESSION:
            case BlockLexer::NODE_EXTENDS:
                $this->normalizeExpressionBlock($block);
                break;
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

    private function buildContext(array $block): string
    {
        $blockContext = $block['ctx'];

        if (!empty($block['children'])) {
            foreach ($block['children'] as $childBlock) {
                $blockContext = str_replace('{@' . $childBlock['id'] . '@}' , $this->buildContext($childBlock), $blockContext);
            }
        }

        return trim($blockContext);
    }

    private function normalizePrintBlock(array &$block, array &$variables = []): void
    {
        $tokens = $this->lexer->parse($block['ctx']);

        do {
            $token = array_shift($tokens);
            if ($token === null || $token['data'] === '{{') break;
        } while ($token !== null);

        // remove the last two token to remove the closing block
        array_pop($tokens);

        $exp = '';
        foreach ($tokens as $token) {
            $exp .= $token['data'];
        }

        $block['exp'] = $exp;
        $block['ctx'] = $variables[trim($exp)];
    }

    private function normalizeExpressionBlock(array &$block): void
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

        $exp = '';
        foreach ($tokens as $token) {
            $exp .= $token['data'];
        }

        $block['exp'] = $exp;
        $block['ctx'] = '';
    }

    private function normalizeComponentBlock(array &$block): void
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

    private function normalizeSpecialBlock(array &$block): void
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
        if ($range['len'] < 0) {
            var_dump($range, $cache, $offset);
        }
        if ($cache !== null) {
            return substr($cache, $range['pos'] - $offset, $range['len']);    
        }

        try {
            return file_get_contents($this->file, false, null, $range['pos'], $range['len']);
        } catch (\Exception $exception) {
            return '';
        }
    }

    private function updateBlock(array &$blocks, string $target, array $newBlock): self
    {
        $keys = explode('.', $target);
        $key = array_shift($keys);

        $found = true;
        $block = &$blocks[$key];

        $key = array_shift($keys);
        while ($key !== null) {
            if (!isset($block['children'][$key])) {
                $found = false;
                break;
            }

            $block = &$block['children'][$key];

            $key = array_shift($keys);
        }

        if ($found === true) {
            $block['ctx'] = $newBlock['ctx'];
            if (isset($newBlock['children'])) {
                $block['children'] = $newBlock['children'];
            }
        }

        return $this;
    }
}