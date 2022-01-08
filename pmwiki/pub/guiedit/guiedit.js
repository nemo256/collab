/*  Copyright 2004-2019 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file provides Javascript functions to support WYSIWYG-style
    editing.  The concepts are borrowed from the editor used in Wikipedia,
    but the code has been rewritten from scratch to integrate better with
    PHP and PmWiki's codebase.

    Script maintained by Petko Yotov www.pmwiki.org/petko
*/

function insButton(mopen, mclose, mtext, mlabel, mkey) {
  if (mkey > '') { mkey = 'accesskey="' + mkey + '" ' }
  document.write("<a tabindex='-1' " + mkey + "onclick=\"insMarkup('"
    + mopen + "','"
    + mclose + "','"
    + mtext + "');\">"
    + mlabel + "</a>");
}

function insMarkup() {
  var func = false, tid='text', mopen = '', mclose = '', mtext = '';
  if (arguments[0] == 'FixSelectedURL') {
    func = FixSelectedURL;
  }
  else if (typeof arguments[0] == 'function') {
    var func = arguments[0];
    if(arguments.length > 1) tid = arguments[1];
    mtext = func('');
  }
  else if (arguments.length >= 3) {
    var mopen = arguments[0], mclose = arguments[1], mtext = arguments[2];
    if(arguments.length > 3) tid = arguments[3];
  }

  var tarea = document.getElementById(tid);
  if (tarea.setSelectionRange > '') {
    var p0 = tarea.selectionStart;
    var p1 = tarea.selectionEnd;
    var top = tarea.scrollTop;
    var str = mtext;
    var cur0 = p0 + mopen.length;
    var cur1 = p0 + mopen.length + str.length;
    while (p1 > p0 && tarea.value.substring(p1-1, p1) == ' ') p1--; 
    if (p1 > p0) {
      str = tarea.value.substring(p0, p1);
      if(func) str = func(str);
      cur0 = p0 + mopen.length + str.length + mclose.length;
      cur1 = cur0;
    }
    tarea.value = tarea.value.substring(0,p0)
      + mopen + str + mclose
      + tarea.value.substring(p1);
    tarea.focus();
    tarea.selectionStart = cur0;
    tarea.selectionEnd = cur1;
    tarea.scrollTop = top;
  } else if (document.selection) {
    var str = document.selection.createRange().text;
    tarea.focus();
    range = document.selection.createRange();
    if (str == '') {
      range.text = mopen + mtext + mclose;
      range.moveStart('character', -mclose.length - mtext.length );
      range.moveEnd('character', -mclose.length );
    } else {
      if (str.charAt(str.length - 1) == " ") {
        mclose = mclose + " ";
        str = str.substr(0, str.length - 1);
        if(func) str = func(str);
      }
      range.text = mopen + str + mclose;
    }
    range.select();
  } else { tarea.value += mopen + mtext + mclose; }
  return;
}

// Helper functions below by Petko Yotov, www.pmwiki.org/petko
function aE(el, ev, fn) {
  if(typeof el == 'string') el = dqsa(el);
  for(var i=0; i<el.length; i++) el[i].addEventListener(ev, fn);
}
function dqs(str)  { return document.querySelector(str); }
function dqsa(str) { return document.querySelectorAll(str); }
function tap(q, fn) { aE(q, 'click', fn); };
function adata(el, x) { return el.getAttribute("data-"+x); }
function FixSelectedURL(str) {
  var rx = new RegExp("[ <>\"{}|\\\\^`()\\[\\]']", 'g');
  str = str.replace(rx, function(a){
    return '%'+a.charCodeAt(0).toString(16); });
  return str;
}

window.addEventListener('DOMContentLoaded', function(){
  var NsForm = false;

  var sTop = dqs("#textScrollTop");
  var tarea = dqs('#text');
  if(sTop && tarea) {
    if(sTop.value) tarea.scrollTop = sTop.value;
    sTop.form.addEventListener('submit', function(){
      sTop.value = tarea.scrollTop;
    });
  }

  var ensw = dqs('#EnableNotSavedWarning');
  if(ensw) {
    var NsMessage = ensw.value;
    NsForm = ensw.form;
    if(NsForm) {
      NsForm.addEventListener('submit', function(e){
        NsMessage="";
      });
      window.onbeforeunload = function(ev) {
        if(NsMessage=="") {return;}
        if (typeof ev == "undefined") {ev = window.event;}
        if (tarea && tarea.codemirror) {tarea.codemirror.save();}

        var tx = NsForm.querySelectorAll('textarea, input[type="text"]');
        for(var i=0; i<tx.length; i++) {
          var el = tx[i];
          if(ensw.className.match(/\bpreview\b/) || el.value != el.defaultValue) {
            if (ev) {ev.returnValue = NsMessage;}
            return NsMessage;
          }
        }
      }
    }
  }
  if(dqs('#EnableEditAutoText')) EditAutoText();
});

/*
 *  Edit helper for PmWiki
 *  (c) 2016 Petko Yotov www.pmwiki.org/petko
 */
function EditAutoText(){
  var t = dqs('#text');
  if(!t) return;


  t.addEventListener('keydown', function(e){
    if (e.keyCode != 13) return;
    //else [Enter/Return]
    var caret = this.selectionStart;
    if(!caret) return true; // old MSIE, sorry
    var content = this.value;
    var before = content.substring(0, caret).split(/\n/g);
    var after  = content.substring(this.selectionEnd);
    var currline = before[before.length-1];

    if(currline.match(/[^\\]\\$/)) return true; // line ending with a single \ backslash
    var insert = "\n";
    if(e.ctrlKey && e.shiftKey) {
      insert = "~~~~\n";
    }
    else if(e.ctrlKey) {
      insert = "[[<<]]\n";
    }
    else if(e.shiftKey) {
      insert = "\\\\\n";
    }
    else {
      var m = currline.match(/^((?: *\*+| *\#+|-+[<>]|:+|\|\|| ) *)/);
      if(!m) return true;
      var insert = "\n"+m[1];
    }
    e.preventDefault();

    content = before.join("\n") + insert + after;
    this.value = content;
    this.selectionStart = caret + insert.length;
    this.selectionEnd = caret + insert.length;
    return false;
  });
};

