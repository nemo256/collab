<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004-2020 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file implements the skin selection code for PmWiki.  Skin 
    selection is controlled by the $Skin variable, which can also
    be an array (in which case the first skin found is loaded).

    In addition, $ActionSkin[$action] specifies other skins to be
    searched based on the current action.

    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

SDV($Skin, 'pmwiki');
SDV($ActionSkin['print'], 'print');
SDV($FarmPubDirUrl, $PubDirUrl);
SDV($PageLogoUrl, "$FarmPubDirUrl/skins/pmwiki/pmwiki-32.gif");
SDVA($TmplDisplay, array('PageEditFmt' => 0));

## from skinchange.php
if (IsEnabled($EnableAutoSkinList, 0) || isset($PageSkinList)) {
  SDV($SkinCookie, $CookiePrefix.'setskin');
  SDV($SkinCookieExpires, $Now+60*60*24*365);

  if (isset($_COOKIE[$SkinCookie])) $sk = $_COOKIE[$SkinCookie];
  if (isset($_GET['setskin'])) {
    $sk = $_GET['setskin'];
    pmsetcookie($SkinCookie, $sk, $SkinCookieExpires, '/');
    if(@$EnableIMSCaching) {
      SDV($IMSCookie, $CookiePrefix.'imstime');
      pmsetcookie($IMSCookie, '', $Now -3600, '/');
      $EnableIMSCaching = 0;
    }
  }
  if (isset($_GET['skin'])) $sk = $_GET['skin'];

  ##  If $EnableAutoSkinList is set, then we accept any skin that
  ##  exists in pub/skins/ or $FarmD/pub/skins/ .
  if (IsEnabled($EnableAutoSkinList, 0) 
      && @$sk && preg_match('/^[-\\w]+$/', $sk)
      && (is_dir("pub/skins/$sk") || is_dir("$FarmD/pub/skins/$sk")))
    $Skin = $sk;

  ##  If there's a specific mapping in $PageSkinList, we use it no
  ##  matter what.
  if (@$PageSkinList[$sk]) $Skin = $PageSkinList[$sk];
}

# $PageTemplateFmt is deprecated
if (isset($PageTemplateFmt)) LoadPageTemplate($pagename,$PageTemplateFmt);
else {
  $x = array_merge((array)@$ActionSkin[$action], (array)$Skin);
  SetSkin($pagename, $x);
}

SDV($PageCSSListFmt,array(
  'pub/css/local.css' => '$PubDirUrl/css/local.css',
  'pub/css/{$Group}.css' => '$PubDirUrl/css/{$Group}.css',
  'pub/css/{$FullName}.css' => '$PubDirUrl/css/{$FullName}.css'));

foreach((array)$PageCSSListFmt as $k=>$v) 
  if (file_exists(FmtPageName($k,$pagename))) 
    $HTMLHeaderFmt[] = "<link rel='stylesheet' type='text/css' href='$v' />\n";

if(IsEnabled($WikiPageCSSFmt, false))
  InsertWikiPageCSS($pagename, $WikiPageCSSFmt);

# SetSkin changes the current skin to the first available skin from 
# the $skin array.
function SetSkin($pagename, $skin) {
  global $Skin, $SkinLibDirs, $SkinDir, $SkinDirUrl, 
    $IsTemplateLoaded, $PubDirUrl, $FarmPubDirUrl, $FarmD, $GCount;
  SDV($SkinLibDirs, array(
    "./pub/skins/\$Skin"      => "$PubDirUrl/skins/\$Skin",
    "$FarmD/pub/skins/\$Skin" => "$FarmPubDirUrl/skins/\$Skin"));
  foreach((array)$skin as $sfmt) {
    $Skin = FmtPageName($sfmt, $pagename); $GCount = 0;
    foreach($SkinLibDirs as $dirfmt => $urlfmt) {
      $SkinDir = FmtPageName($dirfmt, $pagename);
      if (is_dir($SkinDir)) 
        { $SkinDirUrl = FmtPageName($urlfmt, $pagename); break 2; }
    }
  }
  if (!is_dir($SkinDir)) {
    unset($Skin);
    Abort("?unable to find skin from list ".implode(' ',(array)$skin));
  }
  $IsTemplateLoaded = 0;
  if (file_exists("$SkinDir/$Skin.php"))
    include_once("$SkinDir/$Skin.php");
  else if (file_exists("$SkinDir/skin.php"))
    include_once("$SkinDir/skin.php");
  if ($IsTemplateLoaded) return;
  if (file_exists("$SkinDir/$Skin.tmpl")) 
    LoadPageTemplate($pagename, "$SkinDir/$Skin.tmpl");
  else if (file_exists("$SkinDir/skin.tmpl"))
    LoadPageTemplate($pagename, "$SkinDir/skin.tmpl");
  else if (($dh = opendir($SkinDir))) {
    while (($fname = readdir($dh)) !== false) {
      if ($fname[0] == '.') continue;
      if (substr($fname, -5) != '.tmpl') continue;
      if ($IsTemplateLoaded) 
        Abort("?unable to find unique template in $SkinDir");
      LoadPageTemplate($pagename, "$SkinDir/$fname");
    }
    closedir($dh);
  }
  if (!$IsTemplateLoaded) Abort("Unable to load $Skin template", 'skin');
}

function cb_includeskintemplate($m) {
  global $SkinDir, $pagename;
  $x = preg_split('/\\s+/', $m[1], -1, PREG_SPLIT_NO_EMPTY);
  for ($i=0; $i<count($x); $i++) {
    $f = FmtPageName("$SkinDir/{$x[$i]}", $pagename);
    if (strpos($f, '..')!==false || preg_match('/%2e/i', $f)) continue;
    if (file_exists($f)) return implode('', file($f));
  }
}

# LoadPageTemplate loads a template into $TmplFmt
function LoadPageTemplate($pagename,$tfilefmt) {
  global $PageStartFmt, $PageEndFmt, $SkinTemplateIncludeLevel,
    $EnableSkinDiag, $HTMLHeaderFmt, $HTMLFooterFmt,
    $IsTemplateLoaded, $TmplFmt, $TmplDisplay,
    $PageTextStartFmt, $PageTextEndFmt, $SkinDirectivesPattern;

  SDV($PageTextStartFmt, "\n<div id='wikitext'>\n");
  SDV($PageTextEndFmt, "</div>\n");
  SDV($SkinDirectivesPattern, 
      "[[<]!--((?:wiki|file|function|markup):.*?)--[]>]");

  $sddef = array('PageEditFmt' => 0);
  $k = implode('', file(FmtPageName($tfilefmt, $pagename)));
  
  for ($i=0; $i<IsEnabled($SkinTemplateIncludeLevel, 0) && $i<10; $i++) {
    if (stripos($k, '<!--IncludeTemplate')===false) break;
    $k = preg_replace_callback('/[<]!--IncludeTemplate: *(\\S.*?) *--[>]/i',
      'cb_includeskintemplate', $k);
  }

  if (IsEnabled($EnableSkinDiag, 0)) {
    if (!preg_match('/<!--((No)?(HT|X)MLHeader|HeaderText)-->/i', $k))
      Abort("Skin template missing &lt;!--HTMLHeader--&gt;", 'htmlheader');
    if (!preg_match('/<!--(No)?(HT|X)MLFooter-->/i', $k))
      Abort("Skin template missing &lt;!--HTMLFooter--&gt;", 'htmlheader');
  }

  $sect = preg_split(
    '#[[<]!--(/?(?:Page[A-Za-z]+Fmt|(?:HT|X)ML(?:Head|Foot)er|HeaderText|PageText).*?)--[]>]#',
    $k, 0, PREG_SPLIT_DELIM_CAPTURE);
  $TmplFmt['Start'] = array_merge(array('headers:'),
    preg_split("/$SkinDirectivesPattern/s",
      array_shift($sect),0,PREG_SPLIT_DELIM_CAPTURE));
  $TmplFmt['End'] = array($PageTextEndFmt);
  $ps = 'Start';
  while (count($sect)>0) {
    $k = array_shift($sect);
    $v = preg_split("/$SkinDirectivesPattern/s",
      array_shift($sect),0,PREG_SPLIT_DELIM_CAPTURE);
    $TmplFmt[$ps][] = "<!--$k-->";
    if ($k[0] == '/')
      { $TmplFmt[$ps][] = (count($v) > 1) ? $v : $v[0]; continue; }
    @list($var, $sd) = explode(' ', $k, 2);
    $GLOBALS[$var] = (count($v) > 1) ? $v : $v[0];
    if ($sd > '') $sddef[$var] = $sd;
    if ($var == 'PageText') { $ps = 'End'; }
    if ($var == 'HTMLHeader' || $var == 'XMLHeader') 
      $TmplFmt[$ps][] = &$HTMLHeaderFmt; 
    if ($var == 'HTMLFooter' || $var == 'XMLFooter') 
      $TmplFmt[$ps][] = &$HTMLFooterFmt; 
    ##   <!--HeaderText--> deprecated, 2.1.16
    if ($var == 'HeaderText') { $TmplFmt[$ps][] = &$HTMLHeaderFmt; }
    $TmplFmt[$ps][$var] =& $GLOBALS[$var];
  }
  array_push($TmplFmt['Start'], $PageTextStartFmt);
  $PageStartFmt = 'function:PrintSkin Start';
  $PageEndFmt = 'function:PrintSkin End';
  $IsTemplateLoaded = 1;
  SDVA($TmplDisplay, $sddef);
}

# This function is called to print a portion of the skin template
# according to the settings in $TmplDisplay.
function PrintSkin($pagename, $arg) {
  global $TmplFmt, $TmplDisplay;
  foreach ($TmplFmt[$arg] as $k => $v) 
    if (!isset($TmplDisplay[$k]) || $TmplDisplay[$k])
      PrintFmt($pagename, $v);
}

# This function parses a wiki page like Site.LocalCSS
# and inserts CSS rules specific to the current page.
# Based on Cookbook:LocalCSS by Petko Yotov
function InsertWikiPageCSS($pagename, $fmt) {
  global $HTMLStylesFmt, $EnableSelfWikiPageCSS, $WikiPageCSSVars;
  SDV($WikiPageCSSVars,array('FarmPubDirUrl','PubDirUrl','Skin','action','SkinDirUrl'));

  $stylepagename = FmtPageName($fmt, $pagename);
  if ($stylepagename == $pagename &&
    !IsEnabled($EnableSelfWikiPageCSS, 0)) return;

  if ($stylepagename == $pagename && @$_POST['text'])
    $text = stripmagic($_POST['text']);
  else {
    $p = ReadPage($stylepagename, READPAGE_CURRENT);
    $text =  @$p['text'];
  }
  if (!$text) return;

  $text = str_replace(array("\r",'$','<','&#036;='),array('','&#036;','&lt;','$='), $text);
  $varray = array();

  # global PHP variables as @variables
  foreach($WikiPageCSSVars as $var) $varray["@$var"] = $GLOBALS[$var];

  # get @variables from page
  if (preg_match_all("/^\\s*(@\\w+):\\s*(.*?)\\s*$/m", $text, $vars) )
    foreach($vars[1] as $k=>$varname) $varray[$varname] = trim($vars[2][$k]);

  # expand nested @variables
  for ($i=0; $i<10; $i++) $text = strtr($text, $varray);

  # process snippets
  if (preg_match_all("/\\[@\\s*([^\\/!\\s]+)\n(.*?)\\s*@\\]/s", $text, $matches, PREG_SET_ORDER) )
    foreach($matches as $a)
      if (count(MatchPageNames($pagename, trim($a[1]))))
        @$HTMLStylesFmt['WikiPageCSS'] .= trim($a[2]);
}

