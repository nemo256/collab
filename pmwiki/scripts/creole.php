<?php if (!defined('PmWiki')) exit();
/*  Copyright 2007-2017 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script adds Creole v0.4 markup (http://www.wikicreole.org/)
    to PmWiki.  To activate this script, simply add the following into
    a local customization file:

        include_once('scripts/creole.php');

    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

## **strong**
Markup('**', 'inline', 
  '/^\\*\\*(?>(.+?)\\*\\*)(?!\\S)|(?<!^)\\*\\*(.+?)\\*\\*/',
  '<strong>$1$2</strong>');

## //emphasized//
Markup('//', 'inline', 
  '/(?<!http:|https:|ftp:)\\/\\/(.*?)\\/\\//',
  '<em>$1</em>');

## == Headings ==
Markup('^=', 'block',
  '/^(={1,6})\\s?(.*?)(\\s*=*\\s*)$/',
  "MarkupCreole");

## Line breaks
Markup('\\\\', 'inline', '/\\\\\\\\/', '<br />');

## Preformatted
Markup('^{{{', '[=',
  "/^\\{\\{\\{\n(.*?\n)\\}\\}\\}[^\\S\n]*\n/sm",
  "MarkupCreole");
Markup('{{{', '>{{{',
  '/\\{\\{\\{(.*?)\\}\\}\\}/s',
  "MarkupCreole");

## Tables
Markup('|-table', '>^||',
  '/^\\|(.*)$/',
  "MarkupCreole");

## Images
Markup('{{', 'inline',
  '/\\{\\{(?>(\\L))([^|\\]]*)(?:\\|\\s*(.*?)\\s*)?\\}\\}/',
  "MarkupCreole");

function MarkupCreole($m) {
  extract($GLOBALS["MarkupToHTML"]); # get $pagename, $markupid
  switch ($markupid) {
    case '^=': 
      return '<:block,1><h'.strlen($m[1]).'>'.$m[2].'</h'.strlen($m[1]).'>';
    case '^{{{': 
      return Keep('<pre class="escaped">'.$m[1].'</pre>');
    case '{{{': 
      return Keep('<code class="escaped">'.$m[1].'</code>');
    case '|-table': 
      return FormatTableRow($m[0], '\\|');
    case '{{': 
      return Keep($GLOBALS['LinkFunctions'][$m[1]]($pagename, $m[1], $m[2], $m[3],
     $m[1].$m[2], $GLOBALS['ImgTagFmt']),'L');
  }
}

## GUIButtons
SDVA($GUIButtons, array(
  'em'       => array(100, "//", "//", '$[Emphasized]',
                  '$GUIButtonDirUrlFmt/em.gif"$[Emphasized (italic)]"',
                  '$[ak_em]'),
  'strong'   => array(110, "**", "**", '$[Strong]',
                  '$GUIButtonDirUrlFmt/strong.gif"$[Strong (bold)]"',
                  '$[ak_strong]'),
  'h2'       => array(400, '\\n== ', ' ==\\n', '$[Heading]',
                  '$GUIButtonDirUrlFmt/h.gif"$[Heading]"'),

  ));

