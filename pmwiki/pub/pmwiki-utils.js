/*
  JavaScript utilities for PmWiki
  (c) 2009-2021 Petko Yotov www.pmwiki.org/petko
  based on PmWiki addons DeObMail, AutoTOC and Ape
  licensed GNU GPLv2 or any more recent version released by the FSF.

  libsortable() "Sortable tables" adapted for PmWiki from
  a Public Domain event listener by github.com/tofsjonas
*/

(function(){
  function aE(el, ev, fn) {
    if(typeof el == 'string') el = dqsa(el);
    for(var i=0; i<el.length; i++) el[i].addEventListener(ev, fn);
  }
  function dqs(str)  { return document.querySelector(str); }
  function dqsa(str) { return document.querySelectorAll(str); }
  function tap(q, fn) { aE(q, 'click', fn); };
  function adata(el, x) { return el.getAttribute("data-"+x); }
  function sdata(el, x, val) { el.setAttribute("data-"+x, val); }
  function pf(x) {return parseFloat(x);}

  var __script__ = dqs('script[src*="pmwiki-utils.js"]');
  var wikitext = document.getElementById('wikitext');

  function PmXMail() {
    var els = document.querySelectorAll('span._pmXmail');
    var LinkFmt = '<a href="%u" class="mail">%t</a>';

    for(var i=0; i<els.length; i++) {
      var x = els[i].querySelector('span._t');
      var txt = cb_mail(x.innerHTML);
      var y = els[i].querySelector('span._m');
      var url = cb_mail(y.innerHTML.replace(/^ *-&gt; */, ''));

      if(!url) url = 'mailto:'+txt.replace(/^mailto:/, '');

      url = url.replace(/"/g, '%22').replace(/'/g, '%27');
      var html = LinkFmt.replace(/%u/g, url).replace(/%t/g, txt);
      els[i].innerHTML = html;
    }
  }
  function cb_mail(x){
    return x.replace( /<span class=(['"]?)_d\1>[^<]+<\/span>/ig, '.')
      .replace( /<span class=(['"]?)_a\1>[^<]+<\/span>/ig, '@');
  }

  function is_toc_heading(el) {
    if(el.offsetParent === null) {return false;}  // hidden
    if(el.className.match(/\bnotoc\b/)) {return false;} // %notoc%
    var p = el.parentNode;
    while(p && p !== wikitext) { // >>notoc<<, (:markup:)
      if(p.className.match(/\b(notoc|markup2)\b/)) {return false;}
      if(p.parentNode) p = p.parentNode;
    }
    return true;
  }
  function posy(el) {
    var top = 0;
    if (el.offsetParent) {
      do {
        top += el.offsetTop;
      } while (el = el.offsetParent);
    }
    return top;
  }

  function any_id(h) {
    if(h.id) {return h.id;} // %id=anchor%
    var a = h.querySelector('a[id]'); // inline [[#anchor]]
    if(a && a.id) {return a.id;}
    var prev = h.previousElementSibling;
    if(prev) { // [[#anchor]] before !!heading
      var a = prev.querySelectorAll('a[id]');
      if(a.length) {
        last = a[a.length-1];
        if(last.id && ! last.nextElementSibling) {
          var atop = posy(last) + last.offsetHeight;
          var htop = posy(h);
          if( Math.abs(htop-atop)<20 ) {
            h.appendChild(last);
            return last.id;
          }
        }
      }
    }
    return false;
  }

  function repeat(x, times) {
    var y = '';
    for(var i=0; i<times; i++) y += '' + x;
    return y;
  }
  function inittoggle() {
    var tnext = adata(__script__, 'toggle');
    if(! tnext) { return; }
    var x = dqsa(tnext);
    if(! x.length) return;
    for(var i=0; i<x.length; i++) togglenext(x[i]);
    tap(tnext, togglenext);
    tap('.pmtoggleall', toggleall);
  }
  function togglenext(z) {
    var el = z.type == 'click' ? this : z;
    var attr = adata(el, 'pmtoggle')=='closed' ? 'open' : 'closed';
    sdata(el, 'pmtoggle', attr);
  }
  function toggleall(){
    var curr = adata(this, 'pmtoggleall');
    if(!curr) curr = 'closed';
    var toggles = dqsa('*[data-pmtoggle="'+curr+'"]');
    var next = curr=='closed' ? 'open' : 'closed';
    for(var i=0; i<toggles.length; i++) {
      sdata(toggles[i], 'pmtoggle', next);
    }
    var all = dqsa('.pmtoggleall');
    for(var i=0; i<all.length; i++) {
      sdata(all[i], 'pmtoggleall', next);
    }
  }

  function autotoc() {
    if(dqs('.noPmTOC')) { return; } // (:notoc:) in page
    var dtoc = adata(__script__, 'pmtoc');
    try {dtoc = JSON.parse(dtoc);} catch(e) {dtoc = false;}
    if(! dtoc) { return; } // error

    if(! dtoc.Enable || !dtoc.MaxLevel) { return; } // disabled

    if(dtoc.NumberedHeadings)  {
      var specs = dtoc.NumberedHeadings.toString().split(/\./g);
      for(var i=0; i<specs.length; i++) {
        if(specs[i].match(/^[1AI]$/i)) numheadspec[i] = specs[i];
      }
    }

    var query = [];
    for(var i=1; i<=dtoc.MaxLevel; i++) {
      query.push('h'+i);
    }
    if(dtoc.EnableQMarkup) query.push('p.question');
    var pageheadings = wikitext.querySelectorAll(query.join(','));
    if(!pageheadings.length) { return; }

    var toc_headings = [ ];
    var minlevel = 1000, hcache = [ ];
    for(var i=0; i<pageheadings.length; i++) {
      var h = pageheadings[i];
      if(! is_toc_heading(h)) {continue;}
      toc_headings.push(h);
    }
    if(! toc_headings.length) return;

    var tocdiv = dqs('.PmTOCdiv');
    var shouldmaketoc = ( tocdiv || (toc_headings.length >= dtoc.MinNumber && dtoc.MinNumber != -1)) ? 1:0;
    if(!dtoc.NumberedHeadings && !shouldmaketoc) return;

    for(var i=0; i<toc_headings.length; i++) {
      var h = toc_headings[i];
      var level = pf(h.tagName.substring(1));
      if(! level) level = 6;
      minlevel = Math.min(minlevel, level);
      var id = any_id(h);
      hcache.push([h, level, id]);
    }

    prevlevel = 0;
    var html = '';
    for(var i=0; i<hcache.length; i++) {
      var hc = hcache[i];
      var actual_level = hc[1] - minlevel;
//       if(actual_level>prevlevel+1) actual_level = prevlevel+1;
//       prevlevel = actual_level;

      var currnb = numberheadings(actual_level);
      if(! hc[2]) {
        hc[2] = 'toc-'+currnb.replace(/\.+$/g, '');
        hc[0].id = hc[2];
      }
      if(dtoc.NumberedHeadings && currnb.length) hc[0].insertAdjacentHTML('afterbegin', currnb+' ');

      if(! shouldmaketoc) { continue; }
      var txt = hc[0].textContent.replace(/^\s+|\s+$/g, '').replace(/</g, '&lt;');
      var sectionedit = hc[0].querySelector('.sectionedit');
      if(sectionedit) {
        var selength = sectionedit.textContent.length;
        txt = txt.slice(0, -selength);
      }
      
      html += repeat('&nbsp;', 3*actual_level)
        + '<a href="#'+hc[2]+'">' + txt + '</a><br>\n';
      if(dtoc.EnableBacklinks) hc[0].insertAdjacentHTML('beforeend', ' <a class="back-arrow" href="#_toc">&uarr;</a>');
      
    }

    if(! shouldmaketoc) return;

    html = "<b>"+dtoc.contents+"</b> "
      +"[<input type='checkbox' id='PmTOCchk'><label for='PmTOCchk'>"
      +"<span class='pmtoc-show'>"+dtoc.show+"</span>"
      +"<span class='pmtoc-hide'>"+dtoc.hide+"</span></label>]"
      +"<div class='PmTOCtable'>" + html + "</div>";

    if(!tocdiv) {
      var wrap = "<div class='PmTOCdiv'></div>";
      if(dtoc.ParentElement && dqs(dtoc.ParentElement)) {
        dqs(dtoc.ParentElement).insertAdjacentHTML('afterbegin', wrap);
      }
      else {
        hcache[0][0].insertAdjacentHTML('beforebegin', wrap);
      }
      tocdiv = dqs('.PmTOCdiv');

    }
    if(!tocdiv) return; // error?
    tocdiv.className += " frame";
    tocdiv.id = '_toc';

    tocdiv.innerHTML = html;

    if(window.localStorage.getItem('closeTOC')) { dqs('#PmTOCchk').checked = true; }
    aE('#PmTOCchk', 'change', function(e){
      window.localStorage.setItem('closeTOC', this.checked ? "close" : '');
    });

    var hh = location.hash;
    if(hh.length>1) {
      var cc = document.getElementById(hh.substring(1));
      if(cc) cc.scrollIntoView();
    }
  }

  var numhead = [0, 0, 0, 0, 0, 0, 0];
  var numheadspec = '1 1 1 1 1 1 1'.split(/ /g);
  function numhead_alpha(n, upper) {
    if(!n) return '_';
    var alpha = '', mod, start = upper=='A' ? 65 : 97;
    while (n>0) {
      mod = (n-1)%26;
      alpha = String.fromCharCode(start + mod) + '' + alpha;
      n = (n-mod)/26 | 0;
    }
    return alpha;
  }
  function numhead_roman(n, upper) {
    if(!n) return '_';
    // partially based on http://blog.stevenlevithan.com/?p=65#comment-16107
    var lst = [ [1000,'M'], [900,'CM'], [500,'D'], [400,'CD'], [100,'C'], [90,'XC'],
      [50,'L'], [40,'XL'], [10,'X'], [9,'IX'], [5,'V'], [4,'IV'], [1,'I'] ];
    var roman = '';
    for(var i=0; i<lst.length; i++) {
      while(n>=lst[i][0]) {
        roman += lst[i][1];
        n -= lst[i][0];
      }
    }
    return (upper == 'I') ? roman : roman.toLowerCase();
  }

  function numberheadings(n) {
    if(n<numhead[6]) for(var j=numhead[6]; j>n; j--) numhead[j]=0;
    numhead[6]=n;
    numhead[n]++;
    var qq = '';
    for (var j=0; j<=n; j++) {
      var curr = numhead[j];
      var currspec = numheadspec[j];
      if(currspec.match(/a/i)) { curr = numhead_alpha(curr, currspec); }
      else if(currspec.match(/i/i)) { curr = numhead_roman(curr, currspec); }

      qq+=curr+".";
    }
    return qq;
  }

  function makesortable() {
    if(! pf(adata(__script__, 'sortable'))) return;
    var tables = dqsa('table.sortable,table.sortable-footer');
    for(var i=0; i<tables.length; i++) {
      // non-pmwiki-core table, already ready
      if(tables[i].querySelector('thead')) continue;

      tables[i].classList.add('sortable'); // for .sortable-footer

      var thead = document.createElement('thead');
      tables[i].insertBefore(thead, tables[i].firstChild);

      var rows = tables[i].querySelectorAll('tr');
      thead.appendChild(rows[0]);
      var tbody = tables[i].querySelector('tbody');
      if(! tbody) {
        tbody = tables[i].appendChild(document.createElement('tbody'));
        for(var r=1; r<rows.length; r++) tbody.appendChild(rows[r]);
      }
      if(tables[i].className.match(/sortable-footer/)) {
        var tfoot = tables[i].appendChild(document.createElement('tfoot'));
        tfoot.appendChild(rows[rows.length-1]);
      }
      mkdatasort(rows);
    }
    libsortable();
  }
  function mkdatasort(rows) {
    var hcells = rows[0].querySelectorAll('th,td');
    var specialsort = [], span;
    for(var i=0; i<hcells.length; i++) {
      sortspan = hcells[i].querySelector('.sort-number,.sort-number-us,.sort-date');
      if(sortspan) specialsort[i] = sortspan.className;
    }
    if(! specialsort.length) return;
    for(var i=1; i<rows.length; i++) {
      var cells = rows[i].querySelectorAll('td,th');
      var k = 0;
      for(var j=0; j<cells.length && j<specialsort.length; j++) {
        if(! specialsort[j]) continue;
        var t = cells[j].innerText, ds = '';
        if(specialsort[j] == 'sort-number-us') {ds = t.replace(/[^-.\d]+/g, ''); }
        else if(specialsort[j] == 'sort-number') {ds = t.replace(/[^-,\d]+/g, '').replace(/,/g, '.'); }
        else if(specialsort[j] == 'sort-date') {ds = new Date(t).getTime(); }
        if(ds) cells[j].setAttribute('data-sort', ds);
      }
    }
  }
  function libsortable(){
    //adapted from Public Domain code by github.com/tofsjonas
    document.addEventListener('click', function(e) {
      var down_class = ' dir-d ';
      var up_class = ' dir-u ';
      var regex_dir = / dir-(u|d) /;
      var regex_table = /\bsortable\b/;
      var element = e.target;

      function getValue(obj) {
        obj = obj.cells[column_index];
        return obj.getAttribute('data-sort') || obj.innerText;
      }

      function reclassify(element, dir) {
        element.className = element.className.replace(regex_dir, '') + dir;
      }
      if (element.nodeName == 'TH') {
        var table = element.offsetParent;
        if (regex_table.test(table.className)) {
          var column_index;
          var tr = element.parentNode;
          var nodes = tr.cells;
          for (var i = 0; i < nodes.length; i++) {
            if (nodes[i] === element) {
              column_index = i;
            } else {
              reclassify(nodes[i], '');
            }
          }
          var dir = down_class;
          if (element.className.indexOf(down_class) !== -1) {
            dir = up_class;
          }
          reclassify(element, dir);
          var org_tbody = table.tBodies[0];
          var rows = [].slice.call(org_tbody.cloneNode(true).rows, 0);
          var reverse = (dir == up_class);
          rows.sort(function(a, b) {
            a = getValue(a);
            b = getValue(b);
            if (reverse) {
              var c = a;
              a = b;
              b = c;
            }
            return isNaN(a - b) ? a.localeCompare(b) : a - b;
          });
          var clone_tbody = org_tbody.cloneNode();
          for (i = 0; i < rows.length; i++) {
            clone_tbody.appendChild(rows[i]);
          }
          table.replaceChild(clone_tbody, org_tbody);
        }
      }
    });
  }

  function highlight_pre() {
    if (typeof hljs == 'undefined') return;
    
    var x = dqsa('.highlight,.hlt');
    
    for(var i=0; i<x.length; i++) {
      if(x[i].className.match(/(^| )(pm|pmwiki)( |$)/)) { continue;} // core highlighter
      var pre = Array.prototype.slice.call(x[i].querySelectorAll('pre,code'));
      var n = x[i].nextElementSibling;
      if (n && n.tagName == 'PRE') pre.push(n);
      for(var j=0; j<pre.length; j++) {
        pre[j].className += ' ' + x[i].className;
        hljs.highlightElement(pre[j]);
      }
    }

  }
  

  function ready(){
    PmXMail();
    inittoggle();
    autotoc();
    makesortable();
    highlight_pre();
  }
  if( document.readyState !== 'loading' ) ready();
  else window.addEventListener('DOMContentLoaded', ready);
})();
