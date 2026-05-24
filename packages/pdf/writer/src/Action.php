<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Pdf\Core\Action\Action as CoreAction;
use Phpdftk\Pdf\Core\Action\GoToAction;
use Phpdftk\Pdf\Core\Action\GoToRAction;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Action\NamedAction;
use Phpdftk\Pdf\Core\Action\ResetFormAction;
use Phpdftk\Pdf\Core\Action\SubmitFormAction;
use Phpdftk\Pdf\Core\Action\URIAction;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

/**
 * Static factory for the most common PDF action types. Each method
 * returns an `Action` subclass ready to be assigned to
 * `Catalog::$openAction`, an annotation's `/A` entry, or a form
 * field's `/AA` (additional actions) dict.
 *
 * For action types not covered here, construct the relevant
 * `Phpdftk\Pdf\Core\Action\*` class directly.
 */
final class Action
{
    /** URI action — open a URL in the user's browser. */
    public static function uri(string $url): URIAction
    {
        return new URIAction(new PdfString($url));
    }

    /** GoTo action — jump to an explicit destination within this document. */
    public static function goTo(Destination|PdfReference $target): GoToAction
    {
        return new GoToAction($target);
    }

    /**
     * GoToR (Go-to-remote) — jump to a destination in another PDF.
     * `$dest` may be a destination array, a name string referring to a
     * named destination, or a `Destination` value object.
     */
    public static function goToRemote(string $file, Destination|PdfReference|string $dest): GoToRAction
    {
        $destValue = is_string($dest) ? new PdfString($dest) : $dest;
        return new GoToRAction(new PdfString($file), $destValue);
    }

    /** JavaScript action — execute the given source string when triggered. */
    public static function javascript(string $code): JavaScriptAction
    {
        return new JavaScriptAction(new PdfString($code));
    }

    /**
     * Launch action — run an application or open a file via the
     * platform's default handler.
     */
    public static function launch(string $file): LaunchAction
    {
        $action = new LaunchAction();
        $action->f = new FileSpec($file);
        return $action;
    }

    /**
     * Named action — one of the four predefined names (`NextPage`,
     * `PrevPage`, `FirstPage`, `LastPage`) or a viewer-specific
     * extension.
     */
    public static function namedAction(string $name): NamedAction
    {
        return new NamedAction(new PdfName($name));
    }

    /**
     * Reset-form action — clear form field values to their defaults.
     * `$fields` is an optional list of field names; `null` resets all.
     * `$includeExclude` controls /Flags bit 1 (true = include, false = exclude).
     *
     * @param list<string>|null $fields
     */
    public static function resetForm(?array $fields = null, bool $includeExclude = false): ResetFormAction
    {
        $action = new ResetFormAction();
        if ($fields !== null) {
            $action->fields = new PdfArray(array_map(
                static fn(string $f) => new PdfString($f),
                $fields,
            ));
        }
        if ($includeExclude) {
            $action->flags = 1;
        }
        return $action;
    }

    /**
     * Submit-form action — post form field values to an HTTP endpoint.
     */
    public static function submitForm(string $url): SubmitFormAction
    {
        return new SubmitFormAction(new FileSpec($url));
    }
}
