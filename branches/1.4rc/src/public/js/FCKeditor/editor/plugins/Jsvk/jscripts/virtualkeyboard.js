﻿;var VirtualKeyboard=new function(){var i=this;i.$VERSION$="3.6.2.603";var I= /\x03/;var l={'layout':null};var o='kb_b';var O=true;var Q={14:'backspace',15:'tab',28:'enter',29:'caps',41:'shift_left',52:'shift_right',53:'del',54:'ctrl_left',55:'alt_left',56:'space',57:'alt_right',58:'ctrl_right'};var _={'SHIFT':'shift','ALT':'alt','CTRL':'ctrl','CAPS':'caps'};var c;var C={'QWERTY Standard':"À1234567890m=ÜQWERTYUIOPÛÝASDFGHJKL;ÞZXCVBNM¼¾¿",'QWERTY Canadian':"Þ1234567890m=ÜQWERTYUIOPÛÝASDFGHJKL;ÀZXCVBNM¼¾¿",'QWERTY Dutch':"Þ1234567890Û¿ÜQWERTYUIOPÝ;ASDFGHJKL=ÀZXCVBNM¼¾m",'QWERTY Estonian':"¿1234567890m=ÜQWERTYUIOPÞÛASDFGHJKL;ÀZXCVBNM¼¾Ý",'QWERTY Greek (220)':"À1234567890¿ÛÜQWERTYUIOP=ÝASDFGHJKL;ÞZXCVBNM¼¾m",'QWERTY Greek (319)':"À1234567890¿=ÜQWERTYUIOPÛÝASDFGHJKL;ÞZXCVBNM¼¾m",'QWERTY Gujarati':"À1234567890m=XQWERTYUIOPÛÝASDFGHJKL;ÜZXCVBNM¼¾¿",'QWERTY Italian':"Ü1234567890ÛÝ¿QWERTYUIOP;=ASDFGHJKLÀÞZXCVBNM¼¾m",'QWERTY Kannada':"À1234567890m=ZQWERTYUIOPÛÝASDFGHJKL;ÞZXCVBNM¼¾¿",'QWERTY Portuguese':"À1234567890ÛÝ¿QWERTYUIOP=;ASDFGHJKLÞÜZXCVBNM¼¾m",'QWERTY Scandinavian':"Ü1234567890=Û¿QWERTYUIOPÝ;ASDFGHJKLÀÞZXCVBNM¼¾m",'QWERTY Spanish':"Ü1234567890mÛ¿QWERTYUIOPÝ;ASDFGHJKLÀÞZXCVBNM¼¾ß",'QWERTY Tamil':"À1234567890m =ZQWERTYUIOPÛÝASDFGHJKL;ÞCVBNM¼¾ ¿",'QWERTY Turkish':"À1234567890ßm¼QWERTYUIOPÛÝASDFGHJKL;ÞZXCVBNM¿Ü¾",'QWERTY UK':"ß1234567890m=ÞQWERTYUIOPÛÝASDFGHJKL;ÀZXCVBNM¼¾¿",'QWERTZ Albanian':"À1234567890m=ÜQWERTZUIOPÛÝASDFGHJKL;ÞYXCVBNM¼¾¿",'QWERTZ Bosnian':"À1234567890¿=ÜQWERTZUIOPÛÝASDFGHJKL;ÞYXCVBNM¼¾m",'QWERTZ Czech':"À1234567890=¿ÜQWERTZUIOPÛÝASDFGHJKL;ÞYXCVBNM¼¾m",'QWERTZ German':"Ü1234567890ÛÝ¿QWERTZUIOP;=ASDFGHJKLÀÞYXCVBNM¼¾m",'QWERTZ Hungarian':"0123456789À¿=ÜQWERTZUIOPÛÝASDFGHJKL;ÞYXCVBNM¼¾m",'QWERTZ Slovak':"À1234567890¿ßÜQWERTZUIOPÛÝASDFGHJKL;ÞYXCVBNM¼¾m",'QWERTZ Swiss':"Ü1234567890ÛÝßQWERTZUIOP;ÞASDFGHJKLÀ¿YXCVBNM¼¾m",'AZERTY Belgian':"Þ1234567890ÛmÜAZERTYUIOPÝ;QSDFGHJKLMÀWXCVBN¼¾¿=",'AZERTY French':"Þ1234567890Û=ÜAZERTYUIOPÝ;QSDFGHJKLMÀWXCVBN¼¾¿ß",',WERTY Bulgarian':"À1234567890m¾Ü¼WERTYUIOPÛÝASDFGHJKL;ÞZXCVBNMßQ¿",'QGJRMV Latvian':"À1234567890mFÜQGJRMVNZWXYH;USILDATECÞÛBÝKPOß¼¾¿",'/,.PYF UK-Dvorak':"m1234567890ÛÝÜÀ¼¾PYFGCRL¿=AOEUIDHTNSÞ;QJKXBMWVZ",'FG;IOD Turkish F':"À1234567890=mXFG;IODRNHPQWUÛEAÝTKMLYÞJÜVC¿ZSB¾¼",';QBYUR US-Dvorak':"7ÛÝ¿PFMLJ4321Ü;QBYURSO¾65=mKCDTHEAZ8ÞÀXGVWNI¼09",'56Q.OR US-Dvorak':"m1234JLMFP¿ÛÝÜ56Q¾ORSUYB;=78ZAEHTDCKÞ90X¼INWVGÀ"};var v=0,V=0,x=1,X=2,z=4,Z=8,w=z|Z,W=z|x,s=X|Z,S=X|z,k=X|z|Z,K=X|x,q=x|X|z,E=x|Z,r=x|X|z|Z;var R={'buttonUp':'kbButton','buttonDown':'kbButtonDown','buttonHover':'kbButtonHover','hoverShift':'hoverShift','hoverAlt':'hoverAlt','modeAlt':'modeAlt','modeAltCaps':'modeAltCaps','modeCaps':'modeCaps','modeNormal':'modeNormal','modeShift':'modeShift','modeShiftAlt':'modeShiftAlt','modeShiftAltCaps':'modeShiftAltCaps','modeShiftCaps':'modeShiftCaps','charNormal':'charNormal','charShift':'charShift','charAlt':'charAlt','charShiftAlt':'charShiftAlt','charCaps':'charCaps','charShiftCaps':'charShiftCaps','hiddenAlt':'hiddenAlt','hiddenCaps':'hiddenCaps','hiddenShift':'hiddenShift','hiddenShiftCaps':'hiddenShiftCaps','deadkey':'deadKey','noanim':'VK_no_animate'};var t=null;var T=[];T.hash={};T.codes={};T.codeFilter=null;T.options=null;var y={keyboard:null,desk:null,langbox:null,attachedInput:null};var Y=null;i.addLayoutList=function(){for(var b=0,B=arguments.length;b<B;b++){try{i.addLayout(arguments[b]);}catch(e){}}};i.addLayout=function(e){var b=e.code.entityDecode().split("-"),B=e.name.entityDecode(),n=f(e.normal);if(!isArray(n)||47!=n.length)throw new Error('VirtualKeyboard requires \'keys\' property to be an array with 47 items, '+n.length+' detected. Layout code: '+b+', layout name: '+B);e.code=(b[1]||b[0]);e.name=B;e.normal=n;e.domain=b[0];if(T.hash.hasOwnProperty(e.code+" "+e.name))return;if(!T.codes.hasOwnProperty(e.code))T.codes[e.code]=e.code;e.toString=function(){return this.code+" "+this.name};T.push(e);T.options=null};i.switchLayout=function(e){D();if(!T.options.hasOwnProperty(e))return false;i.IME.hide();y.langbox.options[T.options[e]].selected=true;t=T[T.hash[e]];if(!isArray(t))t=T[T.hash[e]]=F(t);if(!isArray(t)){t=T[T.hash[e]]=F(t);}if(!t.html){t.html=J(t);}y.desk.innerHTML=t.html;y.keyboard.className=t.domain;i.IME.css=t.domain;v=V;g();if(isFunction(t.activate)){t.activate();}d();DocumentCookie.set('vk_layout',e);return true};i.getLayouts=function(){var e=[];for(var b=0,B=T.length;b<B;b++){e[e.length]=[T[b].code,T[b].name]}return e.sort();};i.setVisibleLayoutCodes=function(){var e=isArray(arguments[0])?arguments[0]:arguments,b=null,B;for(var n=0,N=e.length;n<N;n++){B=e[n].toUpperCase();if(!T.codes.hasOwnProperty(B))continue;if(!b)b={};b[B]=B}T.codeFilter=b;T.options=null;if(!i.switchLayout(y.langbox.value)){i.switchLayout(y.langbox.value);}};i.getLayoutCodes=function(){var e=[];for(var b in T.codes){if(!T.codes.hasOwnProperty(b))continue;e.push(b);}return e.heapSort();};var u=function(b,B){var n="",N=false;b=b.replace(o,"");switch(b){case _.CAPS:case _.SHIFT:case"shift_left":case"shift_right":case _.ALT:case"alt_left":case"alt_right":return true;case'backspace':if(isFunction(t.charProcessor)&&DocumentSelection.getSelection(y.attachedInput).length){n="\x08"}else if(B){i.IME.hide(true);return true}else{DocumentSelection.deleteAtCursor(y.attachedInput,false);i.IME.hide(true);}break;case'del':i.IME.hide(true);if(B)return true;DocumentSelection.deleteAtCursor(y.attachedInput,true);break;case'space':n=" ";break;case'tab':n="\t";break;case'enter':n="\n";break;default:n=t[b][v];break}if(n){if(!(n=j(n,DocumentSelection.getSelection(y.attachedInput))))return N;try{if(n[1]||n[0].length>1||n.charCodeAt(0)>0x7fff||y.attachedInput.contentDocument||'\t'==n[0]){throw new Error}var m=n[0].charCodeAt(0);if(isFunction(document.createEvent)){var B=null;try{B=document.createEvent("KeyEvents");B.initKeyEvent('keypress',false,true,y.attachedInput.contentWindow,false,false,false,false,0,m);}catch(ex){B=document.createEvent("KeyboardEvents");B.initKeyEvent('keypress',false,true,y.attachedInput.contentWindow,false,false,false,false,m,0);}B.VK_bypass=true;y.attachedInput.dispatchEvent(B);}else{B.keyCode=10==m?13:m;N=true}}catch(e){DocumentSelection.insertAtCursor(y.attachedInput,n[0]);if(n[1]){DocumentSelection.setRange(y.attachedInput,-n[1],0,true);}}}return N};var U=function(e){if(!i.isOpen())return;var b=v;var B=e.getKeyCode();switch(e.type){case'keydown':switch(B){case 37:if(i.IME.isOpen()){i.IME.prevPage(e);return}break;case 39:if(i.IME.isOpen()){i.IME.nextPage(e);return}break;case 8:case 9:case 46:var n=y.desk.childNodes[c[B]];if(O&&!e.getRepeat())DOM.CSS(n).addClass(R.buttonDown);if(!u(n.id,e))e.preventDefault();break;case 20:if(!e.getRepeat()){b=b^Z}break;case 27:if(i.IME.isOpen()){i.IME.hide();}else{var N=DocumentSelection.getStart(y.attachedInput);DocumentSelection.setRange(y.attachedInput,N,N);}return false;default:if(!e.getRepeat()){b=b|e.shiftKey|e.ctrlKey<<2|e.altKey<<1}if(c.hasOwnProperty(B)){if(!(e.altKey^e.ctrlKey)){var n=y.desk.childNodes[c[B]];if(O)DOM.CSS(n).addClass(R.buttonDown);Y=n.id}if(e.altKey&&e.ctrlKey){e.preventDefault();if(e.srcElement){u(y.desk.childNodes[c[B]].id,e);Y=""}}}else{i.IME.hide();}break}break;case'keyup':switch(B){case 20:break;default:if(!e.getRepeat()){b=v&(r^(!e.shiftKey|(!e.ctrlKey<<2)|(!e.altKey<<1)));}if(O&&c.hasOwnProperty(B)){DOM.CSS(y.desk.childNodes[c[B]]).removeClass(R.buttonDown);}}break;case'keypress':if(Y&&!e.VK_bypass){if(!u(Y,e)){e.stopPropagation();e.preventDefault();}Y=null}if(!v^S&&(e.altKey||e.ctrlKey)){i.IME.hide();}if(0==B&&!Y&&!e.VK_bypass&&(!e.ctrlKey&&!e.altKey&&!e.shiftKey)){e.preventDefault();}}if(b!=v){G(b);g();}};var p=function(e){var b=DOM.getParent(e.srcElement||e.target,'a');if(!b||b.parentNode.id.indexOf(o)<0)return;b=b.parentNode;switch(b.id.substring(o.length)){case"caps":case"shift_left":case"shift_right":case"alt_left":case"alt_right":case"ctrl_left":case"ctrl_right":return}if(DOM.CSS(b).hasClass(R.buttonDown)||!O){u(b.id);}if(O){DOM.CSS(b).removeClass(R.buttonDown)}var B=v&(Z|e.shiftKey|e.altKey<<1|e.ctrlKey<<2);if(v!=B){G(B);g();}e.preventDefault();e.stopPropagation();};var P=function(e){var b=DOM.getParent(e.srcElement||e.target,'a');if(!b||b.parentNode.id.indexOf(o)<0)return;b=b.parentNode;var B=v;var n=b.id.substring(o.length);switch(n){case"caps":B=B^Z;break;case"shift_left":case"shift_right":if(e.shiftKey)break;B=B^x;break;case"alt_left":case"alt_right":case"ctrl_left":case"ctrl_right":B=B^(e.altKey<<1^X)^(e.ctrlKey<<2^z);break;default:if(O)DOM.CSS(b).addClass(R.buttonDown);break}if(v!=B){G(B);g();}e.preventDefault();e.stopPropagation();};var a=function(e){var b=DOM.getParent(e.srcElement||e.target,'a'),B={'mouseover':2,'mouseout':3};if(!b||b.parentNode.id.indexOf(o)<0)return;b=b.parentNode;if(b.id.indexOf('shift')>-1){h(B[e.type],_.SHIFT);}else if(b.id.indexOf('alt')>-1||b.id.indexOf('ctrl')>-1){h(B[e.type],_.CTRL);h(B[e.type],_.ALT);}else if(b.id.indexOf('caps')>-1){H(B[e.type],null,b.id);}else if(O){H(B[e.type],null,b.id);if('mouseout'==e.type.toLowerCase()){H(0,null,b.id);}}e.preventDefault();e.stopPropagation();};var A=function(e){DocumentCookie.set('vk_mapping',e.target.value);c=C[e.target.value]};i.attachInput=function(e){if(!e)return y.attachedInput;if(isString(e))e=document.getElementById(e);if(e==y.attachedInput)return y.attachedInput;if(!i.switchLayout(l.layout)&&!i.switchLayout(y.langbox.value)){throw new Error('No layouts available');}i.detachInput();if(!e||!e.tagName){y.attachedInput=null}else{O=!DOM.CSS(e).hasClass(R.noanim);y.attachedInput=e;d();if(e.contentWindow){e=e.contentWindow.document.body.parentNode}e.focus();EM.addEventListener(e,'keydown',U);EM.addEventListener(e,'keyup',U);EM.addEventListener(e,'keypress',U);EM.addEventListener(e,'mousedown',i.IME.blurHandler);}return y.attachedInput};i.detachInput=function(){if(!y.attachedInput)return false;d(true);i.IME.hide();if(y.attachedInput){var e=y.attachedInput;if(e.contentWindow){e=e.contentWindow.document.body.parentNode}EM.removeEventListener(e,'keydown',U);EM.removeEventListener(e,'keypress',U);EM.removeEventListener(e,'keyup',U);EM.removeEventListener(e,'mousedown',i.IME.blurHandler);}y.attachedInput=null;return true};i.getAttachedInput=function(e){return y.attachedInput};i.open=i.show=function(e,b,B){if(!(e=i.attachInput(y.attachedInput||e))||!y.keyboard||!document.body)return false;if(!y.keyboard.parentNode||y.keyboard.parentNode.nodeType==11){if(isString(b))b=document.getElementById(b);if(!b.appendChild)return false;b.appendChild(y.keyboard);if(!isUndefined(B)&&e!=B&&B.appendChild){EM.addEventListener(B,'keydown',U);EM.addEventListener(B,'keyup',U);EM.addEventListener(B,'keypress',U);}}return true};i.close=i.hide=function(){if(!y.keyboard||!i.isOpen())return false;i.detachInput();y.keyboard.parentNode.removeChild(y.keyboard);return true};i.toggle=function(e,b,B){i.isOpen()?i.close():i.show(e,b,B);};i.isOpen=function(){return(!!y.keyboard.parentNode)&&y.keyboard.parentNode.nodeType==1};var d=function(e){if(y.attachedInput){var v=e?"":(t.rtl?'rtl':'ltr');if(y.attachedInput.contentWindow)y.attachedInput.contentWindow.document.body.dir=v;else y.attachedInput.dir=v}};var D=function(){if(null!=T.options)return;var e=T.heapSort(),b,B,n,N={};T.options={};y.langbox.innerHTML="";for(var m=0,M=e.length,ii=0;m<M;m++){b=T[m];n=b.code+" "+b.name;T.hash[n]=m;if(T.codeFilter&&!T.codeFilter.hasOwnProperty(b.code))continue;if(N.label!=b.code){N=document.createElement('optgroup');N.label=b.code;y.langbox.appendChild(N);}B=document.createElement('option');B.value=n;B.appendChild(document.createTextNode(b.name));B.label=b.name;N.appendChild(B);T.options[n]=ii++}};var f=function(e){if(isString(e))return e.match(/\x01.+?\x01|\x03.|[\ud800-\udbff][\udc00-\udfff]|./g).map(function(b){return b.replace(/[\x01\x02]/g,"")});else return e.map(function(b){return isArray(b)?b.map(function(e){return String.fromCharCodeExt(e)}).join(""):String.fromCharCodeExt(b).replace(/[\x01\x02]/g,"")});};var F=function(e){var b=e.normal,B=e.shift||{},n=e.alt||{},N=e.shift_alt||{},m=e.caps||{},M=e.shift_caps||{},ii=e.dk,iI=e.cbk,il,io,iO,iQ,i_=null,ic,iC,ie,iv,iV=-1,ix=[];ix.name=e.name;ix.code=e.code;ix.toString=e.toString;for(var iX=0,iz=b.length;iX<iz;iX++){var iZ=b[iX],iw=null,iW=null,is=null,char=[iZ];if(B.hasOwnProperty(iX)){il=f(B[iX]);ic=iX}if(ic>-1&&il[iX-ic]){is=il[iX-ic];char[x]=is}else if(iZ&&iZ!=(iZ=iZ.toUpperCase())){char[x]=iZ;is=iZ}if(n.hasOwnProperty(iX)){io=f(n[iX]);iC=iX}if(iC>-1&&io[iX-iC]){iw=io[iX-iC];char[S]=iw}if(N.hasOwnProperty(iX)){iO=f(N[iX]);ie=iX}if(ie>-1&&iO[iX-ie]){char[q]=iO[iX-ie]}else if(iw&&iw!=(iw=iw.toUpperCase())){char[q]=iw}if(m.hasOwnProperty(iX)){iQ=f(m[iX]);iv=iX}if(iv>-1&&iQ[iX-iv]){iW=iQ[iX-iv]}if(iW){char[Z]=iW}else if(is&&is.toUpperCase()!=is.toLowerCase()){char[Z]=is}else if(iZ){char[Z]=iZ.toUpperCase();}if(M.hasOwnProperty(iX)){i_=f(M[iX]);iV=iX}if(iV>-1&&i_[iX-iV]){char[E]=i_[iX-iV]}else if(is){char[E]=is.toLowerCase();}else if(iZ){char[E]=iZ}ix[iX]=char}if(ii){ix.dk={};for(var iX in ii){if(ii.hasOwnProperty(iX)){var ik=iX;if(parseInt(iX)&&iX>9){ik=String.fromCharCode(iX);}ix.dk[ik]=f(ii[iX]).join("").replace(ik,ik+ik);}}}ix.rtl=!!ix.join("").match(/[\u05b0-\u06ff]/);ix.domain=e.domain;if(isFunction(iI)){ix.charProcessor=iI}else if(iI){ix.activate=iI.activate;ix.charProcessor=iI.charProcessor}return ix};var g=function(){var e=[];e[V]=R.modeNormal;e[x]=R.modeShift;e[S]=R.modeAlt;e[q]=R.modeShiftAlt;e[Z]=R.modeCaps;e[E]=R.modeShiftCaps;e[X]=R.modeNormal;e[z]=R.modeNormal;e[K]=R.modeShift;e[W]=R.modeShift;e[s]=R.modeCaps;e[w]=R.modeCaps;e[k]=R.modeShiftAltCaps;e[r]=R.modeShiftAltCaps;DOM.CSS(y.desk).removeClass(e).addClass(e[v]);};var G=function(e){var b=v^e;if(b&x){h(!!(e&x),_.SHIFT);}if(b&X){h(!!(e&X),_.ALT);}if(b&z){h(!!(e&z),_.CTRL);}if(b&Z){H(!!(e&Z),_.CAPS);}v=e};var h=function(e,b){var B=document.getElementById(o+b+'_left'),n=document.getElementById(o+b+'_right');switch(0+e){case 0:B.className=DOM.CSS(n).removeClass(R.buttonDown).getClass();break;case 1:DOM.CSS(y.desk).removeClass([R.hoverShift,R.hoverAlt]);B.className=DOM.CSS(n).addClass(R.buttonDown).getClass();break;case 2:if(_.SHIFT==b&&v&x^x){DOM.CSS(y.desk).addClass(R.hoverShift);}else if(_.ALT==b&&v^S){DOM.CSS(y.desk).addClass(R.hoverAlt);}B.className=DOM.CSS(n).addClass(R.buttonHover).getClass();break;case 3:if(_.SHIFT==b){DOM.CSS(y.desk).removeClass(R.hoverShift);}else if(_.ALT==b){DOM.CSS(y.desk).removeClass(R.hoverAlt);}B.className=DOM.CSS(n).removeClass(R.buttonHover).getClass();break}};var H=function(e,b,B){var n=document.getElementById(b?o+b:B);if(n){switch(0+e){case 0:DOM.CSS(n).removeClass(R.buttonDown);break;case 1:DOM.CSS(n).addClass(R.buttonDown);break;case 2:DOM.CSS(n).addClass(R.buttonHover);break;case 3:DOM.CSS(n).removeClass(R.buttonHover);break}}};var j=function(e,b){var B=[e,0];if(isFunction(t.charProcessor)){var n={shift:v&x,alt:v&X,ctrl:v&z,caps:v&Z};B=t.charProcessor(e,b,n);}else if(e=="\x08"){B=['',0]}else if(t.dk&&b.length<=1){var N=I.test(e);e=e.replace(I,"");if(b&&t.dk.hasOwnProperty(b)){B[1]=0;var m=t.dk[b];idx=m.indexOf(e)+1;B[0]=idx?m.charAt(idx):e}else if(N&&t.dk.hasOwnProperty(e)){B[1]=1;B[0]=e}}return B};var J=function(t){var e=document.createElement('span');document.body.appendChild(e);e.style.position='absolute';e.style.left='-1000px';for(var b=0,B=t.length,n=[],N,m;b<B;b++){N=t[b];n.push(["<div id='",o,b,"' class='",R.buttonUp,"'><a href='#'>",L(t,N,V,R.charNormal,e),L(t,N,x,R.charShift,e),L(t,N,S,R.charAlt,e),L(t,N,q,R.charShiftAlt,e),L(t,N,Z,R.charCaps,e),L(t,N,E,R.charShiftCaps,e),"</a></div>"].join(""));}for(var b in Q){if(Q.hasOwnProperty(b)){N=Q[b];m=N.replace(/_.+/,'');n.splice(b,0,["<div id='",o,N,"' class='",R.buttonUp,"'><a title='",m,"'","><span class='title'>",m,"</span>","</a></div>"].join(""));}}document.body.removeChild(e);return n.join("").replace(/(<\w+)/g,"$1 unselectable='on' ");};var L=function(e,b,v,B,n){var N=[],char=b[v]||"",M=I.test(char)&&e.dk&&e.dk.hasOwnProperty(char=char.replace(I,""));if(M)B+=" "+R.deadkey;if((v==E&&b[Z]&&char.toLowerCase()==b[Z].toLowerCase())||(v==Z&&b[E]&&char.toLowerCase()==b[E].toLowerCase())){B+=" "+R.hiddenCaps}if((v==x&&b[V]&&char.toLowerCase()==b[V].toLowerCase())||(v==V&&b[x]&&char.toLowerCase()==b[x].toLowerCase())){B+=" "+R.hiddenShift}if((v==x&&b[E]&&char.toLowerCase()==b[E].toLowerCase())||(v==E&&b[x]&&char.toLowerCase()==b[x].toLowerCase())){B+=" "+R.hiddenShiftCaps}if((v==Z&&b[V]&&char.toLowerCase()==b[V].toLowerCase())||(v==V&&b[Z]&&char.toLowerCase()==b[Z].toLowerCase())){B+=" "+R.hiddenCaps}if((v==q&&b[S]&&char.toLowerCase()==b[S].toLowerCase())||(v==S&&b[x]&&char.toLowerCase()==b[x].toLowerCase())){B+=" "+R.hiddenAlt}N.push("<span");if(B){N.push(" class=\""+B+"\"");}N.push(" >\xa0"+char+"\xa0</span>");return N.join("");};(function(){y.keyboard=document.createElement('div');y.keyboard.unselectable="on";y.keyboard.id='virtualKeyboard';y.keyboard.innerHTML=("<div id=\"kbDesk\"><!-- --></div>"+"<select id=\"kb_langselector\"></select>"+"<select id=\"kb_mappingselector\"></select>"+'<div id="copyrights" nofocus="true"><a href="http://debugger.ru/projects/virtualkeyboard" target="_blank" title="&copy;2006-2009 Debugger.ru">VirtualKeyboard '+i.$VERSION$+'</a></div>').replace(/(<\w+)/g,"$1 unselectable='on' ");y.desk=y.keyboard.firstChild;var e=y.keyboard.childNodes.item(1);EM.addEventListener(e,'change',function(iI){i.switchLayout(this.value)});y.langbox=e;var e=e.nextSibling,b="";c=DocumentCookie.get('vk_mapping');if(!C.hasOwnProperty(c))c='QWERTY Standard';for(var B in C){var n=C[B].split("").map(function(iI){return iI.charCodeAt(0)});n.splice(14,0,8,9);n.splice(28,0,13,20);n.splice(41,0,16);n.splice(52,0,16,46,17,18,32,18,17);var N=n;n=[];for(var m=0,M=N.length;m<M;m++){n[N[m]]=m}C[B]=n;N=B.split(" ",2);if(b.indexOf(b=N[0])!=0){e.appendChild(document.createElement('optgroup'));e.lastChild.label=b}n=document.createElement('option');e.lastChild.appendChild(n);n.value=B;n.innerHTML=N[1];n.selected=(B==c);}c=C[c];EM.addEventListener(e,'change',A);EM.addEventListener(y.desk,'mousedown',P);EM.addEventListener(y.desk,'mouseup',p);EM.addEventListener(y.desk,'mouseover',a);EM.addEventListener(y.desk,'mouseout',a);EM.addEventListener(y.desk,'click',EM.preventDefaultAction);var ii=getScriptQuery('virtualkeyboard.js');l.layout=DocumentCookie.get('vk_layout')||ii.vk_layout||null})();};VirtualKeyboard.Langs={};VirtualKeyboard.IME=new function(){var i=this;var I="<div id=\"VirtualKeyboardIME\"><table><tr><td class=\"IMEControl\"><div class=\"left\"><!-- --></div></td>"+"<td class=\"IMEControl IMEContent\"></td>"+"<td class=\"IMEControl\"><div class=\"right\"><!-- --></div></td></tr>"+"<tr><td class=\"IMEControl IMEInfo\" colspan=\"3\"><div class=\"showAll\"><div class=\"IMEPageCounter\"></div><div class=\"arrow\"></div></div></td></tr></div>";var l=null;var o="";var O=0;var Q=false;var _=[];var c=null;var C=null;i.show=function(x){c=VirtualKeyboard.getAttachedInput();var X=DOM.getWindow(c);if(C!=X){if(l&&l.parentNode){l.parentNode.removeChild(l);}C=X;V();C.document.body.appendChild(l);}l.className=i.css;if(x)i.setSuggestions(x);if(c&&l&&_.length>0){EM.addEventListener(c,'blur',i.blurHandler);l.style.display="block";i.updatePosition(c);}else if('none'!=l.style.display){i.hide();}};i.hide=function(x){if(l&&'none'!=l.style.display){l.style.display="none";EM.removeEventListener(c,'blur',i.blurHandler);if(c&&DocumentSelection.getSelection(c)&&!x)DocumentSelection.deleteSelection(c);c=null;_=[]}};i.updatePosition=function(){var x=DOM.getOffset(c);l.style.left=x.x+'px';var X=DocumentSelection.getSelectionOffset(c);l.style.top=x.y+X.y+X.h+'px'};i.setSuggestions=function(x){if(!isArray(x))return false;_=x;O=0;e();i.updatePosition(c);};i.getSuggestions=function(x){return isNumber(x)?_[x]:_};i.nextPage=function(x){O=Math.max(Math.min(O+1,(Math.ceil(_.length/10))-1),0);e();};i.prevPage=function(x){O=Math.max(O-1,0);e();};i.getPage=function(){return O};i.getChar=function(x){x=--x<0?9:x;return _[i.getPage()*10+x]};i.isOpen=function(){return l&&'block'==l.style.display};i.blurHandler=function(x){i.hide();};i.toggleShowAll=function(x){var X=l.firstChild.rows[1].cells[0].lastChild;if(Q=!Q){O=0;X.className='showPage'}else{X.className='showAll'}e();};var e=function(){var x=['<table>'];for(var X=0,z=Math.ceil(_.length/10);X<z;X++){if(Q||X==O){x.push('<tr>');for(var Z=0,w=X*10;Z<10&&!isUndefined(_[w+Z]);Z++){x.push("<td><a href=''>");if(X==O){x.push("<b>&nbsp;"+((Z+1)%10)+": </b>");}else{x.push("<b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</b>");}x.push(_[w+Z]+"</a></td>");}x.push('</tr>');}}x.push('</table>');l.firstChild.rows[0].cells[1].innerHTML=x.join("");l.firstChild.rows[1].cells[0].firstChild.firstChild.innerHTML=(O+1)+"/"+(0+Q||Math.ceil(_.length/10));var W=l.getElementsByTagName("*");for(var Z=0,s=W.length;Z<s;Z++){W[Z].unselectable="on"}};var v=function(x){var X=DOM.getParent(x.target,'a');if(X){DocumentSelection.insertAtCursor(c,X.lastChild.nodeValue);i.hide();}x.preventDefault();};var V=function(){var x=C.document.createElement('div');x.innerHTML=I;l=x.firstChild;l.style.display='none';var X=l.firstChild.rows[0].cells[0],z=l.firstChild.rows[0].cells[2],Z=l.firstChild.rows[1].cells[0].lastChild;EM.addEventListener(X,'mousedown',i.prevPage);EM.addEventListener(X,'mousedown',EM.preventDefaultAction);EM.addEventListener(X,'mousedown',EM.stopPropagationAction);EM.addEventListener(z,'mousedown',i.nextPage);EM.addEventListener(z,'mousedown',EM.preventDefaultAction);EM.addEventListener(z,'mousedown',EM.stopPropagationAction);EM.addEventListener(Z,'mousedown',i.toggleShowAll);EM.addEventListener(Z,'mousedown',EM.preventDefaultAction);EM.addEventListener(Z,'mousedown',EM.stopPropagationAction);l.unselectable="on";var w=l.getElementsByTagName("*");for(var W=0,s=w.length;W<s;W++){w[W].unselectable="on"}EM.addEventListener(l,'mousedown',v);}};
