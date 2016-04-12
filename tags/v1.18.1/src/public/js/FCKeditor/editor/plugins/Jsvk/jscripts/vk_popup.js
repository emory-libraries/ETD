/**
 *  $Id: vk_popup.js 625 2009-04-22 09:15:09Z wingedfox $
 *
 *  Keyboard Iframe mode loader
 *
 *  This software is protected by patent No.2009611147 issued on 20.02.2009 by Russian Federal Service for Intellectual Property Patents and Trademarks.
 *
 *  @author Ilya Lebedev
 *  @copyright 2006-2009 Ilya Lebedev <ilya@lebedev.net>
 *  @version $Rev: 625 $
 *  @lastchange $Author: wingedfox $ $Date: 2009-04-22 13:15:09 +0400 (Срд, 22 Апр 2009) $
 *  @class PopupVirtualKeyboard
 *  @constructor
 */
PopupVirtualKeyboard=new function(){var i=this;var I=null;var l;var o=(function(Q){var _=document.getElementsByTagName('script'),c=new RegExp('^(.*/|)('+Q+')([#?]|$)');for(var C=0,e=_.length;C<e;C++){var v=String(_[C].src).match(c);if(v){if(v[1].match(/^((https?|file)\:\/{2,}|\w:[\\])/))return v[1];if(v[1].indexOf("/")==0)return v[1];b=document.getElementsByTagName('base');if(b[0]&&b[0].href)return b[0].href+v[1];return(document.location.href.match(/(.*[\/\\])/)[0]+v[1]).replace(/^\/+(?=\w:)/,"");}}return null})('vk_popup.js');i.isOpen=function(){return null!=I&&!I.closed};var O=null;i.attachInput=function(Q){if(I&&!I.closed&&I.VirtualKeyboard){return I.VirtualKeyboard.attachInput(Q);}return false};i.open=i.show=function(Q,_){if(!I||I.closed){I=(window.showModelessDialog||window.open)(o+"vk_popup.html",window.showModelessDialog?window:"_blank","status=0,title=0,dependent=yes,dialog=yes,resizable=no,scrollbars=no,width=500,height=500");l=_;O=Q;return true}return false};i.close=i.hide=function(Q){if(!I||I.closed)return false;if(I.VirtualKeyboard.isOpen())I.VirtualKeyboard.hide();I.close();I=null;if('function'==typeof l){l();}};i.toggle=function(Q){i.isOpen()?i.close():i.open(Q);};i.onload=function(){if('string'==typeof O)O=document.getElementById(O);I.VirtualKeyboard.show(O,I.document.body,I.document.body.parentNode);I.document.body.className=I.document.body.parentNode.className='VirtualKeyboardPopup';if(I.sizeToContent){I.sizeToContent();}else{var Q=I.document.body.firstChild;while("virtualKeyboard"!=Q.id){I.document.body.removeChild(Q);Q=I.document.body.firstChild}I.dialogHeight=Q.offsetHeight+'px';I.dialogWidth=Q.offsetWidth+'px';I.resizeTo(Q.offsetWidth+I.DOM.getOffsetWidth()-I.DOM.getClientWidth(),Q.offsetHeight+I.DOM.getOffsetHeight()-I.DOM.getClientHeight());}I.onunload=i.close};if(window.attachEvent)window.attachEvent('onunload',i.close);else if(window.addEventListener)window.addEventListener('unload',i.close,false);};
