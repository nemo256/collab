/***********************************************************************
**  skin.js
**  Copyright 2016-2017 Petko Yotov www.pmwiki.org/petko
**  
**  This file is part of PmWiki; you can redistribute it and/or modify
**  it under the terms of the GNU General Public License as published
**  by the Free Software Foundation; either version 2 of the License, or
**  (at your option) any later version.  See pmwiki.php for full details.
**  
**  This script fixes the styles of some elements when some directives
**  like (:noleft:) are used in a page.
***********************************************************************/
(function(){
  var W = window, D = document;
  function $(x) { // returns element from id
    return D.getElementById(x);
  }
  function hide(id) { // hides element
    var el = $(id);
    if(el) el.style.display = 'none'; 
  }
  function cname(id, c) { // set element className
    var el = $(id);
    if(el) el.className = c;
  }
  var wsb = $('wikisidebar');
  if(! wsb) { // (:noleft:)
    hide('wikileft-toggle-label')
    cname('wikifoot', 'nosidebar');
  }
  else { 
    var sbcontent = wsb.textContent || wsb.innerText;
    if(! sbcontent.replace(/\s+/, '').length) // empty sidebar, eg. protected
      hide('wikileft-toggle-label');
  }
  var wcmd = $('wikicmds');
  if(wcmd) { // page actions
    var pacontent = wcmd.textContent || wcmd.innerText;
    if(! pacontent.replace(/\s+/, '').length) // empty, eg. protected
      hide('wikicmds-toggle-label');
  }
  if(! $('wikihead-searchform')) // no search form, eg. custom header
    hide('wikihead-search-toggle-label');
  var overlay = $('wikioverlay');
  if(overlay) {
    overlay.addEventListener('click', function(){
      $('wikicmds-toggle').checked = false;
      $('wikihead-search-toggle').checked = false;
      $('wikileft-toggle').checked = false;
    });
  }
  var scrolltables = function() {
    // This function "wraps" large tables in a scrollable div
    // and "unwraps" narrow tables from the scrollable div 
    // allowing table alignement
    var tables = D.getElementsByTagName('table');
    for(var i=0; i<tables.length; i++) {
      var t = tables[i];
      var pn = t.parentNode;
      if(pn.className == 'scrollable') {
        var gp = pn.parentNode;
        if(t.offsetWidth < gp.offsetWidth) {
          gp.insertBefore(t, pn);
          gp.removeChild(pn);
        }
      }
      else  {
        if(t.offsetWidth > pn.offsetWidth) {
          var nn = D.createElement('div');
          pn.insertBefore(nn, t).className = 'scrollable';
          nn.appendChild(t);
        }
      }
    }
  }
  W.addEventListener('resize', scrolltables, false);
  D.addEventListener('DOMContentLoaded', scrolltables, false);
})();
