<?php
namespace Waponix\Siris\Lexer;

/**
 * Converts a string value into an array of tokens
 */
class Lexer
{
    const TOKEN_STRING = 'STR';
    const TOKEN_NUMBER = 'NUM';
    const TOKEN_SPACE = 'SPACE';
    const TOKEN_COLON = 'COLON';
    const TOKEN_PARENTHESIS = 'PARENTHESIS';
    const TOKEN_BRACKET = 'BRACKET';
    const TOKEN_OPERATOR = 'OP';
    const TOKEN_QUOTE = 'QUOTE';
    const TOKEN_NEWLINE = 'NL';
    const TOKEN_COMMA = 'COMMA';
    const TOKEN_DOT = 'DOT';
    const TOKEN_SPECIAL = 'SPECIAL';

    // Symbol hashmap that correspond with the token
    private $symbols = [
        self::TOKEN_SPACE => [' '],
        self::TOKEN_COLON => [':', ';'],
        self::TOKEN_PARENTHESIS => ['(', ')'],
        self::TOKEN_BRACKET => ['[', '{', ']', '}'],
        self::TOKEN_OPERATOR => ['+', '-', '*', '/', '|', '&', '%', '<', '>', '=', '!', '~', '^'],
        self::TOKEN_QUOTE => ['"', "'", '`'],
        self::TOKEN_NEWLINE => ["\n"],
        self::TOKEN_COMMA => [','],
        self::TOKEN_DOT => ['.'],
        self::TOKEN_SPECIAL => ['?', '$', '@', '#', '_', '\\'],
    ];

    private $tokens = [];
    private $state = null; // state is the token name
    private $value = [];

    /**
     * Begin parsing the string
     * 
     * @param string $string
     * @return array
     */
    public function parse(string $string): array
    {
        $length = strlen($string);
        $pos = 0;

        while ($pos < $length) {
            $char = $string[$pos];

            $char1 = $nextChar = $pos + 1 < $length ? $string[$pos + 1] : null;
            $char2 = $pos + 2 < $length ? $string[$pos + 2] : null;
            $char3 = $pos + 3 < $length ? $string[$pos + 3] : null;

            $state = $this->determineState($char);

            switch ($state) {
                case self::TOKEN_DOT: $state = !$this->isRealDot($nextChar) ? $this->state : $state; break;
                case self::TOKEN_COMMA: $state = !$this->isRealComma($char1, $char2, $char3) ? $this->state : $state; break;
                case self::TOKEN_OPERATOR: $state = !$this->isRealOperator($char, $nextChar) ? self::TOKEN_NUMBER: $state; break;
            }
           
            $this->addToken($state, $char, $pos);

            $pos++;
        }

        if (!empty($this->value)) {
            // add the last token to the stack
           $this->addToken(null, null, $pos);
        }

        $tokens = $this->tokens;
        $this->tokens = []; // clear the token stack

        return $tokens;
    }

    /**
     * Determine with state/token does the character belong
     * 
     * @param string $char
     * @return string
     */
    private function determineState(string $char): string
    {
        do {
            foreach ($this->symbols as $token => $symbols) {
                if (in_array($char, $symbols) === true) {
                    $state = $token;

                    break 2;
                }
            }

            if (is_numeric($char)) {
                $state = self::TOKEN_NUMBER;

                // if previous state is not string, it is definitely a number
                if ($this->state === null || !$this->isSameState(self::TOKEN_STRING)) break;
            }

            $state = self::TOKEN_STRING;
            
            // since current state is already string, check if previous state is a number, consider it to be string
            if ($this->isSameState(self::TOKEN_NUMBER)) $this->setState(self::TOKEN_STRING);
            break;
        } while (true);

        return $state;
    }

    /**
     * Decides when to add the token to the stack
     * 
     * @param ?string $state
     * @param ?string $char
     * @param int $pos
     * @return Lexer
     */
    private function addToken(?string $state, ?string $char, int $pos): self
    {
        if ($this->isSameState($state)) {
            // concat this char to the previous value, no need to update state
            $this->appendChar($char);
        } else {
            $this->tokens[] = $this->createToken($this->state, implode('', $this->value), $pos);
            
            // reset the value and update the state
            $this
                ->resetValue()
                ->appendChar($char);
        }

        $this->setState($state);

        return $this;
    }

    /**
     * Updates the state
     * 
     * @param ?string $state
     * @return Lexer
     */
    private function setState(?string $state): self
    {
        $this->state = $state;
        return $this;
    }


    /**
     * Checks if the current state is same with the previous
     * 
     * @param null|string|array $state
     * @return bool
     */
    private function isSameState(null|string|array $state): bool
    {
        if ($this->state === null) return true;
        if (is_array($state)) return in_array($this->state, $state);
        return $this->state === $state;
    }

    /**
     * Checks if the character is actually meant to be a dot
     * 
     * @param string $nextChar
     * @return bool
     */
    private function isRealDot(?string $nextChar): bool
    {
        if ($nextChar === null) return true;
        $nextState = $this->determineState($nextChar);
        return !$this->isSameState([self::TOKEN_NUMBER, self::TOKEN_STRING]) || !in_array($nextState, [self::TOKEN_NUMBER, self::TOKEN_STRING]);
    }

    /**
     * Checks if the character is actually meant to be a comma
     * 
     * @param string $char1
     * @param string $char2
     * @param string $char3
     * @return bool
     */
    private function isRealComma(?string $char1, ?string $char2, ?string $char3): bool
    {
        if ($char1 === null || $char3 === null) return true;
        $state1 = $this->determineState($char1);
        $state2 = $this->determineState($char2);
        $state3 = $this->determineState($char3);

        return !$this->isSameState(self::TOKEN_NUMBER) || !($state1 === self::TOKEN_NUMBER && $state2 === self::TOKEN_NUMBER && $state3 === self::TOKEN_NUMBER);
    }

    /**
     * This checks if the plus or minus operator is not meant to sign a number
     * @param string $currentChar
     * @param string $nextChar
     * @return bool
     */
    private function isRealOperator(?string $currentChar, ?string $nextChar): bool
    {
        if ($nextChar === null) return true;
        if (!in_array($currentChar, ['+', '-'])) return true; // + and - are only checked because it might by signing a number

        $nextState = $this->determineState($nextChar);
        return !in_array($nextState, [self::TOKEN_NUMBER, self::TOKEN_STRING]);
    }

    /**
     * Add the char to the current value stack
     * 
     * @param ?string $char
     * @return Lexer
     */
    private function appendChar(?string $char): self
    {
        if ($char === null) return $this;
        $this->value[] = $char;
        return $this;
    }

    /**
     * Empty the current value
     * 
     * @return Lexer
     */
    private function resetValue(): self
    {
        $this->value = [];
        return $this;
    }

    /**
     * Create the token
     * 
     * @param string $token
     * @param string $value
     * @param int $pos
     * @return array
     */
    private function createToken(string $token, string $value, int $pos): array
    {
        do {
            if ($token !== self::TOKEN_NUMBER) break;

            // remove commas
            $temp = str_replace(',', '', $value);

            if (!is_numeric($temp)) $token = self::TOKEN_STRING;

            break;
        } while(true);

        $length = strlen($value);

        return [
            'token' => $token,
            'data' => $value,
            'pos' => $pos - $length,
            'length' => $length,
        ];
    }
}