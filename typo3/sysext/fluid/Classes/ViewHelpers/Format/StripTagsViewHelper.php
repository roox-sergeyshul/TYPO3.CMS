<?php
namespace TYPO3\CMS\Fluid\ViewHelpers\Format;

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
 * Removes tags from the given string (applying PHPs strip_tags() function)
 *
 * @see http://www.php.net/manual/function.strip-tags.php
 *
 * = Examples =
 *
 * <code title="default notation">
 * <f:format.stripTags>Some Text with <b>Tags</b> and an &Uuml;mlaut.</f:format.stripTags>
 * </code>
 * <output>
 * Some Text with Tags and an &Uuml;mlaut. (strip_tags() applied. Note: encoded entities are not decoded)
 * </output>
 *
 * <code title="inline notation">
 * {text -> f:format.stripTags()}
 * </code>
 * <output>
 * Text without tags (strip_tags() applied)
 * </output>
 *
 * @api
 */
class StripTagsViewHelper extends AbstractViewHelper
{
    /**
     * No output escaping as some tags may be allowed
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initialize ViewHelper arguments
     *
     * @return void
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        $this->registerArgument('value', 'string', 'string to format');
        $this->registerArgument('allowedTags', 'string', 'Optional string of allowed tags as required by PHPs strip_tags() f
unction');
    }

    /**
     * To ensure all tags are removed, child node's output must not be escaped
     *
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * Escapes special characters with their escaped counterparts as needed using PHPs strip_tags() function.
     *
     * @return mixed
     * @see http://www.php.net/manual/function.strip-tags.php
     * @api
     */
    public function render()
    {
        $value = $this->arguments['value'];
        $allowedTags = $this->arguments['allowedTags'];

        return static::renderStatic(
            [
                'value' => $value,
                'allowedTags' => $allowedTags
            ],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * Applies strip_tags() on the specified value.
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param \TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $value = $arguments['value'];
        $allowedTags = $arguments['allowedTags'];
        if ($value === null) {
            $value = $renderChildrenClosure();
        }
        if (!is_string($value)) {
            return $value;
        }
        return strip_tags($value, $allowedTags);
    }
}
