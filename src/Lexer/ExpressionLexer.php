<?php
namespace Waponix\Siris\Lexer;

class ExpressionLexer extends AbstractLexer
{
    const TOKEN_VARIABLE = 'VAR';
    const TOKEN_INTEGER = 'INT';
    const TOKEN_FLOAT = 'FLOAT';
    const TOKEN_BOOLEAN = 'BOOL';
    const TOKEN_NULL = 'NULL';

    const QUOTE_END = "E_QUOTE";

    private $startQuote = [];
    private $skipQuote = false;
    private $lookup = [];

    public function parse (string $string): array
    {
        $tokens = parent::parse($string);

        $stringGroup = [];
        $foundEqualSign = null;

        while (count($tokens)) {
            $token = array_shift($tokens);

            while (true) {
                // determine if backslash is meant for escaping
                if ($this->getStartQuote() !== null && $token['data'] === '\\') {
                    $this->skipQuote = true;
                    $stringGroup[] = '\\';
                    continue 2;
                }

                if ($this->skipQuote === true) {
                    // ignore this quote
                    $this->skipQuote = false;
                    // add the escaped quote to the string group
                    $stringGroup[] = $token['data'];
                    continue 2;
                }
                
                if ($this->getCurrentLookup() === self::QUOTE_END && $this->getStartQuote() === $token['data']) {

                    $stringGroup[] = $token['data'];

                    $this
                        ->clearStartQuote()
                        ->pushToken([
                            'token' => self::TOKEN_STRING,
                            'data' => implode($stringGroup),
                        ]);

                    $stringGroup = [];

                    continue 2;
                }

                if ($token['token'] === self::TOKEN_QUOTE && $this->getStartQuote() === null) {
                    $stringGroup[] = $token['data'];

                    $this
                        ->setNextLookup(self::QUOTE_END)
                        ->addStartQuote($token['data']);

                    continue 2;
                }
                
                if ($this->getStartQuote() !== null) {
                    $stringGroup[] = $token['data'];
                    continue 2;
                }
                
                break;
            }

            // swap the tokens according to the syntax
            if ($token['token'] === self::TOKEN_NUMBER && stripos($token['data'], '.') !== false) {
                $tokenType = self::TOKEN_FLOAT;
                $data = (float) $token['data'];
            } else if ($token['token'] === self::TOKEN_NUMBER) {
                $tokenType = self::TOKEN_INTEGER;
                $data = (integer) $token['data'];
            } else if ($token['token'] === self::TOKEN_STRING && in_array(strtolower($token['data']), ['true', 'false'])) {
                $tokenType = self::TOKEN_BOOLEAN;
                $data = $token['data'];
            } else if ($token['token'] === self::TOKEN_STRING && strtolower($token['data']) === 'null') {
                $tokenType = self::TOKEN_NULL;
                $data = $token['data'];
            } else if ($token['token'] === self::TOKEN_STRING && in_array(strtolower($token['data']), ['is'])) {
                $tokenType = self::TOKEN_OPERATOR;
                $data = '=';
                $foundEqualSign = true;
            } else if ($token['token'] === self::TOKEN_STRING && in_array(strtolower($token['data']), ['not'])) {
                $tokenType = self::TOKEN_OPERATOR;
                if ($foundEqualSign === true) {
                    $data = '!=';
                    $foundEqualSign = null;

                    do {
                        // rewind until it removes the previous equals sign
                        $prevToken = $this->popToken();
                    } while ($prevToken['token'] !== self::TOKEN_OPERATOR || $prevToken['data'] !== '=');
                } else {
                    $data = '!';    
                }
            } else if ($token['token'] === self::TOKEN_STRING) {
                $tokenType = self::TOKEN_VARIABLE;
                $data = '$' . $token['data'];
            } else {
                $tokenType = $token['token'];
                $data = $token['data'];

                if ($token['token'] !== self::TOKEN_SPACE) {
                    $foundEqualSign = null;
                }
            }

            $this->pushToken([
                'token' => $tokenType,
                'data' => $data,
            ]);
        };

        $tokens = $this->tokens;

        $this->tokens = [];
        return $tokens;
    }

    private function pushToken(array $token): self
    {
        $this->tokens[] = $token;
        return $this;
    }

    private function popToken(): array
    {
        return array_pop($this->tokens);
    }

    private function addStartQuote(string $char): self
    {
        $this->startQuote[] = $char;
        return $this;
    }

    private function getStartQuote(): ?string
    {
        $end = end($this->startQuote);
        return !$end ? null : $end;
    }

    private function clearStartQuote(): self
    {
        $this->startQuote = [];
        return $this;
    }

    private function setNextLookup(string $lookup): self
    {
        $this->lookup[] = $lookup;
        return $this;
    }

    private function getCurrentLookup(): ?string
    {
        $end = end($this->lookup);
        return !$end ? null : $end;
    }

    private function moveToNextLookup(): self
    {
        array_pop($this->lookup);
        return $this;
    }
}