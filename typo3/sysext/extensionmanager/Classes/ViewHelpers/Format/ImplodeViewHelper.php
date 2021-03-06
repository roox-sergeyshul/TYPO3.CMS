<?php
namespace TYPO3\CMS\Extensionmanager\ViewHelpers\Format;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * View Helper for imploding arrays
 * @internal
 */
class ImplodeViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments of this view helper
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('implode', 'array', '', true);
        $this->registerArgument('delimiter', 'string', '', false, ', ');
    }

    /**
     * Implodes a string
     *
     * @return string the altered string.
     */
    public function render()
    {
        return static::renderStatic(
            $this->arguments,
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        return implode($arguments['delimiter'], $arguments['implode']);
    }
}
