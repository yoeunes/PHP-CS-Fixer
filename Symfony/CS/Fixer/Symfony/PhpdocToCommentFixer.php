<?php

/*
 * This file is part of the PHP CS utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\CS\Fixer\Symfony;

use Symfony\CS\AbstractFixer;
use Symfony\CS\Tokenizer\Token;
use Symfony\CS\Tokenizer\Tokens;

/**
 * @author Ceeram <ceeram@cakephp.org>
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
final class PhpdocToCommentFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'Docblocks should only be used on structural elements.';
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_DOC_COMMENT);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        /*
         * Should be run before all other docblock fixers so that these fixers
         * don't touch doc comments which are meant to be converted to regular
         * comments.
         */
        return 25;
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        static $controlStructures = array(
            T_FOREACH,
            T_IF,
            T_SWITCH,
            T_WHILE,
            T_FOR,
        );

        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            $nextIndex = $tokens->getNextMeaningfulToken($index);
            $nextToken = null !== $nextIndex ? $tokens[$nextIndex] : null;

            if (null === $nextToken || $nextToken->equals('}')) {
                $tokens->overrideAt($index, array(T_COMMENT, '/*'.ltrim($token->getContent(), '/*')));
                continue;
            }

            if ($this->isStructuralElement($nextToken)) {
                continue;
            }

            if ($nextToken->isGivenkind($controlStructures) && $this->isValidControl($tokens, $token, $nextIndex)) {
                continue;
            }

            if ($nextToken->isGivenkind(T_VARIABLE) && $this->isValidVariable($tokens, $token, $nextIndex)) {
                continue;
            }

            if ($nextToken->isGivenkind(T_LIST) && $this->isValidList($tokens, $token, $nextIndex)) {
                continue;
            }

            // First docblock after open tag can be file-level docblock, so its left as is.
            $prevIndex = $tokens->getPrevMeaningfulToken($index);
            if ($tokens[$prevIndex]->isGivenKind(array(T_OPEN_TAG, T_NAMESPACE))) {
                continue;
            }

            $tokens->overrideAt($index, array(T_COMMENT, '/*'.ltrim($token->getContent(), '/*')));
        }
    }

    /**
     * Check if token is a structural element.
     *
     * @see https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#3-definitions
     *
     * @param Token $token
     *
     * @return bool
     */
    private function isStructuralElement(Token $token)
    {
        static $skip = array(
            T_PRIVATE,
            T_PROTECTED,
            T_PUBLIC,
            T_FUNCTION,
            T_ABSTRACT,
            T_CONST,
            T_NAMESPACE,
            T_REQUIRE,
            T_REQUIRE_ONCE,
            T_INCLUDE,
            T_INCLUDE_ONCE,
            T_FINAL,
            T_STATIC,
        );

        return $token->isClassy() || $token->isGivenKind($skip);
    }

    /**
     * Checks control structures (while, if, foreach, switch) for correct docblock usage.
     *
     * @param Tokens $tokens
     * @param Token  $docsToken    docs Token
     * @param int    $controlIndex index of control structure Token
     *
     * @return bool
     */
    private function isValidControl(Tokens $tokens, Token $docsToken, $controlIndex)
    {
        $index = $tokens->getNextMeaningfulToken($controlIndex);
        $endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index);
        $docsContent = $docsToken->getContent();

        for ($index = $index + 1; $index < $endIndex; ++$index) {
            $token = $tokens[$index];

            if (
                $token->isGivenkind(T_VARIABLE) &&
                false !== strpos($docsContent, $token->getContent())
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks variable assignments through `list()` calls for correct docblock usage.
     *
     * @param Tokens $tokens
     * @param Token  $docsToken docs Token
     * @param int    $listIndex index of variable Token
     *
     * @return bool
     */
    private function isValidList(Tokens $tokens, Token $docsToken, $listIndex)
    {
        $endIndex = $tokens->getNextTokenOfKind($listIndex, array(')'));
        $docsContent = $docsToken->getContent();

        for ($index = $listIndex + 1; $index < $endIndex; ++$index) {
            $token = $tokens[$index];

            if (
                $token->isGivenkind(T_VARIABLE)
                && false !== strpos($docsContent, $token->getContent())
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks variable assignments for correct docblock usage.
     *
     * @param Tokens $tokens
     * @param Token  $docsToken     docs Token
     * @param int    $variableIndex index of variable Token
     *
     * @return bool
     */
    private function isValidVariable(Tokens $tokens, Token $docsToken, $variableIndex)
    {
        $nextIndex = $tokens->getNextMeaningfulToken($variableIndex);

        if (!$tokens[$nextIndex]->equals('=')) {
            return false;
        }

        return false !== strpos($docsToken->getContent(), $tokens[$variableIndex]->getContent());
    }
}
