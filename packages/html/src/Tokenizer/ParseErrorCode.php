<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * Named parse-error codes per WHATWG §13.2.5. Each error case has a
 * specification-defined identifier; we mirror those identifiers so logs and
 * diagnostics line up with the spec's terminology.
 *
 * Only the codes the implemented states can emit are present. Adding a new
 * state (Phase 1B.2-bis) extends this enum additively.
 */
enum ParseErrorCode: string
{
    case AbruptClosingOfEmptyComment = 'abrupt-closing-of-empty-comment';
    case AbruptDoctypePublicIdentifier = 'abrupt-doctype-public-identifier';
    case AbruptDoctypeSystemIdentifier = 'abrupt-doctype-system-identifier';
    case AbsenceOfDigitsInNumericCharacterReference = 'absence-of-digits-in-numeric-character-reference';
    case CdataInHtmlContent = 'cdata-in-html-content';
    case CharacterReferenceOutsideUnicodeRange = 'character-reference-outside-unicode-range';
    case ControlCharacterInInputStream = 'control-character-in-input-stream';
    case ControlCharacterReference = 'control-character-reference';
    case EndTagWithAttributes = 'end-tag-with-attributes';
    case EndTagWithTrailingSolidus = 'end-tag-with-trailing-solidus';
    case EofBeforeTagName = 'eof-before-tag-name';
    case EofInCdata = 'eof-in-cdata';
    case EofInComment = 'eof-in-comment';
    case EofInDoctype = 'eof-in-doctype';
    case EofInScriptHtmlCommentLikeText = 'eof-in-script-html-comment-like-text';
    case EofInTag = 'eof-in-tag';
    case IncorrectlyClosedComment = 'incorrectly-closed-comment';
    case IncorrectlyOpenedComment = 'incorrectly-opened-comment';
    case InvalidCharacterSequenceAfterDoctypeName = 'invalid-character-sequence-after-doctype-name';
    case InvalidFirstCharacterOfTagName = 'invalid-first-character-of-tag-name';
    case MissingAttributeValue = 'missing-attribute-value';
    case MissingDoctypeName = 'missing-doctype-name';
    case MissingDoctypePublicIdentifier = 'missing-doctype-public-identifier';
    case MissingDoctypeSystemIdentifier = 'missing-doctype-system-identifier';
    case MissingEndTagName = 'missing-end-tag-name';
    case MissingQuoteBeforeDoctypePublicIdentifier = 'missing-quote-before-doctype-public-identifier';
    case MissingQuoteBeforeDoctypeSystemIdentifier = 'missing-quote-before-doctype-system-identifier';
    case MissingSemicolonAfterCharacterReference = 'missing-semicolon-after-character-reference';
    case MissingWhitespaceAfterDoctypePublicKeyword = 'missing-whitespace-after-doctype-public-keyword';
    case MissingWhitespaceAfterDoctypeSystemKeyword = 'missing-whitespace-after-doctype-system-keyword';
    case MissingWhitespaceBeforeDoctypeName = 'missing-whitespace-before-doctype-name';
    case MissingWhitespaceBetweenAttributes = 'missing-whitespace-between-attributes';
    case MissingWhitespaceBetweenDoctypePublicAndSystemIdentifiers = 'missing-whitespace-between-doctype-public-and-system-identifiers';
    case NestedComment = 'nested-comment';
    case NoncharacterCharacterReference = 'noncharacter-character-reference';
    case NoncharacterInInputStream = 'noncharacter-in-input-stream';
    case NullCharacterReference = 'null-character-reference';
    case SurrogateCharacterReference = 'surrogate-character-reference';
    case SurrogateInInputStream = 'surrogate-in-input-stream';
    case UnexpectedCharacterAfterDoctypeSystemIdentifier = 'unexpected-character-after-doctype-system-identifier';
    case UnexpectedCharacterInAttributeName = 'unexpected-character-in-attribute-name';
    case UnexpectedCharacterInUnquotedAttributeValue = 'unexpected-character-in-unquoted-attribute-value';
    case UnexpectedEqualsSignBeforeAttributeName = 'unexpected-equals-sign-before-attribute-name';
    case UnexpectedNullCharacter = 'unexpected-null-character';
    case UnexpectedQuestionMarkInsteadOfTagName = 'unexpected-question-mark-instead-of-tag-name';
    case UnexpectedSolidusInTag = 'unexpected-solidus-in-tag';
    case UnknownNamedCharacterReference = 'unknown-named-character-reference';
}
