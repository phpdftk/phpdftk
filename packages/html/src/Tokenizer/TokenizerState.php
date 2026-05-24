<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * Every state defined by WHATWG HTML §13.2.5. The enum cases are declared
 * upfront so the tokenizer's state-set is enumerable and we can wire up
 * "tokenizer state changes by tree construction" cleanly (tree construction
 * for <script>, <textarea>, <title>, etc. flips the tokenizer into a
 * special state). Not every state is implemented at Phase 1B.2 — see the
 * `Tokenizer` source for the staged implementation.
 */
enum TokenizerState
{
    case Data;
    case Rcdata;
    case Rawtext;
    case ScriptData;
    case Plaintext;
    case TagOpen;
    case EndTagOpen;
    case TagName;
    case RcdataLessThanSign;
    case RcdataEndTagOpen;
    case RcdataEndTagName;
    case RawtextLessThanSign;
    case RawtextEndTagOpen;
    case RawtextEndTagName;
    case ScriptDataLessThanSign;
    case ScriptDataEndTagOpen;
    case ScriptDataEndTagName;
    case ScriptDataEscapeStart;
    case ScriptDataEscapeStartDash;
    case ScriptDataEscaped;
    case ScriptDataEscapedDash;
    case ScriptDataEscapedDashDash;
    case ScriptDataEscapedLessThanSign;
    case ScriptDataEscapedEndTagOpen;
    case ScriptDataEscapedEndTagName;
    case ScriptDataDoubleEscapeStart;
    case ScriptDataDoubleEscaped;
    case ScriptDataDoubleEscapedDash;
    case ScriptDataDoubleEscapedDashDash;
    case ScriptDataDoubleEscapedLessThanSign;
    case ScriptDataDoubleEscapeEnd;
    case BeforeAttributeName;
    case AttributeName;
    case AfterAttributeName;
    case BeforeAttributeValue;
    case AttributeValueDoubleQuoted;
    case AttributeValueSingleQuoted;
    case AttributeValueUnquoted;
    case AfterAttributeValueQuoted;
    case SelfClosingStartTag;
    case BogusComment;
    case MarkupDeclarationOpen;
    case CommentStart;
    case CommentStartDash;
    case Comment;
    case CommentLessThanSign;
    case CommentLessThanSignBang;
    case CommentLessThanSignBangDash;
    case CommentLessThanSignBangDashDash;
    case CommentEndDash;
    case CommentEnd;
    case CommentEndBang;
    case Doctype;
    case BeforeDoctypeName;
    case DoctypeName;
    case AfterDoctypeName;
    case AfterDoctypePublicKeyword;
    case BeforeDoctypePublicIdentifier;
    case DoctypePublicIdentifierDoubleQuoted;
    case DoctypePublicIdentifierSingleQuoted;
    case AfterDoctypePublicIdentifier;
    case BetweenDoctypePublicAndSystemIdentifiers;
    case AfterDoctypeSystemKeyword;
    case BeforeDoctypeSystemIdentifier;
    case DoctypeSystemIdentifierDoubleQuoted;
    case DoctypeSystemIdentifierSingleQuoted;
    case AfterDoctypeSystemIdentifier;
    case BogusDoctype;
    case CdataSection;
    case CdataSectionBracket;
    case CdataSectionEnd;
    case CharacterReference;
    case NamedCharacterReference;
    case AmbiguousAmpersand;
    case NumericCharacterReference;
    case HexadecimalCharacterReferenceStart;
    case DecimalCharacterReferenceStart;
    case HexadecimalCharacterReference;
    case DecimalCharacterReference;
    case NumericCharacterReferenceEnd;
}
