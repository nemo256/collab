<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004-2021 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script adds upload capabilities to PmWiki.  Uploads can be
    enabled by setting
        $EnableUpload = 1;
    in config.php.  In addition, an upload password must be set, as
    the default is to lock uploads.  In some configurations it may also
    be necessary to set values for $UploadDir and $UploadUrlFmt,
    especially if any form of URL rewriting is being performed.
    See the PmWiki.UploadsAdmin page for more information.
    
    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

## $EnableUploadOverwrite determines if we allow previously uploaded
## files to be overwritten.
SDV($EnableUploadOverwrite,1);

## $UploadExts contains the list of file extensions we're willing to
## accept, along with the Content-Type: value appropriate for each.
SDVA($UploadExts,array(
  'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
  'png' => 'image/png', 'apng' => 'image/apng', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
  'wbmp'=> 'image/vnd.wap.wbmp', 'xcf' => 'image/x-xcf', 'webp' => 'image/webp',
  'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml',
  'mp3' => 'audio/mpeg', 'au' => 'audio/basic', 'wav' => 'audio/x-wav',
  'ogg' => 'audio/ogg', 'flac' => 'audio/x-flac', 'opus' => 'audio/opus', 
  'ogv' => 'video/ogg', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
  'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg', 'mkv' => 'video/x-matroska',
  'm4v' => 'video/x-m4v', '3gp' => 'video/3gpp',
  'mov' => 'video/quicktime', 'qt' => 'video/quicktime',
  'wmf' => 'text/plain', 'avi' => 'video/x-msvideo',
  'zip' => 'application/zip', '7z' => 'application/x-7z-compressed',
  'gz'  => 'application/x-gzip', 'tgz' => 'application/x-gzip',
  'rpm' => 'application/x-rpm', 
  'hqx' => 'application/mac-binhex40', 'sit' => 'application/x-stuffit',
  'doc' => 'application/msword', 'ppt' => 'application/vnd.ms-powerpoint',
  'xls' => 'application/vnd.ms-excel', 'mdb' => 'text/plain',
  'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'exe' => 'application/octet-stream',
  'pdf' => 'application/pdf', 'psd' => 'text/plain', 
  'ps'  => 'application/postscript', 'ai' => 'application/postscript',
  'eps' => 'application/postscript',
  'htm' => 'text/html', 'html' => 'text/html', 'css' => 'text/css', 
  'fla' => 'application/x-shockwave-flash', 
  'swf' => 'application/x-shockwave-flash',
  'txt' => 'text/plain', 'rtf' => 'application/rtf', 
  'tex' => 'application/x-tex', 'dvi' => 'application/x-dvi',
  'odt' => 'application/vnd.oasis.opendocument.text',
  'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
  'odp' => 'application/vnd.oasis.opendocument.presentation',
  'odg' => 'application/vnd.oasis.opendocument.graphics',
  'epub'=> 'application/epub+zip',
  'kml' => 'application/vnd.google-earth.kml+xml',
  'kmz' => 'application/vnd.google-earth.kmz',
  'vtt' => 'text/vtt',
  '' => 'text/plain'));

# Array containing forbidden strings in a filename, array('.php', '.cgi')
SDV($UploadBlacklist, array());

SDV($UploadMaxSize,50000);
SDV($UploadPrefixQuota,0);
SDV($UploadDirQuota,0);
foreach($UploadExts as $k=>$v) 
  if (!isset($UploadExtSize[$k])) $UploadExtSize[$k]=$UploadMaxSize;

SDV($UploadDir,'uploads');
SDV($UploadPermAdd,0444);
SDV($UploadPermSet,0);
SDV($UploadPrefixFmt,'/$Group');
SDV($UploadFileFmt,"$UploadDir$UploadPrefixFmt");
$v = preg_replace('#^/(.*/)#', '', $UploadDir);
SDV($UploadUrlFmt,preg_replace('#/[^/]*$#', "/$v", $PubDirUrl, 1));
SDV($LinkUploadCreateFmt, "<a rel='nofollow' class='createlinktext' href='\$LinkUpload'>\$LinkText</a><a rel='nofollow' class='createlink' href='\$LinkUpload'>&nbsp;&Delta;</a>");
SDVA($ActionTitleFmt, array('upload' => '| $[Attach]'));


if ($EnablePostAuthorRequired)
  SDV($EnableUploadAuthorRequired, $EnablePostAuthorRequired);

SDV($PageUploadFmt,array("
  <div id='wikiupload'>
  <h2 class='wikiaction'>$[Attachments for] {\$FullName}</h2>
  <h3>\$UploadResult</h3>
  <form enctype='multipart/form-data' action='{\$PageUrl}?action=postupload' method='post'>
  <input type='hidden' name='n' value='{\$FullName}' />
  <input type='hidden' name='action' value='postupload' />
  <input type='hidden' name='\$TokenName' value='\$TokenValue' />
  <table border='0'>
    <tr><td align='right'>$[File to upload:]</td><td><input
      name='uploadfile' type='file' required='required' /></td></tr>
    <tr><td align='right'>$[Name attachment as:]</td>
      <td><input type='text' name='upname' value='\$UploadName' />
        </td></tr>
    <tr><td align='right'>$[Uploader]:</td>
      <td><input type='text' name='author' value='\$UploadAuthor' \$UploadAuthorRequired />
        <input type='submit' value=' $[Upload] ' />
        </td></tr></table></form></div>",
  'wiki:$[{$SiteGroup}/UploadQuickReference]'));
XLSDV('en',array(
  'ULsuccess' => 'successfully uploaded',
  'ULinvalidtoken' => 'Token invalid or missing.',
  'ULauthorrequired' => 'An author name is required.',
  'ULbadname' => 'invalid attachment name',
  'ULbadtype' => '\'$upext\' is not an allowed file extension',
  'ULtoobig' => 'file is larger than maximum allowed by webserver',
  'ULtoobigext' => 'file is larger than allowed maximum of $upmax
     bytes for \'$upext\' files',
  'ULpartial' => 'incomplete file received',
  'ULnofile' => 'no file uploaded',
  'ULexists' => 'file with that name already exists',
  'ULpquota' => 'group quota exceeded',
  'ULtquota' => 'upload quota exceeded'));
SDV($PageAttributes['passwdupload'],'$[Set new upload password:]');
SDV($DefaultPasswords['upload'],'@lock');
SDV($AuthCascade['upload'], 'read');
SDV($FmtPV['$PasswdUpload'], 'PasswdVar($pn, "upload")');

Markup('attachlist', 'directives',
  '/\\(:attachlist\\s*(.*?):\\)/i',
  "MarkupFmtUploadList");
function MarkupFmtUploadList($m) {
  extract($GLOBALS["MarkupToHTML"]); # get $pagename
  return Keep('<ul>'.FmtUploadList($pagename,$m[1]).'</ul>');
}
SDV($GUIButtons['attach'], array(220, 'Attach:', '', '$[file.ext]',
  '$GUIButtonDirUrlFmt/attach.gif"$[Attach file]"'));
SDV($LinkFunctions['Attach:'], 'LinkUpload');
SDV($IMap['Attach:'], '$1');
SDVA($HandleActions, array('upload' => 'HandleUpload',
  'postupload' => 'HandlePostUpload',
  'download' => 'HandleDownload'));
SDVA($HandleAuth, array('upload' => 'upload',
  'download' => 'read'));
SDV($HandleAuth['postupload'], $HandleAuth['upload']);
SDV($UploadVerifyFunction, 'UploadVerifyBasic');

function MakeUploadName($pagename,$x) {
  global $UploadNameChars, $MakeUploadNamePatterns;
  SDV($UploadNameChars, "-\\w. ");
  SDV($MakeUploadNamePatterns, array(
    "/[^$UploadNameChars]/" => '',
    '/(\\.[^.]*)$/' => 'cb_tolower',
    '/^[^[:alnum:]_]+/' => '',
    '/[^[:alnum:]_]+$/' => ''));
   return PPRA($MakeUploadNamePatterns, $x);
}

function LinkUpload($pagename, $imap, $path, $alt, $txt, $fmt=NULL) {
  global $FmtV, $UploadFileFmt, $LinkUploadCreateFmt,
    $UploadUrlFmt, $UploadPrefixFmt, $EnableDirectDownload;
  if (preg_match('!^(.*)/([^/]+)$!', $path, $match)) {
    $pagename = MakePageName($pagename, $match[1]);
    $path = $match[2];
  }
  $upname = MakeUploadName($pagename, $path);
  $encname = rawurlencode($upname);
  $filepath = FmtPageName("$UploadFileFmt/$upname", $pagename);
  $FmtV['$LinkUpload'] =
    FmtPageName("\$PageUrl?action=upload&amp;upname=$encname", $pagename);
  $FmtV['$LinkText'] = $txt;
  if (!file_exists($filepath)) 
    return FmtPageName($LinkUploadCreateFmt, $pagename);
  $path = PUE(FmtPageName(IsEnabled($EnableDirectDownload, 1) 
                            ? "$UploadUrlFmt$UploadPrefixFmt/$encname"
                            : "{\$PageUrl}?action=download&amp;upname=$encname",
                          $pagename));
  return LinkIMap($pagename, $imap, $path, $alt, $txt, $fmt);
}

# Authenticate group downloads with the group password
function UploadAuth($pagename, $auth, $cache=0){
  global $GroupAttributesFmt, $EnableUploadGroupAuth;
  if (IsEnabled($EnableUploadGroupAuth,0)){
    SDV($GroupAttributesFmt,'$Group/GroupAttributes');
    $pn_upload = FmtPageName($GroupAttributesFmt, $pagename);
  } else $pn_upload = $pagename;
  $page = RetrieveAuthPage($pn_upload, $auth, true, READPAGE_CURRENT);
  if (!$page) Abort("?No '$auth' permissions for $pagename");
  if ($cache) PCache($pn_upload,$page);
  return true;
}

function UploadSetVars($pagename) {
  global $Author, $FmtV, $UploadExtMax, $EnableReadOnly,
    $EnablePostAuthorRequired, $EnableUploadAuthorRequired;
  $FmtV['$UploadName'] = MakeUploadName($pagename,@$_REQUEST['upname']);
  $FmtV['$UploadAuthor'] = PHSC($Author,  ENT_QUOTES);
  $upresult = PHSC(@$_REQUEST['upresult']);
  $uprname = PHSC(@$_REQUEST['uprname']);
  $FmtV['$upext'] = PHSC(@$_REQUEST['upext']);
  $FmtV['$upmax'] = PHSC(@$_REQUEST['upmax']);
  $FmtV['$TokenValue'] = pmtoken();
  $FmtV['$UploadResult'] = ($upresult) ?
    FmtPageName("<i>$uprname</i>: $[UL$upresult]",$pagename) : 
      (@$EnableReadOnly ? XL('Cannot modify site -- $EnableReadOnly is set'): '');
  $FmtV['$UploadAuthorRequired'] = @$EnableUploadAuthorRequired ?
    'required="required"' : '';
}

function HandleUpload($pagename, $auth = 'upload') {
  global $HandleUploadFmt,$PageStartFmt,$PageEndFmt,$PageUploadFmt;
  UploadAuth($pagename, $auth, 1);
  UploadSetVars($pagename);
  SDV($HandleUploadFmt,array(&$PageStartFmt,&$PageUploadFmt,&$PageEndFmt));
  PrintFmt($pagename,$HandleUploadFmt);
}

function HandleDownload($pagename, $auth = 'read') {
  global $UploadFileFmt, $UploadExts, $DownloadDisposition, $EnableIMSCaching;
  SDV($DownloadDisposition, "inline");
  UploadAuth($pagename, $auth);
  $upname = MakeUploadName($pagename, @$_REQUEST['upname']);
  $filepath = FmtPageName("$UploadFileFmt/$upname", $pagename);
  if (!$upname || !file_exists($filepath)) {
    header("HTTP/1.0 404 Not Found");
    Abort("?requested file not found");
    exit();
  }
  if (IsEnabled($EnableIMSCaching, 0)) {
    header('Cache-Control: private');
    header('Expires: ');
    $filelastmod = gmdate('D, d M Y H:i:s \G\M\T', filemtime($filepath));
    if (@$_SERVER['HTTP_IF_MODIFIED_SINCE'] == $filelastmod)
      { header("HTTP/1.0 304 Not Modified"); exit(); }
    header("Last-Modified: $filelastmod");
  }
  preg_match('/\\.([^.]+)$/',$filepath,$match); 
  if ($UploadExts[@$match[1]]) 
    header("Content-Type: {$UploadExts[@$match[1]]}");
  $fsize = $length = filesize($filepath);
  $end = $fsize-1;
  header("Accept-Ranges: bytes");
  if (@$_SERVER['HTTP_RANGE']) {
    if(! preg_match('/^\\s*bytes\\s*=\\s*(\\d*)\\s*-\\s*(\\d*)\\s*$/i', $_SERVER['HTTP_RANGE'], $r)
      || intval($r[1])>$end
      || intval($r[2])>$end
      || ($r[2] && intval($r[1])>intval($r[2]))
    ) {
      header('HTTP/1.1 416 Requested Range Not Satisfiable');
      header("Content-Range: bytes 0-$end/$fsize");
      exit;
    }
    if ($r[2]=='') $r[2] = $end;
    if ($r[1]=='') $r[1] = $end - $r[2];
    $length = $r[2] - $r[1] + 1;
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $r[1]-$r[2]/$fsize");
  }
  else {
    $r = array( null, 0, $end);
  }
  header("Content-Length: $length");
  header("Content-Disposition: $DownloadDisposition; filename=\"$upname\"");
  $fp = fopen($filepath, "rb");
  if ($fp) {
    $bf = 8192;
    fseek($fp, $r[1]);
    while (!feof($fp) && ($pos = ftell($fp)) <= $r[2]) {
      $bf = max($bf, $r[2] - $pos + 1);
      echo fread($fp, $bf);
      flush();
    }
    fclose($fp);
  }
  exit();
}

function HandlePostUpload($pagename, $auth = 'upload') {
  global $UploadVerifyFunction, $UploadFileFmt, $LastModFile, 
    $EnableUploadVersions, $Now, $RecentUploadsFmt, $FmtV,
    $NotifyItemUploadFmt, $NotifyItemFmt, $IsUploadPosted,
    $UploadRedirectFunction, $UploadPermAdd, $UploadPermSet,
    $EnableReadOnly;
    
  if (IsEnabled($EnableReadOnly, 0))
    Abort('Cannot modify site -- $EnableReadOnly is set', 'readonly');

  UploadAuth($pagename, $auth);
  $uploadfile = $_FILES['uploadfile'];
  $upname = @$_REQUEST['upname'];
  if ($upname=='') $upname=$uploadfile['name'];
  $upname = MakeUploadName($pagename,$upname);
  if (!function_exists($UploadVerifyFunction))
    Abort('?no UploadVerifyFunction available');
  $filepath = FmtPageName("$UploadFileFmt/$upname",$pagename);
  $result = $UploadVerifyFunction($pagename,$uploadfile,$filepath);
  if ($result=='') {
    $filedir = preg_replace('#/[^/]*$#','',$filepath);
    mkdirp($filedir);
    if (IsEnabled($EnableUploadVersions, 0))
      @rename($filepath, "$filepath,$Now");
    if (!move_uploaded_file($uploadfile['tmp_name'],$filepath))
      { Abort("?cannot move uploaded file to $filepath"); return; }
    fixperms($filepath, $UploadPermAdd, $UploadPermSet);
    if ($LastModFile) { touch($LastModFile); fixperms($LastModFile); }
    $result = "upresult=success";
    $FmtV['$upname'] = $upname;
    $FmtV['$upsize'] = $uploadfile['size'];
    if (IsEnabled($RecentUploadsFmt, 0)) {
      PostRecentChanges($pagename, '', '', $RecentUploadsFmt);
    }
    if (IsEnabled($NotifyItemUploadFmt, 0) && function_exists('NotifyUpdate')) {
      $NotifyItemFmt = $NotifyItemUploadFmt;
      $IsUploadPosted = 1;
      register_shutdown_function('NotifyUpdate', $pagename, getcwd());
    }
  }
  $FmtV['$upresult'] = $result;
  SDV($UploadRedirectFunction, 'Redirect');
  $UploadRedirectFunction($pagename,"{\$PageUrl}?action=upload&uprname=$upname&$result");
}

function UploadVerifyBasic($pagename,$uploadfile,$filepath) {
  global $EnableUploadOverwrite,$UploadExtSize,$UploadPrefixQuota,
    $UploadDirQuota,$UploadDir, $UploadBlacklist,
    $Author, $EnablePostAuthorRequired, $EnableUploadAuthorRequired;

  if(! AutoCheckToken()) {
    return 'upresult=invalidtoken';
  }
  
  if (IsEnabled($EnableUploadAuthorRequired,0) && !$Author)
    return 'upresult=authorrequired';

  if (count($UploadBlacklist)) {
    $tmp = explode("/", $filepath);
    $upname = strtolower(end($tmp));
    foreach($UploadBlacklist as $needle) {
      if (strpos($upname, $needle)!==false) return 'upresult=badname';
    }
  }
  if (!$EnableUploadOverwrite && file_exists($filepath)) 
    return 'upresult=exists';
  preg_match('/\\.([^.\\/]+)$/',$filepath,$match); $ext=@$match[1];
  $maxsize = $UploadExtSize[$ext];
  if ($maxsize<=0) return "upresult=badtype&upext=$ext";
  if ($uploadfile['size']>$maxsize) 
    return "upresult=toobigext&upext=$ext&upmax=$maxsize";
  switch (@$uploadfile['error']) {
    case 1: return 'upresult=toobig';
    case 2: return 'upresult=toobig';
    case 3: return 'upresult=partial';
    case 4: return 'upresult=nofile';
  }
  if (!is_uploaded_file($uploadfile['tmp_name'])) return 'upresult=nofile';
  $filedir = preg_replace('#/[^/]*$#','',$filepath);
  if ($UploadPrefixQuota && 
      (dirsize($filedir)-@filesize($filepath)+$uploadfile['size']) >
        $UploadPrefixQuota) return 'upresult=pquota';
  if ($UploadDirQuota && 
      (dirsize($UploadDir)-@filesize($filepath)+$uploadfile['size']) >
        $UploadDirQuota) return 'upresult=tquota';
  return '';
}

function dirsize($dir) {
  $size = 0;
  $dirp = @opendir($dir);
  if (!$dirp) return 0;
  while (($file=readdir($dirp)) !== false) {
    if ($file[0]=='.') continue;
    if (is_dir("$dir/$file")) $size+=dirsize("$dir/$file");
    else $size+=filesize("$dir/$file");
  }
  closedir($dirp);
  return $size;
}

function FmtUploadList($pagename, $args) {
  global $UploadDir, $UploadPrefixFmt, $UploadUrlFmt, $EnableUploadOverwrite,
    $TimeFmt, $EnableDirectDownload, $IMapLinkFmt, $UrlLinkFmt, $FmtV;

  $opt = ParseArgs($args);
  if (@$opt[''][0]) $pagename = MakePageName($pagename, $opt[''][0]);

  $matchfnames = '';
  if (@$opt['names'] ) $matchfnames = $opt['names'];
  if (@$opt['ext'])
    $matchfnames .= FixGlob($opt['ext'], '$1*.$2');

  $uploaddir = FmtPageName("$UploadDir$UploadPrefixFmt", $pagename);
  $uploadurl = FmtPageName(IsEnabled($EnableDirectDownload, 1) 
                          ? "$UploadUrlFmt$UploadPrefixFmt/"
                          : "\$PageUrl?action=download&amp;upname=",
                      $pagename);

  $dirp = @opendir($uploaddir);
  if (!$dirp) return '';
  $filelist = array();
  while (($file=readdir($dirp)) !== false) {
    if ($file[0] == '.') continue;
    if ($matchfnames && ! MatchNames($file, $matchfnames)) continue;
    $filelist[$file] = rawurlencode($file);
  }
  closedir($dirp);
  $out = array();
  natcasesort($filelist);
  $overwrite = '';
  $fmt = IsEnabled($IMapLinkFmt['Attach:'], $UrlLinkFmt);
  foreach($filelist as $file=>$encfile) {
    $FmtV['$LinkUrl'] = PUE("$uploadurl$encfile");
    $FmtV['$LinkText'] = $file;
    $FmtV['$LinkUpload'] =
      FmtPageName("\$PageUrl?action=upload&amp;upname=$encfile", $pagename);
    $stat = stat("$uploaddir/$file");
    if ($EnableUploadOverwrite) 
      $overwrite = FmtPageName("<a rel='nofollow' class='createlink'
        href='\$LinkUpload'>&nbsp;&Delta;</a>",
        $pagename);
    $lnk = FmtPageName($fmt, $pagename);
    $out[] = "<li> $lnk$overwrite ... ".
      number_format($stat['size']) . " bytes ... " . 
      strftime($TimeFmt, $stat['mtime']) . "</li>";
  }
  return implode("\n",$out);
}

# this adds (:if [!]attachments filepattern pagename:) to the markup
$Conditions['attachments'] = "AttachExist(\$pagename, \$condparm)";
function AttachExist($pagename, $condparm='*') {
  global $UploadFileFmt;
  @list($fpat, $pn) = explode(' ', $condparm, 2);
  $pn = ($pn > '') ? MakePageName($pagename, $pn) : $pagename;
    
  $uploaddir = FmtPageName($UploadFileFmt, $pn);
  $flist = array();
  $dirp = @opendir($uploaddir);
  if ($dirp) {
    while (($file = readdir($dirp)) !== false)
      if ($file[0] != '.') $flist[] = $file;
    closedir($dirp);
    $flist = MatchNames($flist, $fpat);
  }
  return count($flist);
}
