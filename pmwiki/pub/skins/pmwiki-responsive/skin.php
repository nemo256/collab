<?php if (!defined('PmWiki')) exit();
/***********************************************************************
**  skin.php
**  Copyright 2016-2019 Petko Yotov www.pmwiki.org/petko
**  
**  This file is part of PmWiki; you can redistribute it and/or modify
**  it under the terms of the GNU General Public License as published
**  by the Free Software Foundation; either version 2 of the License, or
**  (at your option) any later version.  See pmwiki.php for full details.
***********************************************************************/


global $HTMLStylesFmt, $SkinElementsPages, $DefaultSkinElements, $TableCellAlignFmt, 
  $SearchBoxInputType, $WrapSkinSections, $HideTemplateSections, $EnableTableAttrToStyles;

# Disable inline styles injected by the PmWiki core (we provide these styles in skin.css)
$styles = explode(' ', 'pmwiki rtl-ltr wikistyles markup simuledit diff urlapprove vardoc PmSortable PmTOC');
foreach($styles as $style) $HTMLStylesFmt[$style] = '';

# CSS alignment for table cells (valid HTML5)
SDV($TableCellAlignFmt, " class='%s'");

# For (:searchbox:), valid semantic HTML5
$SearchBoxInputType = "search";

# remove deprecated "name=" parameter from anchor tags
if($GLOBALS['VersionNum'] < 2002056) {
  # we want the skin to also work with older PmWiki versions
  Markup('[[#','<[[','/(?>\\[\\[#([A-Za-z][-.:\\w]*))\\]\\]/e',
    "Keep(TrackAnchors('$1') ? '' : \"<a id='$1'></a>\", 'L')");
}
else {
  Markup('[[#','<[[','/(?>\\[\\[#([A-Za-z][-.:\\w]*))\\]\\]/',
    "MarkupKeepTrackAnchors");
}
function MarkupKeepTrackAnchors($m) {
  return Keep(TrackAnchors($m[1]) ? '' : "<a id='{$m[1]}'></a>", 'L');
}

# in HTML5 "clear" is a style not an attribute
Markup('[[<<]]','inline','/\\[\\[&lt;&lt;\\]\\]/',"<br style='clear:both;' />");

# Allow skin header and footer to be written 
# in a wiki page, and use defaults otherwise
SDVA($WrapSkinSections, array(
  '#skinheader' => '<header id="wikihead">
      <div id="wikihead-content">
      %s
      </div>
    </header>',
  '#skinfooter' => '<footer id="wikifoot">
    %s
  </footer>',
));
SDVA($HideTemplateSections, array(
  '#skinheader' => 'PageHeaderFmt',
  '#skinfooter' => 'PageFooterFmt',
));

# This function prints a skin element which is written 
# inside a [[#header]]...[[#headerend]] section in Site.SkinElements
# overriding the existing section from the template file

function SkinFmt($pagename, $args) {
  global $WrapSkinSections, $HideTemplateSections, $TmplDisplay;
  
  $args = preg_split('!\\s+!', $args, null, PREG_SPLIT_NO_EMPTY);
  
  $section = array_shift($args);
  $hidesection = $HideTemplateSections[$section];
  
  if(isset($TmplDisplay[$hidesection]) && $TmplDisplay[$hidesection] == 0) {
    return;      # Section was disabled by (:noheader:) or (:nofooter:)
  }
  
  foreach($args as $p) {
    $pn = FmtPageName($p, $pagename);
    $elm = RetrieveAuthSection($pn, "$section{$section}end");
    if(!$elm) continue;
    
    $html = MarkupToHTML($pagename, Qualify($pn, $elm));
    echo sprintf($WrapSkinSections[$section], $html);
    SetTmplDisplay($hidesection,0);
    return;
  }
  if(@$DefaultSkinElements[$section])
    echo FmtPageName($DefaultSkinElements[$section], $pagename);
}

