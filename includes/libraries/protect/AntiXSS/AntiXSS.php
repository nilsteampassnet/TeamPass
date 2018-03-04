<?php

namespace protect\AntiXSS;

use protect\AntiXSS\bootup;
use protect\AntiXSS\UTF8;
require_once(dirname(__FILE__)."/bootup.php");
require_once(dirname(__FILE__)."/UTF8.php");

/**
 * Anti XSS library
 *
 * ported from "CodeIgniter"
 *
 * @author      EllisLab Dev Team
 * @author      Lars Moelleken
 * @copyright   Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright   Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @copyright   Copyright (c) 2015 - 2017, Lars Moelleken (https://moelleken.org/)
 *
 * @license     http://opensource.org/licenses/MIT	MIT License
 */
final class AntiXSS
{

  /**
   * @var array
   */
  private static $entitiesFallback = array(
      "\t" => '&Tab;',
      "\n" => '&NewLine;',
      '!'  => '&excl;',
      '"'  => '&quot;',
      '#'  => '&num;',
      '$'  => '&dollar;',
      '%'  => '&percnt;',
      '&'  => '&amp;',
      "'"  => '&apos;',
      '('  => '&lpar;',
      ')'  => '&rpar;',
      '*'  => '&ast;',
      '+'  => '&plus;',
      ','  => '&comma;',
      '.'  => '&period;',
      '/'  => '&sol;',
      ':'  => '&colon;',
      ';'  => '&semi;',
      '<'  => '&lt;',
      '<⃒' => '&nvlt;',
      '='  => '&equals;',
      '=⃥' => '&bne;',
      '>'  => '&gt;',
      '>⃒' => '&nvgt',
      '?'  => '&quest;',
      '@'  => '&commat;',
      '['  => '&lbrack;',
      ']'  => '&rsqb;',
      '^'  => '&Hat;',
      '_'  => '&lowbar;',
      '`'  => '&grave;',
      'fj' => '&fjlig;',
      '{'  => '&lbrace;',
      '|'  => '&vert;',
      '}'  => '&rcub;',
      ' '  => '&nbsp;',
      '¡'  => '&iexcl;',
      '¢'  => '&cent;',
      '£'  => '&pound;',
      '¤'  => '&curren;',
      '¥'  => '&yen;',
      '¦'  => '&brvbar;',
      '§'  => '&sect;',
      '¨'  => '&DoubleDot;',
      '©'  => '&copy;',
      'ª'  => '&ordf;',
      '«'  => '&laquo;',
      '¬'  => '&not;',
      '­'  => '&shy;',
      '®'  => '&reg;',
      '¯'  => '&macr;',
      '°'  => '&deg;',
      '±'  => '&plusmn;',
      '²'  => '&sup2;',
      '³'  => '&sup3;',
      '´'  => '&DiacriticalAcute;',
      'µ'  => '&micro;',
      '¶'  => '&para;',
      '·'  => '&CenterDot;',
      '¸'  => '&Cedilla;',
      '¹'  => '&sup1;',
      'º'  => '&ordm;',
      '»'  => '&raquo;',
      '¼'  => '&frac14;',
      '½'  => '&half;',
      '¾'  => '&frac34;',
      '¿'  => '&iquest;',
      'À'  => '&Agrave;',
      'Á'  => '&Aacute;',
      'Â'  => '&Acirc;',
      'Ã'  => '&Atilde;',
      'Ä'  => '&Auml;',
      'Å'  => '&Aring;',
      'Æ'  => '&AElig;',
      'Ç'  => '&Ccedil;',
      'È'  => '&Egrave;',
      'É'  => '&Eacute;',
      'Ê'  => '&Ecirc;',
      'Ë'  => '&Euml;',
      'Ì'  => '&Igrave;',
      'Í'  => '&Iacute;',
      'Î'  => '&Icirc;',
      'Ï'  => '&Iuml;',
      'Ð'  => '&ETH;',
      'Ñ'  => '&Ntilde;',
      'Ò'  => '&Ograve;',
      'Ó'  => '&Oacute;',
      'Ô'  => '&Ocirc;',
      'Õ'  => '&Otilde;',
      'Ö'  => '&Ouml;',
      '×'  => '&times;',
      'Ø'  => '&Oslash;',
      'Ù'  => '&Ugrave;',
      'Ú'  => '&Uacute;',
      'Û'  => '&Ucirc;',
      'Ü'  => '&Uuml;',
      'Ý'  => '&Yacute;',
      'Þ'  => '&THORN;',
      'ß'  => '&szlig;',
      'à'  => '&agrave;',
      'á'  => '&aacute;',
      'â'  => '&acirc;',
      'ã'  => '&atilde;',
      'ä'  => '&auml;',
      'å'  => '&aring;',
      'æ'  => '&aelig;',
      'ç'  => '&ccedil;',
      'è'  => '&egrave;',
      'é'  => '&eacute;',
      'ê'  => '&ecirc;',
      'ë'  => '&euml;',
      'ì'  => '&igrave;',
      'í'  => '&iacute;',
      'î'  => '&icirc;',
      'ï'  => '&iuml;',
      'ð'  => '&eth;',
      'ñ'  => '&ntilde;',
      'ò'  => '&ograve;',
      'ó'  => '&oacute;',
      'ô'  => '&ocirc;',
      'õ'  => '&otilde;',
      'ö'  => '&ouml;',
      '÷'  => '&divide;',
      'ø'  => '&oslash;',
      'ù'  => '&ugrave;',
      'ú'  => '&uacute;',
      'û'  => '&ucirc;',
      'ü'  => '&uuml;',
      'ý'  => '&yacute;',
      'þ'  => '&thorn;',
      'ÿ'  => '&yuml;',
      'Ā'  => '&Amacr;',
      'ā'  => '&amacr;',
      'Ă'  => '&Abreve;',
      'ă'  => '&abreve;',
      'Ą'  => '&Aogon;',
      'ą'  => '&aogon;',
      'Ć'  => '&Cacute;',
      'ć'  => '&cacute;',
      'Ĉ'  => '&Ccirc;',
      'ĉ'  => '&ccirc;',
      'Ċ'  => '&Cdot;',
      'ċ'  => '&cdot;',
      'Č'  => '&Ccaron;',
      'č'  => '&ccaron;',
      'Ď'  => '&Dcaron;',
      'ď'  => '&dcaron;',
      'Đ'  => '&Dstrok;',
      'đ'  => '&dstrok;',
      'Ē'  => '&Emacr;',
      'ē'  => '&emacr;',
      'Ė'  => '&Edot;',
      'ė'  => '&edot;',
      'Ę'  => '&Eogon;',
      'ę'  => '&eogon;',
      'Ě'  => '&Ecaron;',
      'ě'  => '&ecaron;',
      'Ĝ'  => '&Gcirc;',
      'ĝ'  => '&gcirc;',
      'Ğ'  => '&Gbreve;',
      'ğ'  => '&gbreve;',
      'Ġ'  => '&Gdot;',
      'ġ'  => '&gdot;',
      'Ģ'  => '&Gcedil;',
      'Ĥ'  => '&Hcirc;',
      'ĥ'  => '&hcirc;',
      'Ħ'  => '&Hstrok;',
      'ħ'  => '&hstrok;',
      'Ĩ'  => '&Itilde;',
      'ĩ'  => '&itilde;',
      'Ī'  => '&Imacr;',
      'ī'  => '&imacr;',
      'Į'  => '&Iogon;',
      'į'  => '&iogon;',
      'İ'  => '&Idot;',
      'ı'  => '&inodot;',
      'Ĳ'  => '&IJlig;',
      'ĳ'  => '&ijlig;',
      'Ĵ'  => '&Jcirc;',
      'ĵ'  => '&jcirc;',
      'Ķ'  => '&Kcedil;',
      'ķ'  => '&kcedil;',
      'ĸ'  => '&kgreen;',
      'Ĺ'  => '&Lacute;',
      'ĺ'  => '&lacute;',
      'Ļ'  => '&Lcedil;',
      'ļ'  => '&lcedil;',
      'Ľ'  => '&Lcaron;',
      'ľ'  => '&lcaron;',
      'Ŀ'  => '&Lmidot;',
      'ŀ'  => '&lmidot;',
      'Ł'  => '&Lstrok;',
      'ł'  => '&lstrok;',
      'Ń'  => '&Nacute;',
      'ń'  => '&nacute;',
      'Ņ'  => '&Ncedil;',
      'ņ'  => '&ncedil;',
      'Ň'  => '&Ncaron;',
      'ň'  => '&ncaron;',
      'ŉ'  => '&napos;',
      'Ŋ'  => '&ENG;',
      'ŋ'  => '&eng;',
      'Ō'  => '&Omacr;',
      'ō'  => '&omacr;',
      'Ő'  => '&Odblac;',
      'ő'  => '&odblac;',
      'Œ'  => '&OElig;',
      'œ'  => '&oelig;',
      'Ŕ'  => '&Racute;',
      'ŕ'  => '&racute;',
      'Ŗ'  => '&Rcedil;',
      'ŗ'  => '&rcedil;',
      'Ř'  => '&Rcaron;',
      'ř'  => '&rcaron;',
      'Ś'  => '&Sacute;',
      'ś'  => '&sacute;',
      'Ŝ'  => '&Scirc;',
      'ŝ'  => '&scirc;',
      'Ş'  => '&Scedil;',
      'ş'  => '&scedil;',
      'Š'  => '&Scaron;',
      'š'  => '&scaron;',
      'Ţ'  => '&Tcedil;',
      'ţ'  => '&tcedil;',
      'Ť'  => '&Tcaron;',
      'ť'  => '&tcaron;',
      'Ŧ'  => '&Tstrok;',
      'ŧ'  => '&tstrok;',
      'Ũ'  => '&Utilde;',
      'ũ'  => '&utilde;',
      'Ū'  => '&Umacr;',
      'ū'  => '&umacr;',
      'Ŭ'  => '&Ubreve;',
      'ŭ'  => '&ubreve;',
      'Ů'  => '&Uring;',
      'ů'  => '&uring;',
      'Ű'  => '&Udblac;',
      'ű'  => '&udblac;',
      'Ų'  => '&Uogon;',
      'ų'  => '&uogon;',
      'Ŵ'  => '&Wcirc;',
      'ŵ'  => '&wcirc;',
      'Ŷ'  => '&Ycirc;',
      'ŷ'  => '&ycirc;',
      'Ÿ'  => '&Yuml;',
      'Ź'  => '&Zacute;',
      'ź'  => '&zacute;',
      'Ż'  => '&Zdot;',
      'ż'  => '&zdot;',
      'Ž'  => '&Zcaron;',
      'ž'  => '&zcaron;',
      'ƒ'  => '&fnof;',
      'Ƶ'  => '&imped;',
      'ǵ'  => '&gacute;',
      'ȷ'  => '&jmath;',
      'ˆ'  => '&circ;',
      'ˇ'  => '&Hacek;',
      '˘'  => '&Breve;',
      '˙'  => '&dot;',
      '˚'  => '&ring;',
      '˛'  => '&ogon;',
      '˜'  => '&DiacriticalTilde;',
      '˝'  => '&DiacriticalDoubleAcute;',
      '̑'  => '&DownBreve;',
      'Α'  => '&Alpha;',
      'Β'  => '&Beta;',
      'Γ'  => '&Gamma;',
      'Δ'  => '&Delta;',
      'Ε'  => '&Epsilon;',
      'Ζ'  => '&Zeta;',
      'Η'  => '&Eta;',
      'Θ'  => '&Theta;',
      'Ι'  => '&Iota;',
      'Κ'  => '&Kappa;',
      'Λ'  => '&Lambda;',
      'Μ'  => '&Mu;',
      'Ν'  => '&Nu;',
      'Ξ'  => '&Xi;',
      'Ο'  => '&Omicron;',
      'Π'  => '&Pi;',
      'Ρ'  => '&Rho;',
      'Σ'  => '&Sigma;',
      'Τ'  => '&Tau;',
      'Υ'  => '&Upsilon;',
      'Φ'  => '&Phi;',
      'Χ'  => '&Chi;',
      'Ψ'  => '&Psi;',
      'Ω'  => '&Omega;',
      'α'  => '&alpha;',
      'β'  => '&beta;',
      'γ'  => '&gamma;',
      'δ'  => '&delta;',
      'ε'  => '&epsi;',
      'ζ'  => '&zeta;',
      'η'  => '&eta;',
      'θ'  => '&theta;',
      'ι'  => '&iota;',
      'κ'  => '&kappa;',
      'λ'  => '&lambda;',
      'μ'  => '&mu;',
      'ν'  => '&nu;',
      'ξ'  => '&xi;',
      'ο'  => '&omicron;',
      'π'  => '&pi;',
      'ρ'  => '&rho;',
      'ς'  => '&sigmav;',
      'σ'  => '&sigma;',
      'τ'  => '&tau;',
      'υ'  => '&upsi;',
      'φ'  => '&phi;',
      'χ'  => '&chi;',
      'ψ'  => '&psi;',
      'ω'  => '&omega;',
      'ϑ'  => '&thetasym;',
      'ϒ'  => '&upsih;',
      'ϕ'  => '&straightphi;',
      'ϖ'  => '&piv;',
      'Ϝ'  => '&Gammad;',
      'ϝ'  => '&gammad;',
      'ϰ'  => '&varkappa;',
      'ϱ'  => '&rhov;',
      'ϵ'  => '&straightepsilon;',
      '϶'  => '&backepsilon;',
      'Ё'  => '&IOcy;',
      'Ђ'  => '&DJcy;',
      'Ѓ'  => '&GJcy;',
      'Є'  => '&Jukcy;',
      'Ѕ'  => '&DScy;',
      'І'  => '&Iukcy;',
      'Ї'  => '&YIcy;',
      'Ј'  => '&Jsercy;',
      'Љ'  => '&LJcy;',
      'Њ'  => '&NJcy;',
      'Ћ'  => '&TSHcy;',
      'Ќ'  => '&KJcy;',
      'Ў'  => '&Ubrcy;',
      'Џ'  => '&DZcy;',
      'А'  => '&Acy;',
      'Б'  => '&Bcy;',
      'В'  => '&Vcy;',
      'Г'  => '&Gcy;',
      'Д'  => '&Dcy;',
      'Е'  => '&IEcy;',
      'Ж'  => '&ZHcy;',
      'З'  => '&Zcy;',
      'И'  => '&Icy;',
      'Й'  => '&Jcy;',
      'К'  => '&Kcy;',
      'Л'  => '&Lcy;',
      'М'  => '&Mcy;',
      'Н'  => '&Ncy;',
      'О'  => '&Ocy;',
      'П'  => '&Pcy;',
      'Р'  => '&Rcy;',
      'С'  => '&Scy;',
      'Т'  => '&Tcy;',
      'У'  => '&Ucy;',
      'Ф'  => '&Fcy;',
      'Х'  => '&KHcy;',
      'Ц'  => '&TScy;',
      'Ч'  => '&CHcy;',
      'Ш'  => '&SHcy;',
      'Щ'  => '&SHCHcy;',
      'Ъ'  => '&HARDcy;',
      'Ы'  => '&Ycy;',
      'Ь'  => '&SOFTcy;',
      'Э'  => '&Ecy;',
      'Ю'  => '&YUcy;',
      'Я'  => '&YAcy;',
      'а'  => '&acy;',
      'б'  => '&bcy;',
      'в'  => '&vcy;',
      'г'  => '&gcy;',
      'д'  => '&dcy;',
      'е'  => '&iecy;',
      'ж'  => '&zhcy;',
      'з'  => '&zcy;',
      'и'  => '&icy;',
      'й'  => '&jcy;',
      'к'  => '&kcy;',
      'л'  => '&lcy;',
      'м'  => '&mcy;',
      'н'  => '&ncy;',
      'о'  => '&ocy;',
      'п'  => '&pcy;',
      'р'  => '&rcy;',
      'с'  => '&scy;',
      'т'  => '&tcy;',
      'у'  => '&ucy;',
      'ф'  => '&fcy;',
      'х'  => '&khcy;',
      'ц'  => '&tscy;',
      'ч'  => '&chcy;',
      'ш'  => '&shcy;',
      'щ'  => '&shchcy;',
      'ъ'  => '&hardcy;',
      'ы'  => '&ycy;',
      'ь'  => '&softcy;',
      'э'  => '&ecy;',
      'ю'  => '&yucy;',
      'я'  => '&yacy;',
      'ё'  => '&iocy;',
      'ђ'  => '&djcy;',
      'ѓ'  => '&gjcy;',
      'є'  => '&jukcy;',
      'ѕ'  => '&dscy;',
      'і'  => '&iukcy;',
      'ї'  => '&yicy;',
      'ј'  => '&jsercy;',
      'љ'  => '&ljcy;',
      'њ'  => '&njcy;',
      'ћ'  => '&tshcy;',
      'ќ'  => '&kjcy;',
      'ў'  => '&ubrcy;',
      'џ'  => '&dzcy;',
      ' '  => '&ensp;',
      ' '  => '&emsp;',
      ' '  => '&emsp13;',
      ' '  => '&emsp14;',
      ' '  => '&numsp;',
      ' '  => '&puncsp;',
      ' '  => '&ThinSpace;',
      ' '  => '&hairsp;',
      '​'  => '&ZeroWidthSpace;',
      '‌'  => '&zwnj;',
      '‍'  => '&zwj;',
      '‎'  => '&lrm;',
      '‏'  => '&rlm;',
      '‐'  => '&hyphen;',
      '–'  => '&ndash;',
      '—'  => '&mdash;',
      '―'  => '&horbar;',
      '‖'  => '&Verbar;',
      '‘'  => '&OpenCurlyQuote;',
      '’'  => '&rsquo;',
      '‚'  => '&sbquo;',
      '“'  => '&OpenCurlyDoubleQuote;',
      '”'  => '&rdquo;',
      '„'  => '&bdquo;',
      '†'  => '&dagger;',
      '‡'  => '&Dagger;',
      '•'  => '&bull;',
      '‥'  => '&nldr;',
      '…'  => '&hellip;',
      '‰'  => '&permil;',
      '‱'  => '&pertenk;',
      '′'  => '&prime;',
      '″'  => '&Prime;',
      '‴'  => '&tprime;',
      '‵'  => '&backprime;',
      '‹'  => '&lsaquo;',
      '›'  => '&rsaquo;',
      '‾'  => '&oline;',
      '⁁'  => '&caret;',
      '⁃'  => '&hybull;',
      '⁄'  => '&frasl;',
      '⁏'  => '&bsemi;',
      '⁗'  => '&qprime;',
      ' '  => '&MediumSpace;',
      '  ' => '&ThickSpace;',
      '⁠'  => '&NoBreak;',
      '⁡'  => '&af;',
      '⁢'  => '&InvisibleTimes;',
      '⁣'  => '&ic;',
      '€'  => '&euro;',
      '⃛'  => '&TripleDot;',
      '⃜'  => '&DotDot;',
      'ℂ'  => '&complexes;',
      '℅'  => '&incare;',
      'ℊ'  => '&gscr;',
      'ℋ'  => '&HilbertSpace;',
      'ℌ'  => '&Hfr;',
      'ℍ'  => '&Hopf;',
      'ℎ'  => '&planckh;',
      'ℏ'  => '&planck;',
      'ℐ'  => '&imagline;',
      'ℑ'  => '&Ifr;',
      'ℒ'  => '&lagran;',
      'ℓ'  => '&ell;',
      'ℕ'  => '&naturals;',
      '№'  => '&numero;',
      '℗'  => '&copysr;',
      '℘'  => '&wp;',
      'ℙ'  => '&primes;',
      'ℚ'  => '&rationals;',
      'ℛ'  => '&realine;',
      'ℜ'  => '&Rfr;',
      'ℝ'  => '&Ropf;',
      '℞'  => '&rx;',
      '™'  => '&trade;',
      'ℤ'  => '&Zopf;',
      '℧'  => '&mho;',
      'ℨ'  => '&Zfr;',
      '℩'  => '&iiota;',
      'ℬ'  => '&Bscr;',
      'ℭ'  => '&Cfr;',
      'ℯ'  => '&escr;',
      'ℰ'  => '&expectation;',
      'ℱ'  => '&Fouriertrf;',
      'ℳ'  => '&Mellintrf;',
      'ℴ'  => '&orderof;',
      'ℵ'  => '&aleph;',
      'ℶ'  => '&beth;',
      'ℷ'  => '&gimel;',
      'ℸ'  => '&daleth;',
      'ⅅ'  => '&CapitalDifferentialD;',
      'ⅆ'  => '&DifferentialD;',
      'ⅇ'  => '&exponentiale;',
      'ⅈ'  => '&ImaginaryI;',
      '⅓'  => '&frac13;',
      '⅔'  => '&frac23;',
      '⅕'  => '&frac15;',
      '⅖'  => '&frac25;',
      '⅗'  => '&frac35;',
      '⅘'  => '&frac45;',
      '⅙'  => '&frac16;',
      '⅚'  => '&frac56;',
      '⅛'  => '&frac18;',
      '⅜'  => '&frac38;',
      '⅝'  => '&frac58;',
      '⅞'  => '&frac78;',
      '←'  => '&larr;',
      '↑'  => '&uarr;',
      '→'  => '&srarr;',
      '↓'  => '&darr;',
      '↔'  => '&harr;',
      '↕'  => '&UpDownArrow;',
      '↖'  => '&nwarrow;',
      '↗'  => '&UpperRightArrow;',
      '↘'  => '&LowerRightArrow;',
      '↙'  => '&swarr;',
      '↚'  => '&nleftarrow;',
      '↛'  => '&nrarr;',
      '↝'  => '&rarrw;',
      '↝̸' => '&nrarrw;',
      '↞'  => '&Larr;',
      '↟'  => '&Uarr;',
      '↠'  => '&twoheadrightarrow;',
      '↡'  => '&Darr;',
      '↢'  => '&larrtl;',
      '↣'  => '&rarrtl;',
      '↤'  => '&LeftTeeArrow;',
      '↥'  => '&UpTeeArrow;',
      '↦'  => '&map;',
      '↧'  => '&DownTeeArrow;',
      '↩'  => '&larrhk;',
      '↪'  => '&rarrhk;',
      '↫'  => '&larrlp;',
      '↬'  => '&looparrowright;',
      '↭'  => '&harrw;',
      '↮'  => '&nleftrightarrow;',
      '↰'  => '&Lsh;',
      '↱'  => '&rsh;',
      '↲'  => '&ldsh;',
      '↳'  => '&rdsh;',
      '↵'  => '&crarr;',
      '↶'  => '&curvearrowleft;',
      '↷'  => '&curarr;',
      '↺'  => '&olarr;',
      '↻'  => '&orarr;',
      '↼'  => '&leftharpoonup;',
      '↽'  => '&leftharpoondown;',
      '↾'  => '&RightUpVector;',
      '↿'  => '&uharl;',
      '⇀'  => '&rharu;',
      '⇁'  => '&rhard;',
      '⇂'  => '&RightDownVector;',
      '⇃'  => '&dharl;',
      '⇄'  => '&rightleftarrows;',
      '⇅'  => '&udarr;',
      '⇆'  => '&lrarr;',
      '⇇'  => '&llarr;',
      '⇈'  => '&upuparrows;',
      '⇉'  => '&rrarr;',
      '⇊'  => '&downdownarrows;',
      '⇋'  => '&leftrightharpoons;',
      '⇌'  => '&rightleftharpoons;',
      '⇍'  => '&nLeftarrow;',
      '⇎'  => '&nhArr;',
      '⇏'  => '&nrArr;',
      '⇐'  => '&DoubleLeftArrow;',
      '⇑'  => '&DoubleUpArrow;',
      '⇒'  => '&Implies;',
      '⇓'  => '&Downarrow;',
      '⇔'  => '&hArr;',
      '⇕'  => '&Updownarrow;',
      '⇖'  => '&nwArr;',
      '⇗'  => '&neArr;',
      '⇘'  => '&seArr;',
      '⇙'  => '&swArr;',
      '⇚'  => '&lAarr;',
      '⇛'  => '&rAarr;',
      '⇝'  => '&zigrarr;',
      '⇤'  => '&LeftArrowBar;',
      '⇥'  => '&RightArrowBar;',
      '⇵'  => '&DownArrowUpArrow;',
      '⇽'  => '&loarr;',
      '⇾'  => '&roarr;',
      '⇿'  => '&hoarr;',
      '∀'  => '&forall;',
      '∁'  => '&comp;',
      '∂'  => '&part;',
      '∂̸' => '&npart;',
      '∃'  => '&Exists;',
      '∄'  => '&nexist;',
      '∅'  => '&empty;',
      '∇'  => '&nabla;',
      '∈'  => '&isinv;',
      '∉'  => '&notin;',
      '∋'  => '&ReverseElement;',
      '∌'  => '&notniva;',
      '∏'  => '&prod;',
      '∐'  => '&Coproduct;',
      '∑'  => '&sum;',
      '−'  => '&minus;',
      '∓'  => '&MinusPlus;',
      '∔'  => '&plusdo;',
      '∖'  => '&ssetmn;',
      '∗'  => '&lowast;',
      '∘'  => '&compfn;',
      '√'  => '&Sqrt;',
      '∝'  => '&prop;',
      '∞'  => '&infin;',
      '∟'  => '&angrt;',
      '∠'  => '&angle;',
      '∠⃒' => '&nang;',
      '∡'  => '&angmsd;',
      '∢'  => '&angsph;',
      '∣'  => '&mid;',
      '∤'  => '&nshortmid;',
      '∥'  => '&shortparallel;',
      '∦'  => '&nparallel;',
      '∧'  => '&and;',
      '∨'  => '&or;',
      '∩'  => '&cap;',
      '∩︀' => '&caps;',
      '∪'  => '&cup;',
      '∪︀' => '&cups',
      '∫'  => '&Integral;',
      '∬'  => '&Int;',
      '∭'  => '&tint;',
      '∮'  => '&ContourIntegral;',
      '∯'  => '&DoubleContourIntegral;',
      '∰'  => '&Cconint;',
      '∱'  => '&cwint;',
      '∲'  => '&cwconint;',
      '∳'  => '&awconint;',
      '∴'  => '&there4;',
      '∵'  => '&Because;',
      '∶'  => '&ratio;',
      '∷'  => '&Colon;',
      '∸'  => '&minusd;',
      '∺'  => '&mDDot;',
      '∻'  => '&homtht;',
      '∼'  => '&sim;',
      '∼⃒' => '&nvsim;',
      '∽'  => '&bsim;',
      '∽̱' => '&race;',
      '∾'  => '&ac;',
      '∾̳' => '&acE;',
      '∿'  => '&acd;',
      '≀'  => '&wr;',
      '≁'  => '&NotTilde;',
      '≂'  => '&esim;',
      '≂̸' => '&nesim;',
      '≃'  => '&simeq;',
      '≄'  => '&nsime;',
      '≅'  => '&TildeFullEqual;',
      '≆'  => '&simne;',
      '≇'  => '&ncong;',
      '≈'  => '&approx;',
      '≉'  => '&napprox;',
      '≊'  => '&ape;',
      '≋'  => '&apid;',
      '≋̸' => '&napid;',
      '≌'  => '&bcong;',
      '≍'  => '&CupCap;',
      '≍⃒' => '&nvap;',
      '≎'  => '&bump;',
      '≎̸' => '&nbump;',
      '≏'  => '&HumpEqual;',
      '≏̸' => '&nbumpe;',
      '≐'  => '&esdot;',
      '≐̸' => '&nedot;',
      '≑'  => '&doteqdot;',
      '≒'  => '&fallingdotseq;',
      '≓'  => '&risingdotseq;',
      '≔'  => '&coloneq;',
      '≕'  => '&eqcolon;',
      '≖'  => '&ecir;',
      '≗'  => '&circeq;',
      '≙'  => '&wedgeq;',
      '≚'  => '&veeeq;',
      '≜'  => '&triangleq;',
      '≟'  => '&equest;',
      '≠'  => '&NotEqual;',
      '≡'  => '&Congruent;',
      '≡⃥' => '&bnequiv;',
      '≢'  => '&NotCongruent;',
      '≤'  => '&leq;',
      '≤⃒' => '&nvle;',
      '≥'  => '&ge;',
      '≥⃒' => '&nvge;',
      '≦'  => '&lE;',
      '≦̸' => '&nlE;',
      '≧'  => '&geqq;',
      '≧̸' => '&NotGreaterFullEqual;',
      '≨'  => '&lneqq;',
      '≨︀' => '&lvertneqq;',
      '≩'  => '&gneqq;',
      '≩︀' => '&gvertneqq;',
      '≪'  => '&ll;',
      '≪̸' => '&nLtv;',
      '≪⃒' => '&nLt;',
      '≫'  => '&gg;',
      '≫̸' => '&NotGreaterGreater;',
      '≫⃒' => '&nGt;',
      '≬'  => '&between;',
      '≭'  => '&NotCupCap;',
      '≮'  => '&NotLess;',
      '≯'  => '&ngtr;',
      '≰'  => '&NotLessEqual;',
      '≱'  => '&ngeq;',
      '≲'  => '&LessTilde;',
      '≳'  => '&GreaterTilde;',
      '≴'  => '&nlsim;',
      '≵'  => '&ngsim;',
      '≶'  => '&lessgtr;',
      '≷'  => '&gl;',
      '≸'  => '&ntlg;',
      '≹'  => '&NotGreaterLess;',
      '≺'  => '&prec;',
      '≻'  => '&succ;',
      '≼'  => '&PrecedesSlantEqual;',
      '≽'  => '&succcurlyeq;',
      '≾'  => '&precsim;',
      '≿'  => '&SucceedsTilde;',
      '≿̸' => '&NotSucceedsTilde;',
      '⊀'  => '&npr;',
      '⊁'  => '&NotSucceeds;',
      '⊂'  => '&sub;',
      '⊂⃒' => '&vnsub;',
      '⊃'  => '&sup;',
      '⊃⃒' => '&nsupset;',
      '⊄'  => '&nsub;',
      '⊅'  => '&nsup;',
      '⊆'  => '&SubsetEqual;',
      '⊇'  => '&supe;',
      '⊈'  => '&NotSubsetEqual;',
      '⊉'  => '&NotSupersetEqual;',
      '⊊'  => '&subsetneq;',
      '⊊︀' => '&vsubne;',
      '⊋'  => '&supsetneq;',
      '⊋︀' => '&vsupne;',
      '⊍'  => '&cupdot;',
      '⊎'  => '&UnionPlus;',
      '⊏'  => '&sqsub;',
      '⊏̸' => '&NotSquareSubset;',
      '⊐'  => '&sqsupset;',
      '⊐̸' => '&NotSquareSuperset;',
      '⊑'  => '&SquareSubsetEqual;',
      '⊒'  => '&SquareSupersetEqual;',
      '⊓'  => '&sqcap;',
      '⊓︀' => '&sqcaps;',
      '⊔'  => '&sqcup;',
      '⊔︀' => '&sqcups;',
      '⊕'  => '&CirclePlus;',
      '⊖'  => '&ominus;',
      '⊗'  => '&CircleTimes;',
      '⊘'  => '&osol;',
      '⊙'  => '&CircleDot;',
      '⊚'  => '&ocir;',
      '⊛'  => '&oast;',
      '⊝'  => '&odash;',
      '⊞'  => '&boxplus;',
      '⊟'  => '&boxminus;',
      '⊠'  => '&timesb;',
      '⊡'  => '&sdotb;',
      '⊢'  => '&vdash;',
      '⊣'  => '&dashv;',
      '⊤'  => '&DownTee;',
      '⊥'  => '&perp;',
      '⊧'  => '&models;',
      '⊨'  => '&DoubleRightTee;',
      '⊩'  => '&Vdash;',
      '⊪'  => '&Vvdash;',
      '⊫'  => '&VDash;',
      '⊬'  => '&nvdash;',
      '⊭'  => '&nvDash;',
      '⊮'  => '&nVdash;',
      '⊯'  => '&nVDash;',
      '⊰'  => '&prurel;',
      '⊲'  => '&vartriangleleft;',
      '⊳'  => '&vrtri;',
      '⊴'  => '&LeftTriangleEqual;',
      '⊴⃒' => '&nvltrie;',
      '⊵'  => '&RightTriangleEqual;',
      '⊵⃒' => '&nvrtrie;',
      '⊶'  => '&origof;',
      '⊷'  => '&imof;',
      '⊸'  => '&mumap;',
      '⊹'  => '&hercon;',
      '⊺'  => '&intcal;',
      '⊻'  => '&veebar;',
      '⊽'  => '&barvee;',
      '⊾'  => '&angrtvb;',
      '⊿'  => '&lrtri;',
      '⋀'  => '&xwedge;',
      '⋁'  => '&xvee;',
      '⋂'  => '&bigcap;',
      '⋃'  => '&bigcup;',
      '⋄'  => '&diamond;',
      '⋅'  => '&sdot;',
      '⋆'  => '&Star;',
      '⋇'  => '&divonx;',
      '⋈'  => '&bowtie;',
      '⋉'  => '&ltimes;',
      '⋊'  => '&rtimes;',
      '⋋'  => '&lthree;',
      '⋌'  => '&rthree;',
      '⋍'  => '&backsimeq;',
      '⋎'  => '&curlyvee;',
      '⋏'  => '&curlywedge;',
      '⋐'  => '&Sub;',
      '⋑'  => '&Supset;',
      '⋒'  => '&Cap;',
      '⋓'  => '&Cup;',
      '⋔'  => '&pitchfork;',
      '⋕'  => '&epar;',
      '⋖'  => '&lessdot;',
      '⋗'  => '&gtrdot;',
      '⋘'  => '&Ll;',
      '⋘̸' => '&nLl;',
      '⋙'  => '&Gg;',
      '⋙̸' => '&nGg;',
      '⋚'  => '&lesseqgtr;',
      '⋚︀' => '&lesg;',
      '⋛'  => '&gtreqless;',
      '⋛︀' => '&gesl;',
      '⋞'  => '&curlyeqprec;',
      '⋟'  => '&cuesc;',
      '⋠'  => '&NotPrecedesSlantEqual;',
      '⋡'  => '&NotSucceedsSlantEqual;',
      '⋢'  => '&NotSquareSubsetEqual;',
      '⋣'  => '&NotSquareSupersetEqual;',
      '⋦'  => '&lnsim;',
      '⋧'  => '&gnsim;',
      '⋨'  => '&precnsim;',
      '⋩'  => '&scnsim;',
      '⋪'  => '&nltri;',
      '⋫'  => '&ntriangleright;',
      '⋬'  => '&nltrie;',
      '⋭'  => '&NotRightTriangleEqual;',
      '⋮'  => '&vellip;',
      '⋯'  => '&ctdot;',
      '⋰'  => '&utdot;',
      '⋱'  => '&dtdot;',
      '⋲'  => '&disin;',
      '⋳'  => '&isinsv;',
      '⋴'  => '&isins;',
      '⋵'  => '&isindot;',
      '⋵̸' => '&notindot;',
      '⋶'  => '&notinvc;',
      '⋷'  => '&notinvb;',
      '⋹'  => '&isinE;',
      '⋹̸' => '&notinE;',
      '⋺'  => '&nisd;',
      '⋻'  => '&xnis;',
      '⋼'  => '&nis;',
      '⋽'  => '&notnivc;',
      '⋾'  => '&notnivb;',
      '⌅'  => '&barwed;',
      '⌆'  => '&doublebarwedge;',
      '⌈'  => '&lceil;',
      '⌉'  => '&RightCeiling;',
      '⌊'  => '&LeftFloor;',
      '⌋'  => '&RightFloor;',
      '⌌'  => '&drcrop;',
      '⌍'  => '&dlcrop;',
      '⌎'  => '&urcrop;',
      '⌏'  => '&ulcrop;',
      '⌐'  => '&bnot;',
      '⌒'  => '&profline;',
      '⌓'  => '&profsurf;',
      '⌕'  => '&telrec;',
      '⌖'  => '&target;',
      '⌜'  => '&ulcorner;',
      '⌝'  => '&urcorner;',
      '⌞'  => '&llcorner;',
      '⌟'  => '&drcorn;',
      '⌢'  => '&frown;',
      '⌣'  => '&smile;',
      '⌭'  => '&cylcty;',
      '⌮'  => '&profalar;',
      '⌶'  => '&topbot;',
      '⌽'  => '&ovbar;',
      '⌿'  => '&solbar;',
      '⍼'  => '&angzarr;',
      '⎰'  => '&lmoust;',
      '⎱'  => '&rmoust;',
      '⎴'  => '&OverBracket;',
      '⎵'  => '&bbrk;',
      '⎶'  => '&bbrktbrk;',
      '⏜'  => '&OverParenthesis;',
      '⏝'  => '&UnderParenthesis;',
      '⏞'  => '&OverBrace;',
      '⏟'  => '&UnderBrace;',
      '⏢'  => '&trpezium;',
      '⏧'  => '&elinters;',
      '␣'  => '&blank;',
      'Ⓢ'  => '&oS;',
      '─'  => '&HorizontalLine;',
      '│'  => '&boxv;',
      '┌'  => '&boxdr;',
      '┐'  => '&boxdl;',
      '└'  => '&boxur;',
      '┘'  => '&boxul;',
      '├'  => '&boxvr;',
      '┤'  => '&boxvl;',
      '┬'  => '&boxhd;',
      '┴'  => '&boxhu;',
      '┼'  => '&boxvh;',
      '═'  => '&boxH;',
      '║'  => '&boxV;',
      '╒'  => '&boxdR;',
      '╓'  => '&boxDr;',
      '╔'  => '&boxDR;',
      '╕'  => '&boxdL;',
      '╖'  => '&boxDl;',
      '╗'  => '&boxDL;',
      '╘'  => '&boxuR;',
      '╙'  => '&boxUr;',
      '╚'  => '&boxUR;',
      '╛'  => '&boxuL;',
      '╜'  => '&boxUl;',
      '╝'  => '&boxUL;',
      '╞'  => '&boxvR;',
      '╟'  => '&boxVr;',
      '╠'  => '&boxVR;',
      '╡'  => '&boxvL;',
      '╢'  => '&boxVl;',
      '╣'  => '&boxVL;',
      '╤'  => '&boxHd;',
      '╥'  => '&boxhD;',
      '╦'  => '&boxHD;',
      '╧'  => '&boxHu;',
      '╨'  => '&boxhU;',
      '╩'  => '&boxHU;',
      '╪'  => '&boxvH;',
      '╫'  => '&boxVh;',
      '╬'  => '&boxVH;',
      '▀'  => '&uhblk;',
      '▄'  => '&lhblk;',
      '█'  => '&block;',
      '░'  => '&blk14;',
      '▒'  => '&blk12;',
      '▓'  => '&blk34;',
      '□'  => '&Square;',
      '▪'  => '&squarf;',
      '▫'  => '&EmptyVerySmallSquare;',
      '▭'  => '&rect;',
      '▮'  => '&marker;',
      '▱'  => '&fltns;',
      '△'  => '&bigtriangleup;',
      '▴'  => '&blacktriangle;',
      '▵'  => '&triangle;',
      '▸'  => '&blacktriangleright;',
      '▹'  => '&rtri;',
      '▽'  => '&bigtriangledown;',
      '▾'  => '&blacktriangledown;',
      '▿'  => '&triangledown;',
      '◂'  => '&blacktriangleleft;',
      '◃'  => '&ltri;',
      '◊'  => '&lozenge;',
      '○'  => '&cir;',
      '◬'  => '&tridot;',
      '◯'  => '&bigcirc;',
      '◸'  => '&ultri;',
      '◹'  => '&urtri;',
      '◺'  => '&lltri;',
      '◻'  => '&EmptySmallSquare;',
      '◼'  => '&FilledSmallSquare;',
      '★'  => '&starf;',
      '☆'  => '&star;',
      '☎'  => '&phone;',
      '♀'  => '&female;',
      '♂'  => '&male;',
      '♠'  => '&spadesuit;',
      '♣'  => '&clubs;',
      '♥'  => '&hearts;',
      '♦'  => '&diamondsuit;',
      '♪'  => '&sung;',
      '♭'  => '&flat;',
      '♮'  => '&natur;',
      '♯'  => '&sharp;',
      '✓'  => '&check;',
      '✗'  => '&cross;',
      '✠'  => '&maltese;',
      '✶'  => '&sext;',
      '❘'  => '&VerticalSeparator;',
      '❲'  => '&lbbrk;',
      '❳'  => '&rbbrk;',
      '⟈'  => '&bsolhsub;',
      '⟉'  => '&suphsol;',
      '⟦'  => '&LeftDoubleBracket;',
      '⟧'  => '&RightDoubleBracket;',
      '⟨'  => '&langle;',
      '⟩'  => '&RightAngleBracket;',
      '⟪'  => '&Lang;',
      '⟫'  => '&Rang;',
      '⟬'  => '&loang;',
      '⟭'  => '&roang;',
      '⟵'  => '&longleftarrow;',
      '⟶'  => '&LongRightArrow;',
      '⟷'  => '&LongLeftRightArrow;',
      '⟸'  => '&xlArr;',
      '⟹'  => '&DoubleLongRightArrow;',
      '⟺'  => '&xhArr;',
      '⟼'  => '&xmap;',
      '⟿'  => '&dzigrarr;',
      '⤂'  => '&nvlArr;',
      '⤃'  => '&nvrArr;',
      '⤄'  => '&nvHarr;',
      '⤅'  => '&Map;',
      '⤌'  => '&lbarr;',
      '⤍'  => '&bkarow;',
      '⤎'  => '&lBarr;',
      '⤏'  => '&dbkarow;',
      '⤐'  => '&drbkarow;',
      '⤑'  => '&DDotrahd;',
      '⤒'  => '&UpArrowBar;',
      '⤓'  => '&DownArrowBar;',
      '⤖'  => '&Rarrtl;',
      '⤙'  => '&latail;',
      '⤚'  => '&ratail;',
      '⤛'  => '&lAtail;',
      '⤜'  => '&rAtail;',
      '⤝'  => '&larrfs;',
      '⤞'  => '&rarrfs;',
      '⤟'  => '&larrbfs;',
      '⤠'  => '&rarrbfs;',
      '⤣'  => '&nwarhk;',
      '⤤'  => '&nearhk;',
      '⤥'  => '&searhk;',
      '⤦'  => '&swarhk;',
      '⤧'  => '&nwnear;',
      '⤨'  => '&toea;',
      '⤩'  => '&seswar;',
      '⤪'  => '&swnwar;',
      '⤳'  => '&rarrc;',
      '⤳̸' => '&nrarrc;',
      '⤵'  => '&cudarrr;',
      '⤶'  => '&ldca;',
      '⤷'  => '&rdca;',
      '⤸'  => '&cudarrl;',
      '⤹'  => '&larrpl;',
      '⤼'  => '&curarrm;',
      '⤽'  => '&cularrp;',
      '⥅'  => '&rarrpl;',
      '⥈'  => '&harrcir;',
      '⥉'  => '&Uarrocir;',
      '⥊'  => '&lurdshar;',
      '⥋'  => '&ldrushar;',
      '⥎'  => '&LeftRightVector;',
      '⥏'  => '&RightUpDownVector;',
      '⥐'  => '&DownLeftRightVector;',
      '⥑'  => '&LeftUpDownVector;',
      '⥒'  => '&LeftVectorBar;',
      '⥓'  => '&RightVectorBar;',
      '⥔'  => '&RightUpVectorBar;',
      '⥕'  => '&RightDownVectorBar;',
      '⥖'  => '&DownLeftVectorBar;',
      '⥗'  => '&DownRightVectorBar;',
      '⥘'  => '&LeftUpVectorBar;',
      '⥙'  => '&LeftDownVectorBar;',
      '⥚'  => '&LeftTeeVector;',
      '⥛'  => '&RightTeeVector;',
      '⥜'  => '&RightUpTeeVector;',
      '⥝'  => '&RightDownTeeVector;',
      '⥞'  => '&DownLeftTeeVector;',
      '⥟'  => '&DownRightTeeVector;',
      '⥠'  => '&LeftUpTeeVector;',
      '⥡'  => '&LeftDownTeeVector;',
      '⥢'  => '&lHar;',
      '⥣'  => '&uHar;',
      '⥤'  => '&rHar;',
      '⥥'  => '&dHar;',
      '⥦'  => '&luruhar;',
      '⥧'  => '&ldrdhar;',
      '⥨'  => '&ruluhar;',
      '⥩'  => '&rdldhar;',
      '⥪'  => '&lharul;',
      '⥫'  => '&llhard;',
      '⥬'  => '&rharul;',
      '⥭'  => '&lrhard;',
      '⥮'  => '&udhar;',
      '⥯'  => '&ReverseUpEquilibrium;',
      '⥰'  => '&RoundImplies;',
      '⥱'  => '&erarr;',
      '⥲'  => '&simrarr;',
      '⥳'  => '&larrsim;',
      '⥴'  => '&rarrsim;',
      '⥵'  => '&rarrap;',
      '⥶'  => '&ltlarr;',
      '⥸'  => '&gtrarr;',
      '⥹'  => '&subrarr;',
      '⥻'  => '&suplarr;',
      '⥼'  => '&lfisht;',
      '⥽'  => '&rfisht;',
      '⥾'  => '&ufisht;',
      '⥿'  => '&dfisht;',
      '⦅'  => '&lopar;',
      '⦆'  => '&ropar;',
      '⦋'  => '&lbrke;',
      '⦌'  => '&rbrke;',
      '⦍'  => '&lbrkslu;',
      '⦎'  => '&rbrksld;',
      '⦏'  => '&lbrksld;',
      '⦐'  => '&rbrkslu;',
      '⦑'  => '&langd;',
      '⦒'  => '&rangd;',
      '⦓'  => '&lparlt;',
      '⦔'  => '&rpargt;',
      '⦕'  => '&gtlPar;',
      '⦖'  => '&ltrPar;',
      '⦚'  => '&vzigzag;',
      '⦜'  => '&vangrt;',
      '⦝'  => '&angrtvbd;',
      '⦤'  => '&ange;',
      '⦥'  => '&range;',
      '⦦'  => '&dwangle;',
      '⦧'  => '&uwangle;',
      '⦨'  => '&angmsdaa;',
      '⦩'  => '&angmsdab;',
      '⦪'  => '&angmsdac;',
      '⦫'  => '&angmsdad;',
      '⦬'  => '&angmsdae;',
      '⦭'  => '&angmsdaf;',
      '⦮'  => '&angmsdag;',
      '⦯'  => '&angmsdah;',
      '⦰'  => '&bemptyv;',
      '⦱'  => '&demptyv;',
      '⦲'  => '&cemptyv;',
      '⦳'  => '&raemptyv;',
      '⦴'  => '&laemptyv;',
      '⦵'  => '&ohbar;',
      '⦶'  => '&omid;',
      '⦷'  => '&opar;',
      '⦹'  => '&operp;',
      '⦻'  => '&olcross;',
      '⦼'  => '&odsold;',
      '⦾'  => '&olcir;',
      '⦿'  => '&ofcir;',
      '⧀'  => '&olt;',
      '⧁'  => '&ogt;',
      '⧂'  => '&cirscir;',
      '⧃'  => '&cirE;',
      '⧄'  => '&solb;',
      '⧅'  => '&bsolb;',
      '⧉'  => '&boxbox;',
      '⧍'  => '&trisb;',
      '⧎'  => '&rtriltri;',
      '⧏'  => '&LeftTriangleBar;',
      '⧏̸' => '&NotLeftTriangleBar;',
      '⧐'  => '&RightTriangleBar;',
      '⧐̸' => '&NotRightTriangleBar;',
      '⧜'  => '&iinfin;',
      '⧝'  => '&infintie;',
      '⧞'  => '&nvinfin;',
      '⧣'  => '&eparsl;',
      '⧤'  => '&smeparsl;',
      '⧥'  => '&eqvparsl;',
      '⧫'  => '&lozf;',
      '⧴'  => '&RuleDelayed;',
      '⧶'  => '&dsol;',
      '⨀'  => '&xodot;',
      '⨁'  => '&bigoplus;',
      '⨂'  => '&bigotimes;',
      '⨄'  => '&biguplus;',
      '⨆'  => '&bigsqcup;',
      '⨌'  => '&iiiint;',
      '⨍'  => '&fpartint;',
      '⨐'  => '&cirfnint;',
      '⨑'  => '&awint;',
      '⨒'  => '&rppolint;',
      '⨓'  => '&scpolint;',
      '⨔'  => '&npolint;',
      '⨕'  => '&pointint;',
      '⨖'  => '&quatint;',
      '⨗'  => '&intlarhk;',
      '⨢'  => '&pluscir;',
      '⨣'  => '&plusacir;',
      '⨤'  => '&simplus;',
      '⨥'  => '&plusdu;',
      '⨦'  => '&plussim;',
      '⨧'  => '&plustwo;',
      '⨩'  => '&mcomma;',
      '⨪'  => '&minusdu;',
      '⨭'  => '&loplus;',
      '⨮'  => '&roplus;',
      '⨯'  => '&Cross;',
      '⨰'  => '&timesd;',
      '⨱'  => '&timesbar;',
      '⨳'  => '&smashp;',
      '⨴'  => '&lotimes;',
      '⨵'  => '&rotimes;',
      '⨶'  => '&otimesas;',
      '⨷'  => '&Otimes;',
      '⨸'  => '&odiv;',
      '⨹'  => '&triplus;',
      '⨺'  => '&triminus;',
      '⨻'  => '&tritime;',
      '⨼'  => '&iprod;',
      '⨿'  => '&amalg;',
      '⩀'  => '&capdot;',
      '⩂'  => '&ncup;',
      '⩃'  => '&ncap;',
      '⩄'  => '&capand;',
      '⩅'  => '&cupor;',
      '⩆'  => '&cupcap;',
      '⩇'  => '&capcup;',
      '⩈'  => '&cupbrcap;',
      '⩉'  => '&capbrcup;',
      '⩊'  => '&cupcup;',
      '⩋'  => '&capcap;',
      '⩌'  => '&ccups;',
      '⩍'  => '&ccaps;',
      '⩐'  => '&ccupssm;',
      '⩓'  => '&And;',
      '⩔'  => '&Or;',
      '⩕'  => '&andand;',
      '⩖'  => '&oror;',
      '⩗'  => '&orslope;',
      '⩘'  => '&andslope;',
      '⩚'  => '&andv;',
      '⩛'  => '&orv;',
      '⩜'  => '&andd;',
      '⩝'  => '&ord;',
      '⩟'  => '&wedbar;',
      '⩦'  => '&sdote;',
      '⩪'  => '&simdot;',
      '⩭'  => '&congdot;',
      '⩭̸' => '&ncongdot;',
      '⩮'  => '&easter;',
      '⩯'  => '&apacir;',
      '⩰'  => '&apE;',
      '⩰̸' => '&napE;',
      '⩱'  => '&eplus;',
      '⩲'  => '&pluse;',
      '⩳'  => '&Esim;',
      '⩴'  => '&Colone;',
      '⩵'  => '&Equal;',
      '⩷'  => '&ddotseq;',
      '⩸'  => '&equivDD;',
      '⩹'  => '&ltcir;',
      '⩺'  => '&gtcir;',
      '⩻'  => '&ltquest;',
      '⩼'  => '&gtquest;',
      '⩽'  => '&les;',
      '⩽̸' => '&nles;',
      '⩾'  => '&ges;',
      '⩾̸' => '&nges;',
      '⩿'  => '&lesdot;',
      '⪀'  => '&gesdot;',
      '⪁'  => '&lesdoto;',
      '⪂'  => '&gesdoto;',
      '⪃'  => '&lesdotor;',
      '⪄'  => '&gesdotol;',
      '⪅'  => '&lap;',
      '⪆'  => '&gap;',
      '⪇'  => '&lne;',
      '⪈'  => '&gne;',
      '⪉'  => '&lnap;',
      '⪊'  => '&gnap;',
      '⪋'  => '&lesseqqgtr;',
      '⪌'  => '&gEl;',
      '⪍'  => '&lsime;',
      '⪎'  => '&gsime;',
      '⪏'  => '&lsimg;',
      '⪐'  => '&gsiml;',
      '⪑'  => '&lgE;',
      '⪒'  => '&glE;',
      '⪓'  => '&lesges;',
      '⪔'  => '&gesles;',
      '⪕'  => '&els;',
      '⪖'  => '&egs;',
      '⪗'  => '&elsdot;',
      '⪘'  => '&egsdot;',
      '⪙'  => '&el;',
      '⪚'  => '&eg;',
      '⪝'  => '&siml;',
      '⪞'  => '&simg;',
      '⪟'  => '&simlE;',
      '⪠'  => '&simgE;',
      '⪡'  => '&LessLess;',
      '⪡̸' => '&NotNestedLessLess;',
      '⪢'  => '&GreaterGreater;',
      '⪢̸' => '&NotNestedGreaterGreater;',
      '⪤'  => '&glj;',
      '⪥'  => '&gla;',
      '⪦'  => '&ltcc;',
      '⪧'  => '&gtcc;',
      '⪨'  => '&lescc;',
      '⪩'  => '&gescc;',
      '⪪'  => '&smt;',
      '⪫'  => '&lat;',
      '⪬'  => '&smte;',
      '⪬︀' => '&smtes;',
      '⪭'  => '&late;',
      '⪭︀' => '&lates;',
      '⪮'  => '&bumpE;',
      '⪯'  => '&preceq;',
      '⪯̸' => '&NotPrecedesEqual;',
      '⪰'  => '&SucceedsEqual;',
      '⪰̸' => '&NotSucceedsEqual;',
      '⪳'  => '&prE;',
      '⪴'  => '&scE;',
      '⪵'  => '&precneqq;',
      '⪶'  => '&scnE;',
      '⪷'  => '&precapprox;',
      '⪸'  => '&succapprox;',
      '⪹'  => '&precnapprox;',
      '⪺'  => '&succnapprox;',
      '⪻'  => '&Pr;',
      '⪼'  => '&Sc;',
      '⪽'  => '&subdot;',
      '⪾'  => '&supdot;',
      '⪿'  => '&subplus;',
      '⫀'  => '&supplus;',
      '⫁'  => '&submult;',
      '⫂'  => '&supmult;',
      '⫃'  => '&subedot;',
      '⫄'  => '&supedot;',
      '⫅'  => '&subE;',
      '⫅̸' => '&nsubE;',
      '⫆'  => '&supseteqq;',
      '⫆̸' => '&nsupseteqq;',
      '⫇'  => '&subsim;',
      '⫈'  => '&supsim;',
      '⫋'  => '&subsetneqq;',
      '⫋︀' => '&vsubnE;',
      '⫌'  => '&supnE;',
      '⫌︀' => '&varsupsetneqq;',
      '⫏'  => '&csub;',
      '⫐'  => '&csup;',
      '⫑'  => '&csube;',
      '⫒'  => '&csupe;',
      '⫓'  => '&subsup;',
      '⫔'  => '&supsub;',
      '⫕'  => '&subsub;',
      '⫖'  => '&supsup;',
      '⫗'  => '&suphsub;',
      '⫘'  => '&supdsub;',
      '⫙'  => '&forkv;',
      '⫚'  => '&topfork;',
      '⫛'  => '&mlcp;',
      '⫤'  => '&Dashv;',
      '⫦'  => '&Vdashl;',
      '⫧'  => '&Barv;',
      '⫨'  => '&vBar;',
      '⫩'  => '&vBarv;',
      '⫫'  => '&Vbar;',
      '⫬'  => '&Not;',
      '⫭'  => '&bNot;',
      '⫮'  => '&rnmid;',
      '⫯'  => '&cirmid;',
      '⫰'  => '&midcir;',
      '⫱'  => '&topcir;',
      '⫲'  => '&nhpar;',
      '⫳'  => '&parsim;',
      '⫽'  => '&parsl;',
      '⫽⃥' => '&nparsl;',
      'ﬀ'  => '&fflig;',
      'ﬁ'  => '&filig;',
      'ﬂ'  => '&fllig;',
      'ﬃ'  => '&ffilig;',
      'ﬄ'  => '&ffllig;',
      '𝒜' => '&Ascr;',
      '𝒞' => '&Cscr;',
      '𝒟' => '&Dscr;',
      '𝒢' => '&Gscr;',
      '𝒥' => '&Jscr;',
      '𝒦' => '&Kscr;',
      '𝒩' => '&Nscr;',
      '𝒪' => '&Oscr;',
      '𝒫' => '&Pscr;',
      '𝒬' => '&Qscr;',
      '𝒮' => '&Sscr;',
      '𝒯' => '&Tscr;',
      '𝒰' => '&Uscr;',
      '𝒱' => '&Vscr;',
      '𝒲' => '&Wscr;',
      '𝒳' => '&Xscr;',
      '𝒴' => '&Yscr;',
      '𝒵' => '&Zscr;',
      '𝒶' => '&ascr;',
      '𝒷' => '&bscr;',
      '𝒸' => '&cscr;',
      '𝒹' => '&dscr;',
      '𝒻' => '&fscr;',
      '𝒽' => '&hscr;',
      '𝒾' => '&iscr;',
      '𝒿' => '&jscr;',
      '𝓀' => '&kscr;',
      '𝓁' => '&lscr;',
      '𝓂' => '&mscr;',
      '𝓃' => '&nscr;',
      '𝓅' => '&pscr;',
      '𝓆' => '&qscr;',
      '𝓇' => '&rscr;',
      '𝓈' => '&sscr;',
      '𝓉' => '&tscr;',
      '𝓊' => '&uscr;',
      '𝓋' => '&vscr;',
      '𝓌' => '&wscr;',
      '𝓍' => '&xscr;',
      '𝓎' => '&yscr;',
      '𝓏' => '&zscr;',
      '𝔄' => '&Afr;',
      '𝔅' => '&Bfr;',
      '𝔇' => '&Dfr;',
      '𝔈' => '&Efr;',
      '𝔉' => '&Ffr;',
      '𝔊' => '&Gfr;',
      '𝔍' => '&Jfr;',
      '𝔎' => '&Kfr;',
      '𝔏' => '&Lfr;',
      '𝔐' => '&Mfr;',
      '𝔑' => '&Nfr;',
      '𝔒' => '&Ofr;',
      '𝔓' => '&Pfr;',
      '𝔔' => '&Qfr;',
      '𝔖' => '&Sfr;',
      '𝔗' => '&Tfr;',
      '𝔘' => '&Ufr;',
      '𝔙' => '&Vfr;',
      '𝔚' => '&Wfr;',
      '𝔛' => '&Xfr;',
      '𝔜' => '&Yfr;',
      '𝔞' => '&afr;',
      '𝔟' => '&bfr;',
      '𝔠' => '&cfr;',
      '𝔡' => '&dfr;',
      '𝔢' => '&efr;',
      '𝔣' => '&ffr;',
      '𝔤' => '&gfr;',
      '𝔥' => '&hfr;',
      '𝔦' => '&ifr;',
      '𝔧' => '&jfr;',
      '𝔨' => '&kfr;',
      '𝔩' => '&lfr;',
      '𝔪' => '&mfr;',
      '𝔫' => '&nfr;',
      '𝔬' => '&ofr;',
      '𝔭' => '&pfr;',
      '𝔮' => '&qfr;',
      '𝔯' => '&rfr;',
      '𝔰' => '&sfr;',
      '𝔱' => '&tfr;',
      '𝔲' => '&ufr;',
      '𝔳' => '&vfr;',
      '𝔴' => '&wfr;',
      '𝔵' => '&xfr;',
      '𝔶' => '&yfr;',
      '𝔷' => '&zfr;',
      '𝔸' => '&Aopf;',
      '𝔹' => '&Bopf;',
      '𝔻' => '&Dopf;',
      '𝔼' => '&Eopf;',
      '𝔽' => '&Fopf;',
      '𝔾' => '&Gopf;',
      '𝕀' => '&Iopf;',
      '𝕁' => '&Jopf;',
      '𝕂' => '&Kopf;',
      '𝕃' => '&Lopf;',
      '𝕄' => '&Mopf;',
      '𝕆' => '&Oopf;',
      '𝕊' => '&Sopf;',
      '𝕋' => '&Topf;',
      '𝕌' => '&Uopf;',
      '𝕍' => '&Vopf;',
      '𝕎' => '&Wopf;',
      '𝕏' => '&Xopf;',
      '𝕐' => '&Yopf;',
      '𝕒' => '&aopf;',
      '𝕓' => '&bopf;',
      '𝕔' => '&copf;',
      '𝕕' => '&dopf;',
      '𝕖' => '&eopf;',
      '𝕗' => '&fopf;',
      '𝕘' => '&gopf;',
      '𝕙' => '&hopf;',
      '𝕚' => '&iopf;',
      '𝕛' => '&jopf;',
      '𝕜' => '&kopf;',
      '𝕝' => '&lopf;',
      '𝕞' => '&mopf;',
      '𝕟' => '&nopf;',
      '𝕠' => '&oopf;',
      '𝕡' => '&popf;',
      '𝕢' => '&qopf;',
      '𝕣' => '&ropf;',
      '𝕤' => '&sopf;',
      '𝕥' => '&topf;',
      '𝕦' => '&uopf;',
      '𝕧' => '&vopf;',
      '𝕨' => '&wopf;',
      '𝕩' => '&xopf;',
      '𝕪' => '&yopf;',
      '𝕫' => '&zopf;',
  );

  /**
   * List of never allowed regex replacements.
   *
   * @var  array
   */
  private static $_never_allowed_regex = array(
    // default javascript
    'javascript\s*:',
    // default javascript
    '(document|(document\.)?window)\.(location|on\w*)',
    // Java: jar-protocol is an XSS hazard
    'jar\s*:',
    // Mac (will not run the script, but open it in AppleScript Editor)
    'applescript\s*:',
    // IE: https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#VBscript_in_an_image
    'vbscript\s*:',
    // IE, surprise!
    'wscript\s*:',
    // IE
    'jscript\s*:',
    // IE: https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#VBscript_in_an_image
    'vbs\s*:',
    // https://html5sec.org/#behavior
    'behavior\s:',
    // ?
    'Redirect\s+30\d',
    // data-attribute + base64
    "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?",
    // remove Netscape 4 JS entities
    '&\s*\{[^}]*(\}\s*;?|$)',
    // old IE, old Netscape
    'expression\s*(\(|&\#40;)',
    // old Netscape
    'mocha\s*:',
    // old Netscape
    'livescript\s*:',
    // default view source
    'view-source\s*:',
  );

  /**
   * List of never allowed strings, afterwards.
   *
   * @var array
   */
  private static $_never_allowed_str_afterwards = array(
      'FSCommand',
      'onAbort',
      'onActivate',
      'onAttribute',
      'onAfterPrint',
      'onAfterScriptExecute',
      'onAfterUpdate',
      'onAnimationEnd',
      'onAnimationIteration',
      'onAnimationStart',
      'onAriaRequest',
      'onAutoComplete',
      'onAutoCompleteError',
      'onBeforeActivate',
      'onBeforeCopy',
      'onBeforeCut',
      'onBeforeDeactivate',
      'onBeforeEditFocus',
      'onBeforePaste',
      'onBeforePrint',
      'onBeforeScriptExecute',
      'onBeforeUnload',
      'onBeforeUpdate',
      'onBegin',
      'onBlur',
      'onBounce',
      'onCancel',
      'onCanPlay',
      'onCanPlayThrough',
      'onCellChange',
      'onChange',
      'onClick',
      'onClose',
      'onCommand',
      'onCompassNeedsCalibration',
      'onContextMenu',
      'onControlSelect',
      'onCopy',
      'onCueChange',
      'onCut',
      'onDataAvailable',
      'onDataSetChanged',
      'onDataSetComplete',
      'onDblClick',
      'onDeactivate',
      'onDeviceLight',
      'onDeviceMotion',
      'onDeviceOrientation',
      'onDeviceProximity',
      'onDrag',
      'onDragDrop',
      'onDragEnd',
      'onDragEnter',
      'onDragLeave',
      'onDragOver',
      'onDragStart',
      'onDrop',
      'onDurationChange',
      'onEmptied',
      'onEnd',
      'onEnded',
      'onError',
      'onErrorUpdate',
      'onExit',
      'onFilterChange',
      'onFinish',
      'onFocus',
      'onFocusIn',
      'onFocusOut',
      'onFormChange',
      'onFormInput',
      'onFullScreenChange',
      'onFullScreenError',
      'onGotPointerCapture',
      'onHashChange',
      'onHelp',
      'onInput',
      'onInvalid',
      'onKeyDown',
      'onKeyPress',
      'onKeyUp',
      'onLanguageChange',
      'onLayoutComplete',
      'onLoad',
      'onLoadedData',
      'onLoadedMetaData',
      'onLoadStart',
      'onLoseCapture',
      'onLostPointerCapture',
      'onMediaComplete',
      'onMediaError',
      'onMessage',
      'onMouseDown',
      'onMouseEnter',
      'onMouseLeave',
      'onMouseMove',
      'onMouseOut',
      'onMouseOver',
      'onMouseUp',
      'onMouseWheel',
      'onMove',
      'onMoveEnd',
      'onMoveStart',
      'onMozFullScreenChange',
      'onMozFullScreenError',
      'onMozPointerLockChange',
      'onMozPointerLockError',
      'onMsContentZoom',
      'onMsFullScreenChange',
      'onMsFullScreenError',
      'onMsGestureChange',
      'onMsGestureDoubleTap',
      'onMsGestureEnd',
      'onMsGestureHold',
      'onMsGestureStart',
      'onMsGestureTap',
      'onMsGotPointerCapture',
      'onMsInertiaStart',
      'onMsLostPointerCapture',
      'onMsManipulationStateChanged',
      'onMsPointerCancel',
      'onMsPointerDown',
      'onMsPointerEnter',
      'onMsPointerLeave',
      'onMsPointerMove',
      'onMsPointerOut',
      'onMsPointerOver',
      'onMsPointerUp',
      'onMsSiteModeJumpListItemRemoved',
      'onMsThumbnailClick',
      'onOffline',
      'onOnline',
      'onOutOfSync',
      'onPage',
      'onPageHide',
      'onPageShow',
      'onPaste',
      'onPause',
      'onPlay',
      'onPlaying',
      'onPointerCancel',
      'onPointerDown',
      'onPointerEnter',
      'onPointerLeave',
      'onPointerLockChange',
      'onPointerLockError',
      'onPointerMove',
      'onPointerOut',
      'onPointerOver',
      'onPointerUp',
      'onPopState',
      'onProgress',
      'onPropertyChange',
      'onRateChange',
      'onReadyStateChange',
      'onReceived',
      'onRepeat',
      'onReset',
      'onResize',
      'onResizeEnd',
      'onResizeStart',
      'onResume',
      'onReverse',
      'onRowDelete',
      'onRowEnter',
      'onRowExit',
      'onRowInserted',
      'onRowsDelete',
      'onRowsEnter',
      'onRowsExit',
      'onRowsInserted',
      'onScroll',
      'onSearch',
      'onSeek',
      'onSeeked',
      'onSeeking',
      'onSelect',
      'onSelectionChange',
      'onSelectStart',
      'onStalled',
      'onStorage',
      'onStorageCommit',
      'onStart',
      'onStop',
      'onShow',
      'onSyncRestored',
      'onSubmit',
      'onSuspend',
      'onSynchRestored',
      'onTimeError',
      'onTimeUpdate',
      'onTrackChange',
      'onTransitionEnd',
      'onToggle',
      'onUnload',
      'onURLFlip',
      'onUserProximity',
      'onVolumeChange',
      'onWaiting',
      'onWebKitAnimationEnd',
      'onWebKitAnimationIteration',
      'onWebKitAnimationStart',
      'onWebKitFullScreenChange',
      'onWebKitFullScreenError',
      'onWebKitTransitionEnd',
      'onWheel',
      'seekSegmentTime',
      'userid',
      'datasrc',
      'datafld',
      'dataformatas',
      'ev:handler',
      'ev:event',
      '0;url',
  );

  /**
   * https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#Event_Handlers
   *
   * @var array
   */
  private $_evil_attributes = array(
      'on\w*',
      'style',
      'xmlns',
      'formaction',
      'form',
      'xlink:href',
      'seekSegmentTime',
      'FSCommand',
      'eval',
  );

  /**
   * XSS Hash - random Hash for protecting URLs.
   *
   * @var  string
   */
  private $_xss_hash;

  /**
   * The replacement-string for not allowed strings.
   *
   * @var string
   */
  private $_replacement = '';

  /**
   * List of never allowed strings.
   *
   * @var  array
   */
  private $_never_allowed_str = array();

  /**
   * If your DB (MySQL) encoding is "utf8" and not "utf8mb4", then
   * you can't save 4-Bytes chars from UTF-8 and someone can create stored XSS-attacks.
   *
   * @var bool
   */
  private $_stripe_4byte_chars = false;

  /**
   * @var bool|null
   */
  private $xss_found = null;

  /**
   * __construct()
   */
  public function __construct()
  {
    $this->_initNeverAllowedStr();
  }

  /**
   * Compact exploded words.
   *
   * <p>
   * <br />
   * INFO: Callback method for xss_clean() to remove whitespace from things like 'j a v a s c r i p t'.
   * </p>
   *
   * @param  array $matches
   *
   * @return  string
   */
  private function _compact_exploded_words_callback($matches)
  {
    return preg_replace('/(?:\s+|"|\042|\'|\047|\+)*+/', '', $matches[1]) . $matches[2];
  }

  /**
   * HTML-Entity decode callback.
   *
   * @param array $match
   *
   * @return string
   */
  private function _decode_entity($match)
  {
    // init
    $this->_xss_hash();

    $match = $match[0];

    // protect GET variables in URLs
    $match = preg_replace('|\?([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->_xss_hash . '::GET_FIRST' . '\\1=\\2', $match);
    $match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->_xss_hash . '::GET_NEXT' . '\\1=\\2', $match);

    // un-protect URL GET vars
    return str_replace(
        array(
            $this->_xss_hash . '::GET_FIRST',
            $this->_xss_hash . '::GET_NEXT',
        ),
        array(
            '?',
            '&',
        ),
        $this->_entity_decode($match)
    );
  }

  /**
   * @param string $str
   *
   * @return mixed
   */
  private function _do($str)
  {
    $str = (string)$str;
    $strInt = (int)$str;
    $strFloat = (float)$str;
    if (
        !$str
        ||
        "$strInt" == $str
        ||
        "$strFloat" == $str
    ) {

      // no xss found
      if ($this->xss_found !== true) {
        $this->xss_found = false;
      }

      return $str;
    }

    // removes all non-UTF-8 characters
    // &&
    // remove NULL characters (ignored by some browsers)
    $str = UTF8::clean($str, true, true, false);

    // decode UTF-7 characters
    $str = $this->_repack_utf7($str);

    // decode the string
    $str = $this->_decode_string($str);

    // remove all >= 4-Byte chars if needed
    if ($this->_stripe_4byte_chars === true) {
      $str = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $str);
    }

    // backup the string (for later comparision)
    $str_backup = $str;

    // remove strings that are never allowed
    $str = $this->_do_never_allowed($str);

    // corrects words before the browser will do it
    $str = $this->_compact_exploded_javascript($str);

    // remove disallowed javascript calls in links, images etc.
    $str = $this->_remove_disallowed_javascript($str);

    // remove evil attributes such as style, onclick and xmlns
    $str = $this->_remove_evil_attributes($str);

    // sanitize naughty HTML elements
    $str = $this->_sanitize_naughty_html($str);

    // sanitize naughty JavaScript elements
    $str = $this->_sanitize_naughty_javascript($str);

    // final clean up
    //
    // -> This adds a bit of extra precaution in case something got through the above filters.
    $str = $this->_do_never_allowed_afterwards($str);

    // check for xss
    if ($this->xss_found !== true) {
      $this->xss_found = !($str_backup === $str);
    }

    return $str;
  }

  /**
   * Remove never allowed strings.
   *
   * @param string $str
   *
   * @return string
   */
  private function _do_never_allowed($str)
  {
    static $NEVER_ALLOWED_CACHE = array();
    $NEVER_ALLOWED_CACHE['keys'] = null;
    $NEVER_ALLOWED_CACHE['regex'] = null;

    if (null === $NEVER_ALLOWED_CACHE['keys']) {
      $NEVER_ALLOWED_CACHE['keys'] = array_keys($this->_never_allowed_str);
    }
    $str = str_ireplace($NEVER_ALLOWED_CACHE['keys'], $this->_never_allowed_str, $str);

    if (null === $NEVER_ALLOWED_CACHE['regex']) {
      $NEVER_ALLOWED_CACHE['regex'] = implode('|', self::$_never_allowed_regex);
    }
    $str = preg_replace('#' . $NEVER_ALLOWED_CACHE['regex'] . '#is', $this->_replacement, $str);

    return (string)$str;
  }

  /**
   * Remove never allowed string, afterwards.
   *
   * <p>
   * <br />
   * INFO: clean-up also some string, if there is no html-tag
   * </p>
   *
   * @param string $str
   *
   * @return  string
   */
  private function _do_never_allowed_afterwards($str)
  {
    static $NEVER_ALLOWED_STR_AFTERWARDS_CACHE;

    if (null === $NEVER_ALLOWED_STR_AFTERWARDS_CACHE) {
      foreach (self::$_never_allowed_str_afterwards as &$neverAllowedStr) {
        $neverAllowedStr .= '.*=';
      }

      $NEVER_ALLOWED_STR_AFTERWARDS_CACHE = implode('|', self::$_never_allowed_str_afterwards);
    }

    $str = preg_replace('#' . $NEVER_ALLOWED_STR_AFTERWARDS_CACHE . '#isU', $this->_replacement, $str);

    return (string)$str;
  }

  /**
   * Entity-decoding.
   *
   * @param string $str
   *
   * @return string
   */
  private function _entity_decode($str)
  {
    static $HTML_ENTITIES_CACHE;

    /** @noinspection UsageOfSilenceOperatorInspection */
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    // HHVM dons't support "ENT_DISALLOWED" && "ENT_SUBSTITUTE"
    $flags = Bootup::is_php('5.4') ?
        ENT_QUOTES | ENT_HTML5 | @ENT_DISALLOWED | @ENT_SUBSTITUTE :
        ENT_QUOTES;

    // decode
    if (strpos($str, $this->_xss_hash) !== false) {
      $str = UTF8::html_entity_decode($str, $flags);
    } else {
      $str = UTF8::rawurldecode($str);
    }

    // decode-again, for e.g. HHVM, PHP 5.3, miss configured applications ...
    if (preg_match_all('/&[A-Za-z]{2,}[;]{0}/', $str, $matches)) {

      if (null === $HTML_ENTITIES_CACHE) {

        // links:
        // - http://dev.w3.org/html5/html-author/charref
        // - http://www.w3schools.com/charsets/ref_html_entities_n.asp
        $entitiesSecurity = array(
            '&#x00000;'          => '',
            '&#0;'               => '',
            '&#x00001;'          => '',
            '&#1;'               => '',
            '&nvgt;'             => '',
            '&#61253;'           => '',
            '&#x0EF45;'          => '',
            '&shy;'              => '',
            '&#x000AD;'          => '',
            '&#173;'             => '',
            '&colon;'            => ':',
            '&#x0003A;'          => ':',
            '&#58;'              => ':',
            '&lpar;'             => '(',
            '&#x00028;'          => '(',
            '&#40;'              => '(',
            '&rpar;'             => ')',
            '&#x00029;'          => ')',
            '&#41;'              => ')',
            '&quest;'            => '?',
            '&#x0003F;'          => '?',
            '&#63;'              => '?',
            '&sol;'              => '/',
            '&#x0002F;'          => '/',
            '&#47;'              => '/',
            '&apos;'             => '\'',
            '&#x00027;'          => '\'',
            '&#039;'             => '\'',
            '&#39;'              => '\'',
            '&#x27;'             => '\'',
            '&bsol;'             => '\'',
            '&#x0005C;'          => '\\',
            '&#92;'              => '\\',
            '&comma;'            => ',',
            '&#x0002C;'          => ',',
            '&#44;'              => ',',
            '&period;'           => '.',
            '&#x0002E;'          => '.',
            '&quot;'             => '"',
            '&QUOT;'             => '"',
            '&#x00022;'          => '"',
            '&#34;'              => '"',
            '&grave;'            => '`',
            '&DiacriticalGrave;' => '`',
            '&#x00060;'          => '`',
            '&#96;'              => '`',
            '&#46;'              => '.',
            '&equals;'           => '=',
            '&#x0003D;'          => '=',
            '&#61;'              => '=',
            '&newline;'          => "\n",
            '&#x0000A;'          => "\n",
            '&#10;'              => "\n",
            '&tab;'              => "\t",
            '&#x00009;'          => "\t",
            '&#9;'               => "\t",
        );

        $HTML_ENTITIES_CACHE = array_merge(
            $entitiesSecurity,
            array_flip(get_html_translation_table(HTML_ENTITIES, $flags)),
            array_flip(self::$entitiesFallback)
        );
      }

      $replace = array();
      foreach ($matches[0] as $match) {
        $match .= ';';
        if (isset($HTML_ENTITIES_CACHE[$match])) {
          $replace[$match] = $HTML_ENTITIES_CACHE[$match];
        }
      }

      if (count($replace) > 0) {
        $str = str_replace(array_keys($replace), array_values($replace), $str);
      }
    }

    return $str;
  }

  /**
   * Filters tag attributes for consistency and safety.
   *
   * @param string $str
   *
   * @return string
   */
  private function _filter_attributes($str)
  {
    if ($str === '') {
      return '';
    }

    $out = '';
    if (
        preg_match_all('#\s*[A-Za-z\-]+\s*=\s*("|\042|\'|\047)([^\\1]*?)\\1#', $str, $matches)
        ||
        (
            $this->_replacement
            &&
            preg_match_all('#\s*[a-zA-Z\-]+\s*=' . preg_quote($this->_replacement, '#') . '$#', $str, $matches)
        )
    ) {
      foreach ($matches[0] as $match) {
        $out .= $match;
      }
    }

    return $out;
  }

  /**
   * initialize "$this->_never_allowed_str"
   */
  private function _initNeverAllowedStr()
  {
    $this->_never_allowed_str = array(
        'document.cookie' => $this->_replacement,
        'document.write'  => $this->_replacement,
        '.parentNode'     => $this->_replacement,
        '.innerHTML'      => $this->_replacement,
        '.appendChild'    => $this->_replacement,
        '-moz-binding'    => $this->_replacement,
        '<!--'            => '&lt;!--',
        '-->'             => '--&gt;',
        '<?'              => '&lt;?',
        '?>'              => '?&gt;',
        '<![CDATA['       => '&lt;![CDATA[',
        '<!ENTITY'        => '&lt;!ENTITY',
        '<!DOCTYPE'       => '&lt;!DOCTYPE',
        '<!ATTLIST'       => '&lt;!ATTLIST',
        '<comment>'       => '&lt;comment&gt;',
    );
  }

  /**
   * Callback method for xss_clean() to sanitize links.
   *
   * <p>
   * <br />
   * INFO: This limits the PCRE backtracks, making it more performance friendly
   * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
   * PHP 5.2+ on link-heavy strings.
   * </p>
   *
   * @param array $match
   *
   * @return string
   */
  private function _js_link_removal_callback($match)
  {
    return $this->_js_removal_calback($match, 'href');
  }

  /**
   * Callback method for xss_clean() to sanitize tags.
   *
   * <p>
   * <br />
   * INFO: This limits the PCRE backtracks, making it more performance friendly
   * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
   * PHP 5.2+ on image tag heavy strings.
   * </p>
   *
   * @param array  $match
   * @param string $search
   *
   * @return string
   */
  private function _js_removal_calback($match, $search)
  {
    if (!$match[0]) {
      return '';
    }

    // init
    $replacer = $this->_filter_attributes(str_replace(array('<', '>',), '', $match[1]));
    $pattern = '#' . $search . '=.*(?:\(.+([^\)]*?)(?:\)|$)|javascript:|view-source:|livescript:|wscript:|vbscript:|mocha:|charset=|window\.|document\.|\.cookie|<script|d\s*a\s*t\s*a\s*:)#is';

    $matchInner = array();
    preg_match($pattern, $match[1], $matchInner);
    if (count($matchInner) > 0) {
      $replacer = (string)preg_replace(
          $pattern,
          $search . '="' . $this->_replacement . '"',
          $replacer
      );
    }

    return str_ireplace($match[1], $replacer, $match[0]);
  }

  /**
   * Callback method for xss_clean() to sanitize image tags.
   *
   * <p>
   * <br />
   * INFO: This limits the PCRE backtracks, making it more performance friendly
   * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
   * PHP 5.2+ on image tag heavy strings.
   * </p>
   *
   * @param array $match
   *
   * @return string
   */
  private function _js_src_removal_callback($match)
  {
    return $this->_js_removal_calback($match, 'src');
  }

  /**
   * Sanitize naughty HTML.
   *
   * <p>
   * <br />
   * Callback method for AntiXSS->sanitize_naughty_html() to remove naughty HTML elements.
   * </p>
   *
   * @param array $matches
   *
   * @return string
   */
  private function _sanitize_naughty_html_callback($matches)
  {
    return '&lt;' . $matches[1] . $matches[2] . $matches[3] // encode opening brace
           // encode captured opening or closing brace to prevent recursive vectors:
           . str_replace(
               array(
                   '>',
                   '<',
               ),
               array(
                   '&gt;',
                   '&lt;',
               ),
               $matches[4]
           );
  }

  /**
   * Add some strings to the "_evil_attributes"-array.
   *
   * @param array $strings
   *
   * @return $this
   */
  public function addEvilAttributes(array $strings)
  {
    $this->_evil_attributes = array_merge($strings, $this->_evil_attributes);

    return $this;
  }

  /**
   * Compact any exploded words.
   *
   * <p>
   * <br />
   * INFO: This corrects words like:  j a v a s c r i p t
   * <br />
   * These words are compacted back to their correct state.
   * </p>
   *
   * @param string $str
   *
   * @return string
   */
  private function _compact_exploded_javascript($str)
  {
    static $WORDS_CACHE;

    $words = array(
        'javascript',
        'expression',
        'view-source',
        'vbscript',
        'jscript',
        'wscript',
        'vbs',
        'script',
        'base64',
        'applet',
        'alert',
        'document',
        'write',
        'cookie',
        'window',
        'confirm',
        'prompt',
        'eval',
    );

    foreach ($words as $word) {

      if (!isset($WORDS_CACHE[$word])) {
        $regex = '(?:\s|\+|"|\042|\'|\047)*';
        $word = $WORDS_CACHE[$word] = substr(
            chunk_split($word, 1, $regex),
            0,
            -strlen($regex)
        );
      } else {
        $word = $WORDS_CACHE[$word];
      }

      // We only want to do this when it is followed by a non-word character
      // That way valid stuff like "dealer to" does not become "dealerto".
      $str = preg_replace_callback(
          '#(' . $word . ')(\W)#is',
          array(
              $this,
              '_compact_exploded_words_callback',
          ),
          $str
      );
    }

    return (string)$str;
  }

  /**
   * Decode the html-tags via "UTF8::html_entity_decode()" or the string via "UTF8::rawurldecode()".
   *
   * @param string $str
   *
   * @return string
   */
  private function _decode_string($str)
  {
    // init
    $regExForHtmlTags = '/<\w+.*+/si';

    if (preg_match($regExForHtmlTags, $str, $matches) === 1) {
      $str = preg_replace_callback(
          $regExForHtmlTags,
          array(
              $this,
              '_decode_entity',
          ),
          $str
      );
    } else {
      $str = UTF8::rawurldecode($str);
    }

    return $str;
  }

  /**
   * Check if the "AntiXSS->xss_clean()"-method found an XSS attack in the last run.
   *
   * @return bool|null <p>Will return null if the "xss_clean()" wan't running at all.</p>
   */
  public function isXssFound()
  {
    return $this->xss_found;
  }

  /**
   * Remove some strings from the "_evil_attributes"-array.
   *
   * <p>
   * <br />
   * WARNING: Use this method only if you have a really good reason.
   * </p>
   *
   * @param array $strings
   *
   * @return $this
   */
  public function removeEvilAttributes(array $strings)
  {
    $this->_evil_attributes = array_diff(
        array_intersect($strings, $this->_evil_attributes),
        $this->_evil_attributes
    );

    return $this;
  }

  /**
   * Remove disallowed Javascript in links or img tags
   *
   * <p>
   * <br />
   * We used to do some version comparisons and use of stripos(),
   * but it is dog slow compared to these simplified non-capturing
   * preg_match(), especially if the pattern exists in the string
   * </p>
   *
   * <p>
   * <br />
   * Note: It was reported that not only space characters, but all in
   * the following pattern can be parsed as separators between a tag name
   * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
   * ... however, UTF8::clean() above already strips the
   * hex-encoded ones, so we'll skip them below.
   * </p>
   *
   * @param string $str
   *
   * @return string
   */
  private function _remove_disallowed_javascript($str)
  {
    do {
      $original = $str;

      if (stripos($str, '<a') !== false) {
        $str = preg_replace_callback(
            '#<a[^a-z0-9>]+([^>]*?)(?:>|$)#i',
            array(
                $this,
                '_js_link_removal_callback',
            ),
            $str
        );
      }

      if (stripos($str, '<img') !== false) {
        $str = preg_replace_callback(
            '#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#i',
            array(
                $this,
                '_js_src_removal_callback',
            ),
            $str
        );
      }

      if (stripos($str, '<audio') !== false) {
        $str = preg_replace_callback(
            '#<audio[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#i',
            array(
                $this,
                '_js_src_removal_callback',
            ),
            $str
        );
      }

      if (stripos($str, '<video') !== false) {
        $str = preg_replace_callback(
            '#<video[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#i',
            array(
                $this,
                '_js_src_removal_callback',
            ),
            $str
        );
      }

      if (stripos($str, '<source') !== false) {
        $str = preg_replace_callback(
            '#<source[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#i',
            array(
                $this,
                '_js_src_removal_callback',
            ),
            $str
        );
      }

      if (stripos($str, 'script') !== false) {
        // US-ASCII: ¼ === <
        $str = preg_replace('#(?:¼|<)/*(?:script).*(?:¾|>)#isuU', $this->_replacement, $str);
      }
    } while ($original !== $str);

    return (string)$str;
  }

  /**
   * Remove Evil HTML Attributes (like event handlers and style).
   *
   * It removes the evil attribute and either:
   *
   *  - Everything up until a space. For example, everything between the pipes:
   *
   * <code>
   *   <a |style=document.write('hello');alert('world');| class=link>
   * </code>
   *
   *  - Everything inside the quotes. For example, everything between the pipes:
   *
   * <code>
   *   <a |style="document.write('hello'); alert('world');"| class="link">
   * </code>
   *
   * @param string $str <p>The string to check.</p>
   *
   * @return string <p>The string with the evil attributes removed.</p>
   */
  private function _remove_evil_attributes($str)
  {
    $evil_attributes_string = implode('|', $this->_evil_attributes);

    // replace style-attribute, first (if needed)
    if (in_array('style', $this->_evil_attributes, true)) {
      do {
        $count = $temp_count = 0;

        $str = preg_replace('/(<[^>]+)(?<!\w)(style="(:?[^"]*?)"|style=\'(:?[^\']*?)\')/i', '$1' . $this->_replacement, $str, -1, $temp_count);
        $count += $temp_count;

      } while ($count);
    }

    do {
      $count = $temp_count = 0;

      // find occurrences of illegal attribute strings with and without quotes (042 ["] and 047 ['] are octal quotes)
      $str = preg_replace('/(<[^>]+)(?<!\w)(' . $evil_attributes_string . ')\s*=\s*(?:(?:"|\042|\'|\047)(?:[^\\2]*?)(?:\\2)|[^\s>]*)/is', '$1' . $this->_replacement, $str, -1, $temp_count);
      $count += $temp_count;

    } while ($count);

    return (string)$str;
  }

  /**
   * UTF-7 decoding function.
   *
   * @param string $str <p>HTML document for recode ASCII part of UTF-7 back to ASCII.</p>
   *
   * @return string
   */
  private function _repack_utf7($str)
  {
    return preg_replace_callback(
        '#\+([0-9a-zA-Z/]+)\-#',
        array($this, '_repack_utf7_callback'),
        $str
    );
  }

  /**
   * Additional UTF-7 decoding function.
   *
   * @param string $str <p>String for recode ASCII part of UTF-7 back to ASCII.</p>
   *
   * @return string
   */
  private function _repack_utf7_callback($str)
  {
    $strTmp = base64_decode($str[1]);

    if ($strTmp === false) {
      return $str;
    }

    $str = preg_replace_callback(
        '/^((?:\x00.)*?)((?:[^\x00].)+)/us',
        array($this, '_repack_utf7_callback_back'),
        $strTmp
    );

    return preg_replace('/\x00(.)/us', '$1', $str);
  }

  /**
   * Additional UTF-7 encoding function.
   *
   * @param string $str <p>String for recode ASCII part of UTF-7 back to ASCII.</p>
   *
   * @return string
   */
  private function _repack_utf7_callback_back($str)
  {
    return $str[1] . '+' . rtrim(base64_encode($str[2]), '=') . '-';
  }

  /**
   * Sanitize naughty HTML elements.
   *
   * <p>
   * <br />
   *
   * If a tag containing any of the words in the list
   * below is found, the tag gets converted to entities.
   *
   * <br /><br />
   *
   * So this: <blink>
   * <br />
   * Becomes: &lt;blink&gt;
   * </p>
   *
   * @param string $str
   *
   * @return string
   */
  private function _sanitize_naughty_html($str)
  {
    $naughty = 'alert|prompt|confirm|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|button|select|isindex|layer|link|meta|keygen|object|plaintext|style|script|textarea|title|math|video|source|svg|xml|xss|eval';
    $str = preg_replace_callback(
        '#<(/*\s*)(' . $naughty . ')([^><]*)([><]*)#i',
        array(
            $this,
            '_sanitize_naughty_html_callback',
        ),
        $str
    );

    return (string)$str;
  }

  /**
   * Sanitize naughty scripting elements
   *
   * <p>
   * <br />
   *
   * Similar to above, only instead of looking for
   * tags it looks for PHP and JavaScript commands
   * that are disallowed. Rather than removing the
   * code, it simply converts the parenthesis to entities
   * rendering the code un-executable.
   *
   * <br /><br />
   *
   * For example:  <pre>eval('some code')</pre>
   * <br />
   * Becomes:      <pre>eval&#40;'some code'&#41;</pre>
   * </p>
   *
   * @param string $str
   *
   * @return string
   */
  private function _sanitize_naughty_javascript($str)
  {
    $str = preg_replace(
        '#(alert|eval|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*)\)#siU',
        '\\1\\2&#40;\\3&#41;',
        $str
    );

    return (string)$str;
  }

  /**
   * Set the replacement-string for not allowed strings.
   *
   * @param string $string
   *
   * @return $this
   */
  public function setReplacement($string)
  {
    $this->_replacement = (string)$string;

    $this->_initNeverAllowedStr();

    return $this;
  }

  /**
   * Set the option to stripe 4-Byte chars.
   *
   * <p>
   * <br />
   * INFO: use it if your DB (MySQL) can't use "utf8mb4" -> preventing stored XSS-attacks
   * </p>
   *
   * @param $bool
   *
   * @return $this
   */
  public function setStripe4byteChars($bool)
  {
    $this->_stripe_4byte_chars = (bool)$bool;

    return $this;
  }

  /**
   * XSS Clean
   *
   * <p>
   * <br />
   * Sanitizes data so that "Cross Site Scripting" hacks can be
   * prevented. This method does a fair amount of work but
   * it is extremely thorough, designed to prevent even the
   * most obscure XSS attempts. But keep in mind that nothing
   * is ever 100% foolproof...
   * </p>
   *
   * <p>
   * <br />
   * <strong>Note:</strong> Should only be used to deal with data upon submission.
   *   It's not something that should be used for general
   *   runtime processing.
   * </p>
   *
   * @link http://channel.bitflux.ch/wiki/XSS_Prevention
   *    Based in part on some code and ideas from Bitflux.
   *
   * @link http://ha.ckers.org/xss.html
   *    To help develop this script I used this great list of
   *    vulnerabilities along with a few other hacks I've
   *    harvested from examining vulnerabilities in other programs.
   *
   * @param string|array $str <p>input data e.g. string or array</p>
   *
   * @return string|array|boolean <p>
   *                              boolean: will return a boolean, if the "is_image"-parameter is true<br />
   *                              string: will return a string, if the input is a string<br />
   *                              array: will return a array, if the input is a array<br />
   *                              </p>
   */
  public function xss_clean($str)
  {
    // reset
    $this->xss_found = null;

    // check for an array of strings
    if (is_array($str) === true) {
      foreach ($str as $key => &$value) {
        $str[$key] = $this->xss_clean($value);
      }

      return $str;
    }

    // process
    do {
      $old_str = $str;
      $str = $this->_do($str);
    } while ($old_str !== $str);

    return $str;
  }

  /**
   * Generates the XSS hash if needed and returns it.
   *
   * @return string <p>XSS hash</p>
   */
  private function _xss_hash()
  {
    if ($this->_xss_hash === null) {
      $rand = Bootup::get_random_bytes(16);

      if (!$rand) {
        $this->_xss_hash = md5(uniqid(mt_rand(), true));
      } else {
        $this->_xss_hash = bin2hex($rand);
      }
    }

    return 'voku::anti-xss::' . $this->_xss_hash;
  }

}