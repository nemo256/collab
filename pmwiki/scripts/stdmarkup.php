<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004-2020 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines PmWiki's standard markup.  It is automatically
    included from stdconfig.php unless $EnableStdMarkup==0.

    Each call to Markup() below adds a new rule to PmWiki's translation
    engine (unless a rule with the same name has already been defined).  
    The form of the call is Markup($id,$where,$pat,$rep); 
    $id is a unique name for the rule, $where is the position of the rule
    relative to another rule, $pat is the pattern to look for, and
    $rep is the string to replace it with.
    
    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

## first we preserve text in [=...=] and [@...@]
function PreserveText($sigil, $text, $lead) {
  if ($sigil=='=') return $lead.Keep($text);
  if (strpos($text, "\n")===false) 
    return "$lead<code class='escaped'>".Keep($text)."</code>";
  $text = preg_replace("/\n[^\\S\n]+$/", "\n", $text);
  if ($lead == "" || $lead == "\n") 
    return "$lead<pre class='escaped'>".Keep($text)."</pre>";
  return "$lead<:pre,1>".Keep($text);
}

Markup('[=','_begin',"/(\n[^\\S\n]*)?\\[([=@])(.*?)\\2\\]/s",
    "MarkupPreserveText");
function MarkupPreserveText($m) {return PreserveText($m[2], $m[3], $m[1]);}

Markup('restore','<_end',"/$KeepToken(\\d.*?)$KeepToken/", 'cb_expandkpv');
Markup('<:', '>restore', '/<:[^>]*>/', '');
Markup('<vspace>', '<restore', 
  '/<vspace>/', 
  "<div class='vspace'></div>");
Markup('<vspace><p>', '<<vspace>',
  "/<vspace><p\\b(([^>]*)(\\s)class=(['\"])([^>]*?)\\4)?/",
  "<p$2 class='vspace$3$5'");

## remove carriage returns before preserving text
Markup('\\r','<[=','/\\r/','');

# $[phrase] substitutions
Markup('$[phrase]', '>[=',
  '/\\$\\[(?>([^\\]]+))\\]/', "cb_expandxlang");

# {$var} substitutions
Markup('{$var}', '>$[phrase]',
  '/\\{(\\*|!?[-\\w.\\/\\x80-\\xff]*)(\\$:?\\w[-\\w]*)\\}/',
  "MarkupPageVar");
function MarkupPageVar($m){
  extract($GLOBALS["MarkupToHTML"]);
  return PRR(PVSE(PageVar($pagename, $m[2], $m[1])));
}

# invisible (:textvar:...:) definition
Markup('textvar:', '<split',
   '/\\(: *\\w[-\\w]* *:(?!\\)).*?:\\)/s', '');

## handle relative text vars in includes
if (IsEnabled($EnableRelativePageVars, 1)) 
  SDV($QualifyPatterns["/\\{([-\\w\\x80-\\xfe]*)(\\$:?\\w+\\})/"],
    'cb_qualifypat');

function cb_qualifypat($m) {
  extract($GLOBALS["tmp_qualify"]); 
  return '{' . ($m[1] ? MakePageName($pagename, $m[1]) : $pagename) . $m[2];
}

## character entities
Markup('&','<if','/&amp;(?>([A-Za-z0-9]+|#\\d+|#[xX][A-Fa-f0-9]+));/',
  '&$1;');
Markup('&amp;amp;', '<&', '/&amp;amp;/', Keep('&amp;'));


## (:if:)/(:elseif:)/(:else:)
SDV($CondTextPattern, 
  "/ \\(:if (\d*) (?:end)? \\b[^\n]*?:\\)
     .*?
     (?: \\(: (?:if\\1|if\\1end) \\s* :\\)
     |   (?=\\(:(?:if\\1|if\\1end)\\b[^\n]*?:\\) | $)
     )
   /six");
// SDV($CondTextReplacement, "CondText2(\$pagename, \$m[0], \$m[1])");
SDV($CondTextReplacement, "MarkupCondText2");
Markup('if', 'fulltext', $CondTextPattern, $CondTextReplacement);

function MarkupCondText2($m) {
  extract($GLOBALS["MarkupToHTML"]);
  return CondText2($pagename, $m[0], $m[1]);
}
function CondText2($pagename, $text, $code = '') {
  global $Conditions, $CondTextPattern, $CondTextReplacement;
  $if = "if$code";
  $repl = str_replace('$pagename', "'$pagename'", $CondTextReplacement);
  
  $parts = preg_split("/\\(:(?:{$if}end|$if|else *$if|else$code)\\b\\s*(.*?)\\s*:\\)/", 
                      $text, -1, PREG_SPLIT_DELIM_CAPTURE);
  $x = array_shift($parts);
  while ($parts) {
    list($condspec, $condtext) = array_splice($parts, 0, 2);
    if (!preg_match("/^\\s*(!?)\\s*(\\S*)\\s*(.*?)\\s*$/", $condspec, $match)) continue;
    list($x, $not, $condname, $condparm) = $match;

    if (!isset($Conditions[$condname])) 
      return preg_replace_callback($CondTextPattern, $repl, $condtext);
    $tf = @eval("return ({$Conditions[$condname]});");
    if ($tf xor $not)
      return preg_replace_callback($CondTextPattern, $repl, $condtext);
  }
  return '';
}


## (:include:)
Markup('include', '>if',
  '/\\(:include\\s+(\\S.*?):\\)/i',
  "MarkupRedirectInclude");

## (:redirect:)
Markup('redirect', '<include',
  '/\\(:redirect\\s+(\\S.*?):\\)/i',
  "MarkupRedirectInclude");

function MarkupRedirectInclude($m) {
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case 'include': return PRR(IncludeText($pagename, $m[1]));
    case 'redirect': return RedirectMarkup($pagename, $m[1]);
  }
}
$SaveAttrPatterns['/\\(:(if\\d*|include|redirect)(\\s.*?)?:\\)/i'] = ' ';

## GroupHeader/GroupFooter handling
Markup('nogroupheader', '>include',
  '/\\(:nogroupheader:\\)/i',
  "MarkupGroupHeaderFooter");
Markup('nogroupfooter', '>include',
  '/\\(:nogroupfooter:\\)/i',
  "MarkupGroupHeaderFooter");
Markup('groupheader', '>nogroupheader',
  '/\\(:groupheader:\\)/i',
  "MarkupGroupHeaderFooter");
Markup('groupfooter','>nogroupfooter',
  '/\\(:groupfooter:\\)/i',
  "MarkupGroupHeaderFooter");

function MarkupGroupHeaderFooter($m) {
  extract($GLOBALS["MarkupToHTML"]);
  global $GroupHeaderFmt, $GroupFooterFmt;
  switch ($markupid) {
    case 'nogroupheader': return PZZ($GroupHeaderFmt='');
    case 'nogroupfooter': return PZZ($GroupFooterFmt='');
    case 'groupheader': return PRR(FmtPageName($GroupHeaderFmt,$pagename));
    case 'groupfooter': return PRR(FmtPageName($GroupFooterFmt,$pagename));
  }
}
## (:nl:)
Markup('nl0','<split',"/([^\n])(?>(?:\\(:nl:\\))+)([^\n])/i","$1\n$2");
Markup('nl1','>nl0',"/\\(:nl:\\)/i",'');

## \\$  (end of line joins)
Markup('\\$','>nl1',"/\\\\(?>(\\\\*))\n/", "MarkupEndLineJoin");
function MarkupEndLineJoin($m) { return str_repeat('<br />',strlen($m[1])); }

## Remove one <:vspace> after !headings
Markup('!vspace', '>\\$', "/^(!(?>[^\n]+)\n)<:vspace>/m", '$1');

## (:noheader:),(:nofooter:),(:notitle:)...
Markup('noheader', 'directives', '/\\(:noheader:\\)/i', "MarkupTmplDisplay");
Markup('nofooter', 'directives', '/\\(:nofooter:\\)/i', "MarkupTmplDisplay");
Markup('notitle',  'directives', '/\\(:notitle:\\)/i',  "MarkupTmplDisplay");
Markup('noleft',   'directives', '/\\(:noleft:\\)/i',   "MarkupTmplDisplay");
Markup('noright',  'directives', '/\\(:noright:\\)/i',  "MarkupTmplDisplay");
Markup('noaction', 'directives', '/\\(:noaction:\\)/i', "MarkupTmplDisplay");

function MarkupTmplDisplay($m) {
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case 'noheader': return SetTmplDisplay('PageHeaderFmt',0);
    case 'nofooter': return SetTmplDisplay('PageFooterFmt',0);
    case 'notitle':  return SetTmplDisplay('PageTitleFmt',0);
    case 'noleft':   return SetTmplDisplay('PageLeftFmt',0);
    case 'noright':  return SetTmplDisplay('PageRightFmt',0);
    case 'noaction': return SetTmplDisplay('PageActionFmt',0);
  }
}

## (:spacewikiwords:)
Markup('spacewikiwords', 'directives',
  '/\\(:(no)?spacewikiwords:\\)/i',
  "MarkupDirectives");

## (:linkwikiwords:)
Markup('linkwikiwords', 'directives',
  '/\\(:(no)?linkwikiwords:\\)/i',
  "MarkupDirectives");

## (:linebreaks:)
Markup('linebreaks', 'directives',
  '/\\(:(no)?linebreaks:\\)/i',
  "MarkupDirectives");

## (:messages:)
Markup('messages', 'directives',
  '/^\\(:messages:\\)/i',
  "MarkupDirectives");

function MarkupDirectives($m) {
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case 'linkwikiwords':  return PZZ($GLOBALS['LinkWikiWords']=(@$m[1]!='no'));
    case 'spacewikiwords': return PZZ($GLOBALS['SpaceWikiWords']=(@$m[1]!='no'));
    case 'linebreaks': 
      return PZZ($GLOBALS['HTMLPNewline'] = (@$m[1]!='no') ? '<br  />' : '');
    case 'messages': 
      return '<:block>'.Keep(FmtPageName(
        implode('',(array)$GLOBALS['MessagesFmt']), $pagename));
  }
}


## (:comment:)
Markup('comment', 'directives', '/\\(:comment .*?:\\)/i', '');

## (:title:) +fix for PITS:00266, 00779
$tmpwhen = IsEnabled($EnablePageTitlePriority, 0) ? '<include' : 'directives';
Markup('title', $tmpwhen,
  '/\\(:title\\s(.*?):\\)/i',
  "MarkupSetProperty");
unset($tmpwhen);

function MarkupSetProperty($m){ # title, description, keywords
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case 'title': 
      global $EnablePageTitlePriority;
      return PZZ(PCache($pagename, $zz=array('title' => 
        SetProperty($pagename, 'title', $m[1], NULL, $EnablePageTitlePriority))));
    case 'keywords': 
      return PZZ(SetProperty($pagename, 'keywords', $m[1], ', '));
    case 'description': 
      return PZZ(SetProperty($pagename, 'description', $m[1], '\n'));
  }
}

## (:keywords:), (:description:)
Markup('keywords',    'directives', "/\\(:keywords?\\s+(.+?):\\)/i",   "MarkupSetProperty");
Markup('description', 'directives', "/\\(:description\\s+(.+?):\\)/i", "MarkupSetProperty");
$HTMLHeaderFmt['meta'] = 'function:PrintMetaTags';
function PrintMetaTags($pagename, $args) {
  global $PCache;
  foreach(array('keywords', 'description') as $n) {
    foreach((array)@$PCache[$pagename]["=p_$n"] as $v) {
      $v = str_replace("'", '&#039;', $v);
      print "<meta name='$n' content='$v' />\n";
    }
  }
}

#### inline markups ####
## ''emphasis''
Markup("''",'inline',"/''(.*?)''/",'<em>$1</em>');

## '''strong'''
Markup("'''","<''","/'''(.*?)'''/",'<strong>$1</strong>');

## '''''strong emphasis'''''
Markup("'''''","<'''","/'''''(.*?)'''''/",'<strong><em>$1</em></strong>');

## @@code@@
Markup('@@','inline','/@@(.*?)@@/','<code>$1</code>');

## '+big+', '-small-'
Markup("'+","<'''''","/'\\+(.*?)\\+'/",'<big>$1</big>');
Markup("'-","<'''''","/'\\-(.*?)\\-'/",'<small>$1</small>');

## '^superscript^', '_subscript_'
Markup("'^","<'''''","/'\\^(.*?)\\^'/",'<sup>$1</sup>');
Markup("'_","<'''''","/'_(.*?)_'/",'<sub>$1</sub>');

## [+big+], [-small-]
Markup('[+','inline','/\\[(([-+])+)(.*?)\\1\\]/',
  "MarkupBigSmall");

function MarkupBigSmall($m) {
  return '<span style=\'font-size:'
    .(round(pow(6/5,($m[2]=='-'? -1:1)*strlen($m[1]))*100,0))
    .'%\'>'. $m[3].'</span>';
}
    
## {+ins+}, {-del-}
Markup('{+','inline','/\\{\\+(.*?)\\+\\}/','<ins>$1</ins>');
Markup('{-','inline','/\\{-(.*?)-\\}/','<del>$1</del>');

## [[<<]] (break)
Markup('[[<<]]','inline','/\\[\\[&lt;&lt;\\]\\]/',"<br clear='all' />");

###### Links ######
function MarkupLinks($m){
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case '[[': 
      return Keep(MakeLink($pagename,$m[1],NULL,$m[2]),'L');
    case '[[!': 
      global $CategoryGroup, $LinkCategoryFmt;
      return Keep(MakeLink($pagename,"$CategoryGroup/{$m[1]}",NULL,'',
        $LinkCategoryFmt),'L');
    case '[[|': 
      return Keep(MakeLink($pagename,$m[1],$m[2],$m[3]),'L');
    case '[[->': 
      return Keep(MakeLink($pagename,$m[2],$m[1],$m[3]),'L');
    case '[[|#': 
      return Keep(MakeLink($pagename,$m[1],
        '['.++$GLOBALS['MarkupFrame'][0]['ref'].']'),'L');
    case '[[#': 
      return Keep(TrackAnchors($m[1]) ? '' : "<a name='{$m[1]}' id='{$m[1]}'></a>", 'L');
    case 'urllink': 
      return Keep(MakeLink($pagename,$m[0],$m[0]),'L');
    case 'mailto': 
      return Keep(MakeLink($pagename,$m[0],$m[1]),'L');
    case 'img': 
      global $LinkFunctions, $ImgTagFmt;
      return Keep($LinkFunctions[$m[1]]($pagename,$m[1],$m[2],@$m[4],$m[1].$m[2],
             $ImgTagFmt),'L');
  }
}

## [[free links]]
Markup('[[','links',"/(?>\\[\\[\\s*(.*?)\\]\\])($SuffixPattern)/", "MarkupLinks");

## [[!Category]]
SDV($CategoryGroup,'Category');
SDV($LinkCategoryFmt,"<a class='categorylink' href='\$LinkUrl'>\$LinkText</a>");
Markup('[[!','<[[','/\\[\\[!(.*?)\\]\\]/', "MarkupLinks");
# This is a temporary workaround for blank category pages.
# It may be removed in a future release (Pm, 2006-01-24)
if (preg_match("/^$CategoryGroup\\./", $pagename)) {
  SDV($DefaultPageTextFmt, '');
  SDV($PageNotFoundHeaderFmt, 'HTTP/1.1 200 Ok');
}

## [[target | text]]
Markup('[[|','<[[',
  "/(?>\\[\\[([^|\\]]*)\\|\\s*)(.*?)\\s*\\]\\]($SuffixPattern)/",
  "MarkupLinks");

## [[text -> target ]]
Markup('[[->','>[[|',
  "/(?>\\[\\[([^\\]]+?)\\s*-+&gt;\\s*)(.*?)\\]\\]($SuffixPattern)/",
  "MarkupLinks");

## [[#anchor]]
Markup('[[#','<[[','/(?>\\[\\[#([A-Za-z][-.:\\w]*))\\]\\]/', "MarkupLinks");
function TrackAnchors($x) { global $SeenAnchor; return @$SeenAnchor[$x]++; }

## [[target |#]] reference links
Markup('[[|#', '<[[|',
  "/(?>\\[\\[([^|\\]]+))\\|\\s*#\\s*\\]\\]/",
  "MarkupLinks");

## [[target |+]] title links moved inside LinkPage()

## bare urllinks 
Markup('urllink','>[[',
  "/\\b(?>(\\L))[^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars]/",
  "MarkupLinks");

## mailto: links 
Markup('mailto','<urllink',
  "/\\bmailto:([^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars])/",
  "MarkupLinks");

## inline images
Markup('img','<urllink',
  "/\\b(?>(\\L))([^\\s$UrlExcludeChars]+$ImgExtPattern)(\"([^\"]*)\")?/",
  "MarkupLinks");

if (IsEnabled($EnableRelativePageLinks, 1))
  SDV($QualifyPatterns['/(\\[\\[(?>[^\\]]+?->)?\\s*)([-\\w\\x80-\\xfe\\s\'()]+([|#?].*?)?\\]\\])/'],
    'cb_qualifylinks');

function cb_qualifylinks($m) { 
  extract($GLOBALS['tmp_qualify']);
  return "{$m[1]}$group/{$m[2]}";
}  
  
## bare wikilinks
##    v2.2: markup rule moved to scripts/wikiwords.php)
Markup('wikilink', '>urllink');

## escaped `WikiWords 
##    v2.2: rule kept here for markup compatibility with 2.1 and earlier
Markup('`wikiword', '<wikilink',
  "/`(($GroupPattern([\\/.]))?($WikiWordPattern))/",
  "MarkupNoWikiWord");
function MarkupNoWikiWord($m) { return Keep($m[1]); }

#### Block markups ####
## Completely blank lines don't do anything.
Markup('blank', '<block', '/^\\s+$/', '');

## process any <:...> markup (after all other block markups)
Markup('^<:','>block','/^(?=\\s*\\S)(<:([^>]+)>)?/',"MarkupBlock");
function MarkupBlock($m) {return Block(@$m[2]);}

## unblocked lines w/block markup become anonymous <:block>
Markup('^!<:', '<^<:',
  "/^(?!<:)(?=.*(<\\/?($BlockPattern)\\b)|$KeepToken\\d+B$KeepToken)/",
  '<:block>');

## Lines that begin with displayed images receive their own block.  A
## pipe following the image indicates a "caption" (generates a linebreak).
Markup('^img', 'block',
  "/^((?>(\\s+|%%|%[A-Za-z][-,=:#\\w\\s'\".]*%)*)$KeepToken(\\d+L)$KeepToken)(\\s*\\|\\s?)?(.*)$/",
  "ImgCaptionDiv");
function ImgCaptionDiv($m) {
  global $KPV;
  if (strpos($KPV[$m[3]], '<img')===false) return $m[0];
  $dclass = 'img';
  $ret = $m[1];
  if ($m[4]) {
    $dclass .= " imgcaption";
    $ret .= "<br /><span class='caption'>$m[5]</span>";
  }
  elseif (! $m[5]) $dclass .= " imgonly";
  else $ret .= $m[5];
  return "<:block,1><div class='$dclass'>$ret</div>";
}

## Whitespace at the beginning of lines can be used to maintain the
## indent level of a previous list item, or a preformatted text block.
Markup('^ws', '<^img', '/^\\s+ #1/x', "WSIndent");
function WSIndent($i) {
  if(is_array($i)) $i = $i[0];
  global $MarkupFrame;
  $icol = strlen($i);
  for($depth = count(@$MarkupFrame[0]['cs']); $depth > 0; $depth--)
    if (@$MarkupFrame[0]['is'][$depth] == $icol) {
      $MarkupFrame[0]['idep'] = $depth;
      $MarkupFrame[0]['icol'] = $icol;
      return '';
    }
  return $i;
}

## The $EnableWSPre setting uses leading spaces on markup lines to indicate
## blocks of preformatted text.
SDV($EnableWSPre, 1);
Markup('^ ', 'block',
  '/^\\s+ #2/x',
  "MarkupWSPre");
function MarkupWSPre($m) {
  global $EnableWSPre;
  return ($EnableWSPre > 0 && strlen($m[0]) >= $EnableWSPre)
     ? '<:pre,1>'.$m[0] : $m[0];
}
## bullet lists
Markup('^*','block','/^(\\*+)\\s?(\\s*)/','<:ul,$1,$0>$2');

## numbered lists
Markup('^#','block','/^(#+)\\s?(\\s*)/','<:ol,$1,$0>$2');

## indented (->) /hanging indent (-<) text
Markup('^->','block','/^(?>(-+))&gt;\\s?(\\s*)/','<:indent,$1,$1  $2>$2');
Markup('^-<','block','/^(?>(-+))&lt;\\s?(\\s*)/','<:outdent,$1,$1  $2>$2');

## definition lists
Markup('^::','block','/^(:+)(\s*)([^:]+):/','<:dl,$1,$1$2><dt>$2$3</dt><dd>');

## Q: and A:
Markup('^Q:', 'block', '/^Q:(.*)$/', "<:block,1><p class='question'>$1</p>");
Markup('^A:', 'block', '/^A:/', Keep(''));

## tables
function MarkupTables($m) {
  extract($GLOBALS["MarkupToHTML"]);
  switch ($markupid) {
    case 'table': return Cells(@$m[1],@$m[2]);
    case '^||||': return FormatTableRow($m[0]);
    case '^||':
      $GLOBALS['BlockMarkups']['table'][0] = '<table '.SimpleTableAttr($m[1]).'>';
      return '<:block,1>';
  }
}

## ||cell||, ||!header cell||, ||!caption!||
Markup('^||||', 'block',
  '/^\\|\\|.*\\|\\|.*$/',
  "MarkupTables");
## ||table attributes
Markup('^||','>^||||','/^\\|\\|(.*)$/',
  "MarkupTables");

#### (:table:) markup (AdvancedTables)
Markup('table', '<block',
  '/^\\(:(table|cell|cellnr|head|headnr|tableend|(?:div\\d*|section\\d*|details\\d*|article\\d*|header|footer|nav|address|aside)(?:end)?)(\\s.*?)?:\\)/i',
  "MarkupTables");
Markup('^>>', '<table',
  '/^&gt;&gt;(.+?)&lt;&lt;(.*)$/',
  '(:div:)%div $1 apply=div%$2 ');
Markup('^>><<', '<^>>',
  '/^&gt;&gt;&lt;&lt;/',
  '(:divend:)');

Markup('det-summ', '<table', '/(\\(:details[ ].*?)summary=(?:([\'"])(.*?)\\2
  |(\\S+))(.*?:\\))/xi', '$1$5<summary>$3$4</summary>'); # PITS:01465 

function SimpleTableAttr($attr) {
  global $SimpleTableDefaultClassName;
  $qattr = PQA($attr);
  if(IsEnabled($SimpleTableDefaultClassName) && !preg_match("/(^| )class='.*?' /", $qattr))
    $qattr .= "class='$SimpleTableDefaultClassName'";
  return $qattr;
}

#### (:table:) markup (AdvancedTables)
function Cells($name,$attr) {
  global $MarkupFrame, $EnableTableAutoValignTop;
  $attr = PQA($attr);
  $tattr = @$MarkupFrame[0]['tattr'];
  $name = strtolower($name);
  $key = preg_replace('/end$/', '', $name);
  if (preg_match("/^(?:head|cell)(nr)?$/", $name)) $key = 'cell';
  $out = '<:block>'.MarkupClose($key);
  if (substr($name, -3) == 'end') return $out;
  $cf = & $MarkupFrame[0]['closeall'];
  if ($name == 'table') $MarkupFrame[0]['tattr'] = $attr; 
  else if ($key == 'cell') {
    if (IsEnabled($EnableTableAutoValignTop, 1) && strpos($attr, "valign=")===false)
      $attr .= " valign='top'";
    $t = (strpos($name, 'head')===0 ) ? 'th' : 'td';
    if (!@$cf['table']) {
       $tattr = @$MarkupFrame[0]['tattr'];
       $out .= "<table $tattr><tr><$t $attr>";
       $cf['table'] = '</tr></table>';
    } else if ( preg_match("/nr$/", $name)) $out .= "</tr><tr><$t $attr>";
    else $out .= "<$t $attr>";
    $cf['cell'] = "</$t>";
  } else {
    $tag = preg_replace('/\\d+$/', '', $key);
    $tmp = "<$tag $attr>";
    if ($tag == 'details') { 
      $tmp = preg_replace("#(<details.*) summary='(.*?)'(.*)$#", '$1$3<summary>$2</summary>', $tmp);
    }
    $out .= $tmp;
    $cf[$key] = "</$tag>";
  }
  return $out;
}


## headings
Markup('^!', 'block', '/^(!{1,6})\\s?(.*)$/', "MarkupHeadings");
function MarkupHeadings($m) {
  $len = strlen($m[1]);
  return "<:block,1><h$len>$m[2]</h$len>";
}

## horiz rule
Markup('^----','>^->','/^----+/','<:block,1><hr />');

#### special stuff ####
## (:markup:) for displaying markup examples
function MarkupMarkup($pagename, $text, $opt = '') {
  global $MarkupWordwrapFunction, $MarkupWrapTag;
  SDV($MarkupWordwrapFunction, 'IsEnabled');
  SDV($MarkupWrapTag, 'pre');
  $MarkupMarkupOpt = array('class' => 'vert');
  $opt = array_merge($MarkupMarkupOpt, ParseArgs($opt));
  $html = MarkupToHTML($pagename, $text, array('escape' => 0));
  if (@$opt['caption']) 
    $caption = str_replace("'", '&#039;', 
                           "<caption>{$opt['caption']}</caption>");
  $class = preg_replace('/[^-\\s\\w]+/', ' ', @$opt['class']);
  if (strpos($class, 'horiz') !== false) 
    { $sep = ''; $pretext = $MarkupWordwrapFunction($text, 40); } 
  else 
    { $sep = '</tr><tr>'; $pretext = $MarkupWordwrapFunction($text, 75); }
  return Keep(@"<table class='markup $class' align='center'>$caption
      <tr><td class='markup1' valign='top'><$MarkupWrapTag>$pretext</$MarkupWrapTag></td>$sep<td 
        class='markup2' valign='top'>$html</td></tr></table>");
}

Markup('markup', '<[=',
  "/\\(:markup(\\s+([^\n]*?))?:\\)[^\\S\n]*\\[([=@])(.*?)\\3\\]/si",
  "MarkupMarkupMarkup");
Markup('markupend', '>markup', # $1 only shifts the other matches
  "/\\(:(markup)(\\s+([^\n]*?))?:\\)[^\\S\n]*\n(.*?)\\(:markupend:\\)/si",
  "MarkupMarkupMarkup");
function MarkupMarkupMarkup($m) { # cannot be joined, $markupid resets
  extract($GLOBALS["MarkupToHTML"]); global $MarkupMarkupLevel;
  @$MarkupMarkupLevel++;
  $x = MarkupMarkup($pagename, $m[4], $m[2]);
  $MarkupMarkupLevel--;
  return $x;
}

SDV($HTMLStylesFmt['markup'], "
  table.markup { border:2px dotted #ccf; width:90%; }
  td.markup1, td.markup2 { padding-left:10px; padding-right:10px; }
  table.vert td.markup1 { border-bottom:1px solid #ccf; }
  table.horiz td.markup1 { width:23em; border-right:1px solid #ccf; }
  table.markup caption { text-align:left; }
  div.faq p, div.faq pre { margin-left:2em; }
  div.faq p.question { margin:1em 0 0.75em 0; font-weight:bold; }
  div.faqtoc div.faq * { display:none; }
  div.faqtoc div.faq p.question 
    { display:block; font-weight:normal; margin:0.5em 0 0.5em 20px; line-height:normal; }
  div.faqtoc div.faq p.question * { display:inline; }
  td.markup1 pre { white-space: pre-wrap; }
  ");

#### Special conditions ####
## The code below adds (:if date:) conditions to the markup.
$Conditions['date'] = "CondDate(\$condparm)";

function CondDate($condparm) {
  global $Now;
  if (!preg_match('/^(\\S*?)(\\.\\.(\\S*))?(\\s+\\S.*)?$/',
                  trim($condparm), $match))
    return false;
  if ($match[4] == '') { $x0 = $Now; NoCache(); }
  else { list($x0, $x1) = DRange($match[4]); }
  if ($match[1] > '') {
    list($t0, $t1) = DRange($match[1]);
    if ($x0 < $t0) return false;
    if ($match[2] == '' && $x0 >= $t1) return false;
  }
  if ($match[3] > '') {
    list($t0, $t1) = DRange($match[3]);
    if ($x0 >= $t1) return false;
  }
  return true;
}

# This pattern enables the (:encrypt <phrase>:) markup/replace-on-save
# pattern.
SDV($ROSPatterns['/\\(:encrypt\\s+([^\\s:=]+).*?:\\)/'], 'cb_encrypt');
function cb_encrypt($m) { return pmcrypt($m[1]);}

# Table of contents, based on Cookbook:AutoTOC by Petko Yotov
SDVA($PmTOC, array(
  'Enable' => 0,
  'MaxLevel' => 6,
  'MinNumber' => 3,
  'ParentElement'=>'',
  'NumberedHeadings'=>'',
  'EnableBacklinks'=>0,
  'EnableQMarkup' => 0,
  'contents' => XL('Contents'),
  'hide' => XL('hide'),
  'show' => XL('show'),
));

if ($action!='browse') $PmTOC['Enable'] = 0;

Markup("PmTOC", 'directives', '/^\\(:[#*]?(?:toc|tdm).*?:\\)\\s*$/im', 'FmtPmTOC');
Markup("noPmTOC", 'directives', '/\\(:(no)(?:toc|tdm).*?:\\)/im', 'FmtPmTOC');
function FmtPmTOC($m) {
  if (@$m[1]) return Keep('<span class="noPmTOC"></span>');
  return "<:block,1>".Keep("<div class='PmTOCdiv'></div>");
}
SDV($HTMLStylesFmt['PmTOC'], '.noPmTOC, .PmTOCdiv:empty {display:none;}
.PmTOCdiv { display: inline-block; font-size: 13px; overflow: auto; max-height: 500px;}
.PmTOCdiv a { text-decoration: none;}
.back-arrow {font-size: .9em; text-decoration: none;}
#PmTOCchk + label {cursor: pointer;}
#PmTOCchk {display: none;}
#PmTOCchk:not(:checked) + label > .pmtoc-show {display: none;}
#PmTOCchk:checked + label > .pmtoc-hide {display: none;}
#PmTOCchk:checked + label + div {display: none;}');

SDV($HTMLStylesFmt['PmSortable'], 'table.sortable th { cursor: pointer; }
table.sortable th::after { color: transparent; content: "\00A0\025B8"; }
table.sortable th:hover::after { color: inherit; content: "\00A0\025B8"; }
table.sortable th.dir-u::after { color: inherit; content: "\00A0\025BE"; }
table.sortable th.dir-d::after { color: inherit; content: "\00A0\025B4"; }');

