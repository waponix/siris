<?php
namespace Waponix\Siris\Lexer;

class BlockLexer extends AbstractLexer
{
    const BLOCK_START = 'BLOCK_S';
    const BLOCK_END   = 'BLOCK_E';
    const BLOCK_NAME  = 'BLOCK_N';
    const BLOCK_PRINT = 'BLOCK_P';

    const PRINT_KEY = 'print';

    const NODE_BLOCK      = 'n_block';
    const NODE_IF         = 'n_if';
    const NODE_FORLOOP    = 'n_forloop';
    const NODE_SET        = 'n_set';
    const NODE_PRINT      = 'n_print';
    const NODE_INCLUDE    = 'n_include';
    const NODE_EXTENDS    = 'n_extends';
    const NODE_ATTR       = 'n_attr';
    const NODE_COMPONENT  = 'n_component';
    const NODE_USE        = 'n_use';
    const NODE_EXPRESSION = 'n_expression';

    const RSRV_KEY_SET       = 'set';
    const RSRV_KEY_IF        = 'if';
    const RSRV_KEY_ELSEIF    = 'elseif';
    const RSRV_KEY_ELSE      = 'else';
    const RSRV_KEY_FOR       = 'foreach';
    const RSRV_KEY_INCLUDE   = 'include';
    const RSRV_KEY_EXTENDS   = 'extends';
    const RSRV_KEY_ATTR      = 'attr';
    const RSRV_KEY_COMPONENT = 'component';
    const RSRV_KEY_USE       = 'use';
    const RSRV_KEY_QM        = '?';
    const RSRV_KEY_THEN      = 'then';
    const RSRV_KEY_CONTAINS  = 'contains';

    const N_DIVIDER = '0';
    const L_DIVIDER = '.';

    const RSRV_KEYS = [
        self::RSRV_KEY_SET,
        self::RSRV_KEY_IF,
        self::RSRV_KEY_FOR,
        self::RSRV_KEY_INCLUDE,
        self::RSRV_KEY_EXTENDS,
        self::RSRV_KEY_ATTR,
        self::RSRV_KEY_COMPONENT,
        self::RSRV_KEY_USE,
        self::RSRV_KEY_QM,
    ];

    private $blocks = [];

    private $startBlocks = [];
    private $blockNames = [];
    private $lookups = [];
    private $blockGroups = [];
    private $nodes = [];
    private $offset = 0;
    private $blockMap = [];
    private $printBlockStart = null;

    public function parseFile(string $file): array
    {
        if (!file_exists($file)) {
            return []; // TODO: this should throw exception
        }

        $handle = fopen($file, 'r');

        $tokenGroup = [];
        while ($line = fgets($handle)) {
            $tokens = $this->parse($line);
        
            foreach ($tokens as $token) {
                $this->identifyBlocks($token, $tokenGroup);
            }

            // move the offset
            $this->offset += strlen($line);
        }

        fclose($handle);
        $blocks = $this->blocks;

        return $blocks;
    }

    public function getBlockMap(): array
    {
        return $this->blockMap;
    }

    public function reset(): self
    {
        $this->blocks = [];
        $this->startBlocks = [];
        $this->blockNames = [];
        $this->lookups = [];
        $this->blockGroups = [];
        $this->nodes = [];
        $this->offset = 0;
        $this->blockMap = [];

        return $this;
    }

    private function identifyBlocks(array $token, array &$tokenGroup): self
    {
        while (true) {
            while (true) {
                if ($this->getCurrentLookup() !== self::BLOCK_NAME) break;
                
                // begin looking for the block name

                if (
                    ($token['token'] === self::TOKEN_SPECIAL && $token['data'] === '?') || 
                    $token['token'] === self::TOKEN_STRING
                ) {
                    $this
                        ->setCurrentBlockName($token)
                        ->moveToNextLookup();
                }

                break 2;
            }

            while (true) {
                // only look for start if it hasn't started looking for end
                if (!(empty($this->getCurrentLookup()) || $this->getCurrentLookup() === self::BLOCK_START)) break;

                if ($token['token'] === self::TOKEN_BRACKET && $token['data'] === '{') {
                    $token['pos'] += $this->offset;
                    $tokenGroup[] = $token;
                    $this->setNextLookup(self::BLOCK_START);
                    break 2;
                }

                if ($token['token'] === self::TOKEN_SPECIAL && $token['data'] === '@') {
                    $token['pos'] += $this->offset;
                    $tokenGroup[] = $token;
                }

                if (count($tokenGroup) === 2) {
                    $this
                        ->addStartBlock($tokenGroup)
                        ->moveToNextLookup()
                        // look for block name next
                        ->setNextLookup(self::BLOCK_NAME);
                    $tokenGroup = [];
                    break 2;
                }

                $this->moveToNextLookup();
                $tokenGroup = [];
                break;
            }

            while (true) {
                // detect start of print block
                if ($this->getCurrentLookup() !== self::BLOCK_PRINT && $token['token'] === self::TOKEN_BRACKET && $token['data'] === '{{') {
                    $this->setNextLookup(self::BLOCK_PRINT);

                    $token['pos'] += $this->offset;
                    $this->addStartBlock($token);

                    break 2;
                }

                if ($this->getCurrentLookup() === self::BLOCK_PRINT && $token['token'] === self::TOKEN_BRACKET && $token['data'] === '}}') {
                    $token['pos'] += $this->offset;

                    $temp = $token;
                    $temp['data'] = self::PRINT_KEY;
                    $this->setCurrentBlockName($temp);
                    
                    $name = $this->takeCurrentBlockName();
                    $this
                        ->addBlock($name, [
                            'id' => $name,
                            'name' => '',
                            'loc' => dechex($this->takeStartBlock()['pos']) . self::L_DIVIDER . dechex($token['pos'] + 1)
                        ])
                        ->moveToNextLookup();
                    $this->printBlockStart = null;
                }

                break;
            }

            while (true) {
                // no need to look for an end block if there is no start block found
                if (!$this->hasPendingStartBlock()) break;

                // only look for end if it hasn't started looking for  start
                if (!(empty($this->getCurrentLookup()) || $this->getCurrentLookup() === self::BLOCK_END)) break;

                if ($token['token'] === self::TOKEN_SPECIAL && $token['data'] === '@') {
                    $token['pos'] += $this->offset;
                    $tokenGroup[] = $token;
                    $this->setNextLookup(self::BLOCK_END);
                    break 2;
                }
                
                if ($token['token'] === self::TOKEN_BRACKET && $token['data'] === '}') {
                    $token['pos'] += $this->offset;
                    $tokenGroup[] = $token;
                }

                if (count($tokenGroup) === 2) {
                    $name = $this->takeCurrentBlockName();
                    $this
                        ->addBlock($name, [
                            'id' => $name,
                            'name' => $this->getRealName($name),
                            'loc' => dechex($this->takeStartBlock()[0]['pos']) . self::L_DIVIDER . dechex($tokenGroup[1]['pos'])
                        ])
                        ->moveToNextLookup();
                    $tokenGroup = [];
                    break 2;
                }

                $this->moveToNextLookup();
                $tokenGroup = [];

                break;
            }

            break;
        }

        return $this;
    }

    private function addBlock(string $name, array $block): self 
    {
        $blocks = &$this->blocks;

        // loop through the blocknames to compose the nested structure
        foreach ($this->blockNames as $blockName) {
            $blocks = &$blocks[$blockName]['children'];
        }

        if (!isset($blocks[$name])) {
            $blocks[$name] = $block;
        } else {
            // make sure to merge the existing values, to overwrite only the keys from the block data
            $blocks[$name] = array_merge($blocks[$name], $block);
        }

        // set the node type
        $realName = $this->getRealName($name);
        $blocks[$name]['node'] = $node = $this->identifyNode($realName);

        if ($node === self::NODE_BLOCK) {
            $loc = $this->blockNames;
            $loc[] = $name;
            $this->blockMap[$name] = implode('.', $loc);
        }

        // set flag if node has parent
        $blocks[$name]['hasParent'] = !empty($this->blockNames) ? true : false;

        return $this;
    }

    private function identifyNode(string $name): string
    {
        return match(strtolower($name)) {
            self::RSRV_KEY_EXTENDS => self::NODE_EXTENDS,
            self::RSRV_KEY_INCLUDE => self::NODE_INCLUDE,
            self::RSRV_KEY_FOR => self::NODE_FORLOOP,
            self::RSRV_KEY_IF => self::NODE_IF,
            self::RSRV_KEY_SET => self::NODE_SET,
            self::RSRV_KEY_ATTR => self::NODE_ATTR,
            self::RSRV_KEY_COMPONENT => self::NODE_COMPONENT,
            self::RSRV_KEY_USE => self::NODE_USE,
            self::RSRV_KEY_QM => self::NODE_EXPRESSION,
            self::PRINT_KEY => self::NODE_PRINT,
            strtolower($name) => self::NODE_BLOCK,
        };
    }

    private function setNextLookup(string $lookup): self
    {
        if ($this->getCurrentLookup() === $lookup) return $this;
        $this->lookups[] = $lookup;
        return $this;
    }

    private function getCurrentLookup(): string
    {
        return end($this->lookups);
    }

    private function moveToNextLookup(): self
    {
        array_pop($this->lookups);
        return $this;
    }

    private function setCurrentBlockName(array $token): self
    {
        $name = match(in_array($token['data'], self::RSRV_KEYS) || $token['data'] === self::PRINT_KEY) { 
            true => implode(self::N_DIVIDER, [$token['data'], dechex($token['pos'] + $this->offset) . dechex($token['length'])]),
            false => $token['data']
        };

        $this->blockNames[] = $name;
        return $this;
    }

    private function getCurrentBlockName(): string
    {
        return end($this->blockNames);
    }

    private function takeCurrentBlockName(): string
    {
        return array_pop($this->blockNames);
    }

    private function moveToNextBlockName(): self
    {
        array_pop($this->blockNames);
        return $this;
    }

    private function addStartBlock(array $block)
    {
        $this->startBlocks[] = $block;
        return $this;
    }

    private function takeStartBlock(): array
    {
        return array_pop($this->startBlocks);
    }

    private function hasPendingStartBlock(): bool
    {
        return !empty($this->startBlocks);
    }

    private function getRealName($name)
    {
        return strtok($name, self::N_DIVIDER);
    }
}