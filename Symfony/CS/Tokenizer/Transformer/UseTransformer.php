<?php

/*
 * This file is part of the PHP CS utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\CS\Tokenizer\Transformer;

use Symfony\CS\Tokenizer\AbstractTransformer;
use Symfony\CS\Tokenizer\Token;
use Symfony\CS\Tokenizer\Tokens;

/**
 * Transform T_USE into:
 * - CT_USE_TRAIT for imports,
 * - CT_USE_LAMBDA for lambda variable uses.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class UseTransformer extends AbstractTransformer
{
    /**
     * {@inheritdoc}
     */
    public function getCustomTokenNames()
    {
        return array('CT_USE_TRAIT', 'CT_USE_LAMBDA');
    }

    /**
     * {@inheritdoc}
     */
    public function process(Tokens $tokens, Token $token, $index)
    {
        $prevTokenIndex = $tokens->getPrevMeaningfulToken($index);
        $prevToken = $prevTokenIndex === null ? null : $tokens[$prevTokenIndex];

        // Skip whole class braces content.
        // That way we can skip whole tokens in class declaration, therefore skip `T_USE` for traits.
        if ($token->isClassy() && !$prevToken->isGivenKind(T_DOUBLE_COLON)) {
            $index = $tokens->getNextTokenOfKind($index, array('{'));
            $innerLimit = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $index);

            while ($index < $innerLimit) {
                $token = $tokens[++$index];

                if (!$token->isGivenKind(T_USE)) {
                    continue;
                }

                if ($this->isUseForLambda($tokens, $index)) {
                    $token->override(array(CT_USE_LAMBDA, $token->getContent()));
                } else {
                    $token->override(array(CT_USE_TRAIT, $token->getContent()));
                }
            }

            return;
        }

        if ($token->isGivenKind(T_USE) && $this->isUseForLambda($tokens, $index)) {
            $token->override(array(CT_USE_LAMBDA, $token->getContent()));
        }
    }

    /**
     * Check if token under given index is `use` statement for lambda function.
     *
     * @param Tokens $tokens
     * @param int    $index
     *
     * @return bool
     */
    private function isUseForLambda(Tokens $tokens, $index)
    {
        $nextToken = $tokens[$tokens->getNextMeaningfulToken($index)];

        // test `function () use ($foo) {}` case
        return $nextToken->equals('(');
    }
}
