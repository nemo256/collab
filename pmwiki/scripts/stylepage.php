<?php if (!defined('PmWiki')) exit();

/*  File stylepage.php for pmwiki 2.
    Copyright 2006 Patrick R. Michaud (pmichaud@pobox.com),
    parts copyright 2006 Hans Bracker.
    This file is distributed under the terms of the GNU General Public 
    License as published by the Free Software Foundation; either 
    version 2 of the License, or (at your option) any later version.  

    This module enables loading of css style code from a wiki page.
    Style pages will be cached in pub/cache/ directory by default.
    The style page will be loaded via 
    
    Install in the usual way in cookbook/ directory.
    Create directory pub/cache/ for the cached style pages.
    The style page will be loaded via standard html link syntax.
    <link rel='stylesheet' href='$cssurl' type='text/css' />
    
    Usage: 
    markup (:stylepage:) or (:stylepage Group.StylePage:)
    (:stylepage:) loads css code from a default style page.
    (:stylepage Group.StylePage:) loads css code from an alternative page
    for instance 'Group.StylePage'. By default restricted to $SiteGroup, 
    so the style pages have the benefit of special protection.
    You can set a different group to hold Style pages than $SiteGroup,
    by setting $DefaultStyleGroup = 'GroupName';
    GroupName being the name of this group, for example 'StyleGroup'.
    
    $EnableDefaultGroupStylesOnly = 0; will allow style pages in any group.
    
    Setting $EnableStylePage = 1; will load the default style page for all pages,
    without (:stylepage:) markup necessary. If (:stylepage Group.StylePage:)
    is used it will override the default style page.

*/
# Version date
$RecipeInfo['CSSInWikiPages']['Version'] = '2021-11-10';


# defaults
SDV($EnableStylePage, 0);
SDV($DefaultStylePage, 'Site.StyleSheet');
SDV($DefaultStyleGroup, $SiteGroup); 
SDV($EnableDefaultGroupStylesOnly, 1);
SDV($PubCacheDir, "pub/cache");
SDV($PubCacheUrl, "$PubDirUrl/cache");
SDV($StylePageCacheDir, $PubCacheDir);
SDV($StylePageCacheUrl, $PubCacheUrl);

mkdirp($StylePageCacheDir);

if ($EnableStylePage==1) LoadStylePage($DefaultStylePage);

Markup('stylepage', '<img', "/\\(:stylepage\\s?(.*?)\\s*?:\\)/", "LoadStylePage");

function LoadStylePage($m) {
    $template = $m[1];
    global $HTMLStylesFmt,$HTMLHeaderFmt,$DefaultStylePage,$DefaultStyleGroup,$EnableStylePage,
           $EnableDefaultGroupStylesOnly,$StylePageCacheDir,$StylePageCacheUrl,$PubDirUrl,$LastModTime;
    
    ## check if pagename is supplied
    if ($template) { 
        $stpg = MakePageName($pagename, $template);
        $group = PageVar($stpg, '$Group');
        
        ## check if groupname is default group or no group restrictions, and set name
        if ($group==$DefaultStyleGroup || $EnableDefaultGroupStylesOnly==0) 
            $stylepagename = $stpg;
        
        ## check failed, so do nothing
        else return '';
        }
    
    ## use default page if no pagename supplied
    else $stylepagename = $DefaultStylePage;
    
    ## $stylepagename holds the name of the page to be included

    ## find the name and url of the css file in pub/cache/
    $cssfile = FmtPageName("$StylePageCacheDir/{\$FullName}.css", $stylepagename);
    $cssurl = FmtPageName("$StylePageCacheUrl/{\$FullName}.css", $stylepagename);

    ## if the cached .css file doesn't exist or is older than the
    ## latest site modification, we need to re-generate it
    $fmodtime = @filemtime($cssfile);
    if ($fmodtime < $LastModTime || $fmodtime==0 || $LastModTime==0) {
       ## remove the outdated cache file
       @unlink($cssfile);

       ## read the stylepage, if we can
       $stylepage = 
          RetrieveAuthPage($stylepagename, 'read', false, READPAGE_CURRENT);

       ## if we couldn't read it, we're done
       if (!$stylepage) return '';

       ## if we could read it but it's read-protected, we can't cache it.
       ## so, put its contents into $HTMLStylesFmt[] and return
       if ($stylepage['=passwd']['read']
           && $stylepage['=passwd']['read'][0] != '@nopass') {
             $HTMLStylesFmt[] = $stylepage['text'];
             return '';
       }

       ## save a copy of the text to the cached css file
       $fp = fopen($cssfile, "w");
       fputs($fp, $stylepage['text']);
       fclose($fp);
   }

   ## okay, the cache file is up-to-date, so put a link to it into
   ## $HTMLHeaderFmt[]
   $HTMLHeaderFmt[] = 
      "<link rel='stylesheet' href='$cssurl' type='text/css' />\n";

   ## we're done
   return '';
}
