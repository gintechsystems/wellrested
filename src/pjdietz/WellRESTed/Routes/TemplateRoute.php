<?php

namespace pjdietz\WellRESTed\Routes;

use InvalidArgumentException;

class TemplateRoute extends RegexRoute
{
    /**
     * Regular expression matching URL friendly characters (i.e., letters,
     * digits, hyphen and underscore)
     */
    const RE_SLUG = '[0-9a-zA-Z\-_]+';
    /** Regular expression matching digitis */
    const RE_NUM = '[0-9]+';
    /** Regular expression matching letters */
    const RE_ALPHA = '[a-zA-Z]+';
    /** Regular expression matching letters and digits */
    const RE_ALPHANUM = '[0-9a-zA-Z]+';
    /** Regular expression matching a URI template variable (e.g., {id}) */
    const URI_TEMPLATE_EXPRESSION_RE = '/{([a-zA-Z]+)}/';

    /**
     * @param string $template URI template the path must match
     * @param string $targetClassName Fully qualified name to an autoloadable handler class
     * @param string $defaultPattern Regular expression for variables
     * @param array|null $variablePatterns Map of variable names and regular expression
     */
    public function __construct(
        $template,
        $targetClassName,
        $defaultPattern = self::RE_SLUG,
        $variablePatterns = null
    ) {
        $pattern = $this->buildPattern($template, $defaultPattern, $variablePatterns);
        parent::__construct($pattern, $targetClassName);
    }

    private function buildPattern($template, $defaultPattern, $variablePatterns)
    {
        if (is_null($variablePatterns)) {
            $variablePatterns = array();
        } elseif (is_object($variablePatterns)) {
            $variablePatterns = (array) $variablePatterns;
        }

        if (!$defaultPattern) {
            $defaultPattern = self::RE_SLUG;
        }

        $pattern = '';

        // Explode the template into an array of path segments.
        if ($template[0] === '/') {
            $parts = explode('/', substr($template, 1));
        } else {
            $parts = explode('/', $template);
        }

        foreach ($parts as $part) {

            $pattern .= '\/';

            // Is this part an expression or a literal?
            if (preg_match(self::URI_TEMPLATE_EXPRESSION_RE, $part, $matches)) {

                // This part of the path is an expresion.

                if (count($matches) === 2) {

                    // Locate the name for the variable from the template.
                    $variableName = $matches[1];

                    // If the caller passed an array with this variable name
                    // as a key, use its value for the pattern here.
                    // Otherwise, use the class's current default.
                    if (isset($variablePatterns[$variableName])) {
                        $variablePattern = $variablePatterns[$variableName];
                    } else {
                        $variablePattern = $defaultPattern;
                    }

                    $pattern .= sprintf(
                        '(?<%s>%s)',
                        $variableName,
                        $variablePattern
                    );

                } else {
                    throw new InvalidArgumentException('Invalid URI Template.');
                }

            } else {
                // This part is a literal.
                $pattern .= $part;
            }

        }

        $pattern = '/^' . $pattern . '$/';
        return $pattern;
    }

}