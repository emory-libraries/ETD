// The MIT License

// Copyright (c) 2008 Sergey Ilinsky (http://www.ilinsky.com)

// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:

// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

/*
 * cXBLLanguage implementation
 */
var cXBLLanguage	= {};

cXBLLanguage.namespaceURI	= "http://www.w3.org/ns/xbl";
cXBLLanguage.bindings	= {};
cXBLLanguage.rules		= {};

cXBLLanguage.factory	= document.createElement("span");


cXBLLanguage.fetch=function cXBLLanguage__fetch(sUri) {
	// Make request
	var oXMLHttpRequest	= window.XMLHttpRequest ? new window.XMLHttpRequest : new window.ActiveXObject("Microsoft.XMLHTTP");
	oXMLHttpRequest.open("GET", sUri, false);
	oXMLHttpRequest.send(null);
	//
	return oXMLHttpRequest;
};

// Private Methods
cXBLLanguage.extend=function cXBLLanguage__extend(cBinding, cBase) {
	//
	cBinding.baseBinding	= cBase;

	// Copy handlers
	if (cBase.$handlers) {
		// Create object
		cBinding.$handlers	= {};

		// Iterate over handlers
		for (var sName in cBase.$handlers) {
			if (!(sName in cBinding.$handlers))
				cBinding.$handlers[sName]	= [];
			for (var nIndex = 0, aHandlers = cBase.$handlers[sName], nLength = aHandlers.length; nIndex < nLength; nIndex++)
				cBinding.$handlers[sName].push(aHandlers[nIndex]);
		}
	}

	// Copy implementation
	for (var sMember in cBase.prototype)
		cBinding.prototype[sMember]	= cBase.prototype[sMember];

	// Copy template
	cBinding.$template	= cBase.$template;
};

/*
 * Processes given node and registeres bindings
 */
cXBLLanguage.process=function cXBLLanguage__process(oNode, sLocation) {
	//
//debugger	
	if (oNode.nodeType == 9)
		oNode	= oNode.documentElement;

	if (oNode.nodeType == 1)
		cXBLLanguage.elements(oNode, cXBLLanguage.elements.xbl, sLocation);
//->Debug
	else
		cXBLLanguage.onerror("Not a valid XML Node passed");
//<-Debug
};

cXBLLanguage.getBinding=function cXBLLanguage__getBinding(sDocumentUri) {
	if (!(sDocumentUri in cXBLLanguage.bindings)) {
		var aBinding	= sDocumentUri.split('#');
//->Debug
		if (aBinding.length < 2)
			cXBLLanguage.onerror("Invalid binding URI '" + sDocumentUri + "'");
//<-Debug
		// Make sure element is loaded
		cDocumentXBL.prototype.loadBindingDocument.call(document, aBinding[0]);

		//
//->Debug
		if (!cXBLLanguage.bindings[sDocumentUri])
			cXBLLanguage.onerror("Binding '" + aBinding[1] + "' was not found in '" + aBinding[0] + "'");
//<-Debug
	}
	return cXBLLanguage.bindings[sDocumentUri] || null;
};
//->Debug
cXBLLanguage.onerror=function cXBLLanguage__onerror(sMessage) {
	if (window.console && window.console.error)
		window.console.error("XBL 2.0: " + sMessage);
};
//<-Debug
/*
 * XBL Elements processors
 */
cXBLLanguage.elements=function cXBLLanguage__elements(oParent, fHandler, vParameter) {
	for (var nIndex = 0, sName, oNode; nIndex < oParent.childNodes.length; nIndex++) {
		oNode	= oParent.childNodes[nIndex];
		if (oNode.nodeType == 1)
			if (oNode.namespaceURI == cXBLLanguage.namespaceURI) {
				if (fHandler[sName = (oNode.localName || oNode.baseName)])
					fHandler[sName](oNode, vParameter);
//->Debug
				else
					cXBLLanguage.onerror("Element '" + oNode.nodeName + "' could not be a child of '" + oParent.nodeName + "'");
//<-Debug
			}
	}
};

/*
 * Element: xbl
 */
cXBLLanguage.elements.xbl=function elements__xbl(oNode, sDocumentUri) {
	// Process chlidren
	cXBLLanguage.elements(oNode, arguments.callee, sDocumentUri);
};

/*
 * Element: xbl/binding
 */
cXBLLanguage.elements.xbl.binding=function xbl__binding(oNode, sDocumentUri) {
	var sId		= oNode.getAttribute("id"),
		sElement= oNode.getAttribute("element");

	if (sId || sElement) {
		if (!sId)
			sId		= "xbl" + '-' + window.Math.floor(window.Math.random()*100000000);

		var cBinding	= new window.Function;

		//
		cBinding.$id			= sId;
		cBinding.$documentURI	= sDocumentUri;

		// Register binding
		cXBLLanguage.bindings[cBinding.$documentURI + '#' + cBinding.$id]	= cBinding;
		if (sElement) {
			if (!cXBLLanguage.rules[sElement])
				cXBLLanguage.rules[sElement]	= [];
			cXBLLanguage.rules[sElement].push(cBinding.$documentURI + '#' + cBinding.$id);
		}

		// binding@extends implementation
		var sExtends	= oNode.getAttribute("extends"),
			aExtend,
			sXmlBase	= fGetXmlBase(oNode, sDocumentUri),
			cBase;
		if (sExtends) {
			aExtend	= sExtends.split('#');
			if (cBase = cXBLLanguage.getBinding(fResolveUri(aExtend[0], sXmlBase) + '#' + aExtend[1]))
				cXBLLanguage.extend(cBinding, cBase);
//->Debug
			else
				cXBLLanguage.onerror("Extends '" + sExtends + "' was not found thus ignored");
//<-Debug
		}

		// Process children
		cXBLLanguage.elements(oNode, arguments.callee, cBinding);
	}
//->Debug
	else
		cXBLLanguage.onerror("Either required attribute 'id' or 'element' is missing in " + oNode.nodeName);
//<-Debug
};

/*
 * Element: xbl/script
 */
cXBLLanguage.elements.xbl.script=function xbl__script(oNode, sDocumentUri) {
	var sSrc	= oNode.getAttribute("src"),
		sScript,
		oScript;

	if (sSrc) {
		sSrc	= fResolveUri(sSrc, fGetXmlBase(oNode, sDocumentUri));
		sScript	= cXBLLanguage.fetch(sSrc).responseText;
	}
	else
	if (oNode.firstChild)
		sScript	= oNode.firstChild.nodeValue;

	// Create a script and add it to the owner document
	oScript	= document.createElement("script");
	oScript.setAttribute("type", "text/javascript");
	if (document.namespaces)
		oScript.text	= sScript;
	else
		oScript.appendChild(document.createTextNode(sScript));
	//
	document.getElementsByTagName("head")[0].appendChild(oScript);
};

cXBLLanguage.elements.xbl.binding.implementation=function binding__implementation(oNode, cBinding) {
	var sSrc	= oNode.getAttribute("src"),
		sScript	= '';

	if (sSrc) {
		sSrc	= fResolveUri(sSrc, fGetXmlBase(oNode, cBinding.$documentURI));
		sScript	= cXBLLanguage.fetch(sSrc).responseText;
	}
	else
	if (oNode.firstChild)
		sScript	= oNode.firstChild.nodeValue;

	// Create script
	if (sScript) {
		// Run implementation in the context of binding
		//oBinding.prototype	= window.Function(sScript.replace(/^\s*\(\{(.*?)\}\)\s*$/g, '$1'));	// doesn't work, why?
//		cBinding.$implementation	= window.Function(sScript.replace(/^\s*\(\s*/g, "return ").replace(/\s*\)\s*$/g, ''))();
//		cBinding.prototype	= cBinding.$implementation();

		// Fixing constructor (fix should be reconsidered)
//		var oBinding = cBinding.prototype.constructor;
//		try {
//			cBinding.prototype	= window.Function(sScript.replace(/^\s*\(\s*/g, "return ").replace(/\s*\)\s*$/g, ''))();
//		}
//		catch (oError) {
//->Debug
//			cXBLLanguage.onerror(oError.message);
//<-Debug
//		}
//		cBinding.prototype.constructor	= oBinding;
		try {
			var oImplementation	= window.Function(sScript.replace(/^\s*\(\s*/g, "return ").replace(/\s*\)\s*$/g, ''))();
			for (var sMember in oImplementation)
				cBinding.prototype[sMember]	= oImplementation[sMember];
		}
		catch (oError) {
//->Debug
			cXBLLanguage.onerror(oError.message);
//<-Debug
		}
	}
};

/*
cXBLLanguage.elements.xbl.binding.template=function binding__template(oNode, cBinding) {

	// Get first element child
	for (var nIndex = 0, oTemplate; nIndex < oNode.childNodes.length; nIndex++) {
		if (oNode.childNodes[nIndex].nodeType == 1) {
			oTemplate	= oNode.childNodes[nIndex];
			break;
		}
	}

	if (oTemplate) {
		// Serialize
		var sHtml	= window.XMLSerializer ? new window.XMLSerializer().serializeToString(oTemplate) : oTemplate.xml;

		// Remove all namespaces declarations as we run anyway in HTML only
		sHtml	= sHtml.replace(/\sxmlns:?\w*="([^"]+)"/gi, '');
		// Replace 'xbl:content' by 'strike'
		sHtml	= sHtml.replace(/(<\/?)[\w:]*content/gi, "$1" + "strike");
		// Replace 'xbl:inherited' by 'menu'
		sHtml	= sHtml.replace(/(<\/?)[\w:]*inherited/gi, "$1" + "menu");
		// Replace 'xbl:div' by 'div'
		sHtml	= sHtml.replace(/(<\/?)[\w:]*div/gi, "$1" + "div");

		// Expand all empty tags
	 	sHtml	= sHtml.replace(/<([\w\:]+)([^>]*)\/>/gi, '<$1$2></$1>');
		// Close certain empty tags
		sHtml	= sHtml.replace(/<(br|input|img)([^>]*)><\/[^>]*>/gi, '<$1$2 />');

		// Replace classes for limiting scope
		// class="{CLASS}"	-> class="xbl-{CLASS}-{BINDING_ID}"
		sHtml	= sHtml.replace(/\sclass="([^"]+)"/gi, ' ' + "class" + '="' + "xbl" + '-$1-' + cBinding.$id + '"');
		// id="{ID}"		-> xbl:id="{ID}"
		sHtml	= sHtml.replace(/\sid="([^"]+)"/gi, ' ' + "xbl" + '-' + "id" + '="$1"');
		sHtml	= sHtml.replace(/\sxbl:pseudo="([^"]+)"/gi, ' ' + "xbl" + '-' + "pseudo" + '="$1"');

		// Create a DOM HTML node
		var aMatch		= sHtml.match(/^<([a-z0-9]+)/i),
			oFactory	= cXBLLanguage.factory,
			oTemplate	= null;

		if (aMatch) {
			switch (aMatch[1]) {
				case "td":
				case "th":
					sHtml = '<' + "tr" + '>' + sHtml + '</' + "tr" + '>';
					// No break is left intentionaly
				case "tr":
					sHtml = '<' + "tbody" + '>' + sHtml + '</' + "tbody" + '>';
					// No break is left intentionaly
				case "tbody":
				case "thead":
				case "tfoot":
					oFactory.innerHTML	= '<' + "table" + '>' + sHtml + '</' + "table" + '>';
					oTemplate	= oFactory.getElementsByTagName(aMatch[1])[0];
					break;

				case "option":
					oFactory.innerHTML	= '<' + "select" + '>' + sHtml + '</' + "select" + '>';
					oTemplate	= oFactory.firstChild.firstChild;
					break;

				default:
					oFactory.innerHTML	= '&nbsp;' + sHtml;
					oTemplate	= oFactory.childNodes[1];
					break;
			}
		}

		// Correct classes
//		alert("Before: " + (window.XMLSerializer ? new window.XMLSerializer().serializeToString(oTemplate) : oTemplate.outerHTML));
		arguments.callee.$correct(cBinding, oTemplate, (oNode.getAttributeNS ? oNode.getAttributeNS("http://www.w3.org/XML/1998/namespace", "space") : oNode.getAttribute("xml" + ':' + "space")) == "preserve");
//		alert("After: " + (window.XMLSerializer ? new window.XMLSerializer().serializeToString(oTemplate) : oTemplate.outerHTML));

		// Set template
		cBinding.$template	= oTemplate.parentNode.removeChild(oTemplate);
	}
};
*/
cXBLLanguage.elements.xbl.binding.template=function binding__template(oNode, cBinding) {

	// figure out what kind of children is there
	for (var oNext = oNode.firstChild, sName = ''; oNext; oNext = oNext.nextSibling)
		if (oNext.nodeType == 1 && oNext.namespaceURI != cXBLLanguage.namespaceURI)
			sName	=(oNext.localName || oNext.baseName).toLowerCase();

	// Serialize
	var sHtml	= window.XMLSerializer ? new window.XMLSerializer().serializeToString(oNode) : oNode.xml;

	// Cut out xbl:template open/close tag
	sHtml	= sHtml.replace(/^<[\w:]*template[^>]*>\s*/i, '').replace(/\s*<\/[\w:]*template>$/i, '');

	// Remove all namespaces declarations as we run anyway in HTML only
	sHtml	= sHtml.replace(/\sxmlns:?\w*="([^"]+)"/gi, '');
	// Replace 'xbl:content' by 'strike'
	sHtml	= sHtml.replace(/(<\/?)[\w:]*content/gi, "$1" + "strike");
	// Replace 'xbl:inherited' by 'menu'
	sHtml	= sHtml.replace(/(<\/?)[\w:]*inherited/gi, "$1" + "menu");
	// Replace 'xbl:div' by 'div'
	sHtml	= sHtml.replace(/(<\/?)[\w:]*div/gi, "$1" + "div");

	// Expand all empty tags
 	sHtml	= sHtml.replace(/<([\w\:]+)([^>]*)\/>/gi, '<$1$2></$1>');
	// Close certain empty tags
	sHtml	= sHtml.replace(/<(br|input|img)([^>]*)><\/[^>]*>/gi, '<$1$2 />');

	// Replace classes for limiting scope
	// class="{CLASS}"	-> class="xbl-{CLASS}-{BINDING_ID}"
	sHtml	= sHtml.replace(/\sclass="([^"]+)"/gi, ' ' + "class" + '="' + "xbl" + '-$1-' + cBinding.$id + '"');
	// id="{ID}"		-> xbl:id="{ID}"
	sHtml	= sHtml.replace(/\sid="([^"]+)"/gi, ' ' + "xbl" + '-' + "id" + '="$1"');
	sHtml	= sHtml.replace(/\sxbl:pseudo="([^"]+)"/gi, ' ' + "xbl" + '-' + "pseudo" + '="$1"');

	// Create a DOM HTML node
	var oFactory	= cXBLLanguage.factory,
		oTemplate	= null;

	// sName keeps the element name used as direct child of template
	switch (sName) {
		case "td":
		case "th":
			sHtml = '<' + "tr" + '>' + sHtml + '</' + "tr" + '>';
			// No break is left intentionaly
		case "tr":
			sHtml = '<' + "tbody" + '>' + sHtml + '</' + "tbody" + '>';
			// No break is left intentionaly
		case "tbody":
		case "thead":
		case "tfoot":
			oFactory.innerHTML	= '<' + "table" + '>' + sHtml + '</' + "table" + '>';
			oTemplate	= oFactory.getElementsByTagName(sName)[0].parentNode;
			break;

		case "option":
			oFactory.innerHTML	= '<' + "select" + '>' + sHtml + '</' + "select" + '>';
			oTemplate	= oFactory.firstChild;
			break;

		default:
			oFactory.innerHTML	= '&nbsp;<div>' + sHtml + '</div>';
			oTemplate	= oFactory.childNodes[1];
			break;
	}

	// Correct classes
//	alert("Before: " + (window.XMLSerializer ? new window.XMLSerializer().serializeToString(oTemplate) : oTemplate.outerHTML));
	arguments.callee.$correct(cBinding, oTemplate, (oNode.getAttributeNS ? oNode.getAttributeNS("http://www.w3.org/XML/1998/namespace", "space") : oNode.getAttribute("xml" + ':' + "space")) == "preserve");
//	alert("After: " + (window.XMLSerializer ? new window.XMLSerializer().serializeToString(oTemplate) : oTemplate.outerHTML));

	// Set template
	cBinding.$template	= oTemplate.parentNode.removeChild(oTemplate);
};

cXBLLanguage.elements.xbl.binding.template.$correct=function template__$correct(cBinding, oNode, bXmlSpace) {
	var sValue, oNext;
	while (oNode) {
		oNext	= oNode.nextSibling;
		if (oNode.nodeType == 1) {
			if (sValue = oNode.getAttribute("xbl" + '-' + "id"))
				oNode.className+= (oNode.className ? ' ' : '') + "xbl-id" + '-' + sValue + '-' + cBinding.$id;
			if (sValue = oNode.getAttribute("xbl" + '-' + "pseudo"))
				oNode.className+= (oNode.className ? ' ' : '') + "xbl-pseudo" + '-' + sValue + '-' + cBinding.$id;
			if (oNode.firstChild)
				arguments.callee(cBinding, oNode.firstChild, bXmlSpace);
		}
		else
		if (!bXmlSpace && oNode.nodeType == 3) {
//			sValue = oNode.data.replace(/[ \t\r\n\f]+/g, ' ');	// This expression would leave &nbsp; intact
			sValue = oNode.data.replace(/\s+/g, ' ');
			// remove empty nodes
			if (sValue == ' ')
				oNode.parentNode.removeChild(oNode);
			else
			// strip text nodes
			if (oNode.data != sValue)
				oNode.data = sValue;
		}
		oNode	= oNext;
	}
};

cXBLLanguage.elements.xbl.binding.handlers=function binding__handlers(oNode, cBinding) {
	// Process chlidren
	cXBLLanguage.elements(oNode, arguments.callee, cBinding);
};

/*
var oXBLLanguagePhase	= {};
oXBLLanguagePhase["capture"]		= cEvent.CAPTURING_PHASE;
oXBLLanguagePhase["target"]			= cEvent.AT_TARGET;
oXBLLanguagePhase["default-action"]	= 0x78626C44;
*/

cXBLLanguage.elements.xbl.binding.handlers.handler=function handlers__handler(oNode, cBinding) {
	var sName	= oNode.getAttribute("event"),
		fHandler;
	if (sName) {
		if (oNode.firstChild) {
			// Create object for handlers
			if (!cBinding.$handlers)
				cBinding.$handlers	= {};

			// Create object for handlers of specified type
			if (!cBinding.$handlers[sName])
				cBinding.$handlers[sName]	= [];

			try {
				fHandler	= new window.Function("event", oNode.firstChild.nodeValue);
			}
			catch (oError) {
//->Debug
				cXBLLanguage.onerror(oError.message);
//<-Debug
			}

			if (fHandler) {
				cBinding.$handlers[sName].push(fHandler);

				// Get attributes
				var sValue;
				// Event
				if (sValue = oNode.getAttribute("phase"))
					fHandler["phase"]			= sValue == "capture" ? 1 : sValue == "target" ? 2 /* : sValue == "default-action" ? 0x78626C44*/ : 0;
				if (sValue = oNode.getAttribute("trusted"))
					fHandler["trusted"]			= sValue == "true";
				if (sValue = oNode.getAttribute("propagate"))
					fHandler["propagate"]		= sValue != "stop";
				if (sValue = oNode.getAttribute("default-action"))
					fHandler["default-action"]	= sValue != "cancel";
				// MouseEvent
				if (sValue = oNode.getAttribute("button"))
					fHandler["button"]			= sValue * 1;
				if (sValue = oNode.getAttribute("click-count"))
					fHandler["click-count"]		= sValue * 1;
				// KeyboardEvent
				if (sValue = oNode.getAttribute("modifiers"))
					fHandler["modifiers"]		= sValue;
				if (sValue = oNode.getAttribute("key"))
					fHandler["key"]				= sValue;
				if (sValue = oNode.getAttribute("key-location"))
					fHandler["key-location"]	= sValue;
				// TextInput
				if (sValue = oNode.getAttribute("text"))
					fHandler["text"]			= sValue;
				// MutationEvent
//				if (sValue = oNode.getAttribute("prev-value"))
//					fHandler["prev-value"]		= sValue;
//				if (sValue = oNode.getAttribute("new-value"))
//					fHandler["new-value"]		= sValue;
//				if (sValue = oNode.getAttribute("attr-name"))
//					fHandler["attr-name"]		= sValue;
//				if (sValue = oNode.getAttribute("attr-change"))
//					fHandler["attr-change"]		= sValue;
			}
		}
	}
//->Debug
	else
		cXBLLanguage.onerror("Missing 'event' attribute in " + oNode.nodeName);
//<-Debug
};

cXBLLanguage.elements.xbl.binding.resources=function binding__resources(oNode, cBinding) {
	// Process chlidren
	cXBLLanguage.elements(oNode, arguments.callee, cBinding);
};

cXBLLanguage.elements.xbl.binding.resources.style=function resources__style(oNode, cBinding) {
	// Get the document
	var sSrc	= oNode.getAttribute("src"),
		sMedia	= oNode.getAttribute("media"),
		sBase	= fGetXmlBase(oNode, cBinding.$documentURI),
		sStyle,
		oStyle,
		aCss;

	// 1. Get stylesheet
	if (sSrc) {
		sSrc	= fResolveUri(sSrc, sBase);
		sStyle	= cXBLLanguage.fetch(sSrc).responseText;
	}
	else
	if (oNode.firstChild) {
		sSrc	= sBase;
		sStyle	= oNode.firstChild.nodeValue;
	}

	// Create stylesheet
	if (sStyle) {
		// 2. Setup local context for CSS rules
		// 	.{class}	-> .xbl-{class}-{binding}
		sStyle	= sStyle.replace(/\.([\w-]+)([\s{+~>])/g, '.' + "xbl" + '-$1-' + cBinding.$id + '$2');
		//	#{id}		-> .xbl-id-{id}-{binding}
		sStyle	= sStyle.replace(/#([\w-]+)([\s{+~>])/g, '.' + "xbl-id" + '-$1-' + cBinding.$id + '$2');
		//	::{pseudo}	-> .xbl-pseudo-{pseudo}-{binding}
		sStyle	= sStyle.replace(/::([\w-]+)([\s{+~>])/g, '.' + "xbl-pseudo" + '-$1-' + cBinding.$id + '$2');
		//	:bound-element	-> .xbl-bound-element-{binding}
		sStyle	= sStyle.replace(/:bound-element([\s{+~>.:])/g, '.' + "xbl-bound-element" + '-' + cBinding.$id + '$1');

		// 3. Resolve relative uris
		if (aCss = sStyle.match(/url\s*\([^\)]+\)/g)) {
			for (var i = 0, aUrl; i < aCss.length; i++) {
				aUrl	= aCss[i].match(/(url\s*\(['"]?)([^\)'"]+)(['"]?\))/);
				sStyle	= sStyle.replace(aUrl[0], aUrl[1] + fResolveUri(aUrl[2], sSrc) + aUrl[3]);
			}
		}

		// 4. Create stylesheet in the document
		if (document.namespaces) {
			cXBLLanguage.factory.innerHTML	= '&nbsp;' + '<' + "style" + ' ' + "type" + '="' + "text/css" + '"' + (sMedia ? ' ' + "media" + '="' + sMedia + '"' : '') + '>' + sStyle + '</' + "style" + '>';
			oStyle	= cXBLLanguage.factory.childNodes[1];
		}
		else {
			oStyle	= document.createElement("style");
			oStyle.setAttribute("type", "text/css");
			if (sMedia)
				oStyle.setAttribute("media", sMedia);
			oStyle.appendChild(document.createTextNode(sStyle));
		}
		document.getElementsByTagName("head")[0].appendChild(oStyle);
	}
};

cXBLLanguage.elements.xbl.binding.resources.prefetch=function resources__prefetch(oNode, cBinding) {
	var sSrc	= oNode.getAttribute("src");
	if (sSrc) {
		sSrc	= fResolveUri(sSrc, fGetXmlBase(oNode, cBinding.$documentURI));
		cXBLLanguage.fetch(sSrc);
	}
//->Debug
	else
		cXBLLanguage.onerror("Required attribute 'src' is missing in " + oNode.nodeName);
//<-Debug
};

//window.XBLProcessor	= cXBLLanguage;
function fNumberToHex(nValue, nLength) {
	var sValue	= window.Number(nValue).toString(16);
	if (sValue.length < nLength)
		sValue	= window.Array(nLength + 1 - sValue.length).join('0') + sValue;
	return sValue;
};

// Event-related
function fEventTarget(oElement) {
	for (var oNode = oElement; oNode; oNode = oNode.parentNode)
		if (oNode.xblChild || (oNode.xblImplementations && oNode.xblImplementations instanceof cXBLImplementationsList))
			return oNode;
	return oElement;
};

function fMouseEventButton(nButton) {
	if (!document.namespaces)
		return nButton;
	if (nButton == 4)
		return 1;
	if (nButton == 2)
		return 2;
	return 0;
};

function fKeyboardEventIdentifier(oEvent) {
	return oKeyIdentifiers[oEvent.keyCode] || ('U+' + fNumberToHex(oEvent.keyCode, 4)).toUpperCase();
};

function fKeyboardEventModifiersList(oEvent) {
	var aModifiersList = [];
	if (oEvent.altKey)
		aModifiersList[aModifiersList.length] = "Alt";
	if (oEvent.ctrlKey)
		aModifiersList[aModifiersList.length] = "Control";
	if (oEvent.metaKey)
		aModifiersList[aModifiersList.length] = "Meta";
	if (oEvent.shiftKey)
		aModifiersList[aModifiersList.length] = "Shift";
	return aModifiersList.join(' ');
};

var oKeyIdentifiers	= {
	8:		'U+0008',	// The Backspace (Back) key
	9:		'U+0009',	// The Horizontal Tabulation (Tab) key

	13:		'Enter',

	16:		"Shift",
	17:		"Control",
	18:		"Alt",

	20:		'CapsLock',

	27:		'U+001B',	// The Escape (Esc) key

	33:		'PageUp',
	34:		'PageDown',
	35:		'End',
	36:		'Home',
	37:		'Left',
	38:		'Up',
	39:		'Right',
	40: 	'Down',

	45:		'Insert',
	46:		'U+002E',	// The Full Stop (period, dot, decimal point) key (.)

	91:		'Win',

	112:	'F1',
	113:	'F2',
	114:	'F3',
	115:	'F4',
	116:	'F5',
	117:	'F6',
	118:	'F7',
	119:	'F8',
	120:	'F9',
	121:	'F10',
	122:	'F11',
	123:	'F12'/*,
	144:	'NumLock'*/
};

function fRegisterEventRouter(oBinding, sName) {
	// Forward events that are not implemented by browsers
	if (sName == "textInput")
		sName	= "keypress";
	else
	if (sName == "mousewheel") {
		// Gecko only
		if (window.controllers)
			sName	= "DOMMouseScroll";
	}
	// Pickup events that do not directly lead to required event generation
	else
	if (sName == "mouseenter") {
		// All but IE
		if (!document.namespaces)
			sName	= "mouseover";
	}
	else
	if (sName == "mouseleave") {
		// All but IE
		if (!document.namespaces)
			sName	= "mouseout";
	}
	else
	if (sName == "click") {
		// Additional handlers needed to catch atavistic events
		fRegisterEventRouter(oBinding, "contextmenu");
		fRegisterEventRouter(oBinding, "dblclick");
	}
	else
	if (sName == "DOMFocusIn")
		sName	= "focus";
	else
	if (sName == "DOMFocusOut")
		sName	= "blur";
	else
	if (sName == "DOMActivate")
		sName	= "click";

	// Return if this type of event router was already registered
	if (oBinding.$handlers[sName])
		return;

	var oElement	= oBinding.boundElement,
		fHandler	= function(oEvent) {
			return fRouteEvent(sName, oEvent, oBinding);
		};

	// Add event listener
	if (oElement.attachEvent)
		oElement.attachEvent("on" + sName, fHandler);
	else
		oElement.addEventListener(sName, fHandler, false);

	// Register closure
	oBinding.$handlers[sName]	= fHandler;
};

function fUnRegisterEventRouter(oBinding, sName) {
	// Return if the router was not registered
	if (!oBinding.$handlers[sName])
		return;

	var oElement	= oBinding.boundElement,
		fHandler	= oBinding.$handlers[sName];

	// Remove event listener
	if (oElement.detachEvent)
		oElement.detachEvent("on" + sName, fHandler);
	else
		oElement.removeEventListener(sName, fHandler, false);

	// Unregister closure
	delete oBinding.$handlers[sName];
};

function fRouteEvent(sType, oEvent, oBinding) {
	var oElement	= fEventTarget(oEvent.srcElement || oEvent.target),
		nClick		= 0,
		oEventXBL	= null,
		oRelated	= null;

	// 1: Create XBLEvent (Only list standard events)
	switch (sType) {
		case "contextmenu":	// mutate to "click"
			sType	= "click";
			// No break left intentionally
		case "mouseover":
		case "mouseout":
			// Verify if event is not in shadow content
			oRelated	= oEvent.relatedTarget || (sType == "mouseover" ? oEvent.fromElement : sType == "mouseout" ? oEvent.toElement : null);
			if (oRelated && fEventTarget(oRelated) == oElement)
				return;
			// No break left intentionally
		case "mousemove":
		case "mousedown":
		case "mouseup":
		case "dblclick":
		case "click":
			oEventXBL	= new cMouseEvent;
			oEventXBL.initMouseEvent(sType, true, true, window, sType == "dblclick" ? 2 : oEvent.detail || 1, oEvent.screenX, oEvent.screenY, oEvent.clientX, oEvent.clientY, oEvent.ctrlKey, oEvent.altKey, oEvent.shiftKey, oEvent.metaKey || false, oEvent.type == "contextmenu" ? 2 : fMouseEventButton(oEvent.button), oRelated);
			break;

		case "mouseenter":
		case "mouseleave":
			// TODO: Implement missing mouseenter/mouseleave events dispatching
			// Verify if event is not in shadow content
			oRelated	= oEvent.relatedTarget || (sType == "mouseover" ? oEvent.fromElement : sType == "mouseout" ? oEvent.toElement : null);
			if (oRelated && fEventTarget(oRelated) == oElement)
				return;
			oEventXBL	= new cMouseEvent;
			oEventXBL.initMouseEvent(sType, false, false, window, oEvent.detail || 1, oEvent.screenX, oEvent.screenY, oEvent.clientX, oEvent.clientY, oEvent.ctrlKey, oEvent.altKey, oEvent.shiftKey, oEvent.metaKey || false, fMouseEventButton(oEvent.button), oEvent.relatedTarget);
			break;

		case "keydown":
		case "keyup":
			oEventXBL	= new cKeyboardEvent;
			oEventXBL.initKeyboardEvent(sType, true, true, window, fKeyboardEventIdentifier(oEvent), null, fKeyboardEventModifiersList(oEvent));
			break;

		case "keypress":	// Mutated to textInput
			// Filter out non-alphanumerical keypress events
			if (oEvent.ctrlKey || oEvent.altKey || oEvent.keyCode in oKeyIdentifiers)
				return;
			sType	= "textInput";
			// No break left intentionally
		case "textInput":
			oEventXBL	= new cTextEvent;
			oEventXBL.initTextEvent(sType, true, true, window, window.String.fromCharCode(oEvent.charCode || oEvent.keyCode));
			break;

		case "focus":
		case "blur":
			oEventXBL	= new cUIEvent;
			oEventXBL.initUIEvent(sType, false, false, window, null);
			break;

		case "DOMActivate":
			oEventXBL	= new cUIEvent;
			oEventXBL.initUIEvent(sType, true, true, window, null);
			break;

		case "DOMFocusIn":
		case "DOMFocusOut":
		case "scroll":
		case "resize":
			oEventXBL	= new cUIEvent;
			oEventXBL.initUIEvent(sType, true, false, window, null);
			break;

		case "DOMMouseScroll":
			sType	= "mousewheel";
			// No break left intentionally
		case "mousewheel":
			oEventXBL	= new cMouseWheelEvent;
			oEventXBL.initMouseWheelEvent(sType, true, true, window, null, oEvent.screenX, oEvent.screenY, oEvent.clientX, oEvent.clientY, fMouseEventButton(oEvent.button), null, fKeyboardEventModifiersList(oEvent), oEvent.srcElement ? -1 * oEvent.wheelDelta : oEvent.detail * 40);
			break;

		case "load":
		case "unload":
			oEventXBL	= new cEvent;
			oEventXBL.initEvent(sType, false, false);
			break;

		case "submit":
		case "reset":
			oEventXBL	= new cEvent;
			oEventXBL.initEvent(sType, true, true);
			break;

		case "abort":
		case "error":
		case "change":
		case "select":
			oEventXBL	= new cEvent;
			oEventXBL.initEvent(sType, true, false);
			break;

		case "DOMSubtreeModified":
		case "DOMNodeInserted":
		case "DOMNodeInsertedodeRemoved":
		case "DOMNodeRemovedFromDocument":
		case "DOMNodeInsertedIntoDocument":
		case "DOMAttrModified":
		case "DOMCharacterDataModified":
		case "DOMElementNameChanged":
		case "DOMAttributeNameChanged":
			// Do not propagate whose changes
			return;

		default:
			oEventXBL	= new cCustomEvent;
			oEventXBL.initCustomEventNS(oEvent.namespaceURI || null, sType, !!oEvent.bubbles, !!oEvent.cancelable, oEvent.detail);
	}

	// Set event to be trusted
	oEventXBL.trusted		= true;

	// 2. Pseudo-dispatch - set targets / phasing
	oEventXBL.target		= oElement;
	oEventXBL.currentTarget	= oBinding.boundElement;
	oEventXBL.eventPhase	= oEvent.target == oEvent.currentTarget ? cEvent.AT_TARGET : cEvent.BUBBLING_PHASE;

	// 3: Process event handler
	var aHandlers	= oBinding.constructor.$handlers ? oBinding.constructor.$handlers[oEventXBL.type] : null;
	if (aHandlers) {
		for (var nIndex = 0, fHandler; fHandler = aHandlers[nIndex]; nIndex++) {
			// 1: Apply Filters
			// Common Filters
			if ("trusted" in fHandler && fHandler["trusted"] != oEventXBL.trusted)
				continue;
			if ("phase" in fHandler)
				if (fHandler["phase"] != oEventXBL.eventPhase)
					continue;

			// Mouse Event + Key Event
			if (oEventXBL instanceof cMouseEvent || oEventXBL instanceof cKeyboardEvent) {
				// Modifier Filters
				if ("modifiers" in fHandler) {
					var sModifiersList	= fHandler["modifiers"];
					if (sModifiersList == "none") {
						if (oEventXBL.ctrlKey || oEventXBL.altKey || oEventXBL.shiftKey || oEventXBL.metaKey)
							continue;
					}
					else
					if (sModifiersList == "any") {
						if (!(oEventXBL.ctrlKey || oEventXBL.altKey || oEventXBL.shiftKey || oEventXBL.metaKey))
							continue;
					}
					else {
						for (var nModifier = 0, aModifier, bPass = true, aModifiersList = sModifiersList.split(' '); nModifier < aModifiersList.length; nModifier++) {
							if (aModifier = aModifiersList[nModifier].match(/([+-]?)(\w+)(\??)/))
								if (oEventXBL.getModifierState(aModifier[2]) == (aModifier[1] == '-'))
									bPass	= false;
						}
						if (!bPass)
							continue;
					}
				}

				// Mouse Event Handler Filters
				if (oEventXBL instanceof cMouseEvent) {
					if ("click-count" in fHandler && fHandler["click-count"] != oEventXBL.detail)
						continue;
					if ("button" in fHandler && fHandler["button"] != oEventXBL.button)
						continue;
				}
				else
				// Key Event Handler Filters
				if (oEventXBL instanceof cKeyboardEvent) {
					if ("key" in fHandler && fHandler["key"] != oEventXBL.keyIdentifier)
						continue;
//					if ("key-location" in fHandler && fHandler["key-location"] != oEventXBL.keyLocation)
//						continue;
				}
			}
			else
			// Text Input Event Handler Filters
			if (oEventXBL instanceof cTextEvent) {
				if ("text" in fHandler && fHandler["text"] != oEventXBL.data)
					continue;
			}
/*
			else
			// Mutation Event Handler Filters
			if (oEventXBL instanceof cMutationEvent) {
				// Not supported
			}
*/
			// 2: Apply Actions
			if ("default-action" in fHandler)
				if (!fHandler["default-action"])
					oEventXBL.preventDefault();

			if ("propagate" in fHandler)
				if (!fHandler["propagate"])
					oEventXBL.stopPropagation();

			// 3: Execute handler
			fHandler.call(oBinding, oEventXBL);

			// 4: Exit if propagation stopped
			if (oEventXBL.$stoppedImmediate)
				break;
		}
	}

	// 4: Dispatch derived event
	switch (sType) {
		case "focus":
		case "blur":
			if (!fRouteEvent(sType == "focus" ? "DOMFocusIn" : "DOMFocusOut", oEvent, oBinding))
				oEventXBL.preventDefault();
			break;

		case "mouseover":
		case "mouseout":
			// TODO
			if (oEvent.relatedTarget && oEvent.currentTarget == oEvent.target)
				if (oEvent.target.parentNode == oEvent.relatedTarget || oEvent.target.parentNode == oEvent.relatedTarget.parentNode)
					fRouteEvent(sType == "mouseover" ? "mouseenter" : "mouseleave", oEvent, oBinding);

			break;

		case "click":
			if (oEventXBL.button == 0) {
				var sTagName	= oEventXBL.target.tagName.toLowerCase();
				if (sTagName == "button" || sTagName == "a")
					if (!fRouteEvent("DOMActivate", oEvent, oBinding))
						oEventXBL.preventDefault();
			}
			break;
	}

	// 4: Apply changes to browser event flow
	// 4.1: Stop propagation if neccesary
	if (oEventXBL.$stopped) {
		if (oEvent.stopPropagation)
			oEvent.stopPropagation();
		else
			oEvent.cancelBubble	= true;
	}

	// 4.2: Prevent default if neccesary
	if (oEventXBL.defaultPrevented) {
		if (oEvent.preventDefault)
			oEvent.preventDefault();
		return false;
	}
	return true;
};
// TODO: Implement caching
function fGetUriComponents(sUri) {
	var aResult = sUri.match(/^(([^:\/?#]+):)?(\/\/([^\/?#]*))?([^?#]*)(\\?([^#]*))?(#(.*))?/),
		oResult	= {};
	oResult._scheme		= aResult[2];
	oResult._authority	= aResult[4];
	oResult._path		= aResult[5];
	oResult._query		= aResult[7];
	oResult._fragment	= aResult[9];

	return oResult;
};

/*
 * Resolves Uri to a BaseUri
 */
function fResolveUri(sUri, sBaseUri) {
	if (sUri == '' || sUri.charAt(0) == '#')
		return sBaseUri;

	var oUri = fGetUriComponents(sUri);
	if (oUri._scheme)
		return sUri;

	var oBaseUri = fGetUriComponents(sBaseUri);
	oUri._scheme = oBaseUri._scheme;

	if (!oUri._authority)
	{
		oUri._authority = oBaseUri._authority;
		if (oUri._path.charAt(0) != '/')
		{
			var aUriSegments = oUri._path.split('/'),
				aBaseUriSegments = oBaseUri._path.split('/');
			aBaseUriSegments.pop();
			var nBaseUriStart = aBaseUriSegments[0] == '' ? 1 : 0;
			for (var nIndex = 0, nLength = aUriSegments.length; nIndex < nLength; nIndex++)
			{
				if (aUriSegments[nIndex] == '..')
				{
					if (aBaseUriSegments.length > nBaseUriStart)
						aBaseUriSegments.pop();
					else
					{
						aBaseUriSegments.push(aUriSegments[nIndex]);
						nBaseUriStart++;
					}
				}
				else
				if (aUriSegments[nIndex] != '.')
					aBaseUriSegments.push(aUriSegments[nIndex]);
			}
			if (aUriSegments[--nIndex] == '..' || aUriSegments[nIndex] == '.')
				aBaseUriSegments.push('');
			oUri._path = aBaseUriSegments.join('/');
		}
	}

	var aResult = [];
	if (oUri._scheme)
		aResult.push(oUri._scheme + ':');
	if (oUri._authority)
		aResult.push('/' + '/' + oUri._authority);
	if (oUri._path)
		aResult.push(oUri._path);
	if (oUri._query)
		aResult.push('?' + oUri._query);
	if (oUri._fragment)
		aResult.push('#' + oUri._fragment);

	return aResult.join('');
};

/*
 * Resolves baseUri property for a DOMNode
 */
function fGetXmlBase(oNode, sDocumentUri) {
	if (oNode.nodeType == 9)
		return sDocumentUri;

	if (oNode.nodeType == 1) {
		var sXmlBase	= oNode.getAttribute("xml" + ':' + "base");
		if (!sXmlBase && oNode.getAttributeNS)	// Opera, Safari but not FF
			sXmlBase	= oNode.getAttributeNS("http://www.w3.org/XML/1998/namespace", "base");

		if (sXmlBase)
			return fResolveUri(sXmlBase, fGetXmlBase(oNode.parentNode, sDocumentUri));
	}
	return fGetXmlBase(oNode.parentNode, sDocumentUri);
};
var cEvent	= new window.Function;

// Constants
cEvent.CAPTURING_PHASE	= 1;
cEvent.AT_TARGET		= 2;
cEvent.BUBBLING_PHASE	= 3;

// Public Properties
cEvent.prototype.namespaceURI	= null;
cEvent.prototype.bubbles		= null;
cEvent.prototype.cancelable		= null;
cEvent.prototype.currentTarget	= null;
cEvent.prototype.eventPhase		= null;
cEvent.prototype.target			= null;
cEvent.prototype.timeStamp		= null;
cEvent.prototype.type			= null;
cEvent.prototype.defaultPrevented	= false;

// Private Properties
cEvent.prototype.$stopped			= false;
cEvent.prototype.$stoppedImmediate	= false;

// Public Methods
cEvent.prototype.initEvent=function cEvent_prototype_initEvent(sType, bCanBubble, bCancelable) {
    this.type       = sType;
    this.bubbles    = bCanBubble;
    this.cancelable = bCancelable;
};

cEvent.prototype.initEventNS=function cEvent_prototype_initEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable) {
	this.initEvent(sType, bCanBubble, bCancelable);

	//
    this.namespaceURI	= sNameSpaceURI;
};

cEvent.prototype.stopPropagation=function cEvent_prototype_stopPropagation() {
	this.$stopped	= this.bubbles;
};

cEvent.prototype.stopImmediatePropagation=function cEvent_prototype_stopImmediatePropagation() {
	this.$stoppedImmediate	= this.$stopped	= this.bubbles;
};

cEvent.prototype.preventDefault=function cEvent_prototype_preventDefault() {
	this.defaultPrevented	= this.cancelable;
};
var cUIEvent	= new window.Function;
cUIEvent.prototype	= new cEvent;

// Public Properties
cUIEvent.prototype.view		= null;
cUIEvent.prototype.detail	= null;

// Public Methods
cUIEvent.prototype.initUIEvent=function cUIEvent_prototype_initUIEvent(sType, bCanBubble, bCancelable, oView, nDetail) {
	this.initEvent(sType, bCanBubble, bCancelable);

	//
	this.view	= oView;
	this.detail	= nDetail;
};

cUIEvent.prototype.initUIEventNS=function cUIEvent_prototype_initUIEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oView, nDetail) {
	this.initUIEvent(sType, bCanBubble, bCancelable, oView, nDetail);

	//
	this.namespaceURI	= sNameSpaceURI;
};
var cMouseEvent	= new window.Function;
cMouseEvent.prototype	= new cUIEvent;

// Public Properties
cMouseEvent.prototype.screenX = null;
cMouseEvent.prototype.screenY = null;
cMouseEvent.prototype.clientX = null;
cMouseEvent.prototype.clientY = null;
cMouseEvent.prototype.ctrlKey = null;
cMouseEvent.prototype.altKey  = null;
cMouseEvent.prototype.shiftKey= null;
cMouseEvent.prototype.metaKey = null;
cMouseEvent.prototype.button  = null;
cMouseEvent.prototype.relatedTarget = null;

// Public Methods
cMouseEvent.prototype.initMouseEvent=function cMouseEvent_prototype_initMouseEvent(sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY, bCtrlKey, bAltKey, bShiftKey, bMetaKey, nButton, oRelatedTarget) {
	this.initUIEvent(sType, bCanBubble, bCancelable, oView, nDetail);

	//
	this.screenX  = nScreenX;
	this.screenY  = nScreenY;
	this.clientX  = nClientX;
	this.clientY  = nClientY;
	this.ctrlKey  = bCtrlKey;
	this.altKey   = bAltKey;
	this.shiftKey = bShiftKey;
	this.metaKey  = bMetaKey;
	this.button   = nButton;
	this.relatedTarget = oRelatedTarget;
};

cMouseEvent.prototype.initMouseEventNS=function cMouseEvent_prototype_initMouseEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY, bCtrlKey, bAltKey, bShiftKey, bMetaKey, nButton, oRelatedTarget) {
	this.initMouseEvent(sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY, bCtrlKey, bAltKey, bShiftKey, bMetaKey, nButton, oRelatedTarget);

	//
	this.namespaceURI	= sNameSpaceURI;
};

cMouseEvent.prototype.getModifierState=function cMouseEvent_prototype_getModifierState(sModifier) {
	switch (sModifier) {
		case "Alt":		return this.altKey;
		case "Control":	return this.ctrlKey;
		case "Meta":	return this.metaKey;
		case "Shift":	return this.shiftKey;
	}
	return false;
};
var cMouseWheelEvent	= new window.Function;
cMouseWheelEvent.prototype	= new cMouseEvent;

// Public Properties
cMouseWheelEvent.prototype.wheelDelta	= null;

// Public Methods
cMouseWheelEvent.prototype.initMouseWheelEvent=function cMouseWheelEvent_prototype_initMouseWheelEvent(sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY/*, bCtrlKey, bAltKey, bShiftKey, bMetaKey*/, nButton, oRelatedTarget, sModifiersList, nWheelDelta) {
	this.initMouseEvent(sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY, sModifiersList.indexOf("Control") >-1, sModifiersList.indexOf("Alt") >-1, sModifiersList.indexOf("Shift") >-1, sModifiersList.indexOf("Meta") >-1, nButton, oRelatedTarget);

	//
	this.wheelDelta	= nWheelDelta;
};

cMouseWheelEvent.prototype.initMouseWheelEventNS=function cMouseWheelEvent_prototype_initMouseWheelEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY/*, bCtrlKey, bAltKey, bShiftKey, bMetaKey*/, nButton, oRelatedTarget, sModifiersList, nWheelDelta) {
	this.initMouseWheelEvent(sType, bCanBubble, bCancelable, oView, nDetail, nScreenX, nScreenY, nClientX, nClientY/*, bCtrlKey, bAltKey, bShiftKey, bMetaKey*/, nButton, oRelatedTarget, sModifiersList, nWheelDelta);

	//
	this.namespaceURI	= sNameSpaceURI;
};
var cKeyboardEvent	= new window.Function;
cKeyboardEvent.prototype	= new cUIEvent;

// Constants
cKeyboardEvent.DOM_KEY_LOCATION_STANDARD = 0;
cKeyboardEvent.DOM_KEY_LOCATION_LEFT     = 1;
cKeyboardEvent.DOM_KEY_LOCATION_RIGHT    = 2;
cKeyboardEvent.DOM_KEY_LOCATION_NUMPAD   = 3;

// Public Properties
cKeyboardEvent.prototype.keyIdentifier = null;
cKeyboardEvent.prototype.keyLocation   = null;
cKeyboardEvent.prototype.altKey   = null;
cKeyboardEvent.prototype.ctrlKey  = null;
cKeyboardEvent.prototype.metaKey  = null;
cKeyboardEvent.prototype.shiftKey = null;

// Public Methods
cKeyboardEvent.prototype.initKeyboardEvent=function cKeyboardEvent_prototype_initKeyboardEvent(sType, bCanBubble, bCancelable, oView, sKeyIdentifier, nKeyLocation, sModifiersList) {
	this.initUIEvent(sType, bCanBubble, bCancelable, oView, null);

	//
	this.ctrlKey  = sModifiersList.indexOf("Control") >-1;
	this.altKey   = sModifiersList.indexOf("Alt")     >-1;
	this.shiftKey = sModifiersList.indexOf("Shift")   >-1;
	this.metaKey  = sModifiersList.indexOf("Meta")    >-1;

	this.keyIdentifier = sKeyIdentifier;
	this.keyLocation   = nKeyLocation;
};

cKeyboardEvent.prototype.initKeyboardEventNS=function cKeyboardEvent_prototype_initKeyboardEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oView, sKeyIdentifier, nKeyLocation, sModifiersList) {
	this.initkeyboardEvent(sType, bCanBubble, bCancelable, oView, sKeyIdentifier, nKeyLocation, sModifiersList);

	//
	this.namespaceURI	= sNameSpaceURI;
};

cKeyboardEvent.prototype.getModifierState=function cKeyboardEvent_prototype_getModifierState(sModifier) {
	switch (sModifier) {
		case "Alt":		return this.altKey;
		case "Control":	return this.ctrlKey;
		case "Meta":	return this.metaKey;
		case "Shift":	return this.shiftKey;
	}
	return false;
};
var cTextEvent	= new window.Function;
cTextEvent.prototype	= new cUIEvent;

// Public Properties
cTextEvent.prototype.data	= null;

// Public Methods
cTextEvent.prototype.initTextEvent=function cTextEvent_prototype_initTextEvent(sType, bCanBubble, bCancelable, oView, sData) {
	this.initUIEvent(sType, bCanBubble, bCancelable, oView, null);

	//
	this.data	= sData;
};

cTextEvent.prototype.initTextEventNS=function cTextEvent_prototype_initTextEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oView, sData) {
	this.initTextEvent(sType, bCanBubble, bCancelable, oView, null);

	//
	this.namespaceURI	= sNameSpaceURI;
};


var cCustomEvent	= new window.Function;
cCustomEvent.prototype	= new cEvent;

// Public Properties
cCustomEvent.prototype.detail	= null;

// Public Methods
cCustomEvent.prototype.initCustomEvent=function cCustomEvent_prototype_initCustomEvent(sType, bCanBubble, bCancelable, oDetail) {
	this.initEvent(sType, bCanBubble, bCancelable);

	//
	this.detail	= oDetail;
};

cCustomEvent.prototype.initCustomEventNS=function cCustomEvent_prototype_initCustomEventNS(sNameSpaceURI, sType, bCanBubble, bCancelable, oDetail) {
	this.initCustomEvent(null, sType, bCanBubble, bCancelable, oDetail);

	//
	this.namespaceURI	= sNameSpaceURI;
};
var cElementXBL	= function() {
	// Disallow object instantiation
	throw 9;
};

// Public Properties
cElementXBL.prototype.xblImplementations	= null;

// Public Methods
cElementXBL.prototype.addBinding=function cElementXBL_prototype_addBinding(sDocumentUri) {
	// Validate input parameter
	if (typeof sDocumentUri != "string")
		throw 9;

	//
	sDocumentUri	= fResolveUri(sDocumentUri, document.location.href);

	// 0) Get Binding (synchronously)
	var cBinding	= cXBLLanguage.getBinding(sDocumentUri);
	if (!cBinding)
		return;

	var oBinding	= new cBinding;

	// 3) Attach implementation
	for (var sMember in oBinding)
		if (sMember.indexOf("xbl") != 0)
			this[sMember]	= oBinding[sMember];

	oBinding.$unique	= "xbl" + '-' + window.Math.floor(window.Math.random()*100000000);

	// 1) Create shadowTree
	if (cBinding.$template) {
		var oShadowContent	= fCreateTemplate(cBinding),
			aShadowAnchors	= oShadowContent.getElementsByTagName("strike"),	// live collection
			nShadowAnchor	= 0,
			oShadowAnchor,
			aElements,	// not live collection
			nElement,
			oElement,
			sIncludes;

		// Process content@includes
		while ((oShadowAnchor = aShadowAnchors[nShadowAnchor]) && (nShadowAnchor < aShadowAnchors.length)) {
			if (sIncludes = oShadowAnchor.getAttribute("includes")) {
				aElements = fCSSSelectorQuery([this], '>' + sIncludes);
				for (nElement = 0; oElement = aElements[nElement]; nElement++) {
					if (!oElement.xblChild) {
						oElement.xblChild	= true;
						oShadowAnchor.parentNode.insertBefore(oElement, oShadowAnchor);
					}
				}
				// Remove anchor
				oShadowAnchor.parentNode.removeChild(oShadowAnchor);
			} else {
				nShadowAnchor++;
			}
		}

		// Process content (with no @includes)
		if (oShadowAnchor = aShadowAnchors[0]) {
			for (nElement = 0; oElement = this.childNodes[nElement]; nElement++) {
				if (!oElement.xblChild) {
					oElement.xblChild	= true;
					oShadowAnchor.parentNode.insertBefore(oElement, oShadowAnchor);
					nElement--;
				}
			}
			// Remove anchor
			oShadowAnchor.parentNode.removeChild(oShadowAnchor);
		}

	// Removed, looks to be not necessary
		// Make sure shadow content documentElement has a unique ID set
		//		oShadowContent.setAttribute("id", oBinding.$unique);// + (oShadowContent.getAttribute("id") || ''));

		// Append shadow content
		while (oChild = oShadowContent.firstChild)
			this.appendChild(oShadowContent.firstChild);

		// Create shadowTree
		oBinding.shadowTree	= this;

		// Add "getElementById" member
		oBinding.shadowTree.getElementById	= fTemplateElement_getElementById;
	} else {
		// Mark children for proper target/phasing resolution
		for (var oChild = this.firstChild; oChild; oChild = oChild.nextSibling)
			if (oChild.nodeType == 1)
				oChild.xblChild	= true;

		oBinding.shadowTree	= null;
	}

	// Set boundElement
	oBinding.boundElement	= this;
	oBinding.baseBinding	= cBinding.baseBinding ? cBinding.baseBinding.prototype : null;

	// Add :bound-element pseudo-class
	this.className+=(this.className ? ' ' : '') + "xbl-bound-element" + '-' + cBinding.$id;

	// 2) Register events routers for handlers in use
	oBinding.$handlers	= {};
	if (cBinding.$handlers)
		for (var sName in cBinding.$handlers)
			fRegisterEventRouter(oBinding, sName);

	// 3) Add to the list of bindings
	if (!this.xblImplementations)
		this.xblImplementations	= new cXBLImplementationsList;
	this.xblImplementations[this.xblImplementations.length++]	= oBinding;

	// 4) Execute callback function
	if (typeof oBinding.xblBindingAttached == "function")
		oBinding.xblBindingAttached();
	if (typeof oBinding.xblEnteredDocument == "function")
		oBinding.xblEnteredDocument();
};

cElementXBL.prototype.removeBinding=function cElementXBL_prototype_removeBinding(sDocumentUri) {
	// Validate input parameter
	if (typeof sDocumentUri != "string")
		throw 9;

	if (!this.xblImplementations)
		return;

	//
	sDocumentUri	= fResolveUri(sDocumentUri, document.location.href);

	// 0) Get Binding
	for (var nIndex = 0, oBinding; oBinding = this.xblImplementations[nIndex]; nIndex++)
		if (oBinding.constructor.$documentURI + '#' + oBinding.constructor.$id == sDocumentUri)
			break;

	if (!oBinding)
		return;

	// 1) Detach handlers
	if (oBinding.$handlers)
		for (var sName in oBinding.$handlers)
			fUnRegisterEventRouter(oBinding, sName);

	// 2) Destroy shadowTree
	if (oBinding.shadowTree) {
		// TODO: Restore old DOM structure

		// Destroy circular reference
		delete oBinding.shadowTree;
	}

	// Unset boundElement
	delete oBinding.boundElement;
	delete oBinding.baseBinding;

	// 3) Remove binding from list
	for (; this.xblImplementations[nIndex]; nIndex++)
		this.xblImplementations[nIndex]	= this.xblImplementations[nIndex + 1];
	delete this.xblImplementations[nIndex];
	this.xblImplementations.length--;

	// 4) Execute callback function
	if (typeof oBinding.xblLeftDocument == "function")
		oBinding.xblLeftDocument();
};

cElementXBL.prototype.hasBinding=function cElementXBL_prototype_hasBinding(sDocumentUri) {
	// Validate input parameter
	if (typeof sDocumentUri != "string")
		throw 9;

	if (this.xblImplementations) {
		//
		sDocumentUri	= fResolveUri(sDocumentUri, document.location.href);

		// Walk through the array
		for (var nIndex = 0, oBinding; oBinding = this.xblImplementations[nIndex]; nIndex++)
			if (oBinding.constructor.$documentURI + '#' + oBinding.constructor.$id == sDocumentUri)
				return true;
	}
	return false;
};

// Functions

function fCreateTemplate(cBinding) {
	var oShadowContent,
		aShadowAnchors,
		oShadowAnchor,
		oElement;

	// Create template
	oShadowContent = cBinding.$template.cloneNode(true);

	//
	var aInheritedAnchors	= oShadowContent.getElementsByTagName("menu"),
		oInheritedAnchor,
		oInheritedContent;

	// if there are any "inherited"
	if (aInheritedAnchors.length) {
		oInheritedAnchor	= aInheritedAnchors[0];

		// Check if in the base binding there is a template
		if (cBinding.baseBinding && cBinding.baseBinding.$template) {

			// Create inherited content
			oInheritedContent	= fCreateTemplate(cBinding.baseBinding);

			// if there are "content" tags in the inherited content
			aShadowAnchors	= oInheritedContent.getElementsByTagName("strike");
			if (aShadowAnchors.length && oInheritedAnchor.firstChild) {
				// Move "inherited" tag children to the "content" of inherited template
				while (oElement = oInheritedAnchor.firstChild)
					aShadowAnchors[0].parentNode.appendChild(oInheritedAnchor.firstChild);

				// Remove old "content" anchor
				aShadowAnchors[0].parentNode.removeChild(aShadowAnchors[0]);
			}

			// Replace "inherited" tag with the inherited content
//			oInheritedAnchor.parentNode.replaceChild(oInheritedContent, oInheritedAnchor);
			while (oElement = oInheritedContent.firstChild)
				oInheritedAnchor.parentNode.insertBefore(oElement, oInheritedAnchor);
		}
		else {
//			while (oElement = oInheritedAnchor.firstChild)
//				oInheritedAnchor.parentNode.insertBefore(oInheritedAnchor.childNodes[oInheritedAnchor.childNodes.length-1], oInheritedAnchor);
		}
		//
		oInheritedAnchor.parentNode.removeChild(oInheritedAnchor);
	}

	return oShadowContent;
};

function fTemplateElement_getElementById(sId) {
	if (!this.$cache)
		this.$cache	= {};
	return this.$cache[sId] || (this.$cache[sId] = (function (oNode) {
		for (var oElement = null; oNode; oNode = oNode.nextSibling) {
			// Only go over shadow children, prevent jumping out by checking xblChild property
			if (oNode.nodeType == 1 && !oNode.xblChild) {
				if (oNode.getAttribute("xbl" + '-' + "id") == sId)
					return oNode;
				if (oNode.firstChild &&(oElement = arguments.callee(oNode.firstChild)))
					return oElement;
			}
		}
		return oElement;
	})(this.firstChild));
};
var cDocumentXBL	= function() {
	// Disallow object instantiation
	throw 9;
};

// Public Properties
cDocumentXBL.prototype.bindingDocuments		= null;

// Public Methods
cDocumentXBL.prototype.loadBindingDocument=function cDocumentXBL_prototype_loadBindingDocument(sDocumentUri) {
	// Validate input parameter
	if (typeof sDocumentUri != "string")
		throw 9;

	//
	sDocumentUri	= fResolveUri(sDocumentUri, document.location.href);
//alert('sDocumentUri = '+sDocumentUri);
	if (!(sDocumentUri in this.bindingDocuments)) {
		var oDOMDocument	= cXBLLanguage.fetch(sDocumentUri).responseXML;
		if (oDOMDocument != null && oDOMDocument.documentElement && oDOMDocument.documentElement.tagName == "parsererror")
			oDOMDocument	= null;

		// Save document in cache
		this.bindingDocuments[sDocumentUri]	= oDOMDocument;

		// Process entire document
		// TODO: enable delayed processing
		if (oDOMDocument)
			cXBLLanguage.process(oDOMDocument, sDocumentUri);
//->Debug
		else
			cXBLLanguage.onerror("Binding document '" + sDocumentUri + "' is mall formed");
//<-Debug
	}
	return this.bindingDocuments[sDocumentUri];
};

// Functions
// strike == content
// menu == inherited


function fDocumentXBL_addBindings(oNode) {
	var aElements,	nElement,
		aBindings,	nBinding,
		sRule;
//debugger
//alert(' fDocumentXBL_addBindings \n'+oNode.innerHTML);
	//
//debugger	
	for (sRule in cXBLLanguage.rules)
		for (nElement = 0, aBindings = cXBLLanguage.rules[sRule], aElements = fCSSSelectorQuery([document], sRule); nElement < aElements.length; nElement++)
			for (nBinding = 0; nBinding < aBindings.length; nBinding++)
				cElementXBL.prototype.addBinding.call(aElements[nElement], aBindings[nBinding]);
};

function fDocumentXBL_removeBindings(oNode) {
	for (var oBinding, cBinding; oNode; oNode = oNode.nextSibling) {
		if (oNode.nodeType == 1) {
			// If it is we who defined property
			if (oNode.xblImplementations instanceof cXBLImplementationsList) {
// TODO: Proper shadow content restoration
//				while (oNode.xblImplementations.length) {
//					cBinding	= oNode.xblImplementations[oNode.xblImplementations.length-1].constructor;
//					cElementXBL.prototype.removeBinding.call(oNode, cBinding.$documentURI + '#' + cBinding.$id);
//				}

				while (oBinding = oNode.xblImplementations[--oNode.xblImplementations.length]) {
					cBinding	= oBinding.constructor;

					// Detach handlers
					if (oBinding.$handlers)
						for (var sName in oBinding.$handlers)
							fUnRegisterEventRouter(oBinding, sName);

					//
					delete oBinding.baseBinding;
					delete oBinding.boundElement;
					delete oBinding.shadowTree;

					// Delete binding
					oNode.xblImplementations[oNode.xblImplementations.length]	= null;
				}

				//
				oNode.xblImplementations	= null;
			}

			// Go deeper
			if (oNode.firstChild)
				fDocumentXBL_removeBindings(oNode.firstChild);
		}
	}
};
/*
 * This module contains CSS-Selectors implementation
 */

/*
 * CSSSelector implementation
 */
var rCSSSelectorEscape		= /([\/()[\]?{}|*+-])/g,
	rCSSSelectorQuotes		= /^('[^']*')|("[^"]*")$/;

var rCSSSelectorGroup			= /\s*,\s*/,
	rCSSSelectorCombinator 		= /^[^\s>+~]/,
	rCSSSelectorSelector		= /::|[\s#.:>+~()@\[\]]|[^\s#.:>+~()@\[\]]+/g,
	rCSSSelectorWhiteSpace		= /\s*([\s>+~(,]|^|$)\s*/g,
	rCSSSelectorImplyAttribute	= /(\[[^\]]+\])/g,
	rCSSSelectorImplyElement	= /([\s>+~,]|[^(]\+|^)([#.:@])/g;

var nCSSSelectorIterator	= 0;

function fCSSSelectorQuery(aFrom, sSelectors, fNSResolver, bAll) {
	var aBase		= aFrom,
		aElements	= [],
		nSelector	= 0,
		nIteration	= 0,
		aSelectors	= sSelectors
			// trim whitespace
			.replace(rCSSSelectorWhiteSpace, '$1')
			// e.g "[a~=asd] --> @[a~=asd]
			.replace(rCSSSelectorImplyAttribute, '@$1')
			// e.g. ".class1" --> "*.class1"
			.replace(rCSSSelectorImplyElement, '$1*$2')
			// split by comma
			.split(rCSSSelectorGroup),
		aSelector,
		sSelector;

	for (; nSelector < aSelectors.length; nSelector++) {
		sSelector	= aSelectors[nSelector];
		if (rCSSSelectorCombinator.test(sSelector))
			sSelector = ' ' + sSelector;
		aSelector	= sSelector.match(rCSSSelectorSelector) || [];
		aFrom = aBase;

		var nIndex = 0, sToken, sFilter, sArguments, fSelector, aReturn,
			bBracketRounded, bBracketSquare;
		while (nIndex < aSelector.length) {
			sToken	= aSelector[nIndex++];
			sFilter	= aSelector[nIndex++];
			sArguments	= '';
			bBracketRounded	= aSelector[nIndex] == '(';
			bBracketSquare	= aSelector[nIndex-1] == '[';
			if (bBracketRounded || bBracketSquare) {
				if (bBracketSquare)
					nIndex--;
				while (aSelector[nIndex++] != (bBracketRounded ? ')' : ']') && nIndex < aSelector.length)
					sArguments += aSelector[nIndex];
				sArguments = sArguments.slice(0, -1);
			}

			aReturn		= [];
			if (fSelector = oCSSSelectorElementSelectors[sToken])
				fSelector(aReturn, aFrom, sFilter, sArguments, fNSResolver);
//->Debug
			else
				cXBLLanguage.onerror("Unknown element selector '" + sToken + "' in query '" + sSelector + "'");
//<-Debug
			aFrom	= aReturn;
		}
		// Filter out duplicate elements
		for (nIndex = 0; nIndex < aReturn.length; nIndex++) {
			if (aReturn[nIndex]._nCSSSelectorIterator != nCSSSelectorIterator) {
				aReturn[nIndex]._nCSSSelectorIterator	= nCSSSelectorIterator;
				aElements.push(aReturn[nIndex]);
			}
		}
//		aElements	= aElements.concat(aReturn);
	}

	nCSSSelectorIterator++;

	return aElements;
};

// String utilities
function fCSSSelectorEscape(sValue) {
	return sValue.replace(rCSSSelectorEscape, '\\$1');
};

function fCSSSelectorUnquote(sString) {
	return rCSSSelectorQuotes.test(sString) ? sString.slice(1, -1) : sString;
};

// DOM Utilities
function fCSSSelectorGetPreviousSibling(oElement) {
	while (oElement = oElement.previousSibling)
		if (oElement.nodeType == 1)
			return oElement;

	return null;
};

function fCSSSelectorGetNextSibling(oElement) {
	while (oElement = oElement.nextSibling)
		if (oElement.nodeType == 1)
			return oElement;
	return null;
};

function fCSSSelectorIfElementNS(oElement, sQName, fNSResolver) {
	return sQName == '*' || oElement.tagName.toLowerCase() == sQName.replace('|', ':').toLowerCase();
};

var fCSSSelectorGetAttributeNS	= document.namespaces ?
	function(oElement, sQName, fNSResolver) {
		return sQName == "class" ? oElement.className : sQName == "style" ? oElement.style.cssText : oElement[sQName];
	} :
	function(oElement, sQName, fNSResolver) {
		return oElement.getAttribute(sQName);
	}
;

// Selectors
var oCSSSelectorElementSelectors	= {},
	oCSSSelectorAttributeSelectors	= {},
	oCSSSelectorPseudoSelectors		= {};
/*
 * CSS 1.0 Selectors Implementation
 */
oCSSSelectorElementSelectors[' ']	= function(aReturn, aElements, sTagName, sArguments, fNSResolver) {
	// loop through current selection
//	var aQName		= sTagName.split('|'),
//		sLocalName	= aQName.length > 1 ? aQName[1] : aQName[0],
//		sPrefix		= aQName.length > 1 ? aQName[0] : null;
	sTagName	= sTagName.replace('|', ':');

	for (var i = 0, j, oElement, aSubset; i < aElements.length; i++)
		for (j = 0, aSubset	= (sTagName == '*' && aElements[i].all ? aElements[i].all : aElements[i].getElementsByTagName(sTagName)); oElement = aSubset[j]; j++)
			aReturn.push(oElement);
};

oCSSSelectorElementSelectors['#']	= function(aReturn, aElements, sId) {
	var oElement	= document.getElementById(sId);
	if (oElement)
		for (var nIndex = 0; nIndex < aElements.length; nIndex++)
			if (aElements[nIndex] == oElement) {
   				aReturn.push(oElement);
   				break;
			}
};

oCSSSelectorElementSelectors['.']	= function(aReturn, aElements, sName) {
	// create a RegExp version of the class
	var oCache	= arguments.callee.$cache || (arguments.callee.$cache = {}),
		rRegExp	= oCache[sName] || (oCache[sName] = window.RegExp('(^|\\s)' + sName + '(\\s|$)'));
	// loop through current selection and check class
	for (var i = 0, oElement, sValue; oElement = aElements[i]; i++)
		if ((sValue = oElement.className) && rRegExp.test(sValue))
			aReturn.push(oElement);
};

oCSSSelectorElementSelectors[':']	= function(aReturn, aElements, sPseudoClass, sArguments) {
	// loop through current selection and apply pseudo-class selector
	var fSelector	= oCSSSelectorPseudoSelectors[sPseudoClass];
	if (fSelector) {
		for (var i = 0, oElement; oElement = aElements[i]; i++)
			if (fSelector(oElement, sArguments))
				aReturn.push(oElement);
	}
//->Debug
	else
		cXBLLanguage.onerror("Unknown pseudo-class selector '" + sPseudoClass + "'");
//<-Debug
};

var rCSSSelectorAttribute	= /^([\w-]+\|?[\w-]+)\s*(\W?=)?\s*([^\]]*)$/;
oCSSSelectorElementSelectors['@']	= function(aReturn, aElements, sString, sArguments, fNSResolver) {
	var aMatch;
	if (aMatch = sArguments.match(rCSSSelectorAttribute)) {
		var sAttribute	= aMatch[1].replace('|', ':'),
//			aQName		= sAttribute.split('|'),
//			sLocalName	= aQName.length > 1 ? aQName[1] : aQName[0],
//			sPrefix		= aQName.length > 1 ? aQName[0] : null,
			sSelector	= aMatch[2] || '',
			sCompare	= fCSSSelectorUnquote(aMatch[3]) || '',
			fSelector	= oCSSSelectorAttributeSelectors[sSelector];

		if (fSelector) {
			for (var i = 0, sValue, oElement; oElement = aElements[i]; i++)
				if ((sValue = fCSSSelectorGetAttributeNS(oElement, sAttribute, fNSResolver)) && fSelector(sValue, sCompare))
					aReturn.push(oElement);
		}
//->Debug
		else
			cXBLLanguage.onerror("Unknown attribute selector '" + sSelector + "'");
//<-Debug
	}
};

oCSSSelectorAttributeSelectors['']	= function(sValue, sCompare) {
	return true;
};

/*
 * CSS 2.1 Selectors Implementation
 */
// Element
oCSSSelectorElementSelectors['+']	= function(aReturn, aElements, sTagName, sArguments, fNSResolver) {
	for (var i = 0, oElement; i < aElements.length; i++)
	   	if ((oElement = fCSSSelectorGetNextSibling(aElements[i])) && fCSSSelectorIfElementNS(oElement, sTagName, fNSResolver))
			aReturn.push(oElement);
};

oCSSSelectorElementSelectors['>']	= function(aReturn, aElements, sTagName, sArguments, fNSResolver) {
	for (var i = 0, j, oElement, aSubset; i < aElements.length; i++)
		for (j = 0, aSubset = aElements[i].childNodes; oElement = aSubset[j]; j++)
			if (oElement.nodeType == 1 && fCSSSelectorIfElementNS(oElement, sTagName, fNSResolver))
				aReturn.push(oElement);
};

// Attribute
oCSSSelectorAttributeSelectors['=']	= function(sValue, sCompare) {
	return sValue == sCompare;
};

oCSSSelectorAttributeSelectors['~=']	= function(sValue, sCompare) {
	var oCache	= arguments.callee.$cache || (arguments.callee.$cache = {}),
		rRegExp	= oCache[sCompare] || (oCache[sCompare] = window.RegExp('(^| )' + fCSSSelectorEscape(sCompare) + '( |$)'));
	return rRegExp.test(sValue);
};

oCSSSelectorAttributeSelectors['|=']	= function(sValue, sCompare) {
	var oCache	= arguments.callee.$cache || (arguments.callee.$cache = {}),
		rRegExp	= oCache[sCompare] || (oCache[sCompare] = window.RegExp('^' + fCSSSelectorEscape(sCompare) + '(-|$)'));
	return rRegExp.test(sValue);
};

// Pseudo-class
oCSSSelectorPseudoSelectors["first-child"]	= function(oElement) {
	return fCSSSelectorGetPreviousSibling(oElement) == null;
};

oCSSSelectorPseudoSelectors["link"]	= function(oElement) {
	return oElement.tagName.toLowerCase() == 'a' && oElement.getAttribute("href");
};

oCSSSelectorPseudoSelectors["visited"]	= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["lang"]	= function(oElement, sArgument) {
	for (var sValue; oElement.nodeType != 9; oElement = oElement.parentNode)
		if (sValue =(oElement.getAttribute("lang") || oElement.getAttribute("xml" + ':' + "lang")))
			return window.RegExp('^' + sArgument, 'i').test(sValue);
	return false;
};

// Dynamic pseudo-classes (not supported)
oCSSSelectorPseudoSelectors["active"]	= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["hover"]	= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["focus"]	= function(oElement) {
	return false;
};
/*
 * CSS 3 Selectors Implementation
 */
// Element
oCSSSelectorElementSelectors['~']	= function(aReturn, aElements, sTagName, sArguments, fNSResolver) {

};

// Attribute
oCSSSelectorAttributeSelectors['^=']	= function(sValue, sCompare) {

};

oCSSSelectorAttributeSelectors['$=']	= function(sValue, sCompare) {

};

oCSSSelectorAttributeSelectors['*=']	= function(sValue, sCompare) {

};

// Pseudo-class
oCSSSelectorPseudoSelectors["contains"]	= function(oElement, sParam) {

};

oCSSSelectorPseudoSelectors["root"]		= function(oElement) {
	return oElement == oElement.ownerDocument.documentElement;
};

oCSSSelectorPseudoSelectors["empty"]		= function(oElement) {

};

oCSSSelectorPseudoSelectors["last-child"]	= function(oElement) {

};

oCSSSelectorPseudoSelectors["only-child"]	= function(oElement) {

};

oCSSSelectorPseudoSelectors["not"]		= function(oElement) {

};

oCSSSelectorPseudoSelectors["nth-child"]		= function(oElement, sParam) {

};

oCSSSelectorPseudoSelectors["nth-last-child"]	= function(oElement, sParam) {

};

oCSSSelectorPseudoSelectors["target"]		= function(oElement) {

};

// Dynamic pseudo-classes (not supported)
oCSSSelectorPseudoSelectors["checked"]		= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["enabled"]		= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["disabled"]		= function(oElement) {
	return false;
};

oCSSSelectorPseudoSelectors["indeterminate"]= function(oElement) {
	return false;
};

// Pseudo-elements
oCSSSelectorElementSelectors['::']	= function(aReturn, aElements, sPseudoElement) {
	return false;
};
var cXBLImplementation	= new window.Function;

// Public Methods
cXBLImplementation.prototype.xblBindingAttached	= new window.Function;
cXBLImplementation.prototype.xblEnteredDocument	= new window.Function;
cXBLImplementation.prototype.xblLeftDocument	= new window.Function;
var cXBLImplementationsList	= new window.Function;

// Public Properties
cXBLImplementationsList.prototype.length	= 0;

// Public Methods
cXBLImplementationsList.prototype.item=function cXBLImplementationsList_prototype_item(nIndex) {
	if (typeof nIndex == "number" && nIndex <= this.length)
		return this[nIndex];
	else
		throw 1;	// INDEX_SIZE_ERR
};
/*
 * This module contains XBL driver
 */

/*
 * Utility Functions
 */
/*
function fAttachEvent(oNode, sName, fHandler) {
	if (oNode.attachEvent)
		oNode.attachEvent(sName, fHandler);
	else
		oNode.addEventListener(sName.substr(2), fHandler, false);
};
*/
/*
function fDetachEvent(oNode, sName, fHandler) {
	if (oNode.detachEvent)
		oNode.detachEvent(sName, fHandler);
	else
		oNode.removeEventListener(sName.substr(2), fHandler, false);
};
*/

// Private Methods
var rBindingRules	= /\s*([^\}]+)\s*\{[^}]*binding:([^{\n;]+)[^{]+}/g,
	rBindingUrls	= /url\s*\(['"\s]*([^'"]+)['"\s]*\)/g,
	rBindingComments= /(\/\*.*?\*\/)/g;

function fProcessCSS(sStyle) {
	var sRule,	aRules,	nRule,
		sUrls,	aUrls,	nUrl;
	// Cut off comments
	sStyle	= sStyle.replace(rBindingComments, '');

	// Go over the list of behaviors using rules
	if (aRules = sStyle.match(rBindingRules)) {
		for (nRule = 0; nRule < aRules.length; nRule++) {
			if (aRules[nRule].match(rBindingRules)) {
				sRule	= window.RegExp.$1;
				sUrls	= window.RegExp.$2;
				if (aUrls = sUrls.match(rBindingUrls)) {
					for (nUrl = 0; nUrl < aUrls.length; nUrl++) {
						if (aUrls[nUrl].match(rBindingUrls)) {
							if (!cXBLLanguage.rules[sRule])
								cXBLLanguage.rules[sRule]	= [];
							cXBLLanguage.rules[sRule].push(window.RegExp.$1);
						}
					}
				}
			}
		}
	}
};


function fOnWindowLoad(){};
/*
 * Document Handlers
 */
function fOnWindowLoad2() {
	// Process CSS declarations
	var aElements,
		oNode,
		nIndex;
	// Go over the list of inline style elements
	for (nIndex = 0, aElements = document.getElementsByTagName("style"); oNode = aElements[nIndex]; nIndex++)
		if (oNode.getAttribute("type") == "text/css")
			fProcessCSS(oNode.textContent || oNode.innerHTML);
	// Go over the list of link elements
	for (nIndex = 0, aElements = document.getElementsByTagName("link"); oNode = aElements[nIndex]; nIndex++) {
		if (oNode.getAttribute("type") == "text/css")
		{
//		    alert('before cXBLLanguage.fetch and process href= ' +oNode.getAttribute("href"));
			fProcessCSS(cXBLLanguage.fetch(oNode.getAttribute("href")).responseText);
//		    alert('after cXBLLanguage.fetch and process');
			
		}
		else
		if (oNode.getAttribute("type") == "application/xml" && oNode.getAttribute("rel") == "binding")
			cDocumentXBL.prototype.loadBindingDocument.call(document, oNode.getAttribute("href"));
	}

	// Process elements in the document
//->Source
//	var d = new Date;
//<-Source
//alert('before fDocumentXBL_addBindings');
//debugger
	fDocumentXBL_addBindings(document.body);
//->Source
//	document.title = (new Date - d) + ' ms.';
//<-Source

	// Dispatch xbl-bindings-are-ready to document
//	fDispatchEvent(document.documentElement, fCreateEvent("xbl-bindings-are-ready", true, false));
};
/*
function fOnWindowUnLoad() {
	// TODO: Any actions required
//	fDocumentXBL_removeBindings(document.body);

	// Clean handler
//	fDetachEvent(window,	"on" + "load",		fOnWindowLoad);
//	fDetachEvent(window,	"on" + "unload",	fOnWindowUnLoad);
};
*/

// Publish implementation, Hide implementation details
function fFunctionToString(sName) {
	return function () {return "function" + ' ' + sName + '()' + ' ' + '{\n\t[native code]\n}'};
};

function fObjectToString(sName) {
	return function () {return '[' + sName + ']'};
};

var oImplementation	= document.implementation;

// Check if implementation supports XBL 2.0 natively and if it does, return (IE 5.5 doesn't support document.implementation)
if (!oImplementation || !oImplementation.hasFeature("XBL", '2.0')) {
	// Register framework
//	fAttachEvent(window,	"on" + "load",		fOnWindowLoad);
	//fAttachEvent(window,	"on" + "unload",	fOnWindowUnLoad);

	if (document.createElement("div").addEventListener) {
		// Safari
		if (window.navigator.userAgent.match(/applewebkit/i))
			(function (){
				if (document.readyState == "loaded" || document.readyState == "complete")
					fOnWindowLoad();
				else
					window.setTimeout(arguments.callee, 20);
			})();
		// Gecko / Opera
		else
			window.addEventListener("DOMContentLoaded", fOnWindowLoad, false);
//		window.addEventListener("load", fOnWindowLoad, false);
	}
	else {
//		window.attachEvent("on" + "load", fOnWindowLoad);
		// Internet Explorer
		document.write('<' + "script" + ' ' + "id" + '="' + "xbl" + '_' + "implementation" + '" ' + "defer" + ' ' + "src" + '="/' + '/:"></' + "script" + '>');
		document.getElementById("xbl" + '_' + "implementation").onreadystatechange	= function() {
			if (this.readyState == "interactive" || this.readyState == "complete")
				fOnWindowLoad(this.parentNode.removeChild(this));
		}
	}

	// Publish XBL
	(window.ElementXBL	= cElementXBL).toString		= fObjectToString("ElementXBL");
	(window.DocumentXBL	= cDocumentXBL).toString	= fObjectToString("DocumentXBL");

	(document.bindingDocuments		= {}).toString	= function() {	return '[' + "object" + ' ' + "NamedNodeMap" + ']'};
	(document.loadBindingDocument	= cDocumentXBL.prototype.loadBindingDocument).toString	= fFunctionToString("loadBindingDocument");

	cElementXBL.prototype.addBinding.toString	= fFunctionToString("addBinding");
	cElementXBL.prototype.removeBinding.toString= fFunctionToString("removeBinding");
	cElementXBL.prototype.hasBinding.toString	= fFunctionToString("hasBinding");
};

if (!oImplementation || !oImplementation.hasFeature("Selectors", '3.0')) {
//	(window.ElementSelector		= new window.Function).toString	= fObjectToString("ElementSelector");
//	(window.DocumentSelector	= new window.Function).toString	= fObjectToString("DocumentSelector");

	// Publish Selectors API
	if (!document.querySelector)
		(document.querySelector		= function querySelector(sSelectors, fNSResolver) {
			// Validate input parameter
			if (typeof sSelectors != "string")
				throw 9;
			if (arguments.length > 1 && typeof fNSResolver != "function")
				throw 9;

			return fCSSSelectorQuery([this], sSelectors, fNSResolver)[0] || null;
		}).toString		= fFunctionToString("querySelector");

	if (!document.querySelectorAll)
		(document.querySelectorAll	= function querySelectorAll(sSelectors, fNSResolver) {
			// Validate input parameter
			if (typeof sSelectors != "string")
				throw 9;
			if (arguments.length > 1 && typeof fNSResolver != "function")
				throw 9;

			return fCSSSelectorQuery([this], sSelectors, fNSResolver, true);
		}).toString	= fFunctionToString("querySelectorAll");
};

