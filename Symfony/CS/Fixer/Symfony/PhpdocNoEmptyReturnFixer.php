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
use Symfony\CS\DocBlock\Annotation;
use Symfony\CS\DocBlock\DocBlock;
use Symfony\CS\Tokenizer\Tokens;

/**
 * @author Graham Campbell <graham@mineuk.com>
 */
final class PhpdocNoEmptyReturnFixer extends AbstractFixer
{
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
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach ($tokens as $token) {
            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            $doc = new DocBlock($token->getContent());
            $annotations = $doc->getAnnotationsOfType('return');

            if (empty($annotations)) {
                continue;
            }

            foreach ($annotations as $annotation) {
                $this->fixAnnotation($doc, $annotation);
            }

            $token->setContent($doc->getContent());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return '@return void and @return null annotations should be omitted from phpdocs.';
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // must be run before the PhpdocSeparationFixer and PhpdocOrderFixer
        return 10;
    }

    /**
     * Remove return void or return null annotations..
     *
     * @param DocBlock   $doc
     * @param Annotation $annotation
     */
    private function fixAnnotation(DocBlock $doc, Annotation $annotation)
    {
        if (1 === preg_match('/@return\s+(void|null)(?!\|)/', $doc->getLine($annotation->getStart())->getContent())) {
            $annotation->remove();
        }
    }
}
