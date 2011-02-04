/**
 *  $Id: vk_loader.js 546 2009-02-27 08:53:11Z wingedfox $
 *
 *  Keyboard loader
 *
 *  This software is protected by patent No.2009611147 issued on 20.02.2009 by Russian Federal Service for Intellectual Property Patents and Trademarks.
 *
 *  @author Ilya Lebedev
 *  @copyright 2006-2009 Ilya Lebedev <ilya@lebedev.net>
 *  @version $Rev: 546 $
 *  @lastchange $Author: wingedfox $ $Date: 2009-02-27 11:53:11 +0300 (Птн, 27 Фев 2009) $
 */
VirtualKeyboard=new function(){var i=this,I=null;i.show=i.hide=i.toggle=i.attachInput=function(){window.status='VirtualKeyboard is not loaded yet.';if(!I)setTimeout(function(){window.status=''},1000);};i.isOpen=function(){return false}};(function(){var i=function(e){if('string'!=typeof e||e.length<2)return{};e=e.split(/&amp;|&/g);for(var X=0,z=e.length,Z={},w,W;X<z;X++){w=e[X].split("=");w[0]=w[0].replace(/[{}\[\]]*$/,"");W=Z[w[0]];w[1]=unescape(w[1]?w[1].replace("+"," "):"");if(W)if('array'==typeof(W))Z[w[0]][Z[w[0]].length]=w[1];else Z[w[0]]=[Z[w[0]],w[1]];else Z[w[0]]=w[1]}return Z};var I=window.dialogArguments||window.opener||window.top,l=null,o='vk_loader.js';try{if(I!=window){var l=I.document.getElementsByTagName('head')[0];var o=window.location.href.match(/.*\/(.+)\..+$/)[1]+'.js'}}catch(e){I=window}q=(function(e,X){var z=(X||document).getElementsByTagName('html')[0].innerHTML,Z=new RegExp('<scr'+'ipt[^>]+?src[^"\']+.*?'+e+'([^#"\']*)','i'),w=z.match(Z);if(w)return i(w[1].replace(/^[^?]*\?([^#]+)/,"$1"));return{}})(o,I.document);var O=(function(e){var X=document.getElementsByTagName('script'),z=new RegExp('^(.*/|)('+e+')([#?]|$)');for(var V=0,Z=X.length;V<Z;V++){var w=String(X[V].src).match(z);if(w){if(w[1].match(/^((https?|file)\:\/{2,}|\w:[\\])/))return w[1];if(w[1].indexOf("/")==0)return w[1];b=document.getElementsByTagName('base');if(b[0]&&b[0].href)return b[0].href+w[1];return(document.location.href.match(/(.*[\/\\])/)[0]+w[1]).replace(/^\/+/,"");}}return null})('vk_loader.js');var Q=i(I.location.search.slice(1));var _=["extensions/e.js"];q.skin=Q.vk_skin||q.vk_skin||'winxp';q.layout=Q.vk_layout||q.vk_layout||null;var c=document.getElementsByTagName('head')[0],C;C=document.createElement('link');C.rel='stylesheet';C.type='text/css';C.href=O+'css/'+q.skin+'/keyboard.css';c.appendChild(C);if(l){var v=I.document.createElement('link');v.rel='stylesheet';v.type='text/css';v.href=O+'css/'+q.skin+'/keyboard.css';l.appendChild(v);v=null}for(var V=0,x=_.length;V<x;V++)_[V]=O+_[V];_[V++]=O+'virtualkeyboard.js?vk_layout='+escape(q.layout)+'&vk_skin='+escape(q.skin);_[V]=O+'layouts/layouts.js';if(window.ScriptQueue){ScriptQueue.queue(_);}else{if(!(window.ScriptQueueIncludes instanceof Array))window.ScriptQueueIncludes=[];window.ScriptQueueIncludes=window.ScriptQueueIncludes.concat(_);if(document.body){C=document.createElement('script');C.type="text/javascript";C.src=O+'extensions/scriptqueue.js';c.appendChild(C);}else{document.write("<scr"+"ipt type=\"text/javascript\" src=\""+O+'extensions/scriptqueue.js'+"\"></scr"+"ipt>");}}})();
