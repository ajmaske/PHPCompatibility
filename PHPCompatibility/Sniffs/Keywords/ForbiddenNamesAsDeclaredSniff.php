<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\Keywords;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHPCSUtils\BackCompat\BCTokens;
use PHPCSUtils\Utils\ObjectDeclarations;
use PHPCSUtils\Utils\Namespaces;

/**
 * Detects the use of some reserved keywords to name a class, interface, trait or namespace.
 *
 * Emits errors for reserved words and warnings for soft-reserved words.
 *
 * PHP version 7.0+
 *
 * @link https://www.php.net/manual/en/reserved.other-reserved-words.php
 * @link https://wiki.php.net/rfc/reserve_more_types_in_php_7
 *
 * @since 7.0.8
 * @since 7.1.4 This sniff now throws a warning (soft reserved) or an error (reserved) depending
 *              on the `testVersion` set. Previously it would always throw an error.
 */
class ForbiddenNamesAsDeclaredSniff extends Sniff
{

    /**
     * List of tokens which can not be used as class, interface, trait names or as part of a namespace.
     *
     * @since 7.0.8
     *
     * @var array
     */
    protected $forbiddenTokens = [
        \T_NULL  => '7.0',
        \T_TRUE  => '7.0',
        \T_FALSE => '7.0',
    ];

    /**
     * T_STRING keywords to recognize as forbidden names.
     *
     * @since 7.0.8
     *
     * @var array
     */
    protected $forbiddenNames = [
        'null'     => '7.0',
        'true'     => '7.0',
        'false'    => '7.0',
        'bool'     => '7.0',
        'int'      => '7.0',
        'float'    => '7.0',
        'string'   => '7.0',
        'iterable' => '7.1',
        'void'     => '7.1',
        'object'   => '7.2',
    ];

    /**
     * T_STRING keywords to recognize as soft reserved names.
     *
     * Using any of these keywords to name a class, interface, trait or namespace
     * is highly discouraged since they may be used in future versions of PHP.
     *
     * @since 7.0.8
     *
     * @var array
     */
    protected $softReservedNames = [
        'resource' => '7.0',
        'object'   => '7.0',
        'mixed'    => '7.0',
        'numeric'  => '7.0',
    ];

    /**
     * Combined list of the two lists above.
     *
     * Used for quick check whether or not something is a reserved
     * word.
     * Set from the `register()` method.
     *
     * @since 7.0.8
     *
     * @var array
     */
    private $allForbiddenNames = [];


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @since 7.0.8
     *
     * @return array
     */
    public function register()
    {
        // Do the list merge only once.
        $this->allForbiddenNames = \array_merge($this->forbiddenNames, $this->softReservedNames);

        $targets = [
            \T_CLASS,
            \T_INTERFACE,
            \T_TRAIT,
            \T_NAMESPACE,
        ];

        return $targets;
    }


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @since 7.0.8
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsAbove('7.0') === false) {
            return;
        }

        $tokens    = $phpcsFile->getTokens();
        $tokenCode = $tokens[$stackPtr]['code'];

        if (isset(BCTokens::ooScopeTokens()[$tokenCode]) === true) {
            $name = ObjectDeclarations::getName($phpcsFile, $stackPtr);

            if (isset($name) === false || \is_string($name) === false || $name === '') {
                return;
            }

            $nameLc = \strtolower($name);
            if (isset($this->allForbiddenNames[$nameLc]) === false) {
                return;
            }
        }

        if ($tokenCode === \T_NAMESPACE) {
            $namespaceName = Namespaces::getDeclaredName($phpcsFile, $stackPtr);

            if ($namespaceName === false || $namespaceName === '') {
                return;
            }

            $namespaceParts = \explode('\\', $namespaceName);
            foreach ($namespaceParts as $namespacePart) {
                $partLc = \strtolower($namespacePart);
                if (isset($this->allForbiddenNames[$partLc]) === true) {
                    $name   = $namespacePart;
                    $nameLc = $partLc;
                    break;
                }
            }
        }

        if (isset($name, $nameLc) === false) {
            return;
        }

        // Still here, so this is one of the reserved words.
        // Build up the error message.
        $error     = "'%s' is a";
        $isError   = null;
        $errorCode = $this->stringToErrorCode($nameLc) . 'Found';
        $data      = [
            $nameLc,
        ];

        if (isset($this->softReservedNames[$nameLc]) === true
            && $this->supportsAbove($this->softReservedNames[$nameLc]) === true
        ) {
            $error  .= ' soft reserved keyword as of PHP version %s';
            $isError = false;
            $data[]  = $this->softReservedNames[$nameLc];
        }

        if (isset($this->forbiddenNames[$nameLc]) === true
            && $this->supportsAbove($this->forbiddenNames[$nameLc]) === true
        ) {
            if (isset($isError) === true) {
                $error .= ' and a';
            }
            $error  .= ' reserved keyword as of PHP version %s';
            $isError = true;
            $data[]  = $this->forbiddenNames[$nameLc];
        }

        if (isset($isError) === true) {
            $error .= ' and should not be used to name a class, interface or trait or as part of a namespace (%s)';
            $data[] = $tokens[$stackPtr]['type'];

            $this->addMessage($phpcsFile, $error, $stackPtr, $isError, $errorCode, $data);
        }
    }
}
