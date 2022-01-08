<?php if (!defined('PmWiki')) exit();
/*  Copyright 2002-2018 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script provides special handling for WikiWords that are
    preceded by a $, treating them as PmWiki variables to be looked up
    in the variable documentation pages if such documentation exists.
    The $VarPagesFmt variable contains a list of pages to be searched
    to build an index of the variable documentation.  This index is 
    generated only once per browse request, and then only when needed.
    
    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

SDV($VarPagesFmt,array('$[PmWiki.Variables]'));
Markup('vardef','<links',"/^:\\$($WikiWordPattern|Author|Skin|pagename|Version) *:/",
  ':%apply=item id=$1%$$1:');
Markup('varlink','<wikilink',"/\\$($WikiWordPattern|Author|Skin|pagename|Version)\\b/",
  "MarkupVarLinkIndex");
Markup('varindex', 'directives',
  '/\\(:varindex:\\)/i', "MarkupVarLinkIndex");

function MarkupVarLinkIndex($m) {
  extract($GLOBALS["MarkupToHTML"]); # get $pagename, $markupid
  switch ($markupid) {
    case 'varlink': 
      return Keep(VarLink($pagename,$m[1],'$'.$m[1]));
    case 'varindex': 
      return Keep(VarIndexList($pagename));
  }
}

SDVA($HTMLStylesFmt, array('vardoc' => "a.varlink { text-decoration:none;}\n"));

function VarLink($pagename,$tgt,$txt) {
  global $VarIndex,$FmtV,$VarLinkMissingFmt,$VarLinkExistsFmt;
  SDV($VarLinkMissingFmt,'$LinkText');
  SDV($VarLinkExistsFmt,"<a class='varlink' href='\$LinkUrl'><code class='varlink'>\$LinkText</code></a>");
  VarIndexLoad($pagename);
  $FmtV['$LinkText'] = str_replace('$', '&#36;', $txt);
  if (@!$VarIndex[$tgt]['pagename'])
    return FmtPageName($VarLinkMissingFmt,$pagename);
  return MakeLink($pagename,"{$VarIndex[$tgt]['pagename']}#$tgt",$txt,null,$VarLinkExistsFmt);
}

function VarIndexLoad($pagename) {
  global $VarPagesFmt,$VarIndex,$WikiWordPattern;
  static $loaded;
  $VarIndex = (array)@$VarIndex;
  if ($loaded) return;
  $tmp = array();
  foreach($VarPagesFmt as $vf) {
    $v = FmtPageName($vf, $pagename);
    if (@$loaded[$v]) continue;
    $vlist = array($v);
    $t = ReadTrail($pagename,$v);
    if ($t) 
      for($i=0;$i<count($t);$i++) 
        if (@!$loaded[$t[$i]['pagename']]) $vlist[]=$t[$i]['pagename'];
    foreach($vlist as $vname) {
      $vpage = ReadPage($vname, READPAGE_CURRENT); @$loaded[$vname]++;
      if (!$vpage) continue;
      if (!preg_match_all("/\n:\\$([[:upper:]]\\w+|pagename) *:/",@$vpage['text'],$match))
        continue;
      foreach($match[1] as $n) {
        $tmp[$n]['pagename'] = $vname;
        $tmp[$n]['url'] = FmtPageName("{\$PageUrl}#$n",$vname);
      }
    }
  }
  $keys = array_keys($tmp);
  natcasesort($keys); # ksort requires PHP 5.4 for caseless sort
  foreach($keys as $k) {
    $VarIndex[$k] = $tmp[$k];
  }
}

# VarIndexList() generates a table of all indexed variables.
function VarIndexList($pagename) {
  global $VarIndex;
  if (!isset($VarIndex)) VarIndexLoad($pagename);
  $out = FmtPageName("<table><tr><th>$[Variable]</th><th>$[Documented in]</th></tr>\n", $pagename);
  foreach($VarIndex as $v=>$a) 
    $out .= FmtPageName("<tr><td><a class='varlink' 
      href='{$a['url']}'><code>&#036;$v</code></a></td><td><a 
      href='{\$PageUrl}'>{\$Title}</a></td></tr>\n",$a['pagename']);
  $out .= "</table>";
  return $out;
}

