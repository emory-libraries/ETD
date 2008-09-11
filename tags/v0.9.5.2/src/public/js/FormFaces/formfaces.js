// --- js/userAgent.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


var userAgent = { };

userAgent.isOpera            = (navigator.userAgent.match(/\bOpera\b/));
userAgent.isInternetExplorer = (navigator.userAgent.match(/\bMSIE\b/) && !userAgent.isOpera);
userAgent.isMozilla          = (navigator.userAgent.match(/\bGecko\b/));
userAgent.isKHTML            = (navigator.userAgent.match(/\b(Konqueror|KHTML)\b/));

// --- js/inheritance.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


Function.prototype.inherits  = function(BaseClass) {
  this.prototype             = new BaseClass;
  this.prototype.constructor = this;
  this.base                  = BaseClass;
};

function instanceOf(object, Class) {
  if (object == null) {
    return false;
  }
  
  for (var constructor = object.constructor; constructor != null; constructor = constructor.base) {
    if (constructor == Class) {
      return true;
    }
  }
  
  return false;
};

// --- js/stackTrace.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function stackTrace(exception) {
  // Internet Explorer
  if (userAgent.isInternetExplorer) {
    var callstack = [];
    
    for (var caller = arguments.caller; caller != null; caller = caller.caller) {
      var name       = caller.name ? caller.name : "<anonymous>";
      var parameters = [];
      var len = caller.length;
      
      for (var i = 0; i < len; ++i) {
        parameters.push("?");
      }
      
      callstack.push(name + "(" + parameters.join(", ") + ")");
    }
  }
  // Mozilla
  else if (userAgent.isMozilla) {
    if (!exception || !exception.stack) {
      try {
        var x; x.y;
      }
      catch (e) {
        exception = e;
      }
    }
    
    if (!exception.stack) {
      return "(stack trace not available)\n";
    }
    
    var callstack  = exception.stack.split("\n");
    var commonPath = null;
    
    // callstack.shift(); // Get rid of the call to stackTrace().
    callstack.pop  (); // Remove the last entry, an empty string.
    
    // Break up the lines into method/file/line components, and figure out the
    // common directory all of the source files are in so that it can be removed
    // from the file names (to make the alert message easier to read).
    for (var i = 0; i < callstack.length; ++i) {
      /^(.*?)@(.*?):(\d+)$/.test(callstack[i]);
      
      var method = RegExp.$1;
      var file   = RegExp.$2;
      var line   = RegExp.$3;
      var path   = file.replace(/^(.*\/).*$/, "$1");
      
      callstack[i] = {method: method, file: file, line: line};
      
      if (file != "") {
        if (commonPath == null) {
          commonPath = path;
        }
        else {
          commonPath = commonPath.substr(0, path      .length);
          path       = path      .substr(0, commonPath.length);
          
          while (commonPath != path) {
            commonPath = commonPath.substr(0, commonPath.length - 1);
            path       = path      .substr(0, path      .length - 1);
          }
        }
      }
    }
    
    // Create a string for each function call.
    for (var i = 0; i < callstack.length; ++i) {
      var method = callstack[i].method;
      var file   = callstack[i].file;
      var line   = callstack[i].line;
      
      if (file == "" && method == "") {
        continue;
      }
      
      var call = "";
      
      if (file == "") {
        call += "<unknown>";
      }
      else {
        call += file.substr(commonPath.length) + "(" + line + ")";
      }
      
      if (method != "") {
        call += ": ";
        
        if (method.match(/^\(/)) {
          call += "<anonymous>";
        }
        
        call += method;
      }
        
      callstack[i] = call;
    }
  }
  else {
    var callstack = [];
  }
  
  var string = "";
  
  for (var i = 0; i < callstack.length; ++i) {
    string += "> " + callstack[i] + "\n";
  }

  
  return string;
};

// --- js/assert.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function assert(condition, message) {
  if (!condition) {
    throw new AssertionException(message);
  }
};


function AssertionException(message) {
  this.message   = message;
  this.callstack = stackTrace();
};

AssertionException.prototype.toString = function() {
  var message = (this.message ? "Assertion failed: " + this.message : "Assertion failed.");
  
  if (this.callstack != "") {
    message += "\n\nStack trace:\n" + this.callstack;
  }
  
  return message;
};

// --- js/monitor.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function monitor(code) {
  try {    
    code();
  }
  catch (exception) {
    var message   = exception.toString();
    var callstack = stackTrace(exception);
    
    if (message == "[object Error]") {
      message = "";

      for (var i = 0; i < exception.length; ++i) {
        message += i + ": " + exception[i] + "\n";
      }
    }
    
    message = "An error has occurred!\n\n" + message;
    
    if (callstack != "") {
      if (!message.match(/\n$/)) {
        message += "\n";
      }
      
      message += "\nStack trace:\n" + callstack;
    }
    
    alert(message);

//    throw exception;
  }
};

// --- js/methodCall.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function functionCall(method) {
  assert(method != null, "method is null");
  
  var parameters = [];
  var len = arguments.length;
  
  for (var i = 1; i < len; ++i) {
    parameters.push(arguments[i]);
  }
  
  return function() {
    if (method.apply) {
      return method.apply(null, parameters);
    }
    else {
      var jsString = "";
      var paramLen = parameters.length;
      
      for (var i = 0; i < paramLen; i++) {
        if (jsString != "") {
          jsString += ", ";
        }
        
        jsString += "parameters[" + i + "]";
      }
      
      jsString = "method(" + jsString + ")";
      
      return eval(jsString);
    }
  };
};

function methodCall(self, method) {
  assert(self   != null, "self is null");
  assert(method != null, "method is null");
  
  var parameters = [];
  var len = arguments.length;
  
  for (var i = 2; i < len; ++i) {
    parameters.push(arguments[i]);
  }
  
  return function() {
    return self[method].apply(self, parameters);
  };
};

// --- js/uniqueId.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function uniqueId() {
  return "ff-" + ++uniqueId.counter;
};

uniqueId.counter = 0;
        
        
function executeSequentially() {
  var functions = [];
  
  function execute() {
    if (functions.length == 0) {
      return;
    }
    
    functions.shift()();
    setTimeout(functionCall(monitor, execute), 1);
  };
  
  var args = arguments.length;
  for (var i = 0; i < args; ++i) {
    functions.push(arguments[i]);
  }
  
  monitor(execute);
};

// --- xml/exception.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XmlException(message, cause) {
  this.message = message;
  this.cause   = cause;
};

XmlException.prototype.toString = function() {
  var message = "Error: " + this.message;
  
  if (this.cause != null) {
    message += "\nCause: " + this.cause;
  }
  
  return message;
};

// --- xml/loadDocument.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XML document with the specified document element. If
// unspecified, an element called "root" with no namespace is created.
//
// Opera does not allow the creation of a document without a root element, so it
// is not possible to create a completely blank document with this function.
// Furthermore, in Opera you cannot change, rename, or remove the document
// element either.
//
// Parameters:
//     documentElement: The root element of the document. 
function xmlNewDocument(documentElement) {
  if (documentElement == null) {
    documentElement = document.createElement("root");
  }
  
  if (document.implementation && document.implementation.createDocument) {
    var xmlDocument = document.implementation.createDocument(documentElement.namespaceURI, documentElement.tagName, null);
  }
  else if (typeof(window.ActiveXObject) != "undefined") {
    var xmlDocument = xmlNewMSXMLDocument();
  }
  else {
    throw new XmlException("Incompatible web browser; cannot create new XML document.");
  }
  
  if (documentElement) {
    documentElement = xmlImportNode(xmlDocument, documentElement, true);
    
    if (xmlDocument.firstChild) {
      while (documentElement.hasChildNodes()) {
        xmlDocument.firstChild.appendChild(documentElement.firstChild);
      }
      
      var isHtml = (documentElement.tagName.toLowerCase() == "html");
      var atts   = documentElement.attributes;
      
      for (var i = atts.length - 1; i >= 0; --i) {
        var attribute = atts.item(i);

        if (attribute.specified && attribute.value) {
          xmlDocument.firstChild.setAttribute(attribute.name, attribute.value);
          
          if (isHtml && userAgent.isInternetExplorer) {
            // Many attributes don't take effect unless you set them via element.property
            // syntax. Also, some of these properties are named/capitalized differently.
            switch (attribute.name) {
              case "colspan":     xmlDocument.firstChild.colSpan       = attribute.value; break;
              case "rowspan":     xmlDocument.firstChild.rowSpan       = attribute.value; break;
              case "cellspacing": xmlDocument.firstChild.cellSpacing   = attribute.value; break;
              case "cellpadding": xmlDocument.firstChild.cellPadding   = attribute.value; break;
              case "style":       xmlDocument.firstChild.style.cssText = attribute.value; break;
              case "class":       xmlDocument.firstChild.className     = attribute.value; break;
              
              default:
                xmlDocument.firstChild[attribute.name] = attribute.value;
                break;
            }
          }
        }
      }
      
    }
    else {
      xmlDocument.appendChild(documentElement);
    }
  }
  
  return xmlDocument;
};

// Returns an MSXML ActiveX DOM document, trying different program IDs until it
// finds one that works.
function xmlNewMSXMLDocument() {
  var programIds = xmlNewMSXMLDocument.programIds;
  
  while (programIds.length > 0) {
    try {
      return new ActiveXObject(programIds[0]);
    }
    catch (e) {
      // Didn't work. Try the next program.
      programIds.shift();
    }
  }
  
  throw new XmlException("Unable to create new MSXML DOM document.");
};

xmlNewMSXMLDocument.programIds = [
  "Msxml2.DOMDocument.6.0",
  "Msxml2.DOMDocument.5.0",
  "Msxml2.DOMDocument.4.0",
  "Msxml2.DOMDocument.3.0",
  "MSXML2.DOMDocument",
  "MSXML.DOMDocument",
  "Microsoft.XMLDOM"
];

// Loads an XML document.
//
// Parameters:
//     source:             The source text for the XML document.
//     stripDTD:           If true, the DTD is stripped from the document and
//                         all entities are replaced by their substitution text.
//     processStylesheets: If true, <?xml-stylesheet?> directives are processed.
//
// Return value:
//     An XML DOM document if there are no errors.
//
// Exceptions:
//     Throws an exception if there is an error loading the document.
function xmlLoadDocument(source, stripDTD, processStylesheets) {
  if (stripDTD) {
    source = cleanXml(source);
  }
  
  var xmlDocument = null;
  
  // W3C
  if (document.implementation && document.implementation.createLSParser) {
    var parser = document.implementation.createLSParser(1, null);
    var input  = document.implementation.createLSInput ();
    
    // Opera will manhandle elements and attributes in the XHTML and XML Events
    // namespaces, so we have to change those namespaces to something else so Opera
    // doesn't recognize them. See namespaces.js for the other half of this kludge.
    if (userAgent.isOpera) {
      source = source.replace(/(xmlns(?::[^=]+)?=["']http:\/\/www.w3.org\/1999\/xhtml)(["'])/,      "$1-in-opera$2");
      source = source.replace(/(xmlns(?::[^=]+)?=["']http:\/\/www.w3.org\/2001\/xml-events)(["'])/, "$1-in-opera$2");
    }
    
    input.stringData = source;
    
    xmlDocument = parser.parse(input);
  }
  // Mozilla
  else if (window.DOMParser) {
    xmlDocument = new DOMParser().parseFromString(source, "text/xml");
    
     if (xmlDocument.documentElement.namespaceURI == "http://www.mozilla.org/newlayout/xml/parsererror.xml") {
      throw new XmlException(new XPath("string(/*/text())").evaluate(xmlDocument));
    }
  }
  // Internet Explorer
  else if (window.ActiveXObject) {
    xmlDocument = xmlNewMSXMLDocument();
    
    xmlDocument.preserveWhiteSpace = true;
    xmlDocument.loadXML(source);

    if (xmlDocument.parseError.errorCode != 0) {
      throw new XmlException(
        xmlDocument.parseError.reason.replace(/^\s+|\s+$/g, "") + "\n\n" +
        "Line: "   + xmlDocument.parseError.line + "\n" +
        "Source: " + xmlDocument.parseError.srcText.replace(/^\s+|\s+$/g, ""),
        
        xmlDocument.parseError
      );
    }
  }
  else {
    throw new XmlException("Incompatible web browser; cannot load XML document.");
  }
  
  if (processStylesheets) {
    var stylesheets = new XPath("/processing-instruction('xml-stylesheet')").evaluate(xmlDocument);
    
    for (var i = 0; i < stylesheets.length; ++i) {
      var xslUrl      = stylesheets[i].data.replace(/^.*\bhref\s*=\s*"([^"]*)".*$/, "$1");
      var xslDocument = xmlLoadURI(xslUrl);
      
      // XSL stylesheets might use the "html" method, but we need to get a strict XML
      // document, so we'll stealthily change this setting.
      for (var child = xslDocument.documentElement.firstChild; child != null; child = child.nextSibling) {
        if (child.nodeName.replace(/^.*:/, "") == "output" && child.namespaceURI == XmlNamespaces.XSL) {
          child.setAttribute("method", "xml");
        }
      }
      
      // Apply the stylesheet.
      if (userAgent.isMozilla) {
        var xsltProcessor = new XSLTProcessor();
            xsltProcessor.importStylesheet(xslDocument);
        
        xmlDocument = xsltProcessor.transformToDocument(xmlDocument);
      }
      else if (userAgent.isInternetExplorer) {
        xmlDocument = xmlLoadDocument(xmlDocument.transformNode(xslDocument));
      }
      else {
        assert(false, "Cannot load page: XSL stylesheets are not supported in this browser.");
      }
    }
  }
  
  return xmlDocument;
  

  // Strips the DTD from the XML source and replaces any entities with their
  // substitution text.
  function cleanXml(source) {
    // Remove any <!DOCTYPE> declarations. Internet Explorer, correctly but
    // unfortunately, validates documents against their DTDs.
    source = source.replace(/<!DOCTYPE[^>]*>/, "");
  
    // Replace entities with their replacement characters since we just removed the
    // <!DOCTYPE>.
    var index = -1;
  
    while (true) {
      var entityIndex = source.indexOf("&",         index + 1);
      var cdataIndex  = source.indexOf("<![CDATA[", index + 1);
      
      if (entityIndex < 0) {
        break;
      }
  
      // If the next ampersand comes before the next CDATA section, it is an entity.
      if (cdataIndex < 0 || entityIndex < cdataIndex) {
        // Returns the replacement character for an entity.
        function entityReplacement(entity) {
          var span           = document.createElement("span");
              span.innerHTML = entity;
              
          return (span.firstChild != null) ? span.firstChild.data : "";
        }
        
        // Replace the entity with the equivalent character.
        var entity = source.substring(entityIndex, source.indexOf(";", entityIndex) + 1);
        
        switch (entity) {
          // Don't substitute the built-in entities; it can cause well-formedness errors.
          case "&amp;":
          case "&lt;":
          case "&gt;":
          case "&quot;": 
          case "&apos;":
            break;
            
          default:
            source = source.replace(entity, entityReplacement(entity));
            break;
        }
        
        index = entityIndex;
      }
      // otherwise, we need to first skip over the CDATA section.
      else {
        index = source.indexOf("]]>", cdataIndex);
        
        if (index < 0) {
          break;
        }
      }
    }
    
    return source;
  };
};

// Loads the document at the requested URI.
//
// Return value:
//     A XML document containing the DOM tree for the document.
//
// Exceptions:
//     Throws an XmlException if there is an error fetching the URI, or if there
//     is an error parsing the XML document.
function xmlLoadURI(uri, processStylesheets) {
  var request = userAgent.isInternetExplorer ? new ActiveXObject("Microsoft.XMLHTTP") : new XMLHttpRequest(); 
  
  try {
    request.open("GET", uri, false);
    request.send(null);
  }
  catch (exception) {
    throw new XmlException("Error loading " + uri + ".", exception);
  }
  
  if (request.status != 200 && request.status != 0) {
    throw new XmlException("Error " + request.status + " loading " + uri + ": " + request.statusText);
  }
  
  return xmlLoadDocument(request.responseText, true, processStylesheets);
};

// --- xml/importNode.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Implements the Document.importNode DOM method. If the browser implements
// importNode natively, then that implementation is used.
//
// Parameters:
//     xmlDocument: The document to which to import the node.
//     node:        The node to import.
//     deep:        True to import all of the node's children, false to only
//                  import the node and its attributes.
function xmlImportNode(xmlDocument, node, deep) {
  if (typeof(xmlDocument.importNode) != "undefined" && !userAgent.isInternetExplorer) {
    // If using Opera and importing a node to the HTML document, we need to fix the
    // fake "-in-opera" namespace URI used. See loadDocument.js for more information
    // about this.
    if (!(xmlDocument == document && userAgent.isOpera)) {
      return xmlDocument.importNode(node, deep);
    }
  }

  var isHtml  = (xmlDocument.documentElement && xmlDocument.documentElement.tagName.toLowerCase() == "html");
  var newNode = null;

  switch (node.nodeType) {
    case 1: // Element
      if (xmlDocument.createElementNS) {
        newNode = xmlDocument.createElementNS(node.namespaceURI, node.nodeName);
      }
      else {
        newNode = xmlDocument.createElement(node.nodeName);
      }

      var atts   = node.attributes; 
      var attLen = atts.length;
      for (var i = 0; i < attLen; ++i) {
        var attribute = atts.item(i);

        if (attribute.specified && attribute.value) {
          newNode.setAttribute(attribute.name, attribute.value);
          
          if (isHtml && userAgent.isInternetExplorer) {
            // Many attributes don't take effect unless you set them via element.property
            // syntax. Also, some of these properties are named/capitalized differently.
            switch (attribute.name) {
              case "colspan":     newNode.colSpan       = attribute.value; break;
              case "rowspan":     newNode.rowSpan       = attribute.value; break;
              case "cellspacing": newNode.cellSpacing   = attribute.value; break;
              case "cellpadding": newNode.cellPadding   = attribute.value; break;
              case "style":       newNode.style.cssText = attribute.value; break;
              case "class":       newNode.className     = attribute.value; break;
              
              default:
                newNode[attribute.name] = attribute.value;
                break;
            }
          }
        }
      }

      break;

    case 2: // Attribute
      if (xmlDocument.createAttributeNS) {
        newNode = xmlDocument.createAttributeNS(node.namespaceURI, node.name);
      }
      else {
        newNode = xmlDocument.createAttribute(node.name);
      }
    
      newNode.value = node.value;
      break;

    case 3: // Text
      newNode = xmlDocument.createTextNode(node.data.replace(/\s+/g, " "));
      break;

    case 4: // CDATA section
      newNode = xmlDocument.createCDATASection(node.data);
      break;

    case 5: // Entity reference
      newNode = xmlDocument.createEntityReference(node.nodeName);
      break;

    case 7: // Processing instruction
      newNode = xmlDocument.createProcessingInstruction(node.target, node.data);
      break;

    case 8: // Comment
      newNode = xmlDocument.createComment(node.data);
      break;

    case 11: // Document fragment
      newNode = xmlDocument.createDocumentFragment();
      break;

    default:
      throw new XmlException("Cannot import node: " + node.nodeName);
  }

  if (deep) {
    for (var child = node.firstChild; child != null; child = child.nextSibling) {
      newNode.appendChild(xmlImportNode(xmlDocument, child, true));
    }
  }
  
  return newNode;
};

function isTextNode(node) {
  return node.nodeType == 3 || node.nodeType == 4;
};

// --- xml/namespaces.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Contains string constants for the most common XML namespace URIs.
var XmlNamespaces = {
  SCHEMA: "http://www.w3.org/1999/XMLSchema",
  XFORMS: "http://www.w3.org/2002/xforms",
  XHTML:  "http://www.w3.org/1999/xhtml",
  XML:    "http://www.w3.org/XML/1998/namespace",
  XSL:    "http://www.w3.org/1999/XSL/Transform",
  EVENTS: "http://www.w3.org/2001/xml-events"
};

// Opera will manhandle elements and attributes in the XHTML and XML Events
// namespaces, so we have to change those namespaces to something else so Opera
// doesn't recognize them. See namespaces.js for the other half of this kludge.
if (userAgent.isOpera) {
  XmlNamespaces.XHTML  += "-in-opera";
  XmlNamespaces.EVENTS += "-in-opera";
}


// Returns the namespace URI for a node, or null if it has none.
//
// Internet Explorer does not import namespace URIs, so we need to resolve them
// manually.
function xmlNamespaceURI(node) {
  assert(node != null, "xmlNamespaceURI: node is null");
  
  if (!userAgent.isInternetExplorer) {
    return node.namespaceURI == null ? "" : node.namespaceURI;
  }
  
  // If the namespace URI is set, then IE obviously resolved the namespace
  // properly.
  if (node.namespaceURI != "") {
    return node.namespaceURI;
  }
  
  // Otherwise, we can't be sure, so we'll have to lookup the namespace URI
  // ourselves.
  var namespaceNodeName = "xmlns";
  
  if (node.nodeName.match(/(.*):/)) {
    namespaceNodeName += ":" + RegExp.$1;
  }
  
  if (node.nodeType == 2) {
    // Attributes with no prefix have no namespace.
    if (!node.nodeName.match(/:/)) {
      return "";
    }
    
    node = XPathAxis.PARENT.filterNode(node);
    node = (node.length == 0) ? null : node[0];
  }
  
  if (node.nodeType != 1) {
    return "";
  }
  
  for (; node != null && node.nodeType == 1; node = node.parentNode) {
    var atts   = node.attributes;
    for (var i = atts.length - 1; i >= 0; --i) {
      var attribute = atts.item(i);
    
      if (attribute.name == namespaceNodeName) {
        return attribute.value;
      }
    }
  }
  
  return "";
};

// --- xml/serialize.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Returns the serialized XML string for the specified DOM node.
function xmlSerialize(node, options, filter, xml) {
  assert(node                  != null,        "xmlSerialize: node is null");
  assert(typeof(node.nodeType) != "undefined", "xmlSerialize: node is not a DOM node");
  
  if (typeof(options)                          == "undefined") { options                          = { };     }
  if (typeof(options.standalone)               == "undefined") { options.standalone               = null;    }
  if (typeof(options.encoding)                 == "undefined") { options.encoding                 = "UTF-8"; }
  if (typeof(options.omitXmlDeclaration)       == "undefined") { options.omitXmlDeclaration       = false;   }
  if (typeof(options.includeNamespacePrefixes) == "undefined") { options.includeNamespacePrefixes = null;    }
  if (typeof(options.cdataSectionElements)     == "undefined") { options.cdataSectionElements     = [];      }
  
  return serialize(node, options, filter ? filter : function() { return true; }, []).join("");
  
  
  function serialize(node, options, filter, xml) {
    if (!filter(node)) {
      return xml;
    }
    
    switch (node.nodeType) {
      case 1: // Element
        xml.push("<");
        xml.push(node.tagName);
        
        var attributes    = node.attributes;
        var numAttributes = attributes.length;
        
        for (var i = 0; i < numAttributes; ++i) {
          var attribute = attributes.item(i);
          
          // Opera can have null attributes.
          if (attribute == null) {
            continue;
          }
          
          if (attribute.specified) {
            serialize(attribute, options, filter, xml);
          }
        }
        
        if (node.hasChildNodes()) {
          xml.push(">");
        
          for (var child = node.firstChild; child != null; child = child.nextSibling) {
            serialize(child, options, filter, xml);
          }
          
          xml.push("</");
          xml.push(node.tagName);
          xml.push(">");
        }
        else {
          xml.push("/>");
        }
        
        return xml;
        
      case 2: // Attribute
        // Namespace node.
        if (node.name.match(/^xmlns(:|$)/) && !isVisiblyUtilized(node)) {
          return xml;
        }
        
        var name  = node.name;
        var value = node.value.replace(/&/g, "&amp;")
                              .replace(/"/g, "&quot;")
                              .replace(/</g, "&lt;")
                              .replace(/>/g, "&gt;");
                              
        if (value.indexOf("function(") == 0) {
          value = "<function>";
        }
          
        xml.push(" ");
        xml.push(name);
        xml.push('="');
        xml.push(value);
        xml.push('"');
        
        return xml;
        
      case 3: // Text
        if (isCDataSectionElement(node.parentNode)) {
          // Fall through to CDATA case.
        }
        else {
          xml.push(node.data.replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;"));
          
          return xml;
        }
        
      case 4: // CDATA section
        xml.push("<![CDATA[");
        xml.push(node.data.replace(/]]>/g, "]]]]><![CDATA[>"));
        xml.push("]]>");
        
        return xml;
        
      case 7: // Processing instruction
        xml.push("<?");
        xml.push(node.target);
        xml.push(" ");
        xml.push(node.data);
        xml.push("?>");
        
        return xml;
        
      case 8: // Comment
        xml.push("<!--");
        xml.push(node.data);
        xml.push("-->");
        
        return xml;
        
      case 9: // Document
        var xml = [];
        
        if (!options.omitXmlDeclaration) {
          var encoding   = (options.encoding   == "UTF-16") ? "UTF-16" : "UTF-8";
          var standalone = (options.standalone != null)     ? ' standalone="' + (options.standalone ? "yes" : "no") + '"' : "";
          
          xml.push('<?xml version="1.0" encoding="');
          xml.push(encoding);
          xml.push('"');
          xml.push(standalone);
          xml.push('?>\n\n');
        }
      
        for (var child = node.firstChild; child != null; child = child.nextSibling) {
          serialize(child, options, filter, xml);
          xml.push("\n");
        }
        
        return xml;
        
      case 10: // Document type
        xml.push("<!DOCTYPE ");
        xml.push(node.name);
        xml.push(' PUBLIC "');
        xml.push(node.publicId);
        xml.push('" "');
        xml.push(node.systemId);
        xml.push('" [\n]>');
        
        return xml;
        
      case 11: // Document fragment
        for (var child = node.firstChild; child != null; child = child.nextSibling) {
          serialize(child, options, filter, xml);
        }
        
        return xml;
        
      default:
        assert(false, "Unsupported node: " + node.nodeName + " (" + node.nodeType + ")");
    }
  
  
    function isVisiblyUtilized(namespaceNode) {
      // If includeNamespacePrefixes is null, serialize all namespace nodes.
      if (!options.includeNamespacePrefixes) {
        return true;
      }
      
      var prefix  = namespaceNode.name.substring(6);
      var element = new XPath("..").selectNode(namespaceNode);
      
      // If the prefix is in includeNamespacePrefixes, serialize it.
      for (var i = options.includeNamespacePrefixes.length - 1; i >= 0; --i) {
        var includePrefix = options.includeNamespacePrefixes[i];
        
        if (includePrefix == prefix || includePrefix == "#default" && prefix == "") {
          return true;
        }
      }
      
      // Check that the namespace prefix is visibly utilized based on the Exclusive
      // XML Canonicalization specification.
      if (namespaceNode.name == "xmlns") {
        return element.prefix == null;
      }
      else {
        return new XPath("parent::" + prefix + ":* or ..//@" + prefix + ":*").evaluate(namespaceNode);
      }
    };
    
    function isCDataSectionElement(element) {
      for (var i = options.cdataSectionElements.length - 1; i >= 0; --i) {
        if (options.cdataSectionElements[i].matches(element)) {
          return true;
        }
      }
      
      return false;
    };
  };
};

// --- xml/regexes.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates XmlRegexes, an object containing a number of useful regular
// expressions for parsing XML and XPath.
(function() {
  var BaseChar      = "[\\u0041-\\u005A\\u0061-\\u007A\\u00C0-\\u00D6\\u00D8-\\u00F6\\u00F8-\\u00FF\\u0100-\\u0131\\u0134-\\u013E\\u0141-\\u0148\\u014A-\\u017E\\u0180-\\u01C3\\u01CD-\\u01F0\\u01F4-\\u01F5\\u01FA-\\u0217\\u0250-\\u02A8\\u02BB-\\u02C1\\u0386\\u0388-\\u038A\\u038C\\u038E-\\u03A1\\u03A3-\\u03CE\\u03D0-\\u03D6\\u03DA\\u03DC\\u03DE\\u03E0\\u03E2-\\u03F3\\u0401-\\u040C\\u040E-\\u044F\\u0451-\\u045C\\u045E-\\u0481\\u0490-\\u04C4\\u04C7-\\u04C8\\u04CB-\\u04CC\\u04D0-\\u04EB\\u04EE-\\u04F5\\u04F8-\\u04F9\\u0531-\\u0556\\u0559\\u0561-\\u0586\\u05D0-\\u05EA\\u05F0-\\u05F2\\u0621-\\u063A\\u0641-\\u064A\\u0671-\\u06B7\\u06BA-\\u06BE\\u06C0-\\u06CE\\u06D0-\\u06D3\\u06D5\\u06E5-\\u06E6\\u0905-\\u0939\\u093D\\u0958-\\u0961\\u0985-\\u098C\\u098F-\\u0990\\u0993-\\u09A8\\u09AA-\\u09B0\\u09B2\\u09B6-\\u09B9\\u09DC-\\u09DD\\u09DF-\\u09E1\\u09F0-\\u09F1\\u0A05-\\u0A0A\\u0A0F-\\u0A10\\u0A13-\\u0A28\\u0A2A-\\u0A30\\u0A32-\\u0A33\\u0A35-\\u0A36\\u0A38-\\u0A39\\u0A59-\\u0A5C\\u0A5E\\u0A72-\\u0A74\\u0A85-\\u0A8B\\u0A8D\\u0A8F-\\u0A91\\u0A93-\\u0AA8\\u0AAA-\\u0AB0\\u0AB2-\\u0AB3\\u0AB5-\\u0AB9\\u0ABD\\u0AE0\\u0B05-\\u0B0C\\u0B0F-\\u0B10\\u0B13-\\u0B28\\u0B2A-\\u0B30\\u0B32-\\u0B33\\u0B36-\\u0B39\\u0B3D\\u0B5C-\\u0B5D\\u0B5F-\\u0B61\\u0B85-\\u0B8A\\u0B8E-\\u0B90\\u0B92-\\u0B95\\u0B99-\\u0B9A\\u0B9C\\u0B9E-\\u0B9F\\u0BA3-\\u0BA4\\u0BA8-\\u0BAA\\u0BAE-\\u0BB5\\u0BB7-\\u0BB9\\u0C05-\\u0C0C\\u0C0E-\\u0C10\\u0C12-\\u0C28\\u0C2A-\\u0C33\\u0C35-\\u0C39\\u0C60-\\u0C61\\u0C85-\\u0C8C\\u0C8E-\\u0C90\\u0C92-\\u0CA8\\u0CAA-\\u0CB3\\u0CB5-\\u0CB9\\u0CDE\\u0CE0-\\u0CE1\\u0D05-\\u0D0C\\u0D0E-\\u0D10\\u0D12-\\u0D28\\u0D2A-\\u0D39\\u0D60-\\u0D61\\u0E01-\\u0E2E\\u0E30\\u0E32-\\u0E33\\u0E40-\\u0E45\\u0E81-\\u0E82\\u0E84\\u0E87-\\u0E88\\u0E8A\\u0E8D\\u0E94-\\u0E97\\u0E99-\\u0E9F\\u0EA1-\\u0EA3\\u0EA5\\u0EA7\\u0EAA-\\u0EAB\\u0EAD-\\u0EAE\\u0EB0\\u0EB2-\\u0EB3\\u0EBD\\u0EC0-\\u0EC4\\u0F40-\\u0F47\\u0F49-\\u0F69\\u10A0-\\u10C5\\u10D0-\\u10F6\\u1100\\u1102-\\u1103\\u1105-\\u1107\\u1109\\u110B-\\u110C\\u110E-\\u1112\\u113C\\u113E" +
                       "\\u1140\\u114C\\u114E\\u1150\\u1154-\\u1155\\u1159\\u115F-\\u1161\\u1163\\u1165\\u1167\\u1169\\u116D-\\u116E\\u1172-\\u1173\\u1175\\u119E\\u11A8\\u11AB\\u11AE-\\u11AF\\u11B7-\\u11B8\\u11BA\\u11BC-\\u11C2\\u11EB\\u11F0\\u11F9\\u1E00-\\u1E9B\\u1EA0-\\u1EF9\\u1F00-\\u1F15\\u1F18-\\u1F1D\\u1F20-\\u1F45\\u1F48-\\u1F4D\\u1F50-\\u1F57\\u1F59\\u1F5B\\u1F5D\\u1F5F-\\u1F7D\\u1F80-\\u1FB4\\u1FB6-\\u1FBC\\u1FBE\\u1FC2-\\u1FC4\\u1FC6-\\u1FCC\\u1FD0-\\u1FD3\\u1FD6-\\u1FDB\\u1FE0-\\u1FEC\\u1FF2-\\u1FF4\\u1FF6-\\u1FFC\\u2126\\u212A-\\u212B\\u212E\\u2180-\\u2182\\u3041-\\u3094\\u30A1-\\u30FA\\u3105-\\u312C\\uAC00-\\uD7A3]";
  var Ideographic   = "[\\u4E00-\\u9FA5\\u3007\\u3021-\\u3029]";
  var CombiningChar = "[\\u0300-\\u0345\\u0360-\\u0361\\u0483-\\u0486\\u0591-\\u05A1\\u05A3-\\u05B9\\u05BB-\\u05BD\\u05BF\\u05C1-\\u05C2\\u05C4\\u064B-\\u0652\\u0670\\u06D6-\\u06DC\\u06DD-\\u06DF\\u06E0-\\u06E4\\u06E7-\\u06E8\\u06EA-\\u06ED\\u0901-\\u0903\\u093C\\u093E-\\u094C\\u094D\\u0951-\\u0954\\u0962-\\u0963\\u0981-\\u0983\\u09BC\\u09BE\\u09BF\\u09C0-\\u09C4\\u09C7-\\u09C8\\u09CB-\\u09CD\\u09D7\\u09E2-\\u09E3\\u0A02\\u0A3C\\u0A3E\\u0A3F\\u0A40-\\u0A42\\u0A47-\\u0A48\\u0A4B-\\u0A4D\\u0A70-\\u0A71\\u0A81-\\u0A83\\u0ABC\\u0ABE-\\u0AC5\\u0AC7-\\u0AC9\\u0ACB-\\u0ACD\\u0B01-\\u0B03\\u0B3C\\u0B3E-\\u0B43\\u0B47-\\u0B48\\u0B4B-\\u0B4D\\u0B56-\\u0B57\\u0B82-\\u0B83\\u0BBE-\\u0BC2\\u0BC6-\\u0BC8\\u0BCA-\\u0BCD\\u0BD7\\u0C01-\\u0C03\\u0C3E-\\u0C44\\u0C46-\\u0C48\\u0C4A-\\u0C4D\\u0C55-\\u0C56\\u0C82-\\u0C83\\u0CBE-\\u0CC4\\u0CC6-\\u0CC8\\u0CCA-\\u0CCD\\u0CD5-\\u0CD6\\u0D02-\\u0D03\\u0D3E-\\u0D43\\u0D46-\\u0D48\\u0D4A-\\u0D4D\\u0D57\\u0E31\\u0E34-\\u0E3A\\u0E47-\\u0E4E\\u0EB1\\u0EB4-\\u0EB9\\u0EBB-\\u0EBC\\u0EC8-\\u0ECD\\u0F18-\\u0F19\\u0F35\\u0F37\\u0F39\\u0F3E\\u0F3F\\u0F71-\\u0F84\\u0F86-\\u0F8B\\u0F90-\\u0F95\\u0F97\\u0F99-\\u0FAD\\u0FB1-\\u0FB7\\u0FB9\\u20D0-\\u20DC\\u20E1\\u302A-\\u302F\\u3099\\u309A]";
  var Digit         = "[\\u0030-\\u0039\\u0660-\\u0669\\u06F0-\\u06F9\\u0966-\\u096F\\u09E6-\\u09EF\\u0A66-\\u0A6F\\u0AE6-\\u0AEF\\u0B66-\\u0B6F\\u0BE7-\\u0BEF\\u0C66-\\u0C6F\\u0CE6-\\u0CEF\\u0D66-\\u0D6F\\u0E50-\\u0E59\\u0ED0-\\u0ED9\\u0F20-\\u0F29]";
  var Extender      = "[\\u00B7\\u02D0\\u02D1\\u0387\\u0640\\u0E46\\u0EC6\\u3005\\u3031-\\u3035\\u309D-\\u309E\\u30FC-\\u30FE]";
  var Letter        = "(?:" + BaseChar + "|" + Ideographic + ")";

  var NCNameChar    = "(?:"    + Letter + "|" + Digit + "|" + "[.\\-_]" + "|" + CombiningChar + "|" + Extender + ")";
  var NCName        = "(?:(?:" + Letter + "|_)" + NCNameChar + "*)";
  var QName         = "(?:(?:" + NCName + ":)?" + NCName + ")";

  XmlRegexes = {
    BaseChar:      BaseChar,
    Ideographic:   Ideographic,
    CombiningChar: CombiningChar,
    Digit:         Digit,
    Extender:      Extender,
    Letter:        Letter,

    NCNameChar:    NCNameChar,
    NCName:        NCName,
    QName:         QName
  };
}) ();


// --- xml/qualifiedName.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new QName, which is a namespace URI and local name pair.
//
// Parameters:
//     namespaceURI: A namespace URI.
//     localName:    A local name.
function QualifiedName(namespaceURI, localName) {
  if (arguments.length == 0) {
    return;
  }
  
  this.namespaceURI = namespaceURI;
  this.localName    = localName;
};

// Compares the QName against the specified node's name.
//
// Return value:
//     True if the node's name matches the QName, false if not.
QualifiedName.prototype.matches = function(node) {
  var localName    = node.nodeName.replace(/^.*:/, "");
  var namespaceURI = xmlNamespaceURI(node);
  
  // Opera will give attributes the namespace of their owner element. Reverse that.
  if (userAgent.isOpera && node.nodeType == 2) {
    if (namespaceURI != "" && namespaceURI == xmlNamespaceURI(node.ownerElement)) {
      namespaceURI = "";
    }
  }
  
  return localName    == this.localName
      && namespaceURI == this.namespaceURI;
};

// Returns a string like either local-name or {namespace-uri}local-name.
QualifiedName.prototype.toString = function() {
  return this.namespaceURI == ""
    ? this.localName
    : "{" + this.namespaceURI + "}" + this.localName;
};

// --- events/listener.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new event listener and attaches it to the requested element.
//
// Parameters:
//     observer: The element to attach the listener to.
//     name:     The event name.
//     phase:    The phase to listen in. One of "capture", "default"/"bubble",
//               or null. If null then the listener is only triggered if the
//               event is fired directly on the observer.
//     handler:  A function to call when the event is fired.
function EventListener(observer, name, phase, handler) {
  assert(observer != null, "observer is null");
  assert(name     != null, "name is null");
  
  // Create a list of all the event listeners that have been created. Internet
  // Explorer does not seem to release these automatically when the page is
  // unloaded, so we'll need to detach them ourselves when the page is unloaded.
  if (typeof(EventListener.all) == "undefined") {
    EventListener.all = [];
    
    new EventListener(window, "unload", "default", function() {
      for (var i = EventListener.all.length - 1; i >= 0; --i) {
        EventListener.all[i].detach();
      }
    });
  }
  
  EventListener.all.push(this);

  // "bubble" is synonymous with "default".
  if (phase == "bubble") {
    phase = "default";
  }
  
  this.observer = observer;
  this.name     = name;
  this.phase    = phase;
  
  this.callback = function(event) {
    // Internet Explorer-specific event massaging.
    if (userAgent.isInternetExplorer) {
      if (event == null) {
        event = window.event;
      }
      
      event.target = event.srcElement; 
      
      // fireEvent does not accept user-defined event names, so all nonstandard events
      // become "onerrorupdate" events with trueName set to the real event name.
      if (typeof(event.trueName) != "undefined" && event.trueName != name) {
        return;
      }
      
      // If the event was triggered by the user calling fireEvent, check that the
      // expected phase is not "capture".
      if (typeof(event.phase) == "undefined") {
        if (phase == "capture") {
          return;
        }
      }
      // If the event was triggered by XFormEvent.dispatch, then make sure we're in
      // the expected phase.
      else if (event.phase != phase && phase != null) {
        return;
      }
      
      // Internet Explorer does not support the capture phase natively, so we simulate
      // it by firing the event manually on the target element's ancestor elements and
      // immediately cancelling the bubbling.
      if (phase == "capture") {
        event.cancelBubble = true;
      }
      
      event.preventDefault = function() {
        this.returnValue = false;
      };
      
      event.stopPropagation = function() {
        this.cancelBubble = true;
        this.stopped      = true;
      };
    }
    
    // Safari may set the target to be a text node; if so, use the text node's
    // parent.
    if (event.target != null && isTextNode(event.target)) {
      event.target = event.target.parentNode;
    }
    
    // If the phase is null, then the event must be fired directly on the observer.
    if (phase == null && event.target != observer) {
      return;
    }
    
    // Function to call the handler and then clean up.
    var callHandler = function(event) {
      handler.call(event.target, event);
      
      // Need to get rid of the functions that were created above to prevent Internet
      // Explorer from leaking memory due to circular references from the function
      // closures.
      if (userAgent.isInternetExplorer) {
        event.preventDefault  = null;
        event.stopPropagation = null;
      }
    };

    // Call the handler in 1ms to give time for the UI to be redrawn, unless we're
    // unloading the page in which case we need to call it immediately.
    if (name == "unload") {
      monitor(functionCall(callHandler, event));
    }
    else {
      // Make a copy of the event object. It will get reset by the time the event
      // handler is called.
      var eventClone = {};
      
      for (var i = event.length - 1; i >= 0; --i) {
        eventClone[i] = event[i];
      }
      
      setTimeout(functionCall(monitor, functionCall(callHandler, eventClone)), 1);
    }
  };
  
  this.attach();
};

// W3C methods.
if (document.addEventListener) {
  EventListener.prototype.attach = function() {
    // Opera does not have window.addEventListener, and instead fires events from
    // the document node.
    if (this.observer == window && !window.addEventListener) {
      this.observer = document;
    }
    
    if (this.observer.addEventListener) {
      var name = this.name;
      
      if (name.match(/^DOM/)) {
        name = "_" + name;
      }
      
      this.observer.addEventListener(name, this.callback, this.phase == "capture");
    }
  };
  
  EventListener.prototype.detach = function() {
    // Opera does not have window.removeEventListener, and instead fires events from
    // the document node.
    if (this.observer == window && !window.removeEventListener) {
      this.observer = document;
    }
    
    if (this.observer.removeEventListener) {
      var name = this.name;
      
      if (name.match(/^DOM/)) {
        name = "_" + name;
      }
      
      this.observer.removeEventListener(name, this.callback, this.phase == "capture");
    }
  };
}
// Internet Explorer methods.
else {
  EventListener.prototype.attach = function() {
    if (this.observer.attachEvent) {
      this.observer.attachEvent(this.getName(), this.callback);
    }
  };
  
  EventListener.prototype.detach = function() {
    if (this.observer.detachEvent) {
      this.observer.detachEvent(this.getName(), this.callback);
    }
  };
  
  EventListener.prototype.getName = function() {
    if (this.phase == "capture" || !XmlEvent.isBuiltinEvent(this.name)) {
      return "onerrorupdate";
    }
    
    return "on" + this.name;
  };
}

// --- events/xmlEvent.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XML event.
//
// Parameters:
//     element:       The element from which the event was created.
//     name:          The name of the event.
//     handler:       The element that will handle the event once it has been caught.
//     target:        The target element of the event, or null if unspecified.
//     observer:      The element that will listen for the event.
//     phase:         The phase during which the listener is to be activated.
//     propagate:     Specifies whether or not the event processing is allowed
//                    to continue after all the listeners have been activated.
//     defaultAction: Specifies whether or not the event's default action is to
//                    take place after all the listeners have been activated.
function XmlEvent(element, name, handler, target, observer, phase, propagate, defaultAction) {
  assert(name     != null, "name is null");
  assert(handler  != null, "handler is null");
  assert(observer != null, "observer is null");

  this.name          = name;
  this.handler       = handler;
  this.target        = target;
  this.observer      = observer;
  this.phase         = phase;
  this.propagate     = propagate;
  this.defaultAction = defaultAction;
};

XmlEvent.parseEvents = function(element) {
  return new XmlEventParser().parseEvents(element);
};


function XmlEventParser() {
};

XmlEventParser.prototype.parseEvents = function(element) {
  var events        = [];
  var self          = this;
  
  if (element.namespaceURI == XmlNamespaces.EVENTS && element.getAttribute("event") != null) {
    events.push(this.parseEvent(element));
  }
  
  var atts = element.attributes;
  var eventAtt = false;
  for (var i = atts.length - 1; i > -1; i--) {
    var att = atts.item(i);
    if (att != null && att.namespaceURI == XmlNamespaces.EVENTS && att.nodeName.replace(/^.*:/, "") == "event") {
      eventAtt = true;
      break;
    }
  }
  if (eventAtt) {
    events.push(this.parseEvent(child));
  }
  
  locateEvents(element);
  
  function locateEvents(element) {
    for (var child = element.firstChild; child != null; child = child.nextSibling) {
      if (child.nodeType == 1) {
        if (child.namespaceURI == XmlNamespaces.EVENTS && element.getAttribute("event") != null) {
          events.push(self.parseEvent(child));
        }
        else {
          var atts = child.attributes;
          var eventAtt = false;
          for (var i = atts.length - 1; i > -1; i--) {
            var att = atts.item(i);
            if (att != null && att.namespaceURI == XmlNamespaces.EVENTS && att.nodeName.replace(/^.*:/, "") == "event") {
              eventAtt = true;
              break;
            }
          }
          if (eventAtt) {
            events.push(self.parseEvent(child));
          }
          else {
            locateEvents(child);
          }
        }
      }
    }
  }
  
  return events;
};

XmlEventParser.prototype.parseEvent = function(element) {
  return new XmlEvent(
    element,
    
    this.parseName         (element),
    this.parseHandler      (element),
    this.parseTarget       (element),
    this.parseObserver     (element),
    this.parsePhase        (element),
    this.parsePropagate    (element) == "continue",
    this.parseDefaultAction(element) == "perform"
  );
};

XmlEventParser.prototype.parseName = function(element) {
  return this.attribute(element, "event");
};

XmlEventParser.prototype.parseHandler = function(element) {
  var uri = this.attribute(element, "handler", null);
  
  if (uri == null) {
    return element;
  }
  
  var id = uri.substr(uri.indexOf("#") + 1);

  if (uri.indexOf("#") == 0) {
    var handler = this.getElementById(element.ownerDocument, id);
    
    if (handler == null) {
      handler = window[id];
    }
    
    return handler;
  }
  else {
    return this.getElementById(xmlLoadURI(uri.substring(0, uri.indexOf("#"))), id);
  }
};

XmlEventParser.prototype.parsePhase = function(element) {
  return this.attribute(element, "phase", "default");
};

XmlEventParser.prototype.parsePropagate = function(element) {
  return this.attribute(element, "propagate", "continue");
};

XmlEventParser.prototype.parseDefaultAction = function(element) {
  return this.attribute(element, "defaultAction", "perform");
};

XmlEventParser.prototype.parseTarget = function(element) {
  var target = this.attribute(element, "target", null);

  if (target == null)  {
    return null;
  }
  
  try {
    return xform.getObjectById(target).xmlElement;
  }
  catch (exception) {
    return new XPath("//*[@id = '" + target + "']").selectNode(document);
  }
};

XmlEventParser.prototype.parseObserver = function(element) {
  var observer = this.attribute(element, "observer", null);
  var handler  = this.attribute(element, "handler",  null);

  if (observer != null) {
    return this.getElementById(element.ownerDocument, observer);
  }
  else if (handler != null) {
    return element;
  }
  else {
    return element.parentNode;
  }
};


XmlEventParser.prototype.attribute = function(element, name, defaultValue) {
  var attribute = null;
  var atts = element.attributes;
  
  if (atts != null) {
    for (var i = atts.length - 1; i > -1; i--) {
      var att = atts.item(i);
      if (att != null && att.nodeName.replace(/^.*:/, "") == name) {
        if (element.namespaceURI == XmlNamespaces.EVENTS ||
            att    .namespaceURI == XmlNamespaces.EVENTS)
        {
          attribute = att;
        }
      }
    }
  }
  
  if (attribute == null) {
    if (typeof(defaultValue) == "undefined") {
      throw new XmlException(
        "<" + element.tagName + "/> element is missing the required @" + name +
        " attribute."
      );
    }
    
    return defaultValue;
  }
  
  return attribute.value;
};

XmlEventParser.prototype.getElementById = function(document, id) {
  assert(document.nodeType == 9, "document.nodeType != 9");
  
  try {
    return xform.getObjectById(id).xmlElement;
  }
  catch (exception) {
    return new XPath("//*[@id = '" + id + "']").selectNode(document);
  }
};

// --- events/dispatch.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


XmlEvent.builtinEvents = { };

// Registers a built-in event, defining if it bubbles, is cancelable, and what
// its default action is. When an event is defined, the pre-defined values
// override any values passed to dispatch().
//
// Parameters:
//     name:          The name of the event.
//     type:          The type of event (parameter to document.createEvent).
//     bubbles:       Does the event bubble?
//     cancelable:    Can the event be canceled?
//     defaultAction: An optional function to execute if the event is not
//                    canceled.
XmlEvent.define = function(name, type, bubbles, cancelable, defaultAction) {
  assert(!XmlEvent.builtinEvents[name], "event already defined");
  
  XmlEvent.builtinEvents[name] = {
    type:          type,
    bubbles:       bubbles,
    cancelable:    cancelable,
    defaultAction: defaultAction
  };
};

// Dispatches an event to the specified target element. If the event has been
// predefined, the type, bubbles, cancelable, and defaultAction parameters are
// ignored in favor of the predefined values.
//
// Parameters:
//     target:        The target element.
//     name:          The name of the event.
//     type:          The type of event (parameter to document.createEvent).
//     bubbles:       Does the event bubble?
//     cancelable:    Can the event be canceled?
//     defaultAction: An optional function to execute if the event is not
//                    canceled. The target of the event is passed as the first
//                    parameter when called.
XmlEvent.dispatch = function(target, name, type, bubbles, cancelable, defaultAction) {
  assert(target != null, "target is null");
  assert(name   != null, "name is null");
  
  if (XmlEvent.builtinEvents[name]) {
    var event = XmlEvent.builtinEvents[name];
    
    type          = event.type;
    bubbles       = event.bubbles;
    cancelable    = event.cancelable;
    defaultAction = event.defaultAction;
  }
  
  assert(type != null, "type is null");
  
  if (!defaultAction) {
    defaultAction = function() { };
  }
  
  status("Dispatching event " + name + " on " + xmlSerialize(target.cloneNode(false)));
    
  // W3C
  if (target.dispatchEvent) {
    if (name.match(/^DOM/)) {
      name = "_" + name;
    }
    
    var event = document.createEvent(type);
    
    event.initEvent(name, bubbles, cancelable);
    
    if (target.dispatchEvent(event) || !cancelable) {
      defaultAction(event);
    }
  }
  // Internet Explorer
  else {
    var fauxName = (XmlEvent.isBuiltinEvent(name)) ? name : "errorupdate";
    
    // Capture phase.
    var ancestors = [];
    
    for (var ancestor = target.parentNode; ancestor != null; ancestor = ancestor.parentNode) {
      ancestors.unshift(ancestor);
    }
    
    var len = ancestors.length;
    for (var i = 0; i < len; i++) {
      var event          = document.createEventObject();
          event.trueName = name;
          event.phase    = "capture";
          
      ancestors[i].fireEvent("on" + fauxName, event);
      
      if (event.stopped) {
        return;
      }
    }
    
    // Bubble phase.
    if (!bubbles) {
      // If the event doesn't bubble, add a handler that cancels bubbling since events
      // always bubble in Internet Explorer.
      var bubbleCanceler = new EventListener(target, name, "default", function(event) {
        event.cancelBubble = true;
      });
      
      bubbleCanceler.attach();
    }
    
    var event          = document.createEventObject();
        event.trueName = name;
        event.phase    = "default";
        event.target   = target;
        
    if (target.fireEvent("on" + fauxName, event) || !cancelable) {
      defaultAction(event);
    }
    
    if (!bubbles) {
      bubbleCanceler.detach();
    }
  }
};

XmlEvent.isBuiltinEvent = function(name) {
  switch (name) {
    case "abort":
    case "activate":
    case "afterprint":
    case "afterupdate":
    case "beforeactivate":
    case "beforecopy":
    case "beforecut":
    case "beforedeactivate":
    case "beforeeditfocus":
    case "beforepaste":
    case "beforeprint":
    case "beforeunload":
    case "beforeupdate":
    case "blur":
    case "bounce":
    case "cellchange":
    case "change":
    case "click":
    case "contextmenu":
    case "controlselect":
    case "copy":
    case "cut":
    case "dataavailable":
    case "datasetchanged":
    case "datasetcomplete":
    case "dblclick":
    case "deactivate":
    case "drag":
    case "dragend":
    case "dragenter":
    case "dragleave":
    case "dragover":
    case "dragstart":
    case "drop":
    case "error":
    case "errorupdate":
    case "filterchange":
    case "finish":
    case "focus":
    case "focusin":
    case "focusout":
    case "help":
    case "keydown":
    case "keypress":
    case "keyup":
    case "layoutcomplete":
    case "load":
    case "losecapture":
    case "mousedown":
    case "mouseenter":
    case "mouseleave":
    case "mousemove":
    case "mouseout":
    case "mouseover":
    case "mouseup":
    case "mousewheel":
    case "move":
    case "moveend":
    case "movestart":
    case "paste":
    case "propertychange":
    case "readystatechange":
    case "reset":
    case "resize":
    case "resizeend":
    case "resizestart":
    case "rowenter":
    case "rowexit":
    case "rowsdelete":
    case "rowsinserted":
    case "scroll":
    case "select":
    case "selectionchange":
    case "selectstart":
    case "start":
    case "stop":
    case "submit":
    case "unload":
      return true;
      
    default:
      return false;
  }
};

// --- xpath/token.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// An XPath token type.
// 
// See section 3.7 of the XPath specification for explanation of each of these
//  token types.
function XPathTokenType(name) {
  this.name = name;
};

XPathTokenType.prototype.toString = function() {
  return this.name;
};

XPathTokenType.LEFT_PARENTHESIS         = new XPathTokenType("LEFT_PARENTHESIS");
XPathTokenType.RIGHT_PARENTHESIS        = new XPathTokenType("RIGHT_PARENTHESIS");
XPathTokenType.LEFT_BRACKET             = new XPathTokenType("LEFT_BRACKET");
XPathTokenType.RIGHT_BRACKET            = new XPathTokenType("RIGHT_BRACKET");
XPathTokenType.DOT                      = new XPathTokenType("DOT");
XPathTokenType.DOT_DOT                  = new XPathTokenType("DOT_DOT");
XPathTokenType.ATTRIBUTE_SIGN           = new XPathTokenType("ATTRIBUTE_SIGN");
XPathTokenType.COMMA                    = new XPathTokenType("COMMA");
XPathTokenType.COLON_COLON              = new XPathTokenType("COLON_COLON");
                                                                                      
XPathTokenType.STAR                     = new XPathTokenType("STAR");
XPathTokenType.NAMESPACE_TEST           = new XPathTokenType("NAMESPACE_TEST");
XPathTokenType.QNAME                    = new XPathTokenType("QNAME");
                                                                                      
XPathTokenType.COMMENT                  = new XPathTokenType("COMMENT");
XPathTokenType.TEXT                     = new XPathTokenType("TEXT");
XPathTokenType.PROCESSING_INSTRUCTION   = new XPathTokenType("PROCESSING_INSTRUCTION");
XPathTokenType.NODE                     = new XPathTokenType("NODE");
                                                                                      
XPathTokenType.AND                      = new XPathTokenType("AND");
XPathTokenType.OR                       = new XPathTokenType("OR");
XPathTokenType.MOD                      = new XPathTokenType("MOD");
XPathTokenType.DIV                      = new XPathTokenType("DIV");
XPathTokenType.MULTIPLY                 = new XPathTokenType("MULTIPLY");
XPathTokenType.SLASH                    = new XPathTokenType("SLASH");
XPathTokenType.SLASH_SLASH              = new XPathTokenType("SLASH_SLASH");
XPathTokenType.UNION                    = new XPathTokenType("UNION");
XPathTokenType.PLUS                     = new XPathTokenType("PLUS");
XPathTokenType.MINUS                    = new XPathTokenType("MINUS");
XPathTokenType.EQUALS                   = new XPathTokenType("EQUALS");
XPathTokenType.NOT_EQUALS               = new XPathTokenType("NOT_EQUALS");
XPathTokenType.LESS_THAN                = new XPathTokenType("LESS_THAN");
XPathTokenType.LESS_THAN_OR_EQUAL_TO    = new XPathTokenType("LESS_THAN_OR_EQUAL_TO");
XPathTokenType.GREATER_THAN             = new XPathTokenType("GREATER_THAN");
XPathTokenType.GREATER_THAN_OR_EQUAL_TO = new XPathTokenType("GREATER_THAN_OR_EQUAL_TO");
                                                                                      
XPathTokenType.FUNCTION_NAME            = new XPathTokenType("FUNCTION_NAME");
                                                                                      
XPathTokenType.ANCESTOR                 = new XPathTokenType("ANCESTOR");
XPathTokenType.ANCESTOR_OR_SELF         = new XPathTokenType("ANCESTOR_OR_SELF");
XPathTokenType.ATTRIBUTE                = new XPathTokenType("ATTRIBUTE");
XPathTokenType.CHILD                    = new XPathTokenType("CHILD");
XPathTokenType.DESCENDANT               = new XPathTokenType("DESCENDANT");
XPathTokenType.DESCENDANT_OR_SELF       = new XPathTokenType("DESCENDANT_OR_SELF");
XPathTokenType.FOLLOWING                = new XPathTokenType("FOLLOWING");
XPathTokenType.FOLLOWING_SIBLING        = new XPathTokenType("FOLLOWING_SIBLING");
XPathTokenType.NAMESPACE                = new XPathTokenType("NAMESPACE");
XPathTokenType.PARENT                   = new XPathTokenType("PARENT");
XPathTokenType.PRECEDING                = new XPathTokenType("PRECEDING");
XPathTokenType.PRECEDING_SIBLING        = new XPathTokenType("PRECEDING_SIBLING");
XPathTokenType.SELF                     = new XPathTokenType("SELF");
                                                                                      
XPathTokenType.LITERAL                  = new XPathTokenType("LITERAL");
XPathTokenType.NUMBER                   = new XPathTokenType("NUMBER");
XPathTokenType.VARIABLE_REFERENCE       = new XPathTokenType("VARIABLE_REFERENCE");
                                                                                      
// Virtual token returned by the tokenizer at the end of every XPath expression.
XPathTokenType.END                      = new XPathTokenType("END");


// See the NodeType production in section 3.7 of the XPath specification.
XPathTokenType.prototype               .isNodeType = false;
XPathTokenType.COMMENT                 .isNodeType = true;
XPathTokenType.TEXT                    .isNodeType = true;
XPathTokenType.PROCESSING_INSTRUCTION  .isNodeType = true;
XPathTokenType.NODE                    .isNodeType = true;

// See the Operator production in section 3.7 of the XPath specification.
XPathTokenType.prototype               .isOperator = false;
XPathTokenType.AND                     .isOperator = true;
XPathTokenType.OR                      .isOperator = true;
XPathTokenType.MOD                     .isOperator = true;
XPathTokenType.DIV                     .isOperator = true;
XPathTokenType.MULTIPLY                .isOperator = true;
XPathTokenType.SLASH                   .isOperator = true;
XPathTokenType.SLASH_SLASH             .isOperator = true;
XPathTokenType.UNION                   .isOperator = true;
XPathTokenType.PLUS                    .isOperator = true;
XPathTokenType.MINUS                   .isOperator = true;
XPathTokenType.EQUALS                  .isOperator = true;
XPathTokenType.NOT_EQUALS              .isOperator = true;
XPathTokenType.LESS_THAN               .isOperator = true;
XPathTokenType.LESS_THAN_OR_EQUAL_TO   .isOperator = true;
XPathTokenType.GREATER_THAN            .isOperator = true;
XPathTokenType.GREATER_THAN_OR_EQUAL_TO.isOperator = true;

// See the AxisName production in section 3.7 of the XPath specification.
XPathTokenType.prototype               .isAxisName = false;
XPathTokenType.ANCESTOR                .isAxisName = true;
XPathTokenType.ANCESTOR_OR_SELF        .isAxisName = true;
XPathTokenType.ATTRIBUTE               .isAxisName = true;
XPathTokenType.CHILD                   .isAxisName = true;
XPathTokenType.DESCENDANT              .isAxisName = true;
XPathTokenType.DESCENDANT_OR_SELF      .isAxisName = true;
XPathTokenType.FOLLOWING               .isAxisName = true;
XPathTokenType.FOLLOWING_SIBLING       .isAxisName = true;
XPathTokenType.NAMESPACE               .isAxisName = true;
XPathTokenType.PARENT                  .isAxisName = true;
XPathTokenType.PRECEDING               .isAxisName = true;
XPathTokenType.PRECEDING_SIBLING       .isAxisName = true;
XPathTokenType.SELF                    .isAxisName = true;

// Checks that a lexeme is valid for a particular token type.
//
// Parameters:
//     type:   A token type.
//     lexeme: A lexeme to check.
//
// Return value:
//     True if lexeme is valid, false if not.
XPathTokenType.prototype.isValidLexeme = function(lexeme) {
  return lexeme.match(new RegExp("^(?:" + XPathTokenizer.regexFor(this) + ")$")) != null;
};


// Creates a new XPath token that has been read from an XPath expression string.
// 
// Parameters:
//     type:     The token's type.
//     lexeme:   The token's lexeme.
//     xpath:    The XPath expression string from which the token was read
//               (optional).
//     position: The position of the token within the XPath string (optional).
function XPathToken(type, lexeme, xpath, position) {
  if (arguments.length == 2) {
    xpath    = lexeme;
    position = 0;
  }
  
  this.type     = type;
  this.lexeme   = lexeme;
  this.xpath    = xpath;
  this.position = position;
};

XPathToken.prototype.toString = function() {
  return this.lexeme + " [" + this.type + "]";
};

// --- xpath/tokenizer.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XPath tokenizer, which takes an XPath string and breaks it up
// into the individual tokens needed during parsing.
//
// Parameters:
//     xpath: The string to tokenize.
function XPathTokenizer(xpath) {
  this.xpath        = xpath;
  this.position     = 0;
  this.currentToken = null;
  
  this.next();
};

// Scans the XPath string and reads the next token.
//
// Exceptions:
//     XPathInvalidCharacterException: Thrown if an invalid character was
//     encountered.
XPathTokenizer.prototype.next = function() {
  var nextToken = this.getToken();

  this.disambiguateToken(nextToken);
  this.currentToken = nextToken;
};

// Returns the next token from the XPath.
//
// Exceptions:
//     XPathInvalidCharacterException: Thrown if an invalid character was
//     encountered.
XPathTokenizer.prototype.getToken = function() {
  this.skipWhiteSpace();

  if (this.position == this.xpath.length) {
    return new XPathToken(XPathTokenType.END, "", this.xpath, this.xpath.length);
  }
  
  // Try each regular expression in order.
  var numRegexes = XPathTokenizer.regexes.length;
  
  for (var i = 0; i < numRegexes; ++i) {
    var entry     = XPathTokenizer.regexes[i];
    var tokenType = entry.tokenType;
    var regex     = entry.getTokenRegex;
    
    if (tokenType == XPathTokenType.END) {
      continue;
    }
    
    var match = regex.exec(this.xpath.substr(this.position));

    if (match != null) {
      this.position += match[0].length;

      return new XPathToken(tokenType, match[0], this.xpath, this.position - match[0].length);
    }
  }
  
  throw new XPathInvalidCharacterException(this.xpath, this.position);
};

// Determines the proper token type for an ambiguous lexeme based on the current
// context.
//
// Parameters:
//     nextToken: The token to disambiguate.
XPathTokenizer.prototype.disambiguateToken = function(nextToken) {
  var currentToken = this.currentToken;
  
  // Multiply operator '*' or named operators.
  if ((nextToken.type == XPathTokenType.STAR || nextToken.type == XPathTokenType.QNAME) && currentToken != null) {
    if (currentToken.type != XPathTokenType.ATTRIBUTE_SIGN   &&
        currentToken.type != XPathTokenType.COLON_COLON      &&
        currentToken.type != XPathTokenType.LEFT_PARENTHESIS &&
        currentToken.type != XPathTokenType.LEFT_BRACKET     &&
        currentToken.type != XPathTokenType.COMMA            &&
       !currentToken.type.isOperator)
    {
      switch (nextToken.lexeme) {
        case "*":   nextToken.type = XPathTokenType.MULTIPLY; break;
        case "and": nextToken.type = XPathTokenType.AND;      break;
        case "or":  nextToken.type = XPathTokenType.OR;       break;
        case "mod": nextToken.type = XPathTokenType.MOD;      break;
        case "div": nextToken.type = XPathTokenType.DIV;      break;
      }
    }
  }

  // Node types or function names.
  if (nextToken.type == XPathTokenType.QNAME && this.xpath.substr(this.position).match(/^\s*\(/)) {
    switch (nextToken.lexeme) {
      case "comment":                nextToken.type = XPathTokenType.COMMENT;                break;
      case "text":                   nextToken.type = XPathTokenType.TEXT;                   break;
      case "processing-instruction": nextToken.type = XPathTokenType.PROCESSING_INSTRUCTION; break;
      case "node":                   nextToken.type = XPathTokenType.NODE;                   break;
      default:                       nextToken.type = XPathTokenType.FUNCTION_NAME;          break;
    }
  }

  // Axis names.
  if (nextToken.type == XPathTokenType.QNAME && this.xpath.substr(this.position).match(/^\s*::/)) {
    switch (nextToken.lexeme) {
      case "ancestor":           nextToken.type = XPathTokenType.ANCESTOR;           break;
      case "ancestor-or-self":   nextToken.type = XPathTokenType.ANCESTOR_OR_SELF;   break;
      case "attribute":          nextToken.type = XPathTokenType.ATTRIBUTE;          break;
      case "child":              nextToken.type = XPathTokenType.CHILD;              break;
      case "descendant":         nextToken.type = XPathTokenType.DESCENDANT;         break;
      case "descendant-or-self": nextToken.type = XPathTokenType.DESCENDANT_OR_SELF; break;
      case "following":          nextToken.type = XPathTokenType.FOLLOWING;          break;
      case "following-sibling":  nextToken.type = XPathTokenType.FOLLOWING_SIBLING;  break;
      case "namespace":          nextToken.type = XPathTokenType.NAMESPACE;          break;
      case "parent":             nextToken.type = XPathTokenType.PARENT;             break;
      case "preceding":          nextToken.type = XPathTokenType.PRECEDING;          break;
      case "preceding-sibling":  nextToken.type = XPathTokenType.PRECEDING_SIBLING;  break;
      case "self":               nextToken.type = XPathTokenType.SELF;               break;
    }
  }
};


// Advances the current position to the next non-whitespace character, or to the
// end of the XPath if there is none.
XPathTokenizer.prototype.skipWhiteSpace = function() {
  while (this.position < this.xpath.length && this.xpath.substr(this.position, 1).match(/^\s$/)) {
    ++this.position;
  }
};


(function() {
  XPathTokenizer.regexes = [
    {tokenType: XPathTokenType.LEFT_PARENTHESIS,         regex: "\\("},
    {tokenType: XPathTokenType.RIGHT_PARENTHESIS,        regex: "\\)"},
    {tokenType: XPathTokenType.LEFT_BRACKET,             regex: "\\["},
    {tokenType: XPathTokenType.RIGHT_BRACKET,            regex: "\\]"},
    {tokenType: XPathTokenType.DOT_DOT,                  regex: "\\.\\."},
    {tokenType: XPathTokenType.DOT,                      regex: "\\."},
    {tokenType: XPathTokenType.ATTRIBUTE_SIGN,           regex: "@"},
    {tokenType: XPathTokenType.COMMA,                    regex: ","},
    {tokenType: XPathTokenType.COLON_COLON,              regex: "::"},

    {tokenType: XPathTokenType.STAR,                     regex: "\\*"},
    {tokenType: XPathTokenType.NAMESPACE_TEST,           regex: XmlRegexes.NCName + ":\\*"},
    {tokenType: XPathTokenType.QNAME,                    regex: XmlRegexes.QName},

    {tokenType: XPathTokenType.COMMENT,                  regex: "comment"},
    {tokenType: XPathTokenType.TEXT,                     regex: "text"},
    {tokenType: XPathTokenType.PROCESSING_INSTRUCTION,   regex: "processing-instruction"},
    {tokenType: XPathTokenType.NODE,                     regex: "node"},

    {tokenType: XPathTokenType.AND,                      regex: "and"},
    {tokenType: XPathTokenType.OR,                       regex: "or"},
    {tokenType: XPathTokenType.MOD,                      regex: "mod"},
    {tokenType: XPathTokenType.DIV,                      regex: "div"},
    {tokenType: XPathTokenType.SLASH_SLASH,              regex: "//"},
    {tokenType: XPathTokenType.SLASH,                    regex: "/"},
    {tokenType: XPathTokenType.UNION,                    regex: "\\|"},
    {tokenType: XPathTokenType.PLUS,                     regex: "\\+"},
    {tokenType: XPathTokenType.MINUS,                    regex: "-"},
    {tokenType: XPathTokenType.MULTIPLY,                 regex: "\\*"},
    {tokenType: XPathTokenType.EQUALS,                   regex: "="},
    {tokenType: XPathTokenType.NOT_EQUALS,               regex: "!="},
    {tokenType: XPathTokenType.LESS_THAN_OR_EQUAL_TO,    regex: "<="},
    {tokenType: XPathTokenType.LESS_THAN,                regex: "<"},
    {tokenType: XPathTokenType.GREATER_THAN_OR_EQUAL_TO, regex: ">="},
    {tokenType: XPathTokenType.GREATER_THAN,             regex: ">"},

    {tokenType: XPathTokenType.FUNCTION_NAME,            regex: XmlRegexes.QName},

    {tokenType: XPathTokenType.ANCESTOR,                 regex: "ancestor"},
    {tokenType: XPathTokenType.ANCESTOR_OR_SELF,         regex: "ancestor-or-self"},
    {tokenType: XPathTokenType.ATTRIBUTE,                regex: "attribute"},
    {tokenType: XPathTokenType.CHILD,                    regex: "child"},
    {tokenType: XPathTokenType.DESCENDANT,               regex: "descendant"},
    {tokenType: XPathTokenType.DESCENDANT_OR_SELF,       regex: "descendant-or-self"},
    {tokenType: XPathTokenType.FOLLOWING,                regex: "following"},
    {tokenType: XPathTokenType.FOLLOWING_SIBLING,        regex: "following-sibling"},
    {tokenType: XPathTokenType.NAMESPACE,                regex: "namespace"},
    {tokenType: XPathTokenType.PARENT,                   regex: "parent"},
    {tokenType: XPathTokenType.PRECEDING,                regex: "preceding"},
    {tokenType: XPathTokenType.PRECEDING_SIBLING,        regex: "preceding-sibling"},
    {tokenType: XPathTokenType.SELF,                     regex: "self"},

    {tokenType: XPathTokenType.LITERAL,                  regex: "(?:\"[^\"]*\"|'[^']*')"},
    {tokenType: XPathTokenType.NUMBER,                   regex: "(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)"},
    {tokenType: XPathTokenType.VARIABLE_REFERENCE,       regex: "\\$" + XmlRegexes.QName},

    {tokenType: XPathTokenType.END,                      regex: ""}
  ];
  
  // Pre-compile all of the regular expressions used by the getToken method.
  for (var i = XPathTokenizer.regexes.length - 1; i >= 0; --i) {
    var entry = XPathTokenizer.regexes[i];
    entry.getTokenRegex = new RegExp("^(?:" + entry.regex + ")");
  }
}) ();

XPathTokenizer.regexFor = function(tokenType) {
  for (var i = XPathTokenizer.regexes.length - 1; i >= 0; --i) {
    var entry = XPathTokenizer.regexes[i];
    
    if (entry.tokenType == tokenType) {
      return entry.regex;
    }
  }
};

// --- xpath/parser.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XPath expression parser, which constructs parse trees from
// XPath strings.
function XPathParser() {
  this.cache = { };
};

// Parses an XPath expression string.
// 
// Parameters:
//     xpath:       An XPath expression string.
//     contextNode: An XML node from which to lookup the namespace URIs for the
//                  namespace prefixes in the XPath expression.
// 
// Return value:
//     The parse tree for the XPath.
// 
// Exceptions:
//     XPathException: Thrown if an error occurred while parsing the XPath
//     expression.
XPathParser.prototype.parse = function(xpath, contextNode) {
  // If this XPath has been seen before, return the cached XPathExpression.
  var cache;
  
  if (cache = this.cache[xpath]) {
    for (var i = cache.length - 1; i >= 0; --i) {
      if (cache[i][0] == contextNode) {
        return cache[i][1];
      }
    }
  }
  
  this.tokenizer    = new XPathTokenizer(xpath);
  this.contextNode  = contextNode;
  this.currentToken = this.tokenizer.currentToken;
  
  var expression    = this.parseExpression();
  
  this.needToken(XPathTokenType.END);
  
  // Save the expression in the cache.
  if (typeof(this.cache[xpath]) == "undefined") {
    this.cache[xpath] = [];
  }
  
  this.cache[xpath].push([contextNode, expression]);
  
  // Return.
  return expression;
};

XPathParser.prototype.parseExpression = function() {
  return this.parseOrExpression();
};

XPathParser.prototype.parseOrExpression             = function() { return this.parseBinaryExpression(this.parseAndExpression,            "left", [XPathTokenType.OR]); };
XPathParser.prototype.parseAndExpression            = function() { return this.parseBinaryExpression(this.parseEqualityExpression,       "left", [XPathTokenType.AND]); };
XPathParser.prototype.parseEqualityExpression       = function() { return this.parseBinaryExpression(this.parseRelationalExpression,     "left", [XPathTokenType.EQUALS, XPathTokenType.NOT_EQUALS]); };
XPathParser.prototype.parseRelationalExpression     = function() { return this.parseBinaryExpression(this.parseAdditiveExpression,       "left", [XPathTokenType.LESS_THAN, XPathTokenType.GREATER_THAN, XPathTokenType.LESS_THAN_OR_EQUAL_TO, XPathTokenType.GREATER_THAN_OR_EQUAL_TO]); };
XPathParser.prototype.parseAdditiveExpression       = function() { return this.parseBinaryExpression(this.parseMultiplicativeExpression, "left", [XPathTokenType.PLUS, XPathTokenType.MINUS]); };
XPathParser.prototype.parseMultiplicativeExpression = function() { return this.parseBinaryExpression(this.parseUnaryExpression,          "left", [XPathTokenType.MULTIPLY, XPathTokenType.DIV, XPathTokenType.MOD]); };
XPathParser.prototype.parseUnionExpression          = function() { return this.parseBinaryExpression(this.parsePathExpression,           "left", [XPathTokenType.UNION]); };

// Parses a binary expression, which is composed of two sub-expressions
// separated by an operator.
// 
// Parameters:
//     parseSubExpression: The function to parse a sub-expression.
//     associativity:      The associativity of this expression.
//     operators:          A list of valid operators.
// 
// Return value:
//     A new expression.
XPathParser.prototype.parseBinaryExpression = function(parseSubExpression, associativity, operators) {
  // Returns true if array contains item; otherwise, false.
  function contains(array, item) {
    for (var i = array.length - 1; i >= 0; --i) {
      if (array[i] == item) {
        return true;
      }
    }
    
    return false;
  };
  
  switch (associativity) {
    case "left": {
      var expression = parseSubExpression.call(this);
      
      while (contains(operators, this.currentToken.type)) {
        var operator = this.currentToken;

        this.nextToken();
        
        expression = new XPathBinaryExpression(operator.lexeme, expression, parseSubExpression.call(this));
      }
      
      return expression;
    }

    case "right": {
      var expression = parseSubExpression.call(this);

      if (contains(operators, this.currentToken.type)) {
        var operator = this.currentToken;

        this.nextToken();

        expression = new XPathBinaryExpression(
          operator.lexeme,
          expression,
          parseBinaryExpression(parseSubExpression, associativity, operators)
        );
      }

      return expression;
    }
  }
};

XPathParser.prototype.parseUnaryExpression = function() {
  if (this.currentToken.type != XPathTokenType.MINUS) {
    return this.parseUnionExpression();
  }

  var operator = this.currentToken;

  this.nextToken();

  return new XPathUnaryExpression(operator.lexeme, this.parseUnaryExpression());
};

XPathParser.prototype.parsePathExpression = function() {
  var filterExpression = this.parseFilterExpression();
  var locationPath     = null;
  
  if (filterExpression == null || this.currentToken.type == XPathTokenType.SLASH || this.currentToken.type == XPathTokenType.SLASH_SLASH) {
    locationPath = this.parseLocationPath();
  }
  
  if (locationPath == null) {
    return filterExpression;
  }
  else if (filterExpression == null) {
    return locationPath;
  }
  else {
    locationPath.isAbsolute = false;

    return new XPathPathExpression(filterExpression, locationPath);
  }
};

// Will return null instead of throwing an exception if unable to parse a filter
// expression.
XPathParser.prototype.parseFilterExpression = function() {
  var expression = this.parsePrimaryExpression();

  if (expression == null) {
    return null;
  }

  while (this.currentToken.type == XPathTokenType.LEFT_BRACKET) {
    expression = new XPathFilterExpression(expression, this.parsePredicate());
  }

  return expression;
};

// Will return null instead of throwing an exception if unable to parse a
// primary expression.
XPathParser.prototype.parsePrimaryExpression = function() {
  var expression = null;

  switch (this.currentToken.type) {
    case XPathTokenType.VARIABLE_REFERENCE:
      expression = new XPathVariableReferenceExpression(new QName(this.currentToken.lexeme.substr(1), this.contextNode));

      this.nextToken();
      break;

    case XPathTokenType.LEFT_PARENTHESIS:
      this.skipToken(XPathTokenType.LEFT_PARENTHESIS);

      expression = this.parseExpression();

      this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);
      break;

    case XPathTokenType.LITERAL:
      expression = new XPathLiteralExpression(this.currentToken.lexeme.slice(1, -1));

      this.nextToken();
      break;

    case XPathTokenType.NUMBER:
      expression = new XPathNumberExpression(new Number(this.currentToken.lexeme));

      this.nextToken();
      break;

    case XPathTokenType.FUNCTION_NAME:
      expression = this.parseFunctionCall();
      break;
  }

  return expression;
};

XPathParser.prototype.parseFunctionCall = function() {
  this.needToken(XPathTokenType.FUNCTION_NAME);

  var functionName = new QName(this.currentToken.lexeme, this.contextNode);
  var arguments    = [];

  this.nextToken();
  this.skipToken(XPathTokenType.LEFT_PARENTHESIS);

  if (this.currentToken.type != XPathTokenType.RIGHT_PARENTHESIS) {
    arguments.push(this.parseExpression());

    while (this.currentToken.type == XPathTokenType.COMMA) {
      this.nextToken();
      arguments.push(this.parseExpression());
    }
  }

  this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);

  return new XPathFunctionCallExpression(functionName, arguments);
};

XPathParser.prototype.parsePredicate = function() {
  this.skipToken(XPathTokenType.LEFT_BRACKET);

  var expression = this.parseExpression();

  this.skipToken(XPathTokenType.RIGHT_BRACKET);

  return new XPathPredicate(expression);
};

XPathParser.prototype.parseLocationPath = function() {
  var isAbsolute;
  var stepsRequired;
  var steps = [];

  switch (this.currentToken.type) {
    case XPathTokenType.SLASH:
      isAbsolute    = true;
      stepsRequired = 0;

      this.nextToken();
      break;

    case XPathTokenType.SLASH_SLASH:
      isAbsolute    = true;
      stepsRequired = 2;

      steps.push(new XPathStep(XPathAxis.DESCENDANT_OR_SELF, new XPathNodeNodeTest(), []));

      this.nextToken();
      break;

    default:
      isAbsolute    = false;
      stepsRequired = 1;

      break;
  }
  
  steps = steps.concat(this.parseRelativeLocationPath());
  
  if (steps.length < stepsRequired) {
    throw new XPathInvalidTokenException(this.currentToken);
  }

  return new XPathLocationPath(isAbsolute, steps);
};

// Returns the list of steps in the path, which will be empty if unable to parse
// a relative location path.
XPathParser.prototype.parseRelativeLocationPath = function() {
  var steps = [];

  // Each iteration parses one step of the path. The loop terminates when no more
  // steps are found.
  while (true) {
    switch (this.currentToken.type) {
      case XPathTokenType.DOT:
        steps.push(new XPathStep(XPathAxis.SELF, new XPathNodeNodeTest(), []));

        this.nextToken();
        break;

      case XPathTokenType.DOT_DOT:
        steps.push(new XPathStep(XPathAxis.PARENT, new XPathNodeNodeTest(), []));

        this.nextToken();
        break;

      default:
        // Axis
        var axis        = XPathAxis.CHILD;
        var defaultAxis = true;

        if (this.currentToken.type.isAxisName) {
          switch (this.currentToken.type) {             
            case XPathTokenType.ANCESTOR:           axis = XPathAxis.ANCESTOR;           break;
            case XPathTokenType.ANCESTOR_OR_SELF:   axis = XPathAxis.ANCESTOR_OR_SELF;   break;
            case XPathTokenType.ATTRIBUTE:          axis = XPathAxis.ATTRIBUTE;          break;
            case XPathTokenType.CHILD:              axis = XPathAxis.CHILD;              break;
            case XPathTokenType.DESCENDANT:         axis = XPathAxis.DESCENDANT;         break;
            case XPathTokenType.DESCENDANT_OR_SELF: axis = XPathAxis.DESCENDANT_OR_SELF; break;
            case XPathTokenType.FOLLOWING:          axis = XPathAxis.FOLLOWING;          break;
            case XPathTokenType.FOLLOWING_SIBLING:  axis = XPathAxis.FOLLOWING_SIBLING;  break;
            case XPathTokenType.NAMESPACE:          axis = XPathAxis.NAMESPACE;          break;
            case XPathTokenType.PARENT:             axis = XPathAxis.PARENT;             break;
            case XPathTokenType.PRECEDING:          axis = XPathAxis.PRECEDING;          break;
            case XPathTokenType.PRECEDING_SIBLING:  axis = XPathAxis.PRECEDING_SIBLING;  break;
            case XPathTokenType.SELF:               axis = XPathAxis.SELF;               break;
          }

          defaultAxis = false;

          this.nextToken();
          this.skipToken(XPathTokenType.COLON_COLON);
        }
        else if (this.currentToken.type == XPathTokenType.ATTRIBUTE_SIGN) {
          axis        = XPathAxis.ATTRIBUTE;
          defaultAxis = false;

          this.nextToken();
        }
        
        // Node test
        var nodeTest;

        try {
          switch (this.currentToken.type) {
            case XPathTokenType.STAR:
              nodeTest = new XPathStarNodeTest();

              this.nextToken();
              break;

            case XPathTokenType.NAMESPACE_TEST:
              nodeTest = new XPathNamespaceNodeTest(QName.lookupNamespaceURI(this.contextNode, this.currentToken.lexeme.split(":")[0]));

              this.nextToken();
              break;

            case XPathTokenType.QNAME:
              nodeTest = new XPathQNameNodeTest(new QName(this.currentToken.lexeme, this.contextNode));

              this.nextToken();
              break;

            case XPathTokenType.COMMENT:
              nodeTest = new XPathCommentNodeTest();

              this.nextToken();
              this.skipToken(XPathTokenType.LEFT_PARENTHESIS);
              this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);
              break;

            case XPathTokenType.TEXT:
              nodeTest = new XPathTextNodeTest();

              this.nextToken();
              this.skipToken(XPathTokenType.LEFT_PARENTHESIS);
              this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);
              break;

            case XPathTokenType.PROCESSING_INSTRUCTION:
              this.nextToken();
              this.skipToken(XPathTokenType.LEFT_PARENTHESIS);

              if (this.currentToken.type == XPathTokenType.LITERAL) {
                nodeTest = new XPathProcessingInstructionNodeTest(this.currentToken.lexeme.slice(1, -1));

                this.nextToken();
              }
              else {
                nodeTest = new XPathProcessingInstructionNodeTest();
              }

              this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);
              break;

            case XPathTokenType.NODE:
              nodeTest = new XPathNodeNodeTest();
              
              this.nextToken();
              this.skipToken(XPathTokenType.LEFT_PARENTHESIS);
              this.skipToken(XPathTokenType.RIGHT_PARENTHESIS);
              break;

            default:
              if (defaultAxis && steps.length == 0) {
                return [];
              }

              // Either an invalid node test, or a missing step.
              throw new XPathInvalidTokenException(this.currentToken);
          }
        }
        // Bad namespace prefix.
        catch (exception) {
          throw new XPathException(this.currentToken.xpath, this.currentToken.position, exception);
        }

        // Predicates
        var predicates = [];

        while (this.currentToken.type == XPathTokenType.LEFT_BRACKET) {
          predicates.push(this.parsePredicate());
        }

        steps.push(new XPathStep(axis, nodeTest, predicates));
        break;
    }
    
    // Look for a '/' or '//' token; if we see one, we go back to the top of the
    // loop to find the next step.
    switch (this.currentToken.type) {
      case XPathTokenType.SLASH:
        this.nextToken();
        break;

      case XPathTokenType.SLASH_SLASH:
        steps.push(new XPathStep(XPathAxis.DESCENDANT_OR_SELF, new XPathNodeNodeTest(), []));

        this.nextToken();
        break;

      default:
        return steps;
    }
  }
};


// Gets the next token from the tokenizer.
XPathParser.prototype.nextToken = function() {
  this.tokenizer.next();
  this.currentToken = this.tokenizer.currentToken;
};

// Verifies that the current token's type is one of the specified types. If not,
// throws an exception.
// 
// Parameters:
//     expectedTokenType: The type of token that is expected.
// 
// Exceptions:
//     XPathInvalidTokenException: Thrown if the current token isn't in the
//     list.
XPathParser.prototype.needToken = function(expectedTokenType) {
  if (this.currentToken.type != expectedTokenType) {
    throw new XPathInvalidTokenException(this.currentToken);
  }
};

// Skips over the current token, provided that its type is one of the specified
// types.
// 
// Parameters:
//     expectedTokenType: The type of token that is expected.
// 
// Exceptions:
//     XPathInvalidTokenException: Thrown if the current token is not in the
//     list of tokens to skip.
XPathParser.prototype.skipToken = function(expectedTokenType) {
  this.needToken.call(this, expectedTokenType);
  this.nextToken();
};

// --- xpath/exception.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XPathException(xpath, position, cause) {
  this.xpath    = xpath;
  this.position = position;
  this.cause    = cause;
};

XPathException.prototype.toString = function() {
  var message = "Syntax error at character " + (this.position + 1) + " '"
              + this.xpath.substr(this.position, 1) + "': " + this.xpath;
              
  if (this.cause != null) {
    message += "\nCause: " + this.cause;
  }
  
  return message;
};


// Exception thrown when the namespace URI for an XML namespace prefix cannot be
// resolved.
//
// Parameters:
//     contextNode: The node from which the prefix was to be resolved.
//     prefix:      The prefix that could not be resolved.
function XPathInvalidPrefixException(contextNode, prefix) {
  this.contextNode = contextNode;
  this.prefix      = prefix;
};

XPathInvalidPrefixException.inherits(XPathException);

XPathInvalidPrefixException.prototype.toString = function() {
  return 'Unable to resolve namespace prefix "' + this.prefix + '".';
};


// Exception thrown when there is an invalid character in an XPath string.
//
// Parameters:
//     xpath:    The invalid XPath string.
//     position: The position of the invalid character.
function XPathInvalidCharacterException(xpath, position) {
  XPathException.call(this, xpath, position);
};

XPathInvalidCharacterException.inherits(XPathException);


// Thrown when an invalid token is encountered during parsing.
//
// Parameters:
//     token: The invalid token.
function XPathInvalidTokenException(token) {
  XPathException.call(this, token.xpath, token.position);
  this.token = token;
};

XPathInvalidTokenException.inherits(XPathException);

// --- xpath/xpath.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XPath.
//
// Parameters:
//     source:      The XPath string from which the XPath was created.
//     contextNode: An XML node from which to lookup the namespace URIs for the
//                  namespace prefixes in the XPath expression.
function XPath(source, contextNode) {
  if (typeof(contextNode) == "undefined") {
    contextNode = XPath.DEFAULT_PREFIXES;
  }
  
  this.source     = source;
  this.expression = XPath.PARSER.parse(source, contextNode);
};

XPath.PARSER = new XPathParser();

XPath.DEFAULT_PREFIXES = xmlNewDocument().createElement("default-prefixes");

XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xfm",    XmlNamespaces.XFORMS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xf",     XmlNamespaces.XFORMS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xforms", XmlNamespaces.XFORMS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:form",   XmlNamespaces.XFORMS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:html",   XmlNamespaces.XHTML);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xhtml",  XmlNamespaces.XHTML);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:events", XmlNamespaces.EVENTS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:event",  XmlNamespaces.EVENTS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:ev",     XmlNamespaces.EVENTS);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xs",     XmlNamespaces.SCHEMA);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xsd",    XmlNamespaces.SCHEMA);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:schema", XmlNamespaces.SCHEMA);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xsl",    XmlNamespaces.XSL);
XPath.DEFAULT_PREFIXES.setAttribute("xmlns:xslt",   XmlNamespaces.XSL);

// Evaluates the XPath starting with the given context or context node.
//
// Parameters:
//     context: The context or context node at which to start evaluation.
//
// Return value:
//     The result of the XPath, which is either a string, number, boolean, or
//     node-set.
XPath.prototype.evaluate = function(context) {
  // If a node was passed as the context, turn it into a full context object.
  if (!instanceOf(context, XPathContext)) {
    context = new XPathContext(context, 1, 1);
  }
  
  return this.expression.evaluate(context);
};

// Evaluates the XPath starting with the given context or context node and
// returns the first node in the result node-set.
// 
// Parameters:
//     context: The context or context node at which to start evaluation.
//
// Return value:
//     The first node from the result node-set, or null if the result node-set
//     is empty.
XPath.prototype.selectNode = function(context) {
  var nodeSet = this.evaluate(context);
  
  if (nodeSet.length == 0) {
    return null;
  }
  else {
    return nodeSet[0];
  }
};

// Determines the nodes that this XPath refers to when evaluated starting with
// the given context or context node.
//
// Parameters:
//     context: The context or context node from which to evaluate the XPath.
//
// Return value:
//     The nodes referred to during evaluation.
XPath.prototype.referencedNodes = function(context) {
  // If a node was passed as the context, turn it into a full context object.
  if (!instanceOf(context, XPathContext)) {
    context = new XPathContext(context, 1, 1);
  }

  return this.expression.referencedNodes(context);
};

// Returns the source string for the XPath.
XPath.prototype.toString = function() {
  return this.source;
};

// --- xpath/qName.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new QName, which is a namespace URI and local name pair.
//
// Parameters:
//     qName:       The QName string, either "name" or "ns:name".
//     contextNode: The context node from which to resolve the QName's namespace
//                  prefix, if it has one.
function QName(qName, contextNode) {
  var parts = qName.split(":");
  
  if (parts.length == 2) {
    var namespaceURI = QName.lookupNamespaceURI(contextNode, parts[0]);
    var localName    = parts[1];
    
    // Prefixes are not allowed to map to the default namespace.
    if (namespaceURI == "") {
      throw new XPathInvalidPrefixException(contextNode, parts[0]);
    }
  }
  else {
    var namespaceURI = "";
    var localName    = qName;
  }
  
  QualifiedName.call(this, namespaceURI, localName);
};

QName.inherits(QualifiedName);

// Looks up the namespace URI for a prefix at the specified context node.
QName.lookupNamespaceURI = function(contextNode, prefix) {
  if (prefix == "xml") {
    return XmlNamespaces.XML;
  }
  
  for (var node = contextNode; node != null && node.attributes != null; node = node.parentNode) {
    var atts      = node.attributes;
    var attLength = atts.length;
    for (var i = 0; i < attLength; ++i) {
      var attribute = atts.item(i);
    
      if (attribute.name == "xmlns:" + prefix) {
        return attribute.value;
      }
    }
  }
  
  return "";
};

// --- xpath/nodeSet.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new node-set, which is a list of nodes containing no duplicate
// nodes. The node-set can be iterated over like an array:
//
//     for (var i = 0; i < nodeSet.length; ++i) {
//       ...
//     }
//
// The nodes are stored in the order in which they were added.
//
// Parameters:
//     nodes: Optionally, an array or other iterable collection containing the
//            initial nodes of the node-set.
function NodeSet(nodes) {
  this.length = 0;
  
  if (typeof(nodes) != "undefined") {
    this.addAll(nodes);
  }
};

// Adds a node to the node-set, unless it is already in the node-set.
NodeSet.prototype.add = function(node) {
  if (node == null) {
    return;
  }
  
  if (!this.contains(node)) {
    this[this.length++] = node;
  }
};

// Adds an array or other iterable collection of nodes to the node-set.
NodeSet.prototype.addAll = function(nodes) {
  assert(nodes && typeof(nodes.length) != "undefined", "addAll: nodes is not a node list");
  
  for (var i = 0; i < nodes.length; ++i) {
    this.add(nodes[i]);
  }
  
  return this;
};

// Adds a node to the node-set. The node must not already be in the node-set.
//
// If you can guarantee that the node is not already in the node-set, then
// addUnique will be much more efficient than add: O(1) versus O(n), where n is
// the number of elements in the node-set.
NodeSet.prototype.addUnique = function(node) {
  if (node != null) {
    this[this.length++] = node;
  }
};

// Adds an array or other iterable collection of nodes to the node-set. The two
// sets of nodes must be mutually exclusive, and the node-set being added must
// not contain any duplicate nodes.
//
// If you can guarantee that these conditions, then addAllUnique will be much
// more efficient than addAll: O(k) versus O(kn + k^2), where k is the number of
// nodes being added and n is the size of the original node-set.
NodeSet.prototype.addAllUnique = function(nodes) {
  assert(nodes && typeof(nodes.length) != "undefined", "addAllUnique: nodes is not a node list");
  
  for (var i = 0; i < nodes.length; ++i) {
    this.addUnique(nodes[i]);
  }
  
  return this;
};

// Returns true if the specified node is in the node-set; otherwise, false.
NodeSet.prototype.contains = function(node) {
  // If the node is a namespace node, then we must perform a thorough equality
  // test. Namespace nodes don't exist in the DOM tree; instead, they are virtual
  // nodes created on-the-fly when the namespace axis is used, and so it is
  // possible for duplicate namespace nodes to be created. (For example, when
  // evaluating the XPath "//namespace::*".)
  if (node.nodeType == 2 && node.nodeName.match(/^xmlns(:|$)/)) {
    for (var i = this.length - 1; i >= 0; --i ) {
      if (this[i] == node) {
        return true;
      }
      
      // Check for identical prefixes and namespace URIs.
      if (this[i].nodeType == 2 && this[i].nodeName == node.nodeName && this[i].value == node.value) {
        return true;
      }
    }
  }
  // For all other node types, a simple == suffices.
  else {
    for (var i = this.length - 1; i >= 0; --i) {
      if (this[i] == node) {
        return true;
      }
    }
  }
  
  return false;
};

// Reverses the nodes in the node-set in place, altering the original node-set.
//
// Return value:
//     The node-set.
NodeSet.prototype.reverse = function() {
  for (var i = 0; i < this.length / 2; ++i) {
    var front = i;
    var back  = this.length - 1 - i;
    
    var temp    = this[front];
    this[front] = this[back];
    this[back]  = temp;
  }
  
  return this;
};

NodeSet.prototype.toString = function() {
  var string = "";
  
  for (var i = 0; i < this.length; ++i) {
    var node = this[i];
    
    if (i > 0) {
      string += ", ";
    }
    
    // Attribute node.
    if (node.nodeType == 2) {
      string += "@";
    }
    
    string += node.nodeName;
    
    // If the node contains only text nodes as children, display its value.
    var simpleNode = true;
    
    for (var child = node.firstChild; child != null; child = child.nextSibling) {
      if (!isTextNode(child)) {
        simpleNode = false;
        break;
      }
    }
    
    if (simpleNode) {
      string += "=" + XPathFunction.stringValueOf(node);
    }
  }
  
  return "{" + string + "}";
};

// --- xpath/context.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Contains all of the context information needed during the evaluation of an
// XPath. According to the XPath specification, this is:
//
//    1. The current node.
//    2. The context position (the index of the context node within the current
//       node-set ordered in document order if the current axis is a forward
//       axis, or in reverse document order if it's a reverse axis).
//    3. The size of the current node-set.
function XPathContext(node, position, size) {
  if (node == null) {
    assert(position == null, "position is not null");
    assert(size     == null, "size is not null");
  }
  else {
    assert(position >= 1,    position + " < 1");
    assert(position <= size, position + " > " + size);
  }
  
  this.node     = node;
  this.position = position;
  this.size     = size;
  
  // Save the starting node in currentNode for the current() function.
  this.currentNode = this.node;
};

XPathContext.prototype.functionResolvers = [];

// Creates an identical copy of this context object.
XPathContext.prototype.clone = function() {
  var context = new XPathContext(this.node, this.position, this.size);

  context.contextNode       = this.contextNode;
  context.functionResolvers = this.functionResolvers;

  return context;
};

// Look up the function with the specified name.
//
// Parameters:
//     qName: A QName.
XPathContext.prototype.lookupFunction = function(qName) {
  for (var i = this.functionResolvers.length - 1; i >= 0; --i) {
    var func = this.functionResolvers[i].lookupFunction(qName);
    
    if (func != null) {
      return func;
    }
  }
  
  return null;
};

XPathContext.prototype.toString = function() {
  return this.node.nodeName + "[" + this.position + "/" + this.size + "]";
};

// --- xpath/axis.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new axis.
//
// Parameters:
//     direction: The direction of the axis; either XPathAxis.Direction.FORWARD
//                or XPathAxis.Direction.REVERSE.
function XPathAxis(name, direction) {
  this.name      = name;
  this.direction = direction;
};

// Applies the axis filter to the nodes in the node-set.
//
// Parameters:
//     nodeSet: The node-set to apply the axis to.
//
// Return value:
//     A node-set containing the nodes selected by the axis.
XPathAxis.prototype.filter = function(nodeSet) {
  var result = new NodeSet();
  var length = nodeSet.length;
  
  for (var i = 0; i < length; ++i) {
    // Avoid an expensive addAll() when we can.
    if (i == 0) {
      result = this.filterNode(nodeSet[i]);
    }
    else {
      result.addAll(this.filterNode(nodeSet[i]));
    }
  }
  
  return result;
};

// Applies the axis filter to the specified node.
//
// Parameters:
//     node: The node to apply the axis filter to.
//
// Return value:
//     A node-set containing the nodes selected by the axis.
XPathAxis.prototype.filterNode = function(node) {
  assert(false, "filterNode not implemented");
};

XPathAxis.prototype.toString = function() {
  return this.name;
};

XPathAxis.Direction         = { };
XPathAxis.Direction.FORWARD = 0;
XPathAxis.Direction.REVERSE = 1;



// The child axis.
XPathAxis.CHILD = new XPathAxis("child", XPathAxis.Direction.FORWARD);

XPathAxis.CHILD.filterNode = function(node) {
  var nodeSet = new NodeSet();
  
  // Attribute nodes actually have children; don't ever return them.
  if (node.nodeType != 2) {
    nodeSet.addAllUnique(node.childNodes);
  }
  
  return nodeSet;
};



// The descendant axis.
XPathAxis.DESCENDANT = new XPathAxis("descendant", XPathAxis.Direction.FORWARD);

XPathAxis.DESCENDANT.filterNode = function(node) {
  // Attribute nodes actually have children; don't ever return them.
  if (node.nodeType == 2) {
    return new NodeSet();
  }
  
  // For speed, we'll do an iterative depth-first traversal instead of a recursive
  // one.
  var nodeSet = new NodeSet();
  var stack   = [];
  
  for (var child = node.lastChild; child != null; child = child.previousSibling) {
    stack.push(child);
  }
  
  while (stack.length > 0) {
    var parent = stack.pop();
    
    nodeSet.addUnique(parent);
    
    for (var child = parent.lastChild; child != null; child = child.previousSibling) {
      stack.push(child);
    }
  }
  
  return nodeSet;
};



// The descendant-or-self axis.
XPathAxis.DESCENDANT_OR_SELF = new XPathAxis("descendant-or-self", XPathAxis.Direction.FORWARD);

XPathAxis.DESCENDANT_OR_SELF.filterNode = function(node) {
  // Attribute nodes actually have children.
  if (node.nodeType == 2) {
    return new NodeSet([node]);
  }
  
  // For speed, we'll do an iterative depth-first traversal instead of a recursive
  // one.
  var nodeSet = new NodeSet();
  var stack   = [node];
  
  while (stack.length > 0) {
    var parent = stack.pop();
    
    nodeSet.addUnique(parent);
    
    for (var child = parent.lastChild; child != null; child = child.previousSibling) {
      stack.push(child);
    }
  }
  
  return nodeSet;
};



// The parent axis.
XPathAxis.PARENT = new XPathAxis("parent", XPathAxis.Direction.REVERSE);

XPathAxis.PARENT.filterNode = function(node) {
  if (node.nodeType == 2) {
    if (typeof(node.ownerElement) != "undefined") {
      return new NodeSet([node.ownerElement]);
    }
    // Internet Explorer, aggravatingly enough, doesn't support ownerElement.
    else {
      // This trick courtesy of Cameron McCormack's XPath library.
      // (See https://sourceforge.net/forum/message.php?msg_id=4526120)
      try {
        if (typeof(node.selectSingleNode) != "undefined") {
          return new NodeSet([node.selectSingleNode("..")]);
        }
      }
      catch (e) {
      }
      
      // If that didn't work either, then we'll search the whole document for the
      // parent (extremely slow!).
      return new NodeSet([ownerElement(node.ownerDocument, node)]);
      
      function ownerElement(node, attribute) {
        if (node.attributes != null) {
          for (var i = node.attributes.length - 1; i >= 0; --i) {
            if (node.attributes.item(i) == attribute) {
              return node;
            }
          }
        }
        
        for (var child = node.firstChild; child != null; child = child.nextSibling) {
          var element = ownerElement(child, attribute);
          
          if (element != null) {
            return element;
          }
        }
        
        return null;
      };
    }
  }
  else if (node.parentNode != null && node.parentNode.nodeType != 9) {
    return new NodeSet([node.parentNode]);
  }
  else {
    return new NodeSet();
  }
};



// The ancestor axis.
XPathAxis.ANCESTOR = new XPathAxis("ancestor", XPathAxis.Direction.REVERSE);

XPathAxis.ANCESTOR.filterNode = function(node) {
  var nodeSet = new NodeSet();
  var parent  = XPathAxis.PARENT.filterNode(node);
  
  node = parent.length > 0 ? parent[0] : null;
  
  while (node != null && node.nodeType != 9) {
    nodeSet.addUnique(node);
    node = node.parentNode;
  }
  
  return nodeSet.reverse();
};



// The ancestor-or-self axis.
XPathAxis.ANCESTOR_OR_SELF = new XPathAxis("ancestor-or-self", XPathAxis.Direction.REVERSE);

XPathAxis.ANCESTOR_OR_SELF.filterNode = function(node) {
  var nodeSet = new NodeSet();
  
  nodeSet.addAllUnique(XPathAxis.ANCESTOR.filterNode(node));
  nodeSet.addUnique   (node);
  
  return nodeSet;
};



// The following-sibling axis.
XPathAxis.FOLLOWING_SIBLING = new XPathAxis("following-sibling", XPathAxis.Direction.FORWARD);

XPathAxis.FOLLOWING_SIBLING.filterNode = function(node) {
  var nodeSet = new NodeSet();
  
  while (node.nextSibling != null) {
    nodeSet.addUnique(node = node.nextSibling);
  }
  
  return nodeSet;
};



// The preceding-sibling axis.
XPathAxis.PRECEDING_SIBLING = new XPathAxis("preceding-sibling", XPathAxis.Direction.REVERSE);

XPathAxis.PRECEDING_SIBLING.filterNode = function(node) {
  if (node.parentNode == null) {
    return new NodeSet();
  }
  
  var nodeSet = new NodeSet();
  
  for (var sibling = node.previousSibling; sibling != null; sibling = sibling.previousSibling) {
    nodeSet.addUnique(sibling);
  }
  
  return nodeSet.reverse();
};



// The following axis.
XPathAxis.FOLLOWING = new XPathAxis("following", XPathAxis.Direction.FORWARD);

XPathAxis.FOLLOWING.filterNode = function(node) {
  var nodeSet = new NodeSet();
  
  do {
    for (; node.nextSibling != null; node = node.nextSibling) {
      nodeSet.addAllUnique(XPathAxis.DESCENDANT_OR_SELF.filterNode(node.nextSibling));
    }
    
    node = node.parentNode;
  }
  while (node != null);
  
  return nodeSet;
};



// The preceding axis.
XPathAxis.PRECEDING = new XPathAxis("preceding", XPathAxis.Direction.REVERSE);

XPathAxis.PRECEDING.filterNode = function(node) {
  var nodeSet = new NodeSet();
  
  do {
    for (; node.previousSibling != null; node = node.previousSibling) {
      nodeSet.addAllUnique(XPathAxis.DESCENDANT_OR_SELF.filterNode(node.previousSibling).reverse());
    }
    
    node = node.parentNode;
  }
  while (node != null);
    
  return nodeSet;
};


// The attribute axis.
XPathAxis.ATTRIBUTE = new XPathAxis("attribute", XPathAxis.Direction.FORWARD);

XPathAxis.ATTRIBUTE.filterNode = function(node) {
  if (node.attributes == null) {
    return new NodeSet();
  }
  
  // Get all of the attributes that aren't namespace declarations.
  var nodeSet = new NodeSet();
  
  for (var i = 0; i < node.attributes.length; ++i) {
    var attribute = node.attributes.item(i);
    
    if (!attribute.name.match(/^xmlns(:|$)/)) {
      nodeSet.addUnique(attribute);
    }
  }
  
  return nodeSet;
};



// The namespace axis.
XPathAxis.NAMESPACE = new XPathAxis("namespace", XPathAxis.Direction.FORWARD);

// If true, an xmlns:xml="http://www.w3.org/XML/1998/namespace" attribute will
// always be included in the returned node-set.
XPathAxis.NAMESPACE.includeXmlNamespace = true;

XPathAxis.NAMESPACE.filterNode = function(node) {
  // Only element nodes have namespace nodes.
  if (node.nodeType != 1) {
    return new NodeSet();
  }
  
  var prefixesSeen = { };
  var nodeSet      = new NodeSet();
  
  if (this.includeXmlNamespace) {
    try {
      // The "xml" namespace is implicitly declared in every document.
      var xmlNamespaceNode       = node.ownerDocument.createAttribute("xmlns:xml");
          xmlNamespaceNode.value = "http://www.w3.org/XML/1998/namespace";
        
      nodeSet.addUnique(xmlNamespaceNode);
    }
    catch (exception) {
      // Internet Explorer won't allow the attribute to be created, so don't try
      // again.
      this.includeXmlNamespace = false;
    }
  }
  
  for (; node != null && node.nodeType == 1; node = node.parentNode) {
    for (var i = 0; i < node.attributes.length; ++i) {
      var attribute = node.attributes.item(i);
      var value     = attribute.value;
      var prefix;

      if (attribute.name.match(/^xmlns(:|$)/)) {
        var prefix = attribute.name.substring(6);
        
        // If we've seen this prefix already, skip it.
        if (typeof(prefixesSeen[prefix]) != "undefined") {
          continue;
        }
        
        prefixesSeen[prefix] = true;
        
        // The declaration xmlns="" cancels out the default namespace, and is not a
        // regular namespace declaration.
        if (!(prefix == "" && value == "")) {
          nodeSet.addUnique(attribute);
        }
      }
    }
  }
  
  return nodeSet;
};



// The self axis.
XPathAxis.SELF = new XPathAxis("self", XPathAxis.Direction.FORWARD);

// Override the default filter function with a more efficient implementation.
XPathAxis.SELF.filter = function(nodeSet) {
  return nodeSet;
};

XPathAxis.SELF.filterNode = function(node) {
  return new NodeSet([node]);
};

// --- xpath/nodeTest.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Interface for XPath node tests, which select a set of nodes from a particular
// axis during evaluation of a location path step.
function XPathNodeTest() {
};

// Filters the node-set through the node test, keeping only nodes that pass the
// node test.
//
// Parameters:
//     context: The current evaluation context.
//     nodeSet: The current node-set.
//     axis:    The current axis.
XPathNodeTest.prototype.filter = function(context, nodeSet, axis) {
  var result = new NodeSet();
  
  for (var i = 0; i < nodeSet.length; ++i) {
    var node = nodeSet[i];
  
    if (this.test(context, node, axis)) {
      result.addUnique(node);
    }
  }
  
  return result;
};

// Tests the specified node.
XPathNodeTest.prototype.test = function(context, node, axis) {
};



// "*": Allows only nodes of the axis's principal type.
function XPathStarNodeTest() {
};

XPathStarNodeTest.inherits(XPathNodeTest);

XPathStarNodeTest.prototype.test = function(context, node, axis) {
  switch (axis) {
    case XPathAxis.ATTRIBUTE:
      return node.nodeType == 2;
      
    case XPathAxis.NAMESPACE:
      return node.nodeType == 2 && node.name.match(/^xmlns(:|$)/);
      
    default:
      return node.nodeType == 1;
  }
};



// "ns::*": Allows only elements belonging to a particular namespace.
function XPathNamespaceNodeTest(namespaceURI) {
  this.namespaceURI = namespaceURI;
};

XPathNamespaceNodeTest.inherits(XPathNodeTest);

XPathNamespaceNodeTest.prototype.test = function(context, node, axis) {
  if (!XPathStarNodeTest.prototype.test.call(this, context, node, axis)) {
    return false;
  }
  
  return xmlNamespaceURI(node) == this.namespaceURI;
};



// "ns:name" or "name": Allows only elements with the specified QName, which is
// a namespace URI and local name pair. The namespace URI is "" for nodes
// without a namespace.
function XPathQNameNodeTest(qName) {
  this.qName = qName;
};

XPathQNameNodeTest.inherits(XPathNodeTest);

XPathQNameNodeTest.prototype.test = function(context, node, axis) {
  return this.qName.matches(node);
};



// comment(): Allows only comment nodes.
function XPathCommentNodeTest() {
};

XPathCommentNodeTest.inherits(XPathNodeTest);

XPathCommentNodeTest.prototype.test = function(context, node, axis) {
  return node.nodeType == 8;
};



// text(): Allows only text nodes.
function XPathTextNodeTest() {
};

XPathTextNodeTest.inherits(XPathNodeTest);

XPathTextNodeTest.prototype.test = function(context, node, axis) {
  return isTextNode(node);
};

  
  
// processing-instruction(): Allows only processing instruction nodes.
function XPathProcessingInstructionNodeTest(name) {
  this.name = name;
};

XPathProcessingInstructionNodeTest.inherits(XPathNodeTest);

XPathProcessingInstructionNodeTest.prototype.test = function(context, node, axis) {
  if (node.nodeType != 7) {
    return false;
  }
  
  if (this.name != null && node.target != this.name) {
    return false;
  }
  
  return true;
};



// node(): Allows all nodes.
function XPathNodeNodeTest() {
};

XPathNodeNodeTest.inherits(XPathNodeTest);

// Override the default implementation of testing each node, since node() is
// true for every node.
XPathNodeNodeTest.prototype.filter = function(context, nodeSet, axis) {
  return nodeSet;
};

// --- xpath/expression.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Interface for all XPath expressions, which evaluate to either a string,
// number, boolean, or node-set.
function XPathExpression() {
};

// Evaluate the expression.
XPathExpression.prototype.evaluate = function(context) {
  assert(false, "evaluate not implemented");
};

// Returns a list of the nodes referenced by this expression.
XPathExpression.prototype.referencedNodes = function(context) {
  var value = this.evaluate(context);
  
  if (instanceOf(value, NodeSet)) {
    return value;
  }
  else {
    return new NodeSet();
  }
};



// Creates a new literal expression, which always returns the specified (string)
// literal.
function XPathLiteralExpression(literal) {
  this.literal = literal.toString();
};

XPathLiteralExpression.inherits(XPathExpression);

XPathLiteralExpression.prototype.evaluate = function(context) {
  return this.literal;
};



// Creates a new number expression, which always returns the specified number.
function XPathNumberExpression(number) {
  this.number = Number(number);
};

XPathNumberExpression.inherits(XPathExpression);

XPathNumberExpression.prototype.evaluate = function(context) {
  return this.number;
};



// Creates a new filter expression, which evaluates an expression and filters
// the resultant node-set with a predicate.
function XPathFilterExpression(expression, predicate) {
  this.expression = expression;
  this.predicate  = predicate;
};

XPathFilterExpression.inherits(XPathExpression);

XPathFilterExpression.prototype.evaluate = function(context) {
  return this.predicate.filter(context, this.expression.evaluate(context), XPathAxis.Direction.FORWARD);
};



// Creates a new path expression, which returns the result of evaluating the
// specified location path.
function XPathPathExpression(filterExpression, locationPath) {
  this.filterExpression = filterExpression;
  this.locationPath     = locationPath;
};

XPathPathExpression.inherits(XPathExpression);

XPathPathExpression.prototype.evaluate = function(context) {
  return this.locationPath.filter(context, this.filterExpression.evaluate(context));
};



// Creates a new function call expression, which calls the specified function
// and yields its return value.
//
// Parameters:
//     functionQName: The name of the function to evaluate.
//     arguments:     An array of expressions which are evaluated and used as
//                    arguments to the function.
function XPathFunctionCallExpression(functionQName, arguments) {
  this.functionQName = functionQName;
  this.arguments     = arguments;
};

XPathFunctionCallExpression.inherits(XPathExpression);

XPathFunctionCallExpression.prototype.evaluate = function(context) {
  var func      = context.lookupFunction(this.functionQName);
  var arguments = [];
  
  if (func == null) {
    throw new XmlException("XPath function \"" + this.functionQName + "\" not found.");
  }

  for (var i = 0; i < this.arguments.length; ++i) {
    arguments.push(this.arguments[i].evaluate(context));
  }
  
  return func.call(context, arguments);
};

XPathFunctionCallExpression.prototype.referencedNodes = function(context) {
  if (this.arguments.length == 0) {
    var func = context.lookupFunction(this.functionQName);
    
    if (func == null) {
      throw new XmlException("XPath function \"" + this.functionQName + "\" not found.");
    }
    
    if (func.defaultTo != null) {
      return new NodeSet([context.node]);
    }
    else {
      return new NodeSet();
    }
  }
  else {
    var referencedNodes = new NodeSet();
  
    for (var i = 0; i < this.arguments.length; ++i) {
      referencedNodes.addAll(this.arguments[i].referencedNodes(context));
    }
    
    return referencedNodes;
  }
};


// Creates a new unary expression.
//
// Parameters:
//     operator: The unary operator, which must be "-".
//     operand:  The operand.
function XPathUnaryExpression(operator, operand) {
  if (arguments.length == 0) {
    return;
  }
  
  this.operator = operator;
  this.operand  = operand;
};

XPathUnaryExpression.inherits(XPathExpression);

XPathUnaryExpression.prototype.evaluate = function(context) {
  switch (this.operator) {
    case "-":
      return -XPath.NUMBER_FUNCTION.evaluate(this.operand.evaluate(context));
    
    default:
      assert(false, "Invalid unary operator: " + this.operator);
  }
};


// Creates a new binary expression, which evaluates arithmetic and relational
// expressions.
//
// Parameters:
//     operator:     The binary operator, as a string like "+" or "and".
//     leftOperand:  The sub-expression to the left of the operator.
//     rightOperand: The sub-expression to the right of the operator.
function XPathBinaryExpression(operator, leftOperand, rightOperand) {
  if (arguments.length == 0) {
    return;
  }
  
  this.operator     = this.operators[operator];
  this.leftOperand  = leftOperand;
  this.rightOperand = rightOperand;
  
  assert(this.operator, "Invalid operator: " + operator);
};

XPathBinaryExpression.inherits(XPathExpression);

XPathBinaryExpression.prototype.evaluate = function(context) {
  return this.operator.evaluate(
    this.leftOperand .evaluate(context),
    this.rightOperand.evaluate(context)
  );                            
};

XPathBinaryExpression.prototype.referencedNodes = function(context) {
  return this.leftOperand .referencedNodes(context).addAll(
         this.rightOperand.referencedNodes(context));
};


XPathBinaryExpression.Operator = function() {
};

XPathBinaryExpression.Operator.prototype.evaluate = function(left, right) {
};


XPathBinaryExpression.UnionOperator = function() {
};

XPathBinaryExpression.UnionOperator.prototype.evaluate = function(left, right) {
  return new NodeSet(left).addAll(right);
};


XPathBinaryExpression.BooleanOperator = function(handler) {
  this.handler = handler;
};

XPathBinaryExpression.BooleanOperator.prototype.evaluate = function(left, right) {
  left  = XPath.BOOLEAN_FUNCTION.evaluate(left);
  right = XPath.BOOLEAN_FUNCTION.evaluate(right);
  
  return this.handler(left, right);
};


XPathBinaryExpression.ComparisonOperator = function(handler) {
  this.handler = handler;
};

// See section 3.4 of the XPath specification for an explanation of this function.
XPathBinaryExpression.ComparisonOperator.prototype.evaluate = function(left, right) {
  // If both objects to be compared are node-sets...
  if (instanceOf(left, NodeSet) && instanceOf(right, NodeSet)) {
    for (var i = left .length - 1; i >= 0; --i)
    for (var j = right.length - 1; j >= 0; --j) {
      if (this.compare(XPathFunction.stringValueOf(left[i]), XPathFunction.stringValueOf(right[j]))) {
        return true;
      }
    }
    
    return false;
  }

  // If one object to be compared is a node-set and the other is a
  // [number / string / boolean]...
  if (instanceOf(left, NodeSet)) {
    switch (right.constructor) {
      case Number:
        for (var i = left.length - 1; i >= 0; --i) {
          if (this.compare(XPath.NUMBER_FUNCTION.evaluate(XPathFunction.stringValueOf(left[i])), right)) {
            return true;
          }
        }
        
        return false;
        
      case String:
        for (var i = left.length - 1; i >= 0; --i) {
          if (this.compare(XPathFunction.stringValueOf(left[i]), right)) {
            return true;
          }
        }
        
        return false;
        
      case Boolean:
        return this.compare(XPath.BOOLEAN_FUNCTION.evaluate(left), right);
    }
  }
  
  if (instanceOf(right, NodeSet)) {
    switch (left.constructor) {
      case Number:
        for (var i = right.length - 1; i >= 0; --i) {
          if (this.compare(left, XPath.NUMBER_FUNCTION.evaluate(XPathFunction.stringValueOf(right[i])))) {
            return true;
          }
        }
        
        return false;
        
      case String:
        for (var i = right.length - 1; i >= 0; --i) {
          if (this.compare(left, XPathFunction.stringValueOf(right[i]))) {
            return true;
          }
        }
        
        return false;
        
      case Boolean:
        return this.compare(left, XPath.BOOLEAN_FUNCTION.evaluate(right));
    }
  }
  
  return this.compare(left, right);
};


XPathBinaryExpression.EqualityOperator = function(handler) {
  this.handler = handler;
};

XPathBinaryExpression.EqualityOperator.inherits(XPathBinaryExpression.ComparisonOperator);

XPathBinaryExpression.EqualityOperator.prototype.compare = function(left, right) {
  if (instanceOf(left, Boolean) || instanceOf(right, Boolean)) {
    left  = XPath.BOOLEAN_FUNCTION.evaluate(left);
    right = XPath.BOOLEAN_FUNCTION.evaluate(right);
  }
  else if (instanceOf(left, Number) || instanceOf(right, Number)) {
    left  = XPath.NUMBER_FUNCTION.evaluate(left);
    right = XPath.NUMBER_FUNCTION.evaluate(right);
  }
  else {
    // Both left and right are strings.
  }
  
  return this.handler(left, right);
};


XPathBinaryExpression.RelationalOperator = function(handler) {
  this.handler = handler;
};

XPathBinaryExpression.RelationalOperator.inherits(XPathBinaryExpression.ComparisonOperator);

XPathBinaryExpression.RelationalOperator.prototype.compare = function(left, right) {
  left  = XPath.NUMBER_FUNCTION.evaluate(left);
  right = XPath.NUMBER_FUNCTION.evaluate(right);
  
  return this.handler(left, right);
};


XPathBinaryExpression.ArithmeticOperator = function(handler) {
  this.handler = handler;
};

XPathBinaryExpression.ArithmeticOperator.prototype.evaluate = function(left, right) {
  left  = XPath.NUMBER_FUNCTION.evaluate(left);
  right = XPath.NUMBER_FUNCTION.evaluate(right);

  return this.handler(left, right);  
};

XPathBinaryExpression.prototype.operators = {
  "|":   new XPathBinaryExpression.UnionOperator     (),
  
  "or":  new XPathBinaryExpression.BooleanOperator   (function(left, right) { return left || right; }),
  "and": new XPathBinaryExpression.BooleanOperator   (function(left, right) { return left && right; }),
  "=":   new XPathBinaryExpression.EqualityOperator  (function(left, right) { return left == right; }),
  "!=":  new XPathBinaryExpression.EqualityOperator  (function(left, right) { return left != right; }),
  "<=":  new XPathBinaryExpression.RelationalOperator(function(left, right) { return left <= right; }),
  "<":   new XPathBinaryExpression.RelationalOperator(function(left, right) { return left <  right; }),
  ">=":  new XPathBinaryExpression.RelationalOperator(function(left, right) { return left >= right; }),
  ">":   new XPathBinaryExpression.RelationalOperator(function(left, right) { return left >  right; }),

  "+":   new XPathBinaryExpression.ArithmeticOperator(function(left, right) { return left +  right; }),
  "-":   new XPathBinaryExpression.ArithmeticOperator(function(left, right) { return left -  right; }),
  "*":   new XPathBinaryExpression.ArithmeticOperator(function(left, right) { return left *  right; }),
  "div": new XPathBinaryExpression.ArithmeticOperator(function(left, right) { return left /  right; }),
  "mod": new XPathBinaryExpression.ArithmeticOperator(function(left, right) { return left %  right; })
};

// --- xpath/locationPath.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new location path, which is a series of steps in a path, each step
// searching for particular nodes along an axis.
XPathLocationPath = function(isAbsolute, steps) {
  this.isAbsolute = isAbsolute;
  this.steps      = steps;
};

// A location path can be evaluated as an expression.
XPathLocationPath.inherits(XPathExpression);


// Evaluates the location path starting from the nodes in the specified
// node-set, returning the set of nodes selected by the path.
//
// Parameters:
//     nodeSet: The initial node-set.
//
// Return value:
//     The node-set obtained by evaluating the location path starting at the
//     initial node-set.
XPathLocationPath.prototype.filter = function(context, nodeSet) {
  for (var i = 0; i < this.steps.length; ++i) {
    nodeSet = this.steps[i].filter(context, nodeSet);
  }
  
  return nodeSet;
};

// Returns the set of nodes selected by the path starting from either the
// context node if the path is relative, or the document root if the path is
// absolute.
//
// Parameters:
//     context: The current evaluation context.
//
// Return value:
//     The nodes selected by the path.
XPathLocationPath.prototype.evaluate = function(context) {
  var contextNode;
  
  if (context.node == null) {
    contextNode = null;
  }
  else if (this.isAbsolute) {
    contextNode = (context.node.nodeType == 9)
      ? context.node
      : context.node.ownerDocument;
  }
  else {
    contextNode = context.node;
  }
    
  return this.filter(context, new NodeSet([contextNode]));
};

// --- xpath/step.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new path step, which is the part between a pair of slashes in an
// XPath expression.
//
// Parameters:
//    axis:       The axis along which to select nodes.
//    nodeTest:   The node test at this step.
//    predicates: An array of predicates to apply to filter the nodes from the
//                node test.
function XPathStep(axis, nodeTest, predicates) {
  this.axis       = axis;
  this.nodeTest   = nodeTest;
  this.predicates = predicates;
};

// Filters the nodes in the specified node-set, creating a new node-set.
//
// Parameters:
//     context: The current evaluation context.
//     nodeSet: The node-set to filter.
XPathStep.prototype.filter = function(context, nodeSet) {
  nodeSet = this.axis    .filter(nodeSet);
  nodeSet = this.nodeTest.filter(context, nodeSet, this.axis);
  
  for (var i = 0; i < this.predicates.length; ++i) {
    nodeSet = this.predicates[i].filter(context, nodeSet, this.axis.direction);
  }
  
  return nodeSet;
};

// --- xpath/predicate.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XPath predicate--i.e., a bracketed expression.
function XPathPredicate(expression) {
  this.expression = expression;
};

// Filters the current node-set through the filter. If the filter expression is
// a boolean expression, only nodes satisfying the expression are kept. If the
// filter expression yields a number, then only the node with that index is kept.
//
// Parameters:
//     context:   The current evaluation context.
//     nodeSet:   The node-set to filter.
//     direction: The direction of the current axis, either
//                XPathAxis.Direction.FORWARD or XPathAxis.Direction.REVERSE.
//
// Return value:
//     A new node-set containing only the matching nodes.
XPathPredicate.prototype.filter = function(context, nodeSet, direction) {
  var result = new NodeSet();
  var length = nodeSet.length;
  
  for (var i = 0; i < length; ++i) {
    var filterContext = context.clone();

    filterContext.node     = nodeSet[i];
    filterContext.size     = length;
    filterContext.position = (direction == XPathAxis.Direction.FORWARD ? i + 1 : length - i);
    
    var value   = this.expression.evaluate(filterContext);
    var matched = (instanceOf(value, Number))
                    ? value == filterContext.position
                    : XPath.BOOLEAN_FUNCTION.evaluate(value);
                    
    if (matched) {
      result.addUnique(nodeSet[i]);
    }
  }

  return result;
};

// --- xpath/function.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Generic interface for all XPath functions. Functions take any number of
// parameters and return either a string, number, boolean, or node.
//
// To call the function, you can either use call() or call evaluate() directly
// with the parameters to the function.
//
// Parameters:
//    body:          The body of the function.
//    acceptContext: If true, then the context is passed as the first parameter
//                   to the function. When declaring your own functions, it is
//                   recommended that you use XPathFunction.NO_CONTEXT or
//                   XPathFunction.REQUIRE_CONTEXT.
//    defaultTo:     Optional. If given, specifies what the default argument to
//                   the function should be if no arguments are given. Can be
//                   either XPathFunction.DefaultTo.CONTEXT_NODE or
//                   XPathFunction.DefaultTo.CONTEXT_STRING, in which case the
//                   default argument is the value of the string() function
//                   applied to the context node.
function XPathFunction(body, acceptContext, defaultTo) {
  this.evaluate      = body;
  this.acceptContext = acceptContext;
  this.defaultTo     = defaultTo;
};

XPathFunction.Context                   = { };
XPathFunction.Context.NONE              = false;
XPathFunction.Context.REQUIRED          = true;

XPathFunction.DefaultTo                 = { };
XPathFunction.DefaultTo.NOTHING         = null;
XPathFunction.DefaultTo.CONTEXT_NODE    = 0;
XPathFunction.DefaultTo.CONTEXT_NODESET = 1;
XPathFunction.DefaultTo.CONTEXT_STRING  = 2;

// Call the function. You can call evaluate() directly if you know the signature
// of the function being called.
//
// Parameters:
//     context:   The function call context.
//     arguments: An array containing the arguments to the function, if any.
//
// Return value:
//     The return value of the function.
XPathFunction.prototype.call = function(context, arguments) {
  // If there were no arguments given, see if the function accepts a default argument.
  if (arguments.length == 0) {
    switch (this.defaultTo) {
      case XPathFunction.DefaultTo.CONTEXT_NODE:
        if (context.node != null) {
          arguments = [context.node];
        }
        
        break;
        
      case XPathFunction.DefaultTo.CONTEXT_NODESET:
        if (context.node != null) {
          arguments = [new NodeSet([context.node])];
        }
        
        break;

      case XPathFunction.DefaultTo.CONTEXT_STRING:
        arguments = [XPath.STRING_FUNCTION.evaluate(new NodeSet([context.node]))];
        break;

      default:
        break;
    }
  }
  
  if (this.acceptContext) {
    arguments.unshift(context);
  }

  return this.evaluate.apply(null, arguments);
};
 
 
XPathFunction.stringValueOf = function(node) {
  switch (node.nodeType) {
    case 1:   // Element
    case 9:   // Document
    case 11:  // Document fragment
      var string = "";

      for (var child = node.firstChild; child != null; child = child.nextSibling) {
        switch (child.nodeType) {
          case 1: // Element
          case 3: // Text
          case 4: // CDATA section
            string += XPathFunction.stringValueOf(child);
            break;
            
          default:
            break;
        }
      }

      return string;

    case 2:   // Attribute
    case 3:   // Text
    case 4:   // CDATA section
    case 8:   // Comment
    case 7:   // Processing instruction
      return node.nodeValue;

    case 5:   // Entity reference
    case 6:   // Entity
    case 10:  // Document type
    case 12:  // Notation
      throw new XmlException("Unexpected node type: " + node.nodeType + " [" + node.nodeName + "]");
  }
};

// --- xpath/functionResolver.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XPathFunctionResolver() {
};

// Returns the function with the specified QName, or null if no such function
// exists.
//
// Parameters:
//     qName: The QName of the function to look up.
//
// Return value:
//     An XPathFunction, or null if there is no such function.
XPathFunctionResolver.prototype.lookupFunction = function(qName) {
};



function XPathFunctionMap() {
  this.functions = { };
};

XPathFunctionMap.inherits(XPathFunctionResolver);

XPathFunctionMap.prototype.lookupFunction = function(qName) {
  return this.functions[qName.toString()];
};

XPathFunctionMap.prototype.registerFunction = function(qName, func) {
  this.functions[qName.toString()] = func;
};

XPathFunctionMap.prototype.toString = function() {
  var string = "";
  
  for (var qName in this.functions) {
    string += qName + ": " + this.functions[qName] + "\n";
  }
  
  return string;
};

// --- xpath/coreFunctions.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


XPath.LAST_FUNCTION = new XPathFunction(
  function(context) {
    return context.size;
  },
  
  XPathFunction.Context.REQUIRED
);


XPath.POSITION_FUNCTION = new XPathFunction(
  function(context) {
    return context.position;
  },

  XPathFunction.Context.REQUIRED
);


XPath.COUNT_FUNCTION = new XPathFunction(
  function(nodeSet) {
    return nodeSet.length;
  },
  
  XPathFunction.Context.NONE
);

XPath.CURRENT_FUNCTION = new XPathFunction(
  function(context) {
    return new NodeSet([context.currentNode]);
  },
  
  XPathFunction.Context.REQUIRED
);


XPath.ID_FUNCTION = new XPathFunction(
  function(context, object) {
    var result = new NodeSet();
    
    if (instanceOf(object, NodeSet)) {
      for (var i = 0; i < object.length; ++i) {
        result.addAll(XPath.ID_FUNCTION.evaluate(context, object[i]));
      }
    }
    else if (context.node != null) {
      var ids = XPath.STRING_FUNCTION.evaluate(object).split(/\s+/);
      
      for (var i = 0; i < ids.length; ++i) {
        result.add(context.node.ownerDocument.getElementById(ids[i]));
      }
    }
    
    return result;
  },
  
  XPathFunction.Context.REQUIRED
);


XPath.LOCAL_NAME_FUNCTION = new XPathFunction(
  function(nodeSet) {
    if (nodeSet.length == 0) {
      return "";
    }
    
    return nodeSet[0].nodeName.replace(/^.*:/, "");
  },
  
  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_NODESET
);


XPath.NAMESPACE_URI_FUNCTION = new XPathFunction(
  function(nodeSet) {
    if (nodeSet.length == 0) {
      return "";
    }
    
    return xmlNamespaceURI(nodeSet[0]);
  },
  
  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_NODESET
);


XPath.NAME_FUNCTION = new XPathFunction(
  function(nodeSet) {
    if (nodeSet.length == 0) {
      return "";
    }
    
    return nodeSet[0].nodeName;
  },

  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_NODESET
);


XPath.STRING_FUNCTION = new XPathFunction(
  function(object) {
    assert(object != null, "object is null");
    
    switch (object.constructor) {
      case String:  return object;
      case Boolean: return object ? "true" : "false";
      case NodeSet: return object.length > 0 ? XPathFunction.stringValueOf(object[0]) : "";
      
      case Number:
        var string = object.toString();
        
        // Try to round the number a bit better if toString() returns something like
        // 102.91000000000001.
        if (string.match(/^(\d+\.\d*?)0{6,}\d$/)) {
          string = RegExp.$1;
        }
        else if (string.match(/^(\d+\.\d*?)(\d)9{6,}\d$/)) {
          string = RegExp.$1 + (+RegExp.$2 + 1);
        }
        
        return string;
    }
  },

  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_NODESET
);


XPath.CONCAT_FUNCTION = new XPathFunction(
  function() {
    var string = "";
    
    for (var i = 0; i < arguments.length; ++i) {
      string += XPath.STRING_FUNCTION.evaluate(arguments[i]);
    }
    
    return string;
  },
  
  XPathFunction.Context.NONE
);


XPath.STARTS_WITH_FUNCTION = new XPathFunction(
  function(string, prefix) {
    string = XPath.STRING_FUNCTION.evaluate(string);
    prefix = XPath.STRING_FUNCTION.evaluate(prefix);
    
    return string.indexOf(prefix) == 0;
  },
  
  XPathFunction.Context.NONE
);


XPath.CONTAINS_FUNCTION = new XPathFunction(
  function(string, substring) {
    string    = XPath.STRING_FUNCTION.evaluate(string);
    substring = XPath.STRING_FUNCTION.evaluate(substring);
    
    return string.indexOf(substring) != -1;
  },
  
  XPathFunction.Context.NONE
);


XPath.SUBSTRING_BEFORE_FUNCTION = new XPathFunction(
  function(string, substring) {
    string    = XPath.STRING_FUNCTION.evaluate(string);
    substring = XPath.STRING_FUNCTION.evaluate(substring);
    
    return string.substring(0, string.indexOf(substring));
  },
  
  XPathFunction.Context.NONE
);


XPath.SUBSTRING_AFTER_FUNCTION = new XPathFunction(
  function(string, substring) {
    string    = XPath.STRING_FUNCTION.evaluate(string);
    substring = XPath.STRING_FUNCTION.evaluate(substring);
    
    var index = string.substring(substring);
    
    if (index == -1) {
      return "";
    }
    
    return string.substring(index + substring.length);
  },
  
  XPathFunction.Context.NONE
);


XPath.SUBSTRING_FUNCTION = new XPathFunction(
  function(string, index, length) {
    string = XPath.STRING_FUNCTION.evaluate(string);
    index  = XPath.NUMBER_FUNCTION.evaluate(index);
    length = XPath.NUMBER_FUNCTION.evaluate(length);
    
    return string.substr(
      XPath.ROUND_FUNCTION.evaluate(index) - 1,
      XPath.ROUND_FUNCTION.evaluate(length)
    );
  },
  
  XPathFunction.Context.NONE
);


XPath.STRING_LENGTH_FUNCTION = new XPathFunction(
  function(string) {
    string = XPath.STRING_FUNCTION.evaluate(string);

    return string.length;
  },
  
  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_STRING
);


XPath.NORMALIZE_SPACE_FUNCTION = new XPathFunction(
  function(string) {
    string = XPath.STRING_FUNCTION.evaluate(string);

    return string.replace(/^\s+|\s+$/g, "")
                 .replace(/\s+/,        " ");
  },
  
  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_STRING
);


XPath.TRANSLATE_FUNCTION = new XPathFunction(
  function(string, from, to) {
    string = XPath.STRING_FUNCTION.evaluate(string);
    from   = XPath.STRING_FUNCTION.evaluate(from);
    to     = XPath.STRING_FUNCTION.evaluate(to);
    
    var result = [];
    
    for (var i = 0; i < string.length; ++i) {
      var index = from.indexOf(string.charAt(i));
      
      if (index == -1) {
        result.push(string.charAt(i));
      }
      else {
        result.push(to.charAt(index));
      }
    }
    
    return result.join("");
  },
  
  XPathFunction.Context.NONE
);


XPath.BOOLEAN_FUNCTION = new XPathFunction(
  function(object) {
    switch (object.constructor) {
      case String:  return object != "";
      case Number:  return object != 0 && !isNaN(object);
      case Boolean: return object;
      case NodeSet: return object.length > 0;
    }
  },

  XPathFunction.Context.NONE
);


XPath.NOT_FUNCTION = new XPathFunction(
  function(condition) {
    condition = XPath.BOOLEAN_FUNCTION.evaluate(condition);
    
    return !condition;
  },
  
  XPathFunction.Context.NONE
);


XPath.TRUE_FUNCTION  = new XPathFunction(function() { return true;  });
XPath.FALSE_FUNCTION = new XPathFunction(function() { return false; });


XPath.LANG_FUNCTION = new XPathFunction(
  function(context, language) {
    language = XPath.STRING_FUNCTION.evaluate(language);
    
    // Find the nearest xml:lang attribute.
    for (var node = context.node; node != null; node = node.parentNode) {
      if (typeof(node.attributes) == "undefined") {
        continue;
      }
      
      var xmlLang = node.attributes.getNamedItemNS(XmlNamespaces.XML, "lang");
      
      if (xmlLang != null) {
        xmlLang  = xmlLang.value.toLowerCase();
        language = language     .toLowerCase();
        
        return xmlLang.indexOf(language) == 0
          && (language.length == xmlLang.length || language.charAt(xmlLang.length) == '-');
      }
    }
    
    // Didn't find an xml:lang attribute.
    return false;
  },
  
  XPathFunction.Context.REQUIRED
);


XPath.NUMBER_FUNCTION = new XPathFunction(
  function(object) {
    switch (object.constructor) {
      case String:  return object.match(/^\s*-?(\d+(\.\d+)?|\.\d+)*\s*$/) ? Number(object) : Number.NaN;
      case Number:  return object;
      case Boolean: return object ? 1 : 0;
      case NodeSet: return XPath.NUMBER_FUNCTION.evaluate(XPath.STRING_FUNCTION.evaluate(object));
    }
  },
  
  XPathFunction.Context.NONE,
  XPathFunction.DefaultTo.CONTEXT_NODESET
);


XPath.SUM_FUNCTION = new XPathFunction(
  function(nodeSet) {
    var sum = 0;
    
    for (var i = 0; i < nodeSet.length; ++i) {
      sum += XPath.NUMBER_FUNCTION.evaluate(XPathFunction.stringValueOf(nodeSet[i]));
    }
    
    return sum;
  },
  
  XPathFunction.Context.NONE
);


XPath.FLOOR_FUNCTION = new XPathFunction(
  function(number) {
    number = XPath.NUMBER_FUNCTION.evaluate(number);
    
    return Math.floor(number);
  },
  
  XPathFunction.Context.NONE
);


XPath.CEILING_FUNCTION = new XPathFunction(
  function(number) {
    number = XPath.NUMBER_FUNCTION.evaluate(number);

    return Math.ceil(number);
  },
  
  XPathFunction.Context.NONE
);


XPath.ROUND_FUNCTION = new XPathFunction(
  function(number) {
    number = XPath.NUMBER_FUNCTION.evaluate(number);

    return Math.round(number);
  },
  
  XPathFunction.Context.NONE
);



XPath.CORE_FUNCTIONS = new XPathFunctionMap();

XPath.CORE_FUNCTIONS.registerFunction(new QName("last"),             XPath.LAST_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("position"),         XPath.POSITION_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("count"),            XPath.COUNT_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("current"),          XPath.CURRENT_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("id"),               XPath.ID_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("local-name"),       XPath.LOCAL_NAME_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("namespace-uri"),    XPath.NAMESPACE_URI_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("name"),             XPath.NAME_FUNCTION);
                                                                    
XPath.CORE_FUNCTIONS.registerFunction(new QName("string"),           XPath.STRING_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("concat"),           XPath.CONCAT_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("starts-with"),      XPath.STARTS_WITH_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("contains"),         XPath.CONTAINS_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("substring-before"), XPath.SUBSTRING_BEFORE_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("substring-after"),  XPath.SUBSTRING_AFTER_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("substring"),        XPath.SUBSTRING_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("string-length"),    XPath.STRING_LENGTH_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("normalize-space"),  XPath.NORMALIZE_SPACE_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("translate"),        XPath.TRANSLATE_FUNCTION);

XPath.CORE_FUNCTIONS.registerFunction(new QName("boolean"),          XPath.BOOLEAN_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("not"),              XPath.NOT_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("true"),             XPath.TRUE_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("false"),            XPath.FALSE_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("lang"),             XPath.LANG_FUNCTION);

XPath.CORE_FUNCTIONS.registerFunction(new QName("number"),           XPath.NUMBER_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("sum"),              XPath.SUM_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("floor"),            XPath.FLOOR_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("ceiling"),          XPath.CEILING_FUNCTION);
XPath.CORE_FUNCTIONS.registerFunction(new QName("round"),            XPath.ROUND_FUNCTION);

XPathContext.prototype.functionResolvers.push(XPath.CORE_FUNCTIONS);

// --- xforms/exception.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Exception thrown when an error is encountered while loading the XForms
// document.
//
// Parameters:
//     badNode: The invalid node in the XML document.
//     message: An error message.
//     cause:   The exception that caused this exception to be thrown.                                                      
function XFormException(badNode, message, cause) {
  this.badNode = badNode;
  this.message = message;
  this.cause   = cause;
};

XFormException.prototype.toString = function() {
  var message = this.message;
  
  if (this.cause) {
    message += "\n" + this.cause;
  }
  
  return message;
};

// --- xforms/parser.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XFormParser() {
  this.bindings = [];
};

XFormParser.prototype.parse = function(xmlDocument) {
  this.parseXForm(xmlDocument.documentElement);
};


XFormParser.prototype.stringValue = function(element, attributeName, defaultValue) {
  assert(element          != null, "element is null");
  assert(element.nodeType == 1,    "element.nodeType != 1");
  assert(attributeName    != "",   'attributeName is ""');
  
  var attribute = element.attributes.getNamedItem(attributeName);
  
  if (attribute != null) {
    return attribute.value;
  }
  else {
    if (typeof(defaultValue) == "undefined") {
      throw new XFormException(
        element,
        
        "<" + element.nodeName + "/> element is missing the required @" + attributeName +
        " attribute."
      );
    }

    return defaultValue;
  }
};

XFormParser.prototype.booleanValue = function(element, attributeName, defaultValue) {
  var value = this.stringValue(element, attributeName, defaultValue);
  
  switch (value) {
    case "true":  case "1": return true;
    case "false": case "0": return false;
    
    case null:              return null;
    
    default:
      throw new XFormException(
        element.attributes.getNamedItem(attributeName),
        
        "@" + attributeName + " attribute on <" + element.tagName +
        '/> does not have a valid boolean value: "' + value + '".'
      );
  }
};

XFormParser.prototype.listValue = function(element, attributeName, defaultList) {
  var value = this.stringValue(element, attributeName, defaultList);
  
  if (value == null) {
    return null;
  }
  
  var list = value
    .replace(/^\s+|\s+$/g, "")
    .replace(/\s+/, " ")
    .split(" ");
    
  if (list.length == 1 && list[0] == "") {
    list = [];
  }
  
  return list;
};

XFormParser.prototype.xpathValue = function(element, attributeName, defaultXPath) {
  var xpath = this.stringValue(element, attributeName, defaultXPath);
  
  if (xpath == null) {
    return null;
  }
  
  try {
    return new XPath(xpath, element);
  }
  catch (exception) {
    throw new XFormException(
      element.attributes.getNamedItem(attributeName),

      "@" + attributeName + " attribute on <" + element.nodeName +
      "/> element is not a valid XPath: " + xpath + ".",
      
      exception
    );
  }
};


XFormParser.prototype.getLabelElement = function(parentElement) {
  var labelElement = parentElement.firstChild;
  
  if (labelElement == null) {
    return null;
  }
  
  while (labelElement.nodeType != 1) {
    labelElement = labelElement.nextSibling;
    
    if (labelElement == null) {
      return null;
    }
  }
  
  if (labelElement.nodeName.replace(/^.*:/, "") != "label" ||
      labelElement.namespaceURI != XmlNamespaces.XFORMS)
  {
    return null;
  }
  
  return labelElement;
};


// --- xforms/initialize.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.

new EventListener(window, "load", "default", functionCall(monitor, function() {
  // Check for any other client XForms processors. If there is one, don't load
  // FormFaces. This code was written by Orion Ifland of Trinity Western
  // University.
  
  // Check for the xforms.xpi plugin on a Mozilla browser.
  if (document.implementation && document.implementation.hasFeature &&
      document.implementation.hasFeature("org.w3c.xforms.dom", "1.0")) {
    return;
  }
  // Check for the Novell ActiveX control in Internet Explorer.
  else if (navigator.userAgent.indexOf("nxforms") != -1) {
    // Novell does not remove itself from the user-agent string when you uninstall
    // the plug-in, so this check is unreliable.
    
    // return;
  }
  
  XForm.initialize();
}));

// --- xforms/xform.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


var xform = null;

function XForm(xmlDocument) {
  this.xmlDocument      = xmlDocument;

  this.models           = [];
  this.controls         = [];
  this.actions          = [];
  this.events           = [];

  this.objectsById      = {};
  this.objectsByNode    = [];

  this.bindings         = [];
  this.waitingListeners = [];
};

(function() {
  var start = null;
  var end   = null;

  XForm.startTimer = function() {
    start = new Date().getTime();
  };

  XForm.alertTime = function(message) {
    end   = new Date().getTime();

    alert(message.replace(/%t/g, (end - start) / 1000));
    
    start = new Date().getTime();
  };
}) ();

XForm.initialize = function() {
  //var start = new Date().getTime();
  
  // executeSequentially is used to improve perceived performance by giving a
  // chance for the browser to redraw the page and respond to user input between
  // function calls. It also resets the browser timeouts.
  executeSequentially(
    function() {
      var overlay = document.createElement("div");
      var glass   = document.createElement("div");
      var message = document.createElement("span");

      overlay.id            = "-ff-overlay";
      glass  .style.cssText = "position: absolute; top: 0; left: 0; width: 100%; height: 100%; "
                            + "background: white; opacity: 0.75; filter: alpha(opacity=75); overflow: hidden";
      message.style.cssText = "font: 24pt bold 'Helvetica', sans-serif; background: gray; color: white; "
                            + "padding: 4px 8px; border: 9px double white";

      document.body.appendChild(overlay);
      overlay      .appendChild(glass);
      message      .appendChild(document.createTextNode("Loading..."));
      
      if (userAgent.isInternetExplorer) {
        message.style.position   = "absolute";
        message.style.visibility = "hidden";
        
        setTimeout(function() {
          message.style.top        = ((document.documentElement.clientHeight - message.clientHeight) * 0.40) + "px";
          message.style.left       = ((document.documentElement.clientWidth  - message.clientWidth)  * 0.50) + "px";
          message.style.visibility = "visible";
        }, 1);
        
        overlay.appendChild(message);

        document.body.style.cssText = "position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0";
      }
      else {
        var outside = document.createElement("div");
        var middle  = document.createElement("div");
        var inside  = document.createElement("div");
        
        // Vertical centering courtesy of http://www.jakpsatweb.cz/css/css-vertical-center-solution.html.
        outside.style.cssText = "position: absolute; top: 0; left: 0; width: 100%; height: 100%";
        middle .style.cssText = "display: table; width: 100%; height: 100%; overflow: hidden";
        inside .style.cssText = "display: table-cell; vertical-align: middle; text-align: center";

        overlay.appendChild(outside);
        outside.appendChild(middle);
        middle .appendChild(inside);
        inside .appendChild(message);
      }
    },

    function() {
      // Parse the document.
      if (xform == null) {
        xform = new XForm(xmlLoadURI(window.location.href, true));
      }
    },

    function() {
      XForm.startTimer();
	  xform.THIRD_PARTY_FUNCTIONS = XForms.THIRD_PARTY_FUNCTIONS;
      new XFormParser().parse(xform.xmlDocument);

      //XForm.alertTime("Parsed in %t seconds.");
    },

    function() {
      xform.render();

      //XForm.alertTime("Page rendered in %t seconds.");
    },

    function() {
      for (var i = 0; i < xform.models.length; ++i) {
        var model = xform.models[i];

        model.rebuild    ();
        model.recalculate();
        model.revalidate ();
        model.refresh    ();
//
// make sure that all elements to the DOM are really created
//
        setTimeout(function(){}, 0); 

        XmlEvent.dispatch(model.htmlNode, "xforms-model-construct");
        XmlEvent.dispatch(model.htmlNode, "xforms-model-construct-done");
      }

      //XForm.alertTime("Models rebuilt in %t seconds.");

      for (var i = 0; i < xform.models.length; ++i) {
        XmlEvent.dispatch(xform.models[i].htmlNode, "xforms-ready");
      }
      
      //alert("Time is: " + (new Date().getTime() - start));
    },

    function() {
      var overlay = document.getElementById("-ff-overlay");
      
      if (overlay != null) {
        document.body.removeChild(overlay);
      }
      
      if (userAgent.isInternetExplorer) {
        document.body.style.cssText = "";//"position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0";
      }
    }
  );
};


XFormParser.prototype.parseXForm = function(element) {
                   this    .parseModels  (element); // XForm.alertTime("Models parsed in %t seconds.");
  xform.controls = this    .parseControls(element); // XForm.alertTime("Controls parsed in %t seconds.");
  xform.actions  = this    .parseActions (element); // XForm.alertTime("Actions parsed in %t seconds.");
  xform.events   = XmlEvent.parseEvents  (element); // XForm.alertTime("Events parsed in %t seconds.");
};


XForm.prototype.render = function() {
  this.renderedNodes = [];

  this.renderHead();
  this.renderBody();
};

// Gets the source XML node for the given rendered HTML node.
//
// Parameters:
//     htmlNode: A rendered HTML node.
//
// Return value:
//     The source XML node.
XForm.prototype.getXmlNode = function(htmlNode) {
  for (var i = this.renderedNodes.length - 1; i >= 0; --i) {
    var renderedNodes = this.renderedNodes[i];

    if (renderedNodes[1] == htmlNode) {
      return renderedNodes[0];
    }
  }

  return null;
};

// Gets the rendered HTML node for the given XForm object or XML node.
//
// Parameters:
//     xmlNode: A node from the XForms source XML document.
//
// Return value:
//     The HTML node that was rendered.
XForm.prototype.getHtmlNode = function(xmlNode) {
  for (var i = this.renderedNodes.length - 1; i >= 0; --i) {
    var renderedNodes = this.renderedNodes[i];

    if (renderedNodes[0] == xmlNode) {
      return renderedNodes[1];
    }
  }

  return null;
};

// Gets the rendered HTML nodes for the given XML node.
//
// Parameters:
//     xmlNode: A node from the XForms source XML document.
//
// Return value:
//     A list of HTML nodes that were rendered.
XForm.prototype.getHtmlNodes = function(xmlNode) {
  var htmlNodes = [];

  for (var i = this.renderedNodes.length - 1; i >= 0; --i) {
    var renderedNodes = this.renderedNodes[i];

    if (renderedNodes[0] == xmlNode) {
      htmlNodes.push(renderedNodes[1]);
    }
  }

  return htmlNodes;
};

XForm.prototype.getObjectForHtmlNode = function(htmlNode) {
  return this.getObjectByNode(this.getXmlNode(htmlNode));
};


XForm.prototype.renderHead = function() {
  var head = document.getElementsByTagName("head")[0];

  // Remove the entire contents of the page body.
  var headChild = head.firstChild;

  while (headChild != null) {
    if (headChild.nodeName.match(/:/)) {
      var badChild = headChild;

      headChild = headChild.nextSibling;

      head.removeChild(badChild);
    }
    else {
      headChild = headChild.nextSibling;
    }
  }

  var modelLen = this.models.length;
  for (var i = 0; i < modelLen; ++i) {
    head.appendChild(this.models[i].render());
  }
};

// Render the XML <body/> to the HTML <body/>.
XForm.prototype.renderBody = function() {
  var htmlBody   = document.body;
  var xmlBody      = null;
  
  for (var child = this.xmlDocument.documentElement.firstChild; child != null; child = child.nextSibling) {
    if (child.nodeType == 1 && child.nodeName.replace(/^.*:/, "") == "body" && child.namespaceURI == XmlNamespaces.XHTML) {
      xmlBody = child;
      break;
    }
  }
  
  // Remove the entire contents of the page body.
  for (var i = htmlBody.childNodes.length - 1; i >= 0; --i) {
    // Don't remove the "Loading..." message.
    if (htmlBody.childNodes.item(i).id == "-ff-overlay") {
      continue;
    }
    
    htmlBody.removeChild(htmlBody.childNodes.item(i));
  }

  if (xmlBody == null) {
    alert("Unable to load document; <body> not found:\n\n" + xmlSerialize(this.xmlDocument));
    return;
  }

  this.renderChildNodes(xmlBody, htmlBody);

  for (var i = 0; i < this.controls.length; ++i) {
    var control  = this.controls[i];
    var htmlNode = xform.getHtmlNode(control.xmlNode);

    if (control.getModel() != null) {
      control.getModel().controls.push(control);
    }
    else {
      control.instance = control.instantiate(null, null, 0, htmlNode);
    }
  }
};

XForm.prototype.renderChildNodes = function(xmlParent, htmlParent) {
  for (var xmlChild = xmlParent.firstChild; xmlChild != null; xmlChild = xmlChild.nextSibling) {
    this.renderNode(xmlChild, htmlParent);
  }
};

// Renders a node from the XML source document, inserting a corresponding node
// into the HTML document. XHTML nodes are copied over directly; XForms nodes
// are replaced by placeholder <span/>s. (At this stage, none of the controls
// are rendered properly. That is done when the model is rebuilt.)
//
// Parameters:
//     xmlNode:    The node to render.
//     htmlParent: The parent node for the new HTML node.
//     htmlBefore: The previous sibling for the new HTML node, or null to append
//                 the new node to the end of the parent node. See the DOM
//                 function Node.insertBefore.
XForm.prototype.renderNode = function(xmlNode, htmlParent, htmlBefore) {
  assert(xmlNode    != null, "xmlNode is null");
  assert(htmlParent != null, "htmlParent is null");

  if (typeof(htmlBefore) == "undefined") {
    htmlBefore = null;
  }

  switch (xmlNamespaceURI(xmlNode)) {
    case "":
    case XmlNamespaces.XHTML:
      // Render an HTML node by importing it to the HTML document.
      var htmlNode = xmlImportNode(document, xmlNode, false);

      htmlParent.insertBefore(htmlNode, htmlBefore);

      // If we're rendering a <table/>, and the table has no <tbody/>, add one.
      // Internet Explorer will not render a table without its <tbody/>.
      if (xmlNode.nodeName.replace(/^.*:/, "") == "table" && xmlNode.namespaceURI == XmlNamespaces.XHTML) {
        var tbody = false;
        for (var child = xmlNode.firstChild; child != null; child = child.nextSibling) {
          if (child.nodeType == 1 && child.nodeName.replace(/^.*:/, "") == "tbody" && child.namespaceURI == XmlNamespaces.XHTML) {
            tbody = true;
            break;
          }
        }
        if (!tbody) {
          htmlNode = htmlNode.appendChild(document.createElement("tbody"));
        }
      }

      // Render the children unless this node has XForms attributes (i.e. repeat-bind,
      // repeat-nodeset, etc.).
      var hasXformsAttribute = false;
      var attributes         = xmlNode.attributes;
      
      if (attributes != null) {
        for (var i = attributes.length - 1; i >= 0; i--) {
          var attribute = attributes.item(i);
          
          if (attribute != null && attribute.namespaceURI == XmlNamespaces.XFORMS) {
            hasXformsAttribute = true;
            break;
          }
        }
      }
      
      if (!hasXformsAttribute) {
        this.renderChildNodes(xmlNode, htmlNode);
      }

      break;

    case XmlNamespaces.XFORMS:
      var htmlNode;

      htmlNode           = document.createElement("span");
      htmlNode.className = "xforms-" + xmlNode.nodeName.replace(/^.*:/, "");

      if (xmlNode.attributes.getNamedItem("id") != null) {
        htmlNode.setAttribute("id", xmlNode.attributes.getNamedItem("id").value);
      }

      htmlParent.insertBefore(htmlNode, htmlBefore);
      break;
  }

  this.nodeHasBeenRendered(xmlNode, htmlNode);
};

XForm.prototype.nodeHasBeenRendered = function(xmlNode, htmlNode) {
  var target   = null;
  var events   = this.getEventsFor(xmlNode);

  for (var i = 0; i < events.length; ++i) {
    (function() {
      var xmlEvent = events[i];
      var handler  = xmlEvent.handler;

      if (!instanceOf(handler, Function)) {
        handler = xform.getObjectByNode(handler);
      }

      new EventListener(htmlNode, xmlEvent.name, xmlEvent.phase, function(event) {
        // If the event target was specified, make sure the event has been fired on that
        // exact target element.
        if (target != null && event.target != target) {
          return;
        }

        if (instanceOf(handler, XFormAction)) {
          handler.execute();
        }
        else {
          handler();
        }

        if (!xmlEvent.defaultAction) {
          event.preventDefault();
        }

        if (!xmlEvent.propagate) {
          event.stopPropagation();
        }
      });
    }) ();
  }

  this.renderedNodes.push([xmlNode, htmlNode]);
};

XForm.prototype.getEventsFor = function(xmlNode) {
  var events   = [];

  for (var i = 0; i < this.events.length; ++i) {
    if (this.events[i].observer == xmlNode) {
      events.push(this.events[i]);
    }
  }

  return events;
};


// Call this function in the setUpPage() function for each unit test suite to
// ensure that the "xform" object is created before the tests are run.
//
//     function setUpPage() {
//       XForm.waitForInitialization();
//     }
XForm.waitForInitialization = function() {
  new EventListener(document.documentElement, "xforms-ready", "default",
    function () {
      setUpPageStatus = "complete";
    }
  );
};

// Checks if the given node is an XForms element.
//
// Parameters:
//     node:     The node to check.
//     tagNames: Optional. If specified, this can be either the expected tag
//               name for the element (a single string), or a list of expected
//               tag names (an array of strings).
XForm.isXFormsElement = function(node, tagNames) {
  if (node.nodeType != 1 || node.namespaceURI != XmlNamespaces.XFORMS) {
    return false;
  }
  
  if (tagNames == null) {
    return true;
  }
  
  var tagName = node.nodeName.replace(/^.*:/, "");
  
  if (typeof(tagNames) == "string") {
    return tagName == tagNames;
  }
  else {
    for (var i = 0; i < tagNames.length; ++i) {
      if (tagName == tagNames[i]) {
        return true;
      }
    }
    
    return false;
  }
};

// Checks if the given node is an XHTML element.
//
// Parameters:
//     node:     The node to check.
//     tagNames: Optional. If specified, this can be either the expected tag
//               name for the element (a single string), or a list of expected
//               tag names (an array of strings).
XForm.isXHtmlElement = function(node, tagNames) {
  if (node.nodeType != 1 || node.namespaceURI != XmlNamespaces.XHTML) {
    return false;
  }
  
  if (tagNames == null) {
    return true;
  }
  
  var tagName = node.nodeName.replace(/^.*:/, "");
  
  if (typeof(tagNames) == "string") {
    return tagName == tagNames;
  }
  else {
    for (var i = tagNames.length - 1; i >= 0; --i) {
      if (tagName == tagNames[i]) {
        return true;
      }
    }
    
    return false;
  }
};


// Define the initialization events.
XmlEvent.define("xforms-model-construct",      "Events", true, false);
XmlEvent.define("xforms-model-construct-done", "Events", true, false);
XmlEvent.define("xforms-ready",                "Events", true, false);

// --- xforms/object.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XForm object, an object that stores the information parsed from
// a node in the XForms document.
//
// Parameters:
//     xmlNode:     The element or other node from which the object was created.
//
//     isCanonical: Is this the canonical object for the node? For example, an
//                  <input/> element might have a corresponding
//                  XFormInputControl and XFormBind object. The
//                  XFormInputControl is the canonical object.
//
//                  TODO: Find a better word to describe this concept.
function XFormObject(xmlNode, isCanonical) {
  // If being called by inherits() method, don't do anything.
  if (arguments.length == 0) {
    return;
  }
  
  assert(xmlNode != null, "xmlNode is null");
  
  this.xmlNode    = xmlNode;
  this.xmlElement = (xmlNode.nodeType == 1) ? xmlNode : null;
  this.htmlNode   = null;
  this.id         = null;
  
  if (isCanonical) {
    xform.objectsByNode.push([xmlNode, this]);
    
    var id = xmlNode.getAttribute("id");

    if (id != null) {
      xform.objectsById[this.id = id] = this;
    }
  }
};

XFormObject.prototype.render = function() {
  this.htmlNode           = document.createElement("span");
  this.htmlNode.className = "xforms-" + this.xmlNode.nodeName.replace(/^.*:/, "");
  
  if (this.id != null) {
    this.htmlNode.id = this.id;
  }
  
  xform.nodeHasBeenRendered(this.xmlNode, this.htmlNode);
  
  this.postRender();
  
  return this.htmlNode;
};

// Called after an object is rendered. Can be overridden to attach event
// handlers and the like to the rendered node.
XFormObject.prototype.postRender = function() {
};


// Finds the object with the specified @id.
//
// Parameters:
//     idref:       The ID of the object to find. This can be either a value or
//                  a DOM attribute.
//     Constructor: If given, the object must be of this type. (Optional.)
//
// Return value:
//     The object with the specified @id.
//
// Exceptions:
//     
XForm.prototype.getObjectById = function(idref, Constructor) {
  assert(idref != null, "idref is null");
  
  if (typeof(idref.value) == "undefined") {
    var object = this.objectsById[idref];
  
    if (!object || Constructor != null && !instanceOf(object, Constructor)) {
      throw new XFormException(null, "Invalid element ID (" + idref + ").");
    }
  }
  else {
    var object = this.objectsById[idref.value];
  
    if (!object || Constructor != null && !instanceOf(object, Constructor)) {
      throw new XFormException(idref,
        "@" + idref.name + " attribute has an invalid element ID (" + idref.value + ")."
      );
    }
  }
  
  return object;
};

XForm.prototype.getObjectByNode = function(xmlNode) {
  var objLen = this.objectsByNode.length;
  
  for (var i = 0; i < objLen; ++i) {
    if (this.objectsByNode[i][0] == xmlNode) {
      return this.objectsByNode[i][1];
    }
  }
  
  assert(false, "No object for node:\n" + xmlSerialize(xmlNode));
};

// --- xforms/submission.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new submission object.
//
// Parameters:
//     element: The element from which the submission was created.
//
//     bind: The submission's bind.
//
//     action: The action URI.
//
//     method: The submission method. One of "post", "put", "get",
//         "multi-part-post", "form-data-post", or "urlencoded-post".
//
//     version: The XML version to be serialized, or null to leave it
//         unspecified.
//
//     indent: Value specifying whether the serializer should add extra white
//         space nodes for readability.
//
//     mediaType: The media type for XML serialization. The type specified must
//         be compatible with application/xml.
//
//     encoding: The character encoding for serialization, or null to leave it
//         unspecified.
//
//     omitXmlDeclaration: Value specifying whether to omit the XML declaration
//         on the serialized instance data or not.
//
//     standalone: Value specifying whether to include a standalone declaration
//         in the serialized XML or not.
//
//     cdataSectionElements: A list of element names to be serialized with CDATA
//         sections.
//
//     replace: Value specifying how the information returned after submit
//         should be applied. One of "all", "instance", or "none".
//
//     separator: The separator character between name/value pairs in URL-
//         encoding.
//
//     includeNamespacePrefixes: If null, all namespace nodes present in the
//         instance data are considered for serialization. Otherwise, specifies
//         a list of namespace prefixes to consider for serialization, in
//         addition to those visibly utilized.
function XFormSubmission(element, bind, action, method, version, indent,
                         mediaType, encoding, omitXmlDeclaration, standalone,
                         cdataSectionElements, replace, separator,
                         includeNamespacePrefixes,
                         instanceID)
{
  assert(bind                 != null, "submission: bind is null");
  assert(action               != null, "submission: action is null");
  assert(method               != null, "submission: method is null");
  assert(mediaType            != null, "submission: mediaType is null");
  assert(cdataSectionElements != null, "submission: cdataSectionElements is null");

  XFormObject.call(this, element, true);
  
  this.bind                     = bind;
  this.action                   = action;
  this.method                   = method;
  this.mediaType                = mediaType;
  this.replace                  = replace;
  this.separator                = separator;
  this.version                  = version;
  this.indent                   = indent;
  this.encoding                 = encoding;
  this.omitXmlDeclaration       = omitXmlDeclaration;
  this.standalone               = standalone;
  this.cdataSectionElements     = cdataSectionElements;
  this.includeNamespacePrefixes = includeNamespacePrefixes;
  if(instanceID)
  {
  	this.instanceID = instanceID;
  	this.replace = "byInstance";
  }

  this.model = null;
};

XFormSubmission.inherits(XFormObject);


XFormParser.prototype.parseSubmissions = function(element) {
  var submissions        = [];
  
  for (var child = element.firstChild; child != null; child = child.nextSibling) {
    if (child.nodeType == 1 && child.nodeName.replace(/^.*:/, "") == "submission" && child.namespaceURI == XmlNamespaces.XFORMS) {
      submissions.push(this.parseSubmission(child));
    }
  }
  
  return submissions;
};

XFormParser.prototype.parseSubmission = function(element) {
  return new XFormSubmission(
    element,
    
    this.parseSubmissionBind                    (element),
    this.parseSubmissionAction                  (element),
    this.parseSubmissionMethod                  (element),
    this.parseSubmissionVersion                 (element),
    this.parseSubmissionIndent                  (element),
    this.parseSubmissionMediaType               (element),
    this.parseSubmissionEncoding                (element),
    this.parseSubmissionOmitXmlDeclaration      (element),
    this.parseSubmissionStandalone              (element),
    this.parseSubmissionCDataSectionElements    (element),
    this.parseSubmissionReplace                 (element),
    this.parseSubmissionSeparator               (element),
    this.parseSubmissionIncludeNamespacePrefixes(element),
    this.parseSubmissionInstanceName            (element)

  );
};

XFormParser.prototype.parseSubmissionBind = function(element) {
  var bindAttribute = element.attributes.getNamedItem("bind");
  
  if (bindAttribute != null) {
    return xform.getObjectById(bindAttribute);
  }
  else {
    return new XFormBind(element, "ref", this.xpathValue(element, "ref", "/"), null, []);
  }
};

XFormParser.prototype.parseSubmissionAction = function(element) {
  return this.stringValue(element, "action");
};

XFormParser.prototype.parseSubmissionInstanceName = function(element) {
  return this.stringValue(element, "instance", null);
};

XFormParser.prototype.parseSubmissionMethod = function(element) {
  var method = this.stringValue(element, "method");

  switch (method) {
    case "post":
    case "put":
    case "get":
    case "multi-part-post":
    case "form-data-post":
    case "urlencoded-post":
    case "internal":
      return method;

    default:
      throw new XFormException(
        element.attributes.getNamedItem("method"),
        "<" + element.tagName + '/> element has an invalid @method: "' + method + '".'
      );
  }
};

XFormParser.prototype.parseSubmissionVersion            = function(element) { return this.stringValue (element, "version",              null);              };
XFormParser.prototype.parseSubmissionIndent             = function(element) { return this.booleanValue(element, "indent",               "false");           };
XFormParser.prototype.parseSubmissionMediaType          = function(element) { return this.stringValue (element, "mediatype",            "application/xml"); };
XFormParser.prototype.parseSubmissionEncoding           = function(element) { return this.stringValue (element, "encoding",             null);              };
XFormParser.prototype.parseSubmissionOmitXmlDeclaration = function(element) { return this.booleanValue(element, "omit-xml-declaration", "false");           };
XFormParser.prototype.parseSubmissionStandalone         = function(element) { return this.booleanValue(element, "standalone",           "false");           };

XFormParser.prototype.parseSubmissionCDataSectionElements = function(element) {
  var elementNames = this.listValue(element, "cdata-section-elements", "");
  var elements     = [];

  var names = elementNames.length;
  for (var i = 0; i < names; i++) {
    elements.push(new QName(elementNames[i], element));
  }
  
  return elements;
};

XFormParser.prototype.parseSubmissionReplace = function(element) {
  var replace = this.stringValue(element, "replace", "all");

  switch (replace) {
    case "all":
    case "instance":
    case "none":
    case "byInstance":
      return replace;

    default:
      throw new XFormException(
        element.attributes.getNamedItem("replace"),
        '<' + element.tagName + '/> has invalid value for @replace: "' + replace + '".'
      );
  }
};

XFormParser.prototype.parseSubmissionSeparator = function(element) {
  return this.stringValue(element, "separator", ";");
};

XFormParser.prototype.parseSubmissionIncludeNamespacePrefixes = function(element) {
  var prefixes = this.listValue(element, "includenamespaceprefixes", null);
  
  if (prefixes == null) {
    return null;
  }

  var allPrefixes = prefixes.length;
  for (var i = 0; i < allPrefixes; i++) {
    if (prefixes[i] == "#default") {
      prefixes[i] = "";
    }
  }

  return prefixes;
};


XFormSubmission.prototype.submit = function() {
  var graph       = this.model.graph;
  var boundNode   = this.bind.defaultBinding.boundNodes[0];
  var boundVertex = graph.getVertex(boundNode, "text");
  
  if (!boundVertex.isValid) {
     status("Bound vertex node is not valid!: " + boundNode + " :: " + boundVertex);
     XmlEvent.dispatch(this.htmlNode, "xforms-submit-error");
     return;
  }
  
  var method = {
    "post":            "POST",
    "get":             "GET",
    "put":             "PUT",
    "multipart-post":  "POST",
    "form-data-post":  "POST",
    "urlencoded-post": "POST",
    "internal":        "INTERNAL"
  } [this.method];
  
  switch (this.method) {
    case "internal":
    try
    {
    	window[this.action](this);
    }
    catch(e)
    {
    	XmlEvent.dispatch(this.htmlNode, "xforms-submit-error");
    }
    	return;
    case "post":
    case "put":
      // 
      var content = xmlSerialize(boundNode, this, function(node) {
        var vertex = graph.getVertex(node, "relevant");
        
        return !vertex || vertex.value;
      });
      
      break;
    
    case "get":
    case "urlencoded-post":
      var elements      = new XPath("descendant-or-self::*[count(node()) = 1 and text()]").evaluate(boundNode);
      var relevantNodes = [];
      var content       = "";
      
      for (var i = 0; i < elements.length; ++i) {
        var vertex = graph.getVertex(elements[i], "relevant");
        
        if (!vertex || vertex.value) {
          relevantNodes.push(elements[i]);
        }
      }
      
      for (var i = 0; i < relevantNodes.length; ++i) {
        if (i > 0) {
          content += this.separator;
        }
        
        content += relevantNodes[i].nodeName.replace(/^.*:/, "");
        content += "=";
        content += escape(relevantNodes[i].firstChild.data);
      }
      
      break;
      
    default:
      assert(false, this.method + " submission method not yet implemented.");
  }
  
  //var request = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
  var request = userAgent.isInternetExplorer ? new ActiveXObject("Microsoft.XMLHTTP") : new XMLHttpRequest(); 
  var self    = this;
  
  request.onreadystatechange = functionCall(monitor, function() {
    if (request.readyState != 4) {
      return;
    }
    
    status("Response: " + request.status + " " + request.statusText);
    status("Headers:  " + (request.getAllResponseHeaders() != null ? request.getAllResponseHeaders() : ""));
    status("Content:  " + request.responseText);
      
    // 200 is the HTTP success status code. 0 is the status code for local files.
    if (request.status != 200 && request.status != 0) {
      status("Status code is = " + request.status);
      XmlEvent.dispatch(self.htmlNode, "xforms-submit-error");
      return;
    }
    
    if (request.responseText != "" && self.replace != "none") {
      switch (self.replace) {
        case "byInstance":
        case "instance":
        var _model = self.bind.model;
          try {
            // Check that the response body is XML.
            var contentType = request.getResponseHeader("Content-Type");
            
            if (!contentType.match(/(^$|[\/+]xml(;.*)?$)/)) {
              status("Non-XML content type: " + contentType);
              
              XmlEvent.dispatch(self.htmlNode, "xforms-submit-error");
              return;
            }
            
            var responseXml = xmlLoadDocument(request.responseText, true);
 // this.replace = instance
 		if(self.replace == "instance" )
 		{                 
            if (boundNode.nodeType == 9) {
              boundNode = boundNode.documentElement;
            }
            
            // If replacing the entire document, don't use replaceChild as that doesn't work in Opera.
            if (boundNode == boundNode.ownerDocument.documentElement) {
              // Find the instance boundNode is from and replace its XML document.
              var instances = self.model.instances.length;
              for (var i = 0; i < instances; i++) {
                var instance = self.model.instances[i];
                
                if (instance.document == boundNode.ownerDocument) {
                  instance.document = responseXml;
                  break;
                }
              }
            }
            else {
              var responseNode = xmlImportNode(boundNode.ownerDocument, responseXml.documentElement, true);
              
              boundNode.parentNode.replaceChild(responseNode, boundNode);
            }
// end of this.replace = instance
		}
		else
		{
			// sel.replace = byInstance
			xform.objectsById[self.instanceID].document = responseXml;
			_model = xform.objectsById[self.instanceID].model;			
		}
            
            XmlEvent.dispatch(_model.htmlNode, "xforms-rebuild");
            XmlEvent.dispatch(_model.htmlNode, "xforms-recalculate");
            XmlEvent.dispatch(_model.htmlNode, "xforms-revalidate");
            XmlEvent.dispatch(_model.htmlNode, "xforms-refresh");

            break;
          }
          catch (exception) {
            status("Exception thrown during submission: " + exception);
            XmlEvent.dispatch(self.htmlNode, "xforms-submit-error");
            return;
          }
          
        case "all":
          try {
            var responseXml = xmlLoadDocument(request.responseText, true);
            
            if (new XPath("boolean(xhtml:html)").evaluate(responseXml)) {
              xform = new XForm(responseXml);
              XForm.initialize();
              
              break;
            }
            
            status("Response is valid XML but not XHTML.");
          }
          catch (exception) {
            status("Failed to parse response as XML (" + exception + ").");
          }
          var head = request.responseText.replace(/[\s\S]*<head[\s\S]?>/i,   "")
                                         .replace(/<\/head[\s\S]?>[\s\S]*/i, "");
          var body = request.responseText.replace(/[\s\S]*<body[\s\S]?>/i,   "")
                                         .replace(/<\/body[\s\S]?>[\s\S]*/i, "");
                                         
          if (userAgent.isInternetExplorer) {
            document.body.innerHTML = body;
          }
          else {
            while (document.documentElement.hasChildNodes()) {
              document.documentElement.removeChild(document.documentElement.firstChild);
            }
          
            document.documentElement.appendChild(document.createElement("head"));
            document.documentElement.appendChild(document.createElement("body"));
            
            document.documentElement.childNodes[0].innerHTML = head;
            document.documentElement.childNodes[1].innerHTML = body;
          }
          status(xmlSerialize(document));
          break;
          
        default:
          assert(false, 'Unrecognized replace option: "' + self.replace + '".');
      }
    }
    
    XmlEvent.dispatch(self.htmlNode, "xforms-submit-done");
  });
  
  try {
    var url = this.action;
    
    if (method == "GET" && content != "") {
      url    += (url.match(/\?/) ? "&" : "?");
      url    += content;
      
      content = "";
    }
    
    status("Request: " + method + " " + url);
    status("Content: " + content);
    
    request.open            (method, url);
    request.setRequestHeader("Content-Type", this.mediaType);
    request.setRequestHeader("If-Modified-Since", "Thu, 1 Jan 1970 00:00:00 GMT");
    request.send            (content);
  }
  catch (exception) {
    status("Unable to submit form to URL " + this.action + ".");

    XmlEvent.dispatch(self.htmlNode, "xforms-submit-error");
  }
};

//Uncomment if doing a full release

XmlEvent.define("xforms-submit", "Events", true, true, function(event) {
  xform.getObjectForHtmlNode(event.target).submit();
});

XmlEvent.define("xforms-submit-done",  "Events", true, false);
XmlEvent.define("xforms-submit-error", "Events", true, false);


// --- xforms/instance.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XFormInstance(element, document, source) {
  XFormObject.call(this, element, true);
  
  this.document = document;
  this.source   = source;
  
  this.model    = null;
};

XFormInstance.inherits(XFormObject);


XFormParser.prototype.parseInstances = function(element) {
  var instances        = [];
  
  for (var child = element.firstChild; child != null; child = child.nextSibling) {
    if (child.nodeType == 1 && child.nodeName.replace(/^.*:/, "") == "instance" && child.namespaceURI == XmlNamespaces.XFORMS) {
      instances.push(this.parseInstance(child));
    }
  }
  
  return instances;
};

XFormParser.prototype.parseInstance = function(element) {
  var srcAttribute = element.attributes.getNamedItem("src");
  var instance     = null;
  
  if (srcAttribute != null) {
    instance = new XFormInstance(element, xmlLoadURI(srcAttribute.value), srcAttribute.value);
  }
  else {
    // We can't just use childNodes since that will include text nodes, comments,
    // etc.
    var instanceNodes = new XPath("*").evaluate(element);
    
    switch (instanceNodes.length) {
      case 0:
        instance = new XFormInstance(element, null, null);
        break;
        
      case 1:
        var instanceDocument = xmlNewDocument(instanceNodes[0]);
        var instanceNode     = instanceDocument.documentElement;
        
        // Copy the namespaces visible on the <instance/> element to the instance
        // document root element.
        var namespaceNodes = new XPath("namespace::*").evaluate(instanceNodes[0]);
        
        var namespaces = namespaceNodes.length;
        for (var i = 0; i < namespaces; ++i) {
          var namespaceNode = namespaceNodes[i];
          
          if (namespaceNode.name == "xmlns:xml") {
            continue;
          }
          
          if (!instanceNode.attributes.getNamedItem(namespaceNode.name)) {
            instanceNode.setAttribute(namespaceNode.name, namespaceNode.value);
          }
        }
        
        instance = new XFormInstance(element, instanceDocument, null);
        break;
      
      default:
        throw new XFormException(
          element,
          "<" + element.tagName + "/> contains more than one root element."
        );
    }
  }
  
  while (element.hasChildNodes()) {
    element.removeChild(element.firstChild);
  }
  
  return instance;
};

// --- xforms/binding.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new bind, either from a <bind/> element or from a element bound
// with a @ref or @nodeset attribute.
//
// Parameters:
//     element:    The element from which this bind was created.
//
//     type:       The bind type, either "ref" or "nodeset".
//     xpath:      The binding expression from the @ref or @nodeset attribute.
//
//     properties: An associative array of model item property XPath
//                 expressions. The expected properties are readOnly, required,
//                 relevant, calculate, and constraint.
function XFormBind(element, type, xpath, properties) {
  XFormObject.call(this, element, properties != null);

  this.type       = type;
  this.xpath      = xpath;
  this.properties = (properties != null) ? properties : { };

  // If "calculate" is given then "readonly" defaults to "true()".
  if (this.properties.calculate && !this.properties.readOnly) {
    this.properties.readOnly = new XPath("true()");
  }

  this.outerBind  = null;
  this.index      = null;
  this.innerBinds = [];

  this.defaultBinding = null;
};

XFormBind.inherits(XFormObject);

XFormBind.prototype.setModel = function(model) {
  this.model       = model;
  var innerBindLen = this.innerBinds.length;

  //for (var i in this.innerBinds) {
  for (var i = 0; i < innerBindLen; ++i) {
    this.innerBinds[i].setModel(model);
  }
};

XFormBind.prototype.toString = function() {
  var string = "<bind " + this.type + '="' + this.xpath + '"';

  for (var property in this.properties) {
    if (this.properties[property] != null) {
      string += " " + property + '="' + this.properties[property] + '"';
    }
  }

  if (this.innerBinds.length == 0) {
    string += "/>";
  }
  else {
    string += ">\n";

    var innerBindLen = this.innerBinds.length;
    //for (var i in this.innerBinds) {
    for (var i = 0; i < innerBindLen; ++i) {
      string += "  " + this.innerBinds[i].toString().replace(/\n/g, "\n  ") + "\n";
    }

    string += "</bind>";
  }

  return string;
};


XFormParser.prototype.parseBinds = function(element) {
  var binds        = [];
  var timeout = 280; // adjust to fit needs
  var child = element.firstChild;
  _self = this;
  (function(){
  var start = new Date().getTime();  
  for ( ; child != null; child = child.nextSibling) {
    if (child.nodeType == 1 && child.nodeName.replace(/^.*:/, "") == "bind" && child.namespaceURI == XmlNamespaces.XFORMS) {
      binds.push(_self.parseBind(child));
        if (new Date().getTime() - start > timeout ) {
            setTimeout(arguments.callee, 0);
            break;
        }

    }
  }
  })();
  return binds;

};

XFormParser.prototype.parseBind = function(element) {
  var bind = new XFormBind(
    element,

    "nodeset",
    this.xpathValue(element, "nodeset"),

    // Model item properties.
    { readOnly:   this.xpathValue(element, "readonly",   null),
      required:   this.xpathValue(element, "required",   null),
      relevant:   this.xpathValue(element, "relevant",   null),
      calculate:  this.xpathValue(element, "calculate",  null),
      constraint: this.xpathValue(element, "constraint", null)
    }
  );

  var outerBind  = bind;
  var innerBinds = this.parseBinds(element);

  var innerBindLen = innerBinds.length;
  //for (var i in innerBinds) {
  for (var i = 0; i < innerBindLen; ++i) {
    var innerBind = innerBinds[i];

    innerBind.outerBind = outerBind;
    innerBind.index     = i;

    outerBind.innerBinds.push(innerBind);
  }

  return bind;
};


XFormParser.prototype.parseBoundElements = function(element, contextModel, outerBind) {
  // Get all bound elements, excluding <bind/> and <submission/> elements, which
  // have already been parsed.
  var boundElements = new NodeSet();
  var binds         = [];

  locateBoundElements(boundElements, element);

  // If there are bound elements then there must be a model.
  if (boundElements.length > 0 && xform.models.length == 0) {
    throw new XFormException(element, "Document does not contain a model.");
  }

  for (var i = boundElements.length - 1; i >= 0; --i) {
    var innerBind = this.parseBoundElement(boundElements[i], contextModel, outerBind);

    // If this element has a @bind attribute, check that the corresponding <bind/>'s
    // outer <bind/> is the same as the bind for the outer bound element. If that
    // makes any sense.
    var bindXmlElement = innerBind.xmlElement;
    if(bindXmlElement.nodeName.replace(/^.*:/, "") == "bind" && bindXmlElement.namespaceURI == XmlNamespaces.XFORMS) {
      if (innerBind.outerBind != outerBind) {
        throw new XFormException(boundElements[i].attributes.getNamedItem("bind"),
          "<" + element.tagName + "/>'s binding is improperly nested."
        );
      }
    }
    // The bind for the bound element was created from a @ref or @nodeset attribute,
    // so add it to the binds list and set its outerBind.
    else {
      binds.push(innerBind);
      
      if (outerBind != null) {
        innerBind.outerBind = outerBind;
        innerBind.index     = outerBind.innerBinds.length;

        outerBind.innerBinds.push(innerBind);
      }
    }
  }

  return binds;


  // Locates any bound elements underneath the specified element and adds them to
  // the boundElements node-set.
  //
  // Parameters:
  //     boundElements: The node-set to add the bound elements to.
  //     element:       The parent element under which to search for bound
  //                    elements.
  function locateBoundElements(boundElements, element) {
    for (var child = element.lastChild; child != null; child = child.previousSibling) {
      if(child.nodeType == 1) {
        if(child.getAttributeNode("bind") != null || child.getAttributeNode("nodeset") != null 
          || child.getAttributeNode("ref") != null) {
          var childName = child.nodeName.replace(/^.*:/, "");
          if((childName != "submission" && childName != "bind") && child.namespaceURI == XmlNamespaces.XFORMS) {
            boundElements.addUnique(child);
          }
        }
        else {
          var atts  = child.attributes;
          var bound = false;

          for (var i = atts.length - 1; i > -1; i--) {
            var att = atts.item(i);
            if(att != null && att.namespaceURI == XmlNamespaces.XFORMS) {
              var attName = att.nodeName.replace(/^.*:/, "");
              if(attName == "repeat-bind" || attName == "repeat-nodeset") {
                bound = true;
                break;
              }
            }
          }
          if (bound) {
            boundElements.addUnique(child);
          }
          else {
            locateBoundElements(boundElements, child);
          }
        }
      }
    }
  };
};

XFormParser.prototype.parseBoundElement = function(element, contextModel, outerBind) {
  var bindAttribute = element.getAttributeNode("bind");

  if(bindAttribute == null && element.namespaceURI != XmlNamespaces.XFORMS) {
    var atts = element.attributes;
    for(var i = atts.length - 1; i > -1; i--) {
      var att = atts.item(i);
      if (att != null && att.namespaceURI == XmlNamespaces.XFORMS && att.nodeName.replace(/^.*:/, "") == "repeat-bind") {
        bindAttribute = att;
        break;
      }
    }
  }

  // Find the bind with the specified ID.
  if (bindAttribute != null) {
    var bind = xform.getObjectById(bindAttribute);

    if (contextModel != null && bind.model != contextModel) {
      throw new XFormException(bindAttribute,
        "<" + element.tagName + "/> is bound to a different model than the immediately enclosing element."
      );
    }

    this.parseBoundElements(element, bind.model, bind);

    return bind;
  }
  // Create a bind from the @nodeset or @ref attribute.
  else {
    var nodesetAttribute = element.getAttributeNode("nodeset");
    var refAttribute = element.getAttributeNode("ref");
    var modelAttribute = element.getAttributeNode("model");

    if(nodesetAttribute == null && refAttribute == null) {
      var atts = element.attributes;
      for(var i = atts.length - 1; i > -1; i--) {
        var att = atts.item(i);
        if (att != null && att.namespaceURI == XmlNamespaces.XFORMS && att.nodeName.replace(/^.*:/, "") == "repeat-nodeset") {
          nodesetAttribute = att;
          break;
        }
      }
    }

    assert(nodesetAttribute != null || refAttribute != null, "missing @nodeset or @ref");

    var type  = (nodesetAttribute != null) ? "nodeset" : "ref";
    var xpath = new XPath((nodesetAttribute != null) ? nodesetAttribute.value : refAttribute.value, element);

    var model = (contextModel != null ? contextModel : xform.models[0]);

    // Get the context model from the @model attribute.
    if (modelAttribute != null) {
      model = xform.getObjectById(modelAttribute, XFormModel);

      if (contextModel != null && model != contextModel) {
        throw new XFormException(modelAttribute,
          "<" + element.tagName + "/> is bound to a different model than the immediately enclosing element."
        );
      }
    }

    var bind   = new XFormBind(element, type, xpath, null);
    bind.model = model;

    if (contextModel == null) {
      bind .index = model.binds.length;

      bind .setModel  (model);
      model.binds.push(bind);
    }

    this.parseBoundElements(element, model, bind);

    return bind;
  }
};

// Gets the XFormBind that was created for the specified element.
//
// Parameters:
//     boundElement: An element with binding attributes.
//
// Return value:
//     The XFormBind for the element, or null if the element is unbound.
XFormParser.prototype.getBindFor = function(boundElement, debug) {
  if (boundElement.attributes.getNamedItem("bind") != null) {
    var bind = xform.getObjectById(boundElement.attributes.getNamedItem("bind"));

    assert(instanceOf(bind, XFormBind), "bind is not an XFormBind");

    return bind;
  }

  var models   = xform.models;
  var modelLen = models.length;
  for (var i = 0; i < modelLen; ++i) {
    var bind  = getBindFor(boundElement, models[i].binds);

    if (bind != null) {
      return bind;
    }
  }

  return null;


  // Recursive function to search for the bind for the specified element in the
  // given list of binds and their inner binds.
  function getBindFor(boundElement, binds) {
    for (var i = binds.length - 1; i >= 0; --i) {
      var bind = binds[i];

      if (bind.xmlElement == boundElement) {
        return bind;
      }

      var innerBind = getBindFor(boundElement, bind.innerBinds);

      if (innerBind != null) {
        return innerBind;
      }
    }

    return null;
  };
};


XFormBind.prototype.reset = function() {
  this.defaultBinding = null;
  
  for (var i = this.innerBinds.length - 1; i >= 0; --i) {
    this.innerBinds[i].reset();
  }
};


XFormBind.nonRelevantNode = xmlNewDocument().createElement("non-relevant");

XFormBind.prototype.createBinding = function(context) {
  var boundNodes    = this.xpath.evaluate(context);
  var innerBindings = [];

  if (boundNodes.length == 0) {
    boundNodes = new NodeSet([XFormBind.nonRelevantNode]);
  }

  // If this is a "ref" binding, only use the first node.
  if (this.type == "ref" && boundNodes.length > 0) {
    boundNodes = new NodeSet([boundNodes[0]]);
  }

  var boundNodeLen  = boundNodes.length;
  for (var i = 0; i < boundNodeLen; ++i) {
    innerBindings[i] = [];

    var innerBinds   = this.innerBinds;
    var innerBindLen = innerBinds.length;
    for (var j = 0; j < innerBindLen; ++j) {
      innerBindings[i][j] = innerBinds[j].createBinding(new XPathContext(boundNodes[i], i + 1, boundNodeLen));
    }
  }

  var binding = new XFormBinding(this, context, boundNodes, innerBindings);

  if (this.defaultBinding == null) {
    this.defaultBinding = binding;
  }

  return binding;
};


// Creates a new binding, which is a joining of bound nodes, their model item
// properties, and the bound controls.
function XFormBinding(bindOrModel, context, boundNodes, innerBindings) {
  if (instanceOf(bindOrModel, XFormBind)) {
    this.bind  = bindOrModel;
    this.model = bindOrModel.model;

    assert(this.model != null, "this.model is null");
  }
  else {
    assert(instanceOf(bindOrModel, XFormModel), "bindOrModel is not an XFormModel");

    this.bind  = null;
    this.model = bindOrModel;
  }

  this.context       = context;
  this.boundNodes    = boundNodes;

  this.outerBinding  = null;
  this.innerBindings = innerBindings;

  for (var i = this.innerBindings.length - 1; i >= 0; --i) {
    for (var j = this.innerBindings[i].length - 1; j >= 0; --j) {
      this.innerBindings[i][j].outerBinding = this;
    }
  }

  this.controls = [];
};

// Adds the dependency information for this binding to the specified dependency
// graph, creating vertices for each model item property of each bound node.
//
// Parameters:
//     graph: A dependency graph.
XFormBinding.prototype.setupGraph = function(graph) {
  var boundNodeList   = this.boundNodes;
  var boundNodeLen = boundNodeList.length;
  for (var i = 0; i < boundNodeLen; ++i) {
    var boundNode    = boundNodeList[i];
    var boundContext = new XPathContext(boundNode, i + 1, boundNodeLen);
    var boundVertex  = graph.addVertex(boundNode, "text");

    for (var property in this.bind.properties) {
      if (!this.bind.properties[property]) {
        continue;
      }

      var xpath         = this.bind.properties[property];
      var references    = xpath.referencedNodes(boundContext);
      var vertex        = graph.addVertex(boundNode, property, boundContext, xpath);
      var referencesLen = references.length;

      for (var j = 0; j < referencesLen; ++j) {
        vertex.dependsOn(graph.addVertex(references[j], "text"));
      }

      if (property == "calculate") {
        boundVertex.dependsOn(vertex);
      }
    }
  }

  if (this.outerBinding == null) {
    //XForm.alertTime("Setup graph in %t seconds.");
  }

  var bindingsLen = this.innerBindings.length;
  for (var i = 0; i < bindingsLen; ++i) {
    var innerBindingsLen = this.innerBindings[i].length;
    for (var j = 0; j < innerBindingsLen; ++j) {
      this.innerBindings[i][j].setupGraph(graph);
    }
  }

  //if (this.outerBinding == null) {
    //XForm.alertTime("Setup graph for inner bindings in %t seconds.");
  //}
};

// Gets the nodes the binding is bound to, re-evaluating the binding XPath. The
// recommended way to get the bound node-set is to access the binding.boundNodes
// property. Call this function if that node-set is stale.
XFormBinding.prototype.getBoundNodes = function() {
  assert(this.context != null, "context is null");

  return this.bind.xpath.evaluate(this.context);
};

XFormBinding.prototype.toString = function() {
  return this.boundNodes.toString() + "\n\n" + this.bind.toString();
};

// --- xforms/model.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XFormModel(element, instances, binds, submissions) {
  XFormObject.call(this, element, true);

  this.instances   = instances;
  this.binds       = binds;
  this.submissions = submissions;
  this.controls    = [];

  for (var i = this.instances  .length - 1; i >= 0; --i) { this.instances  [i].model = this; }
  for (var i = this.submissions.length - 1; i >= 0; --i) { this.submissions[i].model = this; }

  for (var i = this.binds.length - 1; i >= 0; --i) {
    this.binds[i].index = i;
    this.binds[i].setModel(this);
  }
};

XFormModel.inherits(XFormObject);

XFormParser.prototype.parseModels = function(element) {
  for (var child = element.firstChild; child != null; child = child.nextSibling) {
    if (!XForm.isXHtmlElement(child, "head")) {
      continue;
    }
    
    for (var headChild = child.firstChild; headChild != null; headChild = headChild.nextSibling) {
      if (XForm.isXFormsElement(headChild, "model")) {
        xform.models.push(this.parseModel(headChild));
      }
    }
    
    break;
  }

  // XForm.alertTime("Models parsed in %t seconds.");
  this.parseBoundElements(element, null);
  // XForm.alertTime("Bound Elements parsed in %t seconds.");
};


XFormParser.prototype.parseModel = function(element) {
  var instances   = this.parseInstances  (element); // XForm.alertTime("Instances parsed in %t seconds.");
  var binds       = this.parseBinds      (element); // XForm.alertTime("Binds parsed in %t seconds.");
  var submissions = this.parseSubmissions(element); // XForm.alertTime("Submissions parsed in %t seconds.");

  var submissionsLength = submissions.length;
  
  for (var i = 0; i < submissionsLength; ++i) {
    var submission = submissions[i];

    if (submission.bind.type == "ref") {
      binds.push(submission.bind);
    }
  }

  return new XFormModel(element, instances, binds, submissions);
};


XFormModel.prototype.postRender = function() {
  var self = this;
  
  for (var i = 0; i < this.submissions.length; ++i) {
    this.htmlNode.appendChild(this.submissions[i].render());
  }
  
  for (var i = 0; i < this.instances.length; ++i) {
    this.htmlNode.appendChild(this.instances[i].render());
  }
  
  this.htmlNode.getInstanceDocument = function(instanceId) {
    return xform.getObjectById(instanceId, XFormInstance).document;
  };
  
  this.htmlNode.rebuild     = function() {                        self.rebuild    (); };
  this.htmlNode.recalculate = function() { self.graph.touchAll(); self.recalculate(); };
  this.htmlNode.revalidate  = function() {                        self.revalidate (); };
  this.htmlNode.refresh     = function() {                        self.refresh    (); };

  // When the page is unloaded, remove these functions to prevent a memory leak in
  // Internet Explorer.
  new EventListener(window, "unload", "default", function() {
    self.htmlNode.getInstanceDocument = null;
    
    self.htmlNode.rebuild     = null;
    self.htmlNode.recalculate = null;
    self.htmlNode.revalidate  = null;
    self.htmlNode.refresh     = null;
  });
};

// Rebuilds the master dependency graph.
XFormModel.prototype.rebuild = function() {
  this.rebuildBindings        (); // XForm.alertTime("Bindings rebuilt in %t seconds.");
  this.rebuildControlInstances(); // XForm.alertTime("Controls instantiated in %t seconds.");
};

XFormModel.prototype.rebuildBindings = function() {
  this.bindings   = [];
  this.graph      = new XFormDependencyGraph(this);
  
  var contextNode = this.instances[0].document.documentElement;
  
  //XForm.startTimer();

  // Create a binding for each bind, and then setup the dependency graph.
  var bindsLength = this.binds.length;
  
  for (var i = 0; i < bindsLength; ++i) {
    this.binds[i].reset();

    var  binding     = this.binds[i].createBinding(new XPathContext(contextNode, 1, 1));
    this.bindings[i] = binding;

    //XForm.alertTime("Created binding #" + (i+1) + " in %t seconds.\n\n" + binding);
    
    binding.setupGraph(this.graph);
    
    //XForm.alertTime("Setup graph for binding #" + (i+1) + " in %t seconds.\n\n" + binding);
  }
  
  // Set up vertices for each node that inherits "relevant" or "readonly" from an
  // ancestor.
  for (var i in this.graph.vertices) {
    var vertex = this.graph.vertices[i];

    if (vertex.property == "relevant" || vertex.property == "readOnly") {
      addInheritedVertex(vertex, vertex.node);
    }
  }
  

  function addInheritedVertex(vertex, descendantNode) {
    // The first time this function is called, do not add a vertex.
    if (descendantNode != vertex.node) {
      // If the descendant node already has this property, stop.
      if (vertex.graph.getVertex(descendantNode, vertex.property) != null) {
        return;
      }

      // Add a vertex to the descendant node for the inherited property, with a null
      // XPath.
      vertex.graph.addVertex(descendantNode, vertex.property, null, null).dependsOn(vertex);
    }

    var children   = XPathAxis.CHILD    .filterNode(descendantNode);
    var attributes = XPathAxis.ATTRIBUTE.filterNode(descendantNode);
    var childLen   = children  .length;
    var attLen     = attributes.length;

    for (var i = 0; i < childLen; ++i) { addInheritedVertex(vertex, children  [i]); }
    for (var i = 0; i < attLen;   ++i) { addInheritedVertex(vertex, attributes[i]); }
  };
};

XFormModel.prototype.rebuildControlInstances = function() {
  var numControls = this.controls.length;
  
  for (var i = 0; i < numControls; i++) {
    var control  = this.controls[i];
    var htmlNode = xform.getHtmlNode(control.xmlNode);
    
    if (htmlNode == null) {
      var ancestor = control.xmlElement.parentNode;
      
      while (ancestor != null && ancestor.namespaceURI != XmlNamespaces.XFORMS) {
        ancestor = ancestor.parentNode;
      }
      
      switch (ancestor.nodeName.replace(/^.*:/, "")) {
        case "repeat":
        case "switch": 
        case "label" :
          htmlNode = xform.getObjectByNode(ancestor).activeInstance.container;
          break;
          
        default:
          htmlNode = document.createElement("span");
          break;
      }
    }
    
    while (htmlNode.hasChildNodes()) {
      htmlNode.removeChild(htmlNode.lastChild);
    }
    
    control.instance = control.instantiate(null, null, 0, htmlNode);
    
    if (control.enclosedBy != null) {
      var numInnerControls = control.enclosedBy.innerControls.length;
      
      for (var j = 0; j < numInnerControls; ++j) {
        if (control.enclosedBy.innerControls[j] == control) {
          control.enclosedBy.instance.innerControlInstances[0][j] = control.instance;
          break;
        }
      }
    }
    
    control.instance.activate();
  }
};

// Recalculates all of the model item properties.
XFormModel.prototype.recalculate = function() {
  var updated = [];

  // Get a sub-graph containing only the changed vertices.
  var subGraph            = this.graph.getPertinentSubGraph();
  var independentVertices = [];
  
  for (var i in subGraph.vertices) {
    var vertex = subGraph.vertices[i];
    
    if (vertex.inDegree == 0) {
      independentVertices.push(vertex);
    }
  }
  
  // Each iteration, find an independent vertex, recalculate its value, and then
  // decrement the inDegree of its dependents. An independent vertex is one whose
  // value doesn't depend on any other vertex in the sub-graph (i.e. inDegree is
  // 0).
  var subVertexCount = subGraph.count;
  
  for (var j = 0; j < subVertexCount; ++j) {
    if (independentVertices.length == 0) {
      throw new XFormComputeException("Dependency graph contains a cycle.");
    }
    
    var subVertex = independentVertices.pop();
    var vertex    = this.graph.vertices[subVertex.index];
    
    // Decrease the inDegree of all of the subgraph vertex's dependents and add them
    // to the independent vertex list if their inDegree goes to 0.
    var dependents = subVertex.dependents;
    
    for (var i = dependents.length - 1; i >= 0; --i) {
      var dependent = dependents[i];
      
      if (--dependent.inDegree == 0) {
        independentVertices.push(dependent);
      }
    }
    
    if (updated.length > 0) {
      updated.push(", ");
    }

    updated.push(vertex);

    switch (vertex.property) {
      case "text":
        updated.push(" = \"");
        updated.push(vertex.getValue());
        updated.push("\"");

        // Mark the vertex as changed.
        vertex.touch();
        break;

      // Calculate vertices are a special case; they change the value of the "text"
      // vertex for their nodes, rather than setting their own value.
      case "calculate":
        var value = XPath.STRING_FUNCTION.evaluate(vertex.xpath.evaluate(vertex.context));

        updated.push(" := \"");
        updated.push(value);
        updated.push("\"");

        this.graph.getVertex(vertex.node, "text").setValue(value);
        break;

      // Inheritable properties.
      case "relevant":
      case "readOnly":
        switch (vertex.property) {
          case "relevant": var defaultValue = true;  break;
          case "readOnly": var defaultValue = false; break;
        }

        var parentNode   = XPathAxis.PARENT.filterNode(vertex.node)[0];
        var parentVertex = (parentNode == null) ? null : this.graph.getVertex(parentNode, vertex.property);

        if (parentVertex != null && parentVertex.value != defaultValue) {
          var value = parentVertex.getValue();
        }
        else if (vertex.xpath != null) {
          var value = XPath.BOOLEAN_FUNCTION.evaluate(vertex.xpath.evaluate(vertex.context));
        }
        else {
          var value = defaultValue;
        }

        updated.push(" := ");
        updated.push(value);

        vertex.setValue(value);
        
        break;

      // Non-inheritable properties.
      case "required":
      case "constraint":
        var value      = XPath.BOOLEAN_FUNCTION.evaluate(vertex.xpath.evaluate(vertex.context));
        var textVertex = this.graph.getVertex(vertex.node, "text");

        updated.push(" := ");
        updated.push(value);

        vertex.setValue(value);
        
        //needs to have the "(vertex.value && !textVertex.isValid)" check so that it will update the isValid
        //boolean from when it was invalid otherwise it would stay invalid.
        if(!vertex.value || (vertex.value && !textVertex.isValid)) {
          textVertex.isValid = value;
          for (var ancestor = vertex.node.parentNode; ancestor != null; ancestor = ancestor.parentNode) {
            textVertex = this.graph.getVertex(ancestor, "text");
            
            if (textVertex != null) {
              textVertex.isValid = value;
            }
          }
        }
        
        break;

      default:
        throw new XmlException("Unknown property: " + vertex.property);
    }
  }

  status("Calculated: " + updated.join(""));
};

XFormModel.prototype.revalidate = function() {
  var refreshed = [];
  
  // For each changed vertex, notify its bindings of the new value.
  var changedVertices  = this.graph.changedVertices;
  var changedVertexLen = changedVertices.length;
  for (var i = 0; i < changedVertexLen; ++i) {
    var vertex         = changedVertices[i];
    var controls       = vertex.controls;
    var controlsLength = controls.length;

    vertex.refresh();
    
    for (var j = 0; j < controlsLength; ++j) {
      if (refreshed.length > 0) {
        refreshed.push(", ");
      }

      refreshed.push(controls[j]);
    }
  }
  
  // Notify each waiting listener that a property value has changed. This loop
  // must be written this way rather than as "for (var i in xform...)"
  // because listeners can be added to the list while iterating over the array,
  // and the for...in syntax doesn't process those new listeners.
  for (var i = 0; i < xform.waitingListeners.length; ++i) {
    var listener = xform.waitingListeners[i][0];
    var control  = xform.waitingListeners[i][1];
    var property = xform.waitingListeners[i][2];
    var value    = xform.waitingListeners[i][3];
    
    if (control.binding == null || this.haveChanged(control.binding.boundNodes, property)) {
      listener(control, property, value);
    }
  }

  xform.waitingListeners = [];

  this.graph.resetChangedVertices();
  
  status("Revalidated: " + refreshed.join(""));
};

// Refresh the values of each control based on the values of the instance nodes.
// Doesn't do anything code has been moved up into the revalidate function.
XFormModel.prototype.refresh = function() {
  status("");
};

// Returns true if any model item properties have changed; otherwise, false.
XFormModel.prototype.hasChanged = function() {
  return this.graph.changedVertices.length > 0;
};

// Returns true if the specified property has changed for any of the nodes in
// the specified node-set.
//
// Parameters:
//     nodeSet:  A set of nodes to check.
//     property: The property to check. If not specified, defaults to "text".
//
// Return value:
//     the value of the node if any of the nodes' properties have changed, null if not.
XFormModel.prototype.haveChanged = function(nodeSet, property) {
  if (typeof(property) == "undefined") {
    property = "text";
  }

  for (var i = nodeSet.length - 1; i >= 0; --i) {
    var vertex = this.graph.getVertex(nodeSet[i], property);

    if (vertex != null && vertex.hasChanged) {
      return true;
    }
  }

  return false;
};


function clearStatus() {
  var statusElement = document.getElementById("status");

  if (statusElement != null) {
    while (statusElement.hasChildNodes()) {
      statusElement.removeChild(statusElement.lastChild);
    }
  }
};

function status(message) {
  var statusElement = document.getElementById("status");

  if (statusElement != null) {
    statusElement.appendChild(document.createTextNode(message));
    statusElement.appendChild(document.createElement ("br"));
  }
};


XmlEvent.define("xforms-rebuild",     "Events", true, true, function(event) { xform.getObjectForHtmlNode(event.target).rebuild    (); });
XmlEvent.define("xforms-recalculate", "Events", true, true, function(event) { xform.getObjectForHtmlNode(event.target).recalculate(); });
XmlEvent.define("xforms-revalidate",  "Events", true, true, function(event) { xform.getObjectForHtmlNode(event.target).revalidate (); });
XmlEvent.define("xforms-refresh",     "Events", true, true, function(event) { xform.getObjectForHtmlNode(event.target).refresh    (); });
XmlEvent.define("xforms-reset",       "Events", true, true, function(event) { xform.getObjectForHtmlNode(event.target).reset      (); });

// --- xforms/dependencyGraph.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates an empty dependency graph.
function XFormDependencyGraph(model) {
  this.model            = model;
  
  this.vertices         = { };
  this.vertexLookupHash = { };
  this.count            = 0;
  
  this.changedVertices  = [];

  this.addVertex(XFormBind.nonRelevantNode, "relevant", new XPathContext(null, null, null), new XPath("false()"));
};

// Adds a "text" vertex to the graph.
//
// Parameters:
//     node:     The vertex node.
//     property: The node property name.
//     xpath:    The property XPath expression, or null if the property is "text".
//
// Return value:
//     The new vertex.
XFormDependencyGraph.prototype.addVertex = function(node, property, context, xpath) {
  assert(node     != null, "node is null");
  assert(property != null, "property is null");
  
  var vertex = this.getVertex(node, property);
  
  if (vertex == null) {
    var hash  = node.nodeName + "." + property;
    var index = this.count++;
    
    vertex               = new XFormDependencyGraphVertex(this, index, node, property, context, xpath);
    this.vertices[index] = vertex;
    
    vertex.touch();

    // Add the vertex to the lookup hash table.
    if (!this.vertexLookupHash[hash]) {
      this.vertexLookupHash[hash] = [];
    }
    
    this.vertexLookupHash[hash].push(vertex);
  }
  else {
    if (property != "text") {
      throw new XmlException("Duplicate model item property: " + property);
    }
  }
  
  return vertex;
};

// Gets a vertex in the graph.
//
// Parameters:
//     node:     The vertex's node.
//     property: The node's property.
//
// Return value:
//     The matching vertex, or null if there is none.
XFormDependencyGraph.prototype.getVertex = function(node, property) {
  var vertices = this.vertexLookupHash[node.nodeName + "." + property];
  
  if (!vertices) {
    return null;
  }
  
  var verticeLen = vertices.length;
  for (var i = 0; i < verticeLen; i++) {
    var vertex = vertices[i];
    
    if (vertex == null) {
      continue;
    }
    
    if (vertex.property != property) {
      continue;
    }
    
    if (vertex.node.isSameNode) {
      if (vertex.node.isSameNode(node)) {
        return vertex;
      }
    }
    else {
      if (vertex.node == node) {
        return vertex;
      }
    }
  }
  
  return null;
};

// Given a list of changed instance data nodes, builds a sub-graph containing
// the vertices for those nodes and those vertices' dependents.
//
// Return value:
//     The sub-graph.
XFormDependencyGraph.prototype.getPertinentSubGraph = function() {
  var subGraph = new XFormDependencyGraph();
  
  subGraph.addSuperGraphVertices(this.changedVertices);
  
  return subGraph;
};

// Taking this graph to be a sub-graph, adds the specified vertices from the
// master graph as well as their dependents. All of the added vertices are
// copies of the vertices from the master graph.
//
// Parameters:
//     vertices: An array of vertices from the master graph.
XFormDependencyGraph.prototype.addSuperGraphVertices = function(vertices) {
  var verticeLen = vertices.length;
  for (var i = 0; i < verticeLen; i++) {
    var vertex = vertices[i];
    
    if (this.vertices[vertex.index] != null) {
      continue;
    }
    
    var parentClone = vertex.clone();
    var dependents  = vertex.dependents;
    
    this.vertices[vertex.index] = parentClone;
    this.count                 += 1;
    
    this.addSuperGraphVertices(dependents);
    
    var dependentLen = dependents.length;
    for (var j = 0; j < dependentLen; ++j) {
      var dependent      = dependents[j];
      var dependentClone = this.vertices[dependent.index];
    
      dependentClone.dependsOn(parentClone);
    }
  }
};

XFormDependencyGraph.prototype.resetChangedVertices = function() {
  var changedVertexLen = this.changedVertices.length;
  for (var i = 0; i < changedVertexLen; ++i) {
    this.changedVertices[i].hasChanged = false;
  }
  
  this.changedVertices = [];
};

XFormDependencyGraph.prototype.touchAll = function() {
  var verticeLen = vertices.length;
  for (var i = 0; i < verticeLen; i++) {
    this.vertices[i].touch();
  }
};

XFormDependencyGraph.prototype.toString = function() {
  var str = "";
  
  var verticeLen = vertices.length;
  for (var i = 0; i < verticeLen; i++) {
    var vertex = this.vertices[i];
    var dependents = vertex.dependents;
    
    str += "#" + i + ": " + vertex + " -- in: " + vertex.inDegree + ", out: [";
    
    var dependentLen = dependents.length;
    for (var j = 0; j < dependentLen; ++j) {
      if (j > 0) {
        str += ", ";
      }
      
      str += dependents[j];
    }
    
    str += "]\n";
  }
  
  return str;
};
  

// Creates a new dependency graph vertex. Only the XFormDependencyGraph object
// should call this constructor; other objects using the graph will call
// graph.addTextVertex and graph.addPropertyVertex.
//
// Parameters:
//     graph:    The graph to which the vertex belongs.
//     index:    The index of the vertex within the containing graph.
//
//     node:     The vertex node.
//     property: The node property.
//     xpath:    The XPath expression for the property. If the property is
//               "text", then this parameter is not needed.
function XFormDependencyGraphVertex(graph, index, node, property, context, xpath) {
  assert(graph != null, "graph is null");
  assert(node  != null, "node is null");
  
  this.graph      = graph;
  this.index      = index;
  
  this.node       = node;
  this.property   = property;
  this.context    = context;
  this.xpath      = xpath;
  this.value      = null;
  
  this.dependents = [];
  this.inDegree   = 0;
  
  this.hasChanged = false;
  this.controls   = [];
  
  if(this.property == "text") {
    this.isValid    = true;
  }
};

// Creates a free-floating copy of this vertex with no dependents and in-degree
// 0.
XFormDependencyGraphVertex.prototype.clone = function() {
  var vertex = new XFormDependencyGraphVertex(
                 this.graph,
                 this.index,
                 this.node,
                 this.property,
                 this.context,
                 this.xpath
               );
  
  vertex.contextNode = this.contextNode;
  vertex.hasChanged  = this.hasChanged;
  
  return vertex;
};

// Adds this vertex to the specified vertex's dependents list.
//
// Parameters:
//     vertex: The vertex that this vertex depends on.
XFormDependencyGraphVertex.prototype.dependsOn = function(vertex) {
  vertex.dependents.push(this);
  ++this.inDegree;
};

XFormDependencyGraphVertex.prototype.setValue = function(value) {
  if (value == this.getValue()) {
    // Commented out this line. Why is it here?
    // Please comment why this is needed if uncommenting...
    // this.graph.changedVertices.push(this);
    
    return;
  }
  
  if (this.property == "text") {
    switch (this.node.nodeType) {
      case 1: // Element
        // Look for the first child text node.
        for (var textNode = this.node.firstChild; textNode != null; textNode = textNode.nextSibling) {
          if (isTextNode(textNode)) {
            break;
          }
        }
        
        if (textNode != null) {
          textNode.nodeValue = value;
        }
        else if (value != "") {
          this.node.insertBefore(this.node.ownerDocument.createTextNode(value), this.node.firstChild);
        }
        
        break;
        
      case 2: // Attribute
      case 3: // Text node
      case 4: // CDATA node
        this.node.nodeValue = value;
        break;
        
      default:
        throw new XFormException(this.node, "Invalid node in dependency graph: " + this.node.nodeName + " (" + this.node.nodeType + ")");
    }
  }
  else {
    this.value = value;
  }
  
  this.touch();
};

// Mark the vertex as changed.
XFormDependencyGraphVertex.prototype.touch = function() {
  if (!this.hasChanged) {
    this.hasChanged = true;
    this.graph.changedVertices.push(this);
  }
};

XFormDependencyGraphVertex.prototype.getValue = function() {
  if (this.property == "text") {
    switch (this.node.nodeType) {
      case 1: // Element
        for (var textNode = this.node.firstChild; textNode != null; textNode = textNode.nextSibling) {
          if (isTextNode(textNode)) {
            return textNode.nodeValue;
          }
        }
        
        return "";
        
      case 2: // Attribute
      case 3: // Text node
      case 4: // CDATA node
        return this.node.nodeValue;
        
      case 9: // Document
        return "";
        
      default:
        throw new XmlException("Unexpected vertex node: " + this.node.nodeName + " (" + this.node.nodeType + ")");
    }
  }
  else {
    return this.value;
  }
};

XFormDependencyGraphVertex.prototype.refresh = function() {
  var value      = this.getValue();
  var property   = this.property;
  var controlLen = this.controls.length;
  
  for (var i = 0; i < controlLen; ++i) {
    this.controls[i].setProperty(property, value);
  }
};

XFormDependencyGraphVertex.prototype.toString = function() {
  var prefix   = (this.node.nodeType == 2) ? "@" : "";
  var name     =  this.node.nodeName;
  var property =  this.property;

  // Code is a huge bottleneck. Commented out for efficiency.
  //
  // if (this.node.nodeType == 1 || this.node.nodeType == 2) {
  //   var size     = new XPath("count(../" + prefix + name + ")")       .evaluate(this.node);
  //   var position = new XPath("count(preceding-sibling::" + name + ")").evaluate(this.node) + 1;
  //   
  //   if (size > 1) {
  //     name += "[" + position + "]";
  //   }
  // }
  
  return prefix + name + "." + property;
};

// --- xforms/xpathFunctions.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


function XForms() {
};

XForms.BOOLEAN_FROM_STRING_FUNCTION = new XPathFunction(
  function(string) {
    string = XPath.STRING_FUNCTION.evaluate(string);

    switch (string.toLowerCase()) {
      case "true":  case "1": return true;
      case "false": case "0": return false;
      
      default:
        status("Bad call to boolean-from-string(\"" + string + "\")");
        throw new XFormsComputeException();
    }
  },
  
  XPathFunction.Context.NONE
);


XForms.IF_FUNCTION = new XPathFunction(
  function(condition, a, b) {
    condition = XPath.BOOLEAN_FUNCTION.evaluate(condition);
    
    return condition ? a : b;
  },
  
  XPathFunction.Context.NONE
);


XForms.AVG_FUNCTION = new XPathFunction(
  function(nodeSet) {
    return XPath.SUM_FUNCTION  .evaluate(nodeSet)
         / XPath.COUNT_FUNCTION.evaluate(nodeSet);
  },
  
  XPathFunction.Context.NONE
);


XForms.MIN_FUNCTION = new XPathFunction(
  function (nodeSet) {
    var setLen = nodeSet.length;
    if (setLen == 0) {
      return Number.NaN;
    }
    
    var minimum = XPathFunction.stringValueOf(nodeSet[0]);
    
    for (var i = setLen - 1; i >= 0; --i) {
      var value = XPath.NUMBER_FUNCTION.evaluate(XPathFunction.stringValueOf(nodeSet[i]));
      
      if (isNaN(value)) {
        return Number.NaN;
      }
      
      if (value < minimum) {
        minimum = value;
      }
    }
    
    return minimum;
  },
  
  XPathFunction.Context.NONE
);


XForms.MAX_FUNCTION = new XPathFunction(
  function (nodeSet) {
    var setLen = nodeSet.length;
    if (setLen == 0) {
      return Number.NaN;
    }
    
    var maximum = XPathFunction.stringValueOf(nodeSet[0]);
    
    for (var i = setLen - 1; i >= 0; --i) {
      var value = XPath.NUMBER_FUNCTION.evaluate(XPathFunction.stringValueOf(nodeSet[i]));
      
      if (isNaN(value)) {
        return Number.NaN;
      }
      
      if (value > maximum) {
        maximum = value;
      }
    }
    
    return maximum;
  },
  
  XPathFunction.Context.NONE
);


XForms.COUNT_NON_EMPTY_FUNCTION = new XPathFunction(
  function(nodeSet) {
    var count = 0;
    
    for (var i = nodeSet.length - 1; i >= 0; --i) {
      if (XPathFunction.stringValueOf(nodeSet[i]) != "") {
        ++count;
      }
    }
    
    return count;
  },
  
  XPathFunction.Context.NONE
);


XForms.INDEX_FUNCTION = new XPathFunction(
  function(repeatId) {
    repeatId = XPath.STRING_FUNCTION.evaluate(repeatId);

    return xform.getObjectById(repeatId, XFormRepeatControl).index + 1;
  },
  
  XPathFunction.Context.NONE
);


XForms.PROPERTY_FUNCTION = new XPathFunction(
  function(name) {
    name = XPath.STRING_FUNCTION.evaluate(name);
    
    switch (name) {
      case "version":           return "1.0";
      case "conformance-level": return "full";
    }
    
    throw new XFormsComputeException("Invalid property name: " + name);
  },
  
  XPathFunction.Context.NONE
);


XForms.NOW_FUNCTION = new XPathFunction(
  function() {
    // Pads out a number to the specified number of digits.
    //
    // Parameters:
    //     number: A number.
    //     digits: The minimum number of digits. If the number is not long enough,
    //             it is padded on the left with 0s.
    function padNumber(number, digits) {
      var string = number.toString();
      
      while (string.length < digits) {
        string = "0" + string;
      }
      
      return string;
    };
    
    var now = new Date();
    
    return padNumber(now.getUTCFullYear(),     4) + "-"
         + padNumber(now.getUTCMonth   () + 1, 2) + "-"
         + padNumber(now.getUTCDate    (),     2) + "T"
         + padNumber(now.getUTCHours   (),     2) + ":"
         + padNumber(now.getUTCMinutes (),     2) + ":"
         + padNumber(now.getUTCSeconds (),     2) + "Z";
  },
  
  XPathFunction.Context.NONE
);


XForms.DAYS_FROM_DATE_FUNCTION = new XPathFunction(
  function(date) {
    date = XPath.STRING_FUNCTION.evaluate(date);
    
    if (!date.match(/^(-?\d\d\d\d+)-(\d\d)-(\d\d)(?:T(\d\d):(\d\d):(\d\d(?:\.\d+)?))?(?:Z|([-+])(\d\d):(\d\d))?$/)) {
      return Number.NaN;
    }
    
    date = Date.UTC(RegExp.$1, RegExp.$2 - 1, RegExp.$3);
                     
    return date / 1000 / 60 / 60 / 24;
  },
  
  XPathFunction.Context.NONE
);


XForms.SECONDS_FROM_DATETIME_FUNCTION = new XPathFunction(
  function(date) {
    var zone = "";
    var date = XPath.STRING_FUNCTION.evaluate(date);
    
    if (!date.match(/^(-?\d\d\d\d+)-(\d\d)-(\d\d)T(\d\d):(\d\d):(\d\d(?:\.\d+)?)(?:Z|([-+])(\d\d):(\d\d))?$/)) {
      return Number.NaN;
    }
    
    date = date.split("T");
    zone = (date[1].split("Z").length > 1) ? date[1].split("Z")
         : (date[1].split("+").length > 1) ? date[1].split("+")
                                           : date[1].split("-");
    var date     = date[0].split("-");
    var time     = zone[0].split(":");
    var year     = +RegExp.$1;
    var month    = +RegExp.$2;
    var day      = +RegExp.$3;
    var hour     = +RegExp.$4; 
    var minute   = +RegExp.$5; 
    var second   = +RegExp.$6;
    var timeZone = [+RegExp.$7, +RegExp.$8, +RegExp.$9];
    
    if (timeZone[0] == "+") {
      hour   += timeZone[1];
      minute += timeZone[2];
      
      if (hour >= 24) {
        day  += 1;
        hour -= 24;
      }
      
      switch (month) {
        case 1: case 3: case 5: case 7: case 8: case 10: case 12:
          var daysInMonth = 31;
          break;
          
        case 2:
          var daysInMonth = (year % 4 != 0 || year % 100 == 0 || year % 400 != 0) ? 28 : 29;
          break;
        
        case 4: case 6: case 9: case 11:
          var daysInMonth = 30;
          break;
        
        default:
          assert(false, month + " is not a month");
      }
      
      if (day > daysInMonth) {
        month += 1;
        day   -= daysInMonth;
      }
      
      if (month > 12) {
        year  += 1;
        month -= 12;
      }
    }
    
    if (timeZone[0] == "-") {
      hour   -= (tz[1] == "30") ? (parseInt(tz[0]) + 1) : parseInt(tz[0]);
      minute += parseInt(tz[1]);
      
      if (hour < 0) {
        day  -= 1;
        hour += 24;
      }
      
      if (month == 3 && day < 1 && ((year % 4) == 0 && (year % 100) > 0)) {
        month -= 1;
        day   += 29;
      }
      
      if (month == 3 && day < 1) {
        month -= 1;
        day   += 28;
      }
      
      if ((month == 2 || month == 5 || month == 7 || month == 10) && day < 1) {
        month -= 1;
        day   += 30;
      }
      
      if (day < 1) {
        month -= 1;
        day   += 31;
      }
      
      if (month < 1) {
        year  -= 1;
        month += 12;
      }
    }
    
    date = Date.UTC(((date.length > 3) ? "-" : "") + year, month - 1, day, hour, minute, second);
    
    return date / 1000;
  },
  
  XPathFunction.Context.NONE
);


XForms.SECONDS_FUNCTION = new XPathFunction(
  function(durStr) {
    var d = XPath.STRING_FUNCTION.evaluate(durStr);

    if (d.match(/^-?P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/)) {
      var p = d.split("P");
      var t = p[1].split("T");
      var result = 0;
      var date = t[0];
      var time = (t[1]) ? t[1] : "";
      
      ((date.split("Y")).length > 1) ?  date = date.split("Y")[1] : date;
      ((date.split("M")).length > 1) ?  date = date.split("M")[1] : date;
      
      if ((date.split("D")).length > 1) {
        date = date.split("D");
        result += (parseInt(date[0]) * 60 * 60 * 24);
        date = date[1];
      }
      
      if ((time.split("H")).length > 1) {
        time = time.split("H");
        result += (parseInt(time[0]) * 60 * 60);
        time = time[1];
      }
      
      if ((time.split("M")).length > 1) {
        time = time.split("M");
        result += (parseInt(time[0]) * 60);
        time = time[1];
      }
      
      if ((time.split("S")).length > 1) {
        time = time.split("S");
        result += parseFloat(time[0]);
        time = time[1];
      }
      
      if (p[0] != "" && result != 0) {
        result = p[0] + result;
      }
      
      return result;
    }
    
    return "NaN";
  },
  
  XPathFunction.Context.NONE
);


XForms.MONTHS_FUNCTION = new XPathFunction(
  function(durStr) {
    var d = XPath.STRING_FUNCTION.evaluate(durStr);
    
    if (d.match(/^-?P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/)) {
      var p = d.split("P");
      var t = p[1].split("T");
      var result = 0;
      var date = t[0];
      
      if ((date.split("Y")).length > 1) {
        date = date.split("Y");
        result += (parseInt(date[0]) * 12);
        date = date[1];
      }
      
      if ((date.split("M")).length > 1) {
        date = date.split("M");
        result += parseInt(date[0]);
      }
      
      if (p[0] != "") {
        result = p[0] + result;
      }
      
      return result;
    }
    
    return "NaN";
  },
  
  XPathFunction.Context.NONE
);


XForms.INSTANCE_FUNCTION = new XPathFunction(
  function(idRef) {
    for (var i = xform.models.length - 1; i >= 0; --i) {
      var model = xform.models[i];
      
      for (var j = model.instances.length - 1; j >= 0; --j) {
        var instance = model.instances[j];
        
        if (instance.xmlElement.getAttribute("id") == idRef) {
          return new NodeSet([instance.document.documentElement]);
        }
      }
    }
    
    return new NodeSet();
  },
  
  XPathFunction.Context.NONE
);


XForms.FUNCTIONS = new XPathFunctionMap();

XForms.FUNCTIONS.registerFunction(new QName("boolean-from-string"),   XForms.BOOLEAN_FROM_STRING_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("if"),                    XForms.IF_FUNCTION);
                                                                    
XForms.FUNCTIONS.registerFunction(new QName("avg"),                   XForms.AVG_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("min"),                   XForms.MIN_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("max"),                   XForms.MAX_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("count-non-empty"),       XForms.COUNT_NON_EMPTY_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("index"),                 XForms.INDEX_FUNCTION);
                                                                    
XForms.FUNCTIONS.registerFunction(new QName("property"),              XForms.PROPERTY_FUNCTION);
                                                                    
XForms.FUNCTIONS.registerFunction(new QName("now"),                   XForms.NOW_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("days-from-date"),        XForms.DAYS_FROM_DATE_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("seconds-from-dateTime"), XForms.SECONDS_FROM_DATETIME_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("seconds"),               XForms.SECONDS_FUNCTION);
XForms.FUNCTIONS.registerFunction(new QName("months"),                XForms.MONTHS_FUNCTION);

XForms.FUNCTIONS.registerFunction(new QName("instance"),              XForms.INSTANCE_FUNCTION);

XPathContext.prototype.functionResolvers.push(XForms.FUNCTIONS);

// --- xforms/controls/control.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.

// Base class for all controls.
//
// Parameters:
//     element:    The element from which the control was created.
//     bind:       The control's bind, or null if it is unbound.
//     label:      The control's label, or null if it has none.
//     appearance: The appearance of the control.
function XFormControl(element, bind, innerControls, appearance) {
  if (arguments.length == 0) {
    return;
  }

  assert(element != null, "element is null");
  
  XFormObject.call(this, element, true);

  this.bind           = bind;
  this.label          = null;
  this.innerControls  = innerControls;
  this.appearance     = appearance;
  
  this.enclosedBy     = null;
  this.activeInstance = null;
  
  var innerControlsLength = this.innerControls.length;
  
  for (var i = 0; i < innerControlsLength; i++) {
    assert(this.innerControls[i].enclosedBy == null, "enclosedBy is not null");
           this.innerControls[i].enclosedBy = this;
  }
};

XFormControl.inherits(XFormObject);

XFormControl.prototype.getModel = function() {
  if (this.bind != null) {
    return this.bind.model;
  }
  
  if (this.enclosedBy != null && this.enclosedBy.getModel() != null) {
    return this.enclosedBy.getModel();
  }
  
  if (instanceOf(this, XFormOutputControl) && this.value != null) {
    return xform.models[0];
  }
  
  return null;
};

// Returns an XFormControlInstance based on this control. Derived classes must
// implement createInstance(). Callers should call instantiate() and not
// createInstance().
XFormControl.prototype.instantiate = function(enclosingControlInstance, outerBinding, position, htmlNode) {
  var binding = null;
  
  // Get the binding for a bound control.
  if (this.bind != null) {
    if (outerBinding == null) {
      binding = this.bind.model.bindings[this.bind.index];
    }
    else {
      assert(this.bind.outerBind == outerBinding.bind, "outer binds don't match");
      
      binding = outerBinding.innerBindings[position][this.bind.index];
    }
  }
  // <output value="..."/>
  else if (instanceOf(this, XFormOutputControl) && this.value != null) {
    var model            = (outerBinding != null) ? outerBinding.bind.model : xform.models[0];
    
    var valueNode        = document.createElement("output-value");
    var calculateContext = (outerBinding != null)
                             ? new XPathContext(outerBinding.boundNodes[position], position + 1, outerBinding.boundNodes.length)
                             : new XPathContext(model.instances[0].document.documentElement, 1, 1);
    var referencedNodes  = this.value.referencedNodes(calculateContext);
                         
    var textVertex       = model.graph.addVertex(valueNode, "text");
    var calculateVertex  = model.graph.addVertex(valueNode, "calculate", calculateContext, this.value);
                         
    var binding          = new XFormBinding(model, null, new NodeSet([valueNode]), []);
    
    for (var i = 0; i < referencedNodes.length; ++i) {
      calculateVertex.dependsOn(model.graph.addVertex(referencedNodes[i], "text"));
    }
    
    textVertex.dependsOn(calculateVertex);                    
  }
  
  var instance = this.createInstance(binding, htmlNode, outerBinding, position);
  
  instance.enclosedBy = enclosingControlInstance;
  
  if (this.activeInstance == null) {
    this.activeInstance = instance;
  }
  
  // Set default control properties before we add the event listeners.
  instance.setProperty("readOnly",   false);
  instance.setProperty("required",   false);
  instance.setProperty("relevant",   true);
  instance.setProperty("constraint", true);
  
  // Dispatch events when MIP values change.
  instance.addListener("readOnly",   function(control, property, value) { XmlEvent.dispatch(instance.htmlNode, value ? "xforms-readonly" : "xforms-readwrite"); });
  instance.addListener("required",   function(control, property, value) { XmlEvent.dispatch(instance.htmlNode, value ? "xforms-required" : "xforms-optional");  });
  instance.addListener("relevant",   function(control, property, value) { XmlEvent.dispatch(instance.htmlNode, value ? "xforms-enabled"  : "xforms-disabled");  });
  instance.addListener("constraint", function(control, property, value) { XmlEvent.dispatch(instance.htmlNode, value ? "xforms-valid"    : "xforms-invalid");   });

  // If the control is bound to an empty node-set, it is non-relevant.
  if (binding != null && binding.boundNodes.length == 0) {
    instance.setProperty("relevant", false);
  }
  
  var boundNodes = (binding != null ? binding.boundNodes.length : 1);
  
  for (var i = 0; i < boundNodes; ++i) {
    if (instance.container != undefined) {
      xform.renderChildNodes(this.xmlNode, instance.container);
    }
	
    instance.innerControlInstances[i] = [];
    
    var innerControlsCount = this.innerControls.length;
    
    for (var j = 0; j < innerControlsCount; j++) {
      var control = this.innerControls[j];
      
      // If this control is not tied to a model but the inner control is, don't
      // instantiate the inner control. It will be instantiated once the model has
      // been initialized.
      if (this.getModel() == null && control.getModel() != null) {
        control.getModel().controls.push(control);
      }
      else {
        var htmlNode = null;
        
        if (control == "label") {
          if (this == "repeat" || this == "switch" || this == "label") {
            htmlNode = instance.container;
          }
          else {
            htmlNode = document.createElement("span");
          }
        }
        else {
          htmlNode = xform.getHtmlNode(control.xmlNode);
        }
        
        instance.innerControlInstances[i][j]
          = control.instance
          = control.instantiate(instance, instance.nearestBinding, binding != null ? i : position, htmlNode);
      }
    }
  }
  
  instance.postInstantiate();
  
  return instance;
};

// Returns an XFormControlInstance based on this control. Derived classes must
// implement this method. Callers, however, should call instantiate(), not
// createInstance().
XFormControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  assert(false, "createInstance not implemented");
};

XFormParser.prototype.parseControls = function(element) {
  var controls = [];
  getInnerXFormsElements(this, controls, element);;
  return controls;
  
  
  function getInnerXFormsElements(parser, controls, element) {
//  debugger
    for (var child = element.firstChild; child != null; child = child.nextSibling) {
      if (child.nodeType == 1) {
        if (child.namespaceURI == XmlNamespaces.XFORMS) {
          switch (child.nodeName.replace(/^.*:/, "")) {
            case "label":    
              var parentName = element.nodeName.replace(/^.*:/, "");
              if(parentName != "repeat" && parentName != "switch") {
                controls.push(parser.parseLabel(child));
              }
              break;
            case "input":    controls.push(parser.parseInputControl   (child)); break;
            case "textarea": controls.push(parser.parseTextAreaControl(child)); break;
            case "secret":   controls.push(parser.parseSecretControl  (child)); break;
            case "output":   controls.push(parser.parseOutputControl  (child)); break;
            case "select":
            case "select1":  controls.push(parser.parseSelectControl  (child)); break;
            case "trigger":  controls.push(parser.parseTriggerControl (child)); break;
            case "group":    controls.push(parser.parseGroupControl   (child)); break;
            case "repeat":   controls.push(parser.parseRepeatControl  (child)); break;
            case "switch":   controls.push(parser.parseSwitchControl  (child)); break;
            case "submit":   controls.push(parser.parseSubmitControl  (child)); break;
            case "extension":   controls.push(parser.parseExtensionControl  (child)); break;
            case "help":     controls.push(parser.parseHelpControl    (child)); break;
            case "hint":     controls.push(parser.parseHintControl    (child)); break;
            case "alert":    controls.push(parser.parseAlertControl   (child)); break;
            
            case "filename":
            case "mediatype":
            break;
            default:
           var qqn = new QName(child.nodeName,child);
           if(XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn))controls.push(XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn)(parser, child));

          }
        }
//XmlNamespaces.XHTML
        else if(child.namespaceURI == XmlNamespaces.XHTML) {
          var hasXFormsAttribute = false;
          var attributes         = child.attributes;
          
          for (var i = attributes.length - 1; i >= 0; --i) {
            var att = attributes.item(i);
            
            if (att != null && att.namespaceURI == XmlNamespaces.XFORMS) {
              var n = child.nodeName.replace(/^.*:/, "");
              if( n.toLowerCase() == 'input' || n.toLowerCase() == 'select' || n.toLowerCase() == 'textarea')
              	break;
              hasXFormsAttribute = true;
              break;
            }
          }
          
          if (hasXFormsAttribute) {
            controls.push(parser.parseRepeatControl(child));
          }
          else {
            getInnerXFormsElements(parser, controls, child);
          }
        }
        else
        {
// add external handling here

           var qqn = new QName(child.nodeName,child);
           if(XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn))
           	controls.push(XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn)(parser, child));
           else {
            getInnerXFormsElements(parser, controls, child);
          }

        }
      }
    }
    
    return controls;
  };
};

XFormParser.prototype.parseUiInline = function(element) {
  var controls = [];

  for (var child = element.firstChild; child != null; child = child.nextSibling) {
    if (XForm.isXFormsElement(child, "output")) {
      controls.push(this.parseOutputControl(child));
    }
  }

  return controls;
};

XFormParser.prototype.parseUiCommon = function(element) {
  this.parseActions(element);
};

XFormParser.prototype.parseIncremental = function(element, defaultValue) {
  return this.booleanValue(element, "incremental", defaultValue ? "true" : "false");
};

XFormParser.prototype.parseAppearance = function(element, defaultValue) {
  return this.stringValue(element, "appearance", defaultValue);
};


// Parameters:
//     control: The control that was rendered.
//     binding: The XFormBinding to which the rendered control is bound, or null
//              if the control is unbound.
function XFormControlInstance(control, binding, htmlNode, outerBinding, position) {
  if (arguments.length == 0) {
    return;
  }
  
  assert(control  != null, "control is null");
  assert(htmlNode != null, "htmlNode is null");
  
  XFormObject.call(this, control.xmlNode, false);
  
  this.control        = control;
  this.binding        = binding;
  this.htmlNode       = htmlNode;
  this.outerBinding   = outerBinding;
  
  this.nearestBinding = this.binding && this.binding.bind ? this.binding : this.outerBinding;
  this.position       = this.binding ? 0 : position;
	
  this.innerControlInstances = [];

  // Register the control instance with all of the vertices in the dependency
  // graph to which it is bound so it'll have its relevance/validity/etc. updated
  // when the instance data changes.
  if (this.binding != null) {
    var properties = ["text", "readOnly", "required", "relevant", "constraint"];
    for (var i = this.binding.boundNodes.length - 1; i >= 0; --i) {
      for (var j = properties.length; j >= 0; --j) {
        var vertex = this.binding.model.graph.getVertex(this.binding.boundNodes[i], properties[j]);
        
        if (vertex != null) {
          vertex.controls.push(this);
        }
      }
    }
  }
  
  // For each property, a list of functions to call whenever that property
  // changes.
  this.listeners = {
    "text":       [],
    "readOnly":   [],
    "relevant":   [],
    "required":   [],
    "constraint": []
  };
};

XFormControlInstance.inherits(XFormObject);

XFormControlInstance.prototype.getLabelElement = function() {
  var labelElement = this.control.xmlNode.firstChild;
  
  if (labelElement == null) {
    return null;
  }
  
  while (labelElement.nodeType != 1) {
    labelElement = labelElement.nextSibling;
    
    if (labelElement == null) {
      return null;
    }
  }
  
  if (labelElement.nodeName.replace(/^.*:/, "") != "label" ||
      labelElement.namespaceURI != XmlNamespaces.XFORMS)
  {
    return null;
  }
  
  return labelElement;
};

XFormControlInstance.prototype.addInstantiatedLabel = function(label) {
};

XFormControlInstance.prototype.activate = function() {
  for (var i = this.innerControlInstances   .length - 1; i >= 0; --i)
  for (var j = this.innerControlInstances[i].length - 1; j >= 0; --j) {
    this.innerControlInstances[i][j].activate();
  }
};

XFormControlInstance.prototype.deactivate = function() {
  for (var i = this.innerControlInstances   .length - 1; i >= 0; --i)
  for (var j = this.innerControlInstances[i].length - 1; j >= 0; --j) {
    this.innerControlInstances[i][j].deactivate();
  }
};

XFormControlInstance.prototype.addLabel = function(htmlElement, label) {
  if (!label) {
    if (!this.control.label) {
      return htmlElement;
    }
    
    label = this.control.label;
  }
  
  var table = document.createElement("table");
  var tbody = document.createElement("tbody");
  var tr    = document.createElement("tr");
  var td    = document.createElement("td");
  
  table.appendChild(tbody);
  tbody.appendChild(tr);
  tr   .appendChild(td);
  td   .appendChild(label.htmlNode);
  td   .appendChild(document.createTextNode(" "));
  td   .appendChild(htmlElement);
  
  // Set the label's @for attribute to point to the HTML element.
  if (!htmlElement.id) {
    htmlElement.id = uniqueId();
  }
  
  label.labelElement.setAttribute("for", htmlElement.id);
  
  table.cellSpacing    = 0;
  table.style.display  = "inline";
  table.style.margin   = "0";
  table.style.padding  = "0";

  return table;
};

XFormControlInstance.prototype.postInstantiate = function() {
};

XFormControlInstance.prototype.setProperty = function(property, value) {
  var classNames = {
    "readOnly":   ["xforms-read-write", "xforms-read-only"],
    "required":   ["xforms-optional",   "xforms-required"],
    "relevant":   ["xforms-disabled",   "xforms-enabled"],
    "constraint": ["xforms-invalid",    "xforms-valid"]
  };

  if (typeof(classNames[property]) != "undefined") {
    var falseClass = classNames[property][0];
    var trueClass  = classNames[property][1];
    
    this.htmlNode.className  = this.htmlNode.className.replace(new RegExp("\\b" + falseClass + "\\b", "g"), "");
    this.htmlNode.className  = this.htmlNode.className.replace(new RegExp("\\b" + trueClass  + "\\b", "g"), "");
    
    this.htmlNode.className += " " + (value ? trueClass : falseClass);
  }
  
  switch (property) {
    case "text":       this.setValue     (value); break;
    case "readOnly":   this.setReadOnly  (value); break;
    case "required":   this.setRequired  (value); break;
    case "relevant":   this.setRelevant  (value); break;
    case "constraint": this.setConstraint(value); break;
    
    default:
      assert(false, "bad property: " + property);
  }
  
  this.touchProperty(property, value);
};

// Called to indicate that the specified property for this control has changed.
//
// Parameters:
//     property: The property name.
//     value:    The new value for the property.
XFormControlInstance.prototype.touchProperty = function(property, value) {
  var listenersCount = this.listeners[property].length;
  
  for (var i = 0; i < listenersCount; i++) {
    xform.waitingListeners.push([this.listeners[property][i], this, property, value]);
  }
};

// Adds a listener that is notified whenever the specified property of this
// control changes.
//
// Parameters:
//     property: The property to monitor.
//     listener: A function to call when the property value changes.
XFormControlInstance.prototype.addListener = function(property, listener) {
  this.listeners[property].push(listener);
};

// Gets the string value of the control.
XFormControlInstance.prototype.getValue = function() {
  throw new XFormException(this.xmlNode, this + " control is write-only.");
};

// Sets the string value of the control.
XFormControlInstance.prototype.setValue = function(value) {
};

// Should be called whenever the control's value changes. Changes the value of
// the underlying instance data and recalculates/refreshes the model.
//
// Parameters:
//     element:   The control's UI element. If the control has multiple UI
//                elements, valueHasChanged should be called for each one.
//     eventName: The name of the event to handle.
XFormControlInstance.prototype.valueHasChanged = function() {
  var model  = this.binding.model;
  var vertex = model.graph.getVertex(this.binding.boundNodes[0], "text");
  
  vertex.setValue(this.getValue());
  
  if (!model.hasChanged()) {
    return;
  }
  
  clearStatus();
  
  XmlEvent.dispatch(model.htmlNode, "xforms-recalculate");
  XmlEvent.dispatch(model.htmlNode, "xforms-revalidate");
  XmlEvent.dispatch(this .htmlNode, "xforms-value-changed");
  XmlEvent.dispatch(model.htmlNode, "xforms-refresh");
};

// Adds event handlers to the specified element to call valueHasChanged whenever
// the specified event is triggered.
//
// Parameters:
//     element:    The control's UI element. If the control has multiple UI
//                 elements, addEventHandlers should be called for each one.
//     eventNames: A list of event names to create listeners for.
XFormControlInstance.prototype.valueChangedOn = function(element, eventNames) {
  if (this.binding == null) {
    return;
  }
  
  for (var i = 0; i < eventNames.length; i++) {
    new EventListener(
      element, eventNames[i], null,
      
      functionCall(setTimeout, methodCall(this, "valueHasChanged"), 1)
    );
  }
};

// Adds event handlers to the specified element to issue a DOMFocusIn/Out event
// whenever the specified HTML element gains/loses focus.
//
// Parameters:
//     element: The control's UI element, such as an <input/> or <select/>
//              element. If the control has multiple UI elements, this function
//              should be called for each one.
XFormControlInstance.prototype.watchForFocusChange = function(element) {
  new EventListener(element, "focus", null, functionCall(XmlEvent.dispatch, this.htmlNode, "DOMFocusIn"));
  new EventListener(element, "blur",  null, functionCall(XmlEvent.dispatch, this.htmlNode, "DOMFocusOut"));
};

// Adds an event handler to the specified element to issue a DOMActivate event
// when the user presses enter.
//
// Parameters:
//     element: The control's UI element, such as an <input/> or <select/>
//              element. If the control has multiple UI elements, this function
//              should be called for each one.
XFormControlInstance.prototype.activateOnEnter = function(element) {
  var self = this;
  
  new EventListener(element, "keypress", null,
    function(event) {
      if (event.keyCode == 13) {
        XmlEvent.dispatch(self.htmlNode, "DOMActivate");
      }
    }
  );
};


// Sets the read-only status of the control: if true, the control is made
// read-only; if false, the control is made read-write.
XFormControlInstance.prototype.setReadOnly = function(isReadOnly) {
};

// Sets the relevance of the control: if true, the control is enabled; if false,
// the control is disabled.
XFormControlInstance.prototype.setRelevant = function(isEnabled) {
};

// Sets the "requiredness" of the control: if true, a value is required; if
// false, a value is optional.
XFormControlInstance.prototype.setRequired = function(isRequired) {
};

// Sets the validity of the control: if true, the control is valid; if false,
// the control is invalid.
XFormControlInstance.prototype.setConstraint = function(isValid) {
};

XForms.THIRD_PARTY_FUNCTIONS = new XPathFunctionMap();
XmlEvent.define("DOMActivate",          "Events", true,  true);
XmlEvent.define("DOMFocusIn",           "Events", true,  false);
XmlEvent.define("DOMFocusOut",          "Events", true,  false);
XmlEvent.define("xforms-select",        "Events", true,  false);
XmlEvent.define("xforms-deselect",      "Events", true,  false);
XmlEvent.define("xforms-value-changed", "Events", true,  false);

XmlEvent.define("xforms-valid",         "Events", true,  false);
XmlEvent.define("xforms-invalid",       "Events", true,  false);
XmlEvent.define("xforms-enabled",       "Events", true,  false);
XmlEvent.define("xforms-disabled",      "Events", true,  false);
XmlEvent.define("xforms-optional",      "Events", true,  false);
XmlEvent.define("xforms-required",      "Events", true,  false);
XmlEvent.define("xforms-readonly",      "Events", true,  false);
XmlEvent.define("xforms-readwrite",     "Events", true,  false);

// --- xforms/controls/container.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Base class for all controls.
//
// Parameters:
//     element:       The element from which the control was created.
//     bind:          The control's bind, or null if it is unbound.
//     label:         The control's label, or null if it has none.
//     innerControls: The controls inside of this control.
//     isGroup:       Is the control a container for other controls?
function XFormContainerControl(element, bind, label, innerControls) {
  if (arguments.length == 0) {
    return;
  }

  assert(innerControls != null, "innerControls is null");
  
  XFormControl.call(this, element, bind, label);

  this.innerControls = innerControls;
  
  for (var i in this.innerControls) {
    assert(this.innerControls[i].enclosedBy == null, "enclosedBy is not null");
           this.innerControls[i].enclosedBy = this;
  }
};

XFormContainerControl.inherits(XFormControl);


// Parameters:
//     control: The control that was rendered.
//     binding: The XFormBinding to which the rendered control is bound, or null
//              if the control is unbound.
function XFormContainerControlInstance(control, binding, htmlNode, outerBinding, position) {
  if (arguments.length == 0) {
    return;
  }
  
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding, position);
  
  this.innerControlInstances = [];
};

XFormContainerControlInstance.inherits(XFormControlInstance);

XFormContainerControlInstance.prototype.activate = function() {
  for (var i in this.innerControlInstances)
  for (var j in this.innerControlInstances[i]) {
    this.innerControlInstances[i][j].activate();
  }
};

XFormContainerControlInstance.prototype.deactivate = function() {
  for (var i in this.innerControlInstances)
  for (var j in this.innerControlInstances[i]) {
    this.innerControlInstances[i][j].deactivate();
  }
};

// --- xforms/controls/label.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new label.
//
// Parameters:
//     element:  The element from which the label was created.
//     bind:     The label's bind, or null if it is unbound.
//     children: Any controls inside of the label.
function XFormLabel(element, bind, children) {
  XFormControl.call(this, element, bind, children);
};

XFormLabel.inherits(XFormControl);


XFormParser.prototype.parseLabel = function(element) {
  var labelElement = this.getLabelElement(element);
  var parseElement = (labelElement == null) ? element : labelElement;

  if (parseElement == null) {
    return null;
  }

  var bind     = this.getBindFor   (parseElement);
  var children = this.parseUiInline(parseElement);

  return new XFormLabel(parseElement, bind, children);
};


XFormLabel.prototype.createInstance = function(binding, htmlNode, outerBinding, position) {
  return new XFormLabelInstance(this, binding, htmlNode, outerBinding, position);
};

function XFormLabelInstance(control, binding, htmlNode, outerBinding, position) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding, position);

  this.labelElement = document.createElement("label");
  this.container    = this.labelElement;

  this.htmlNode.appendChild(this.labelElement);
  this.htmlNode.className = "xforms-label";
};

XFormLabelInstance.inherits(XFormControlInstance);

XFormLabelInstance.prototype.postInstantiate = function() {
  var self           = this;
  var innerInstances = this.innerControlInstances[0].length;
  
  for (var i = 0; i < innerInstances; i++) {
    this.innerControlInstances[0][i].addListener("text", function() {
      self.touchProperty("text", self.getValue());
    });
  }

  // Notify parent control that the label has been instantiated.
  var parent   = this.enclosedBy;
  var ancestor = this.xmlElement.parentNode;
  
  while (ancestor != null && ancestor.namespaceURI != XmlNamespaces.XFORMS) {
    ancestor = ancestor.parentNode;
  }
  
  if (XForm.isXFormsElement(ancestor, ["item", "itemset"])) {
    return;
  }

  if (parent == null) {
    var parentControl = xform.getObjectByNode(ancestor);
    parent = parentControl.activeInstance;
  }

  parent.addInstantiatedLabel(this);
};

XFormLabelInstance.prototype.getValue = function() {
  return XPathFunction.stringValueOf(this.labelElement);
};

XFormLabelInstance.prototype.setValue = function(value) {
  // Remove the current contents of the label and replace them with a new text
  // node.
  while (this.labelElement.hasChildNodes()) {
    this.labelElement.removeChild(this.labelElement.firstChild);
  }

  this.labelElement.appendChild(document.createTextNode(value));
};


XFormLabel        .prototype.toString =
XFormLabelInstance.prototype.toString = function() {
  return "label";
};

// --- xforms/controls/input.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new input control.
//
// Parameters:
//     element: The element from which the input control was created.
//     bind:    The control's bind.
//     label:   The control's label.
function XFormInputControl(element, bind, innerControls, incremental) {
  assert(bind  != null, "input: bind is null");
  assert(innerControls != null, "innerControls is null");
  
  var labelControl     = null;
  var numInnerControls = innerControls.length;
  
  for (var i = 0; i < numInnerControls; i++) {
    if (innerControls[i] != null && innerControls[i] == "label") {
      labelControl = innerControls[i];
    }
  }
  
  assert(labelControl != null, "input: label is null");
  
  XFormControl.call(this, element, bind, innerControls);
  
  this.incremental = incremental;
};

XFormInputControl.inherits(XFormControl);


XFormParser.prototype.parseInputControl = function(element) {
  return new XFormInputControl(
    element,
    
    this.getBindFor      (element),
    this.parseControls   (element),
    this.parseIncremental(element, false)
  );
};


XFormInputControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormInputControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormInputControlInstance(control, binding, htmlNode, outerBinding) {
  assert(binding != null, "binding is null");
  
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.inControl      = document.createElement("input");
  this.inControl.type = "text";
  
  this.valueChangedOn(this.inControl,
    this.control.incremental
      ? ["keypress", "keydown", "click", "mousedown", "change"]
      : ["change"]
  );
  
  this.watchForFocusChange(this.inControl);
  this.activateOnEnter    (this.inControl);
};

XFormInputControlInstance.inherits(XFormControlInstance);

XFormInputControlInstance.prototype.addInstantiatedLabel = function(label) {
	this.control.label = label;
	this.htmlNode.appendChild(this.addLabel(this.inControl));
};

XFormInputControlInstance.prototype.getValue = function() {
  return this.inControl.value;
};

XFormInputControlInstance.prototype.setValue = function(value) {
  if (this.inControl.value != value) {
      this.inControl.value  = value;
  }
};

XFormInputControlInstance.prototype.setReadOnly = function(isReadOnly) {
  this.inControl.readOnly = isReadOnly;
};


XFormInputControl        .prototype.toString =
XFormInputControlInstance.prototype.toString = function() {
  return "input";
};

// --- xforms/controls/textArea.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new text area control.
//
// Parameters:
//     element: The element from which the text area was created.
//     bind:    The control's bind.
//     label:   The control's label.
function XFormTextAreaControl(element, bind, innerControls, incremental) {
  assert(bind  != null, "textarea: bind is null");
  assert(innerControls != null, "innerControls is null");
  
  var labelControl = null;
  var len = innerControls.length;
  for (var i = 0; i < len; i++) {
    if (innerControls[i] != null && innerControls[i] == "label") {
      labelControl = innerControls[i];
    }
  }
  
  assert(labelControl != null, "textarea: label is null");
  
  XFormControl.call(this, element, bind, innerControls);
  
  this.incremental = incremental;
};

XFormTextAreaControl.inherits(XFormControl);


XFormParser.prototype.parseTextAreaControl = function(element) {
  return new XFormTextAreaControl(
    element, 
    
    this.getBindFor      (element), 
    this.parseControls   (element),
    this.parseIncremental(element, false)
  );
};



XFormTextAreaControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormTextAreaControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormTextAreaControlInstance(control, binding, htmlNode, outerBinding) {
  assert(binding != null, "binding is null");
  
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.textArea = document.createElement("textarea");
  
  this.valueChangedOn(this.textArea,
    this.control.incremental
      ? ["keypress", "keydown", "click", "mousedown", "change"]
      : ["change"]
  );
  
  this.watchForFocusChange(this.textArea);
};

XFormTextAreaControlInstance.inherits(XFormControlInstance);

XFormTextAreaControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.control.label = label;
  this.htmlNode.appendChild(this.addLabel(this.textArea));
};

XFormTextAreaControlInstance.prototype.getValue = function() {
  return this.textArea.value;
};

XFormTextAreaControlInstance.prototype.setValue = function(value) {
  if (this.textArea.value != value) {
      this.textArea.value  = value;
  }
};

XFormTextAreaControlInstance.prototype.setReadOnly = function(isReadOnly) {
  this.textArea.readOnly = isReadOnly;
};


XFormTextAreaControl        .prototype.toString =
XFormTextAreaControlInstance.prototype.toString = function() {
  return "textarea";
};

// --- xforms/controls/secret.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new secret control.
//
// Parameters:
//     element: The element from which the secret control was created.
//     bind:    The control's bind.
//     label:   The control's label.
function XFormSecretControl(element, bind, innerControls, incremental) {
  assert(bind  != null, "secret: bind is null");
  assert(innerControls != null, "innerControls is null");
  
  var labelControl = null;
  var len = innerControls.length;
  for (var i = 0; i < len; i++) {
    if (innerControls[i] != null && innerControls[i] == "label") {
      labelControl = innerControls[i];
    }
  }
  
  assert(labelControl != null, "secret: label is null");
  
  XFormControl.call(this, element, bind, innerControls);
  
  this.incremental = incremental;
};

XFormSecretControl.inherits(XFormControl);


XFormParser.prototype.parseSecretControl = function(element) {
  return new XFormSecretControl(
    element,
    
    this.getBindFor      (element),
    this.parseControls   (element),
    this.parseIncremental(element, false)
  );
};


XFormSecretControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormSecretControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormSecretControlInstance(control, binding, htmlNode, outerBinding) {
  assert(binding != null, "binding is null");
  
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.secret      = document.createElement("input");
  this.secret.type = "password";
  
  this.valueChangedOn(this.secret,
    this.control.incremental
      ? ["keypress", "keydown", "click", "mousedown", "change"]
      : ["change"]
  );
  
  this.watchForFocusChange(this.secret);
  this.activateOnEnter    (this.secret);
  
  // Fire "DOMActivate" events when the user presses enter.
  var self = this;
  
  new EventListener(this.secret, "keypress", null,
    function(event) {
      if (event.keyCode == 13) {
        XmlEvent.dispatch(self.htmlNode, "DOMActivate");
      }
    }
  );
};

XFormSecretControlInstance.inherits(XFormControlInstance);

XFormSecretControlInstance.prototype.addInstantiatedLabel = function(label) {
	this.control.label = label;
	this.htmlNode.appendChild(this.addLabel(this.secret));
};

XFormSecretControlInstance.prototype.getValue = function() {
  return this.secret.value;
};

XFormSecretControlInstance.prototype.setValue = function(value) {
  if (this.secret.value != value) {
      this.secret.value  = value;
  }
};

XFormSecretControlInstance.prototype.setReadOnly = function(isReadOnly) {
  this.secret.readOnly = isReadOnly;
};


XFormSecretControl        .prototype.toString =
XFormSecretControlInstance.prototype.toString = function() {
  return "secret";
};

// --- xforms/controls/output.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XForms output control.
//
// Parameters:
//     element: The element from which the output control was created.
//     bind:    The control's bind, or an XPath expression if the <output/>
//              element had a @value attribute.
//     label:   The control's label, or null if it has none.
function XFormOutputControl(element, bind, innerControls) {
  if (instanceOf(bind, XFormBind)) {
    XFormControl.call(this, element, bind, innerControls);
  }
  else {
    if (bind == null) {
      XFormControl.call(this, element, null, innerControls);
    }
    else {
      assert(instanceOf(bind, XPath), "bind is not an XPath");
      
      XFormControl.call(this, element, null, innerControls);
      
      this.value = bind;
    }
  }
};

XFormOutputControl.inherits(XFormControl);


XFormParser.prototype.parseOutputControl = function(element) {
  var bind          = this.getBindFor   (element);
  var innerControls = this.parseControls(element);
  
  if (bind != null)  {
    return new XFormOutputControl(element, bind, innerControls);
  }
  
  var value = this.xpathValue(element, "value", null);
  
  return new XFormOutputControl(element, value, innerControls);
};


XFormOutputControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormOutputControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormOutputControlInstance(control, binding, htmlNode, outerBinding) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.output = document.createElement("span");
  
  var labelElement = this.getLabelElement();
  
	if(labelElement == null) {
    this.htmlNode.appendChild(this.addLabel(this.output));
	}
};

XFormOutputControlInstance.inherits(XFormControlInstance);

XFormOutputControlInstance.prototype.addInstantiatedLabel = function(label) {
	this.control.label = label;
	this.htmlNode.appendChild(this.addLabel(this.output));
};

XFormOutputControlInstance.prototype.setValue = function(value) {
  while (this.output.hasChildNodes()) {
    this.output.removeChild(this.output.lastChild);
  }
  
  this.output.appendChild(document.createTextNode(value));
};

XFormOutputControl        .prototype.toString =
XFormOutputControlInstance.prototype.toString = function() {
  return "output";
};

// --- xforms/controls/select.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new select control.
//
// Parameters:
//     element: The element from which the select control was created.
//     bind:    The control's bind.
//     label:   The control's label.
function XFormSelectControl(element, selectMany, bind, innerControls, items, incremental, appearance) {
  assert(bind  != null, "select: bind is null");
  assert(items != null, "select: items is null");
  assert(innerControls != null, "innerControls is null");
  
  var labelControl = null;
  var len = innerControls.length;
  for (var i = 0; i < len; i++) {
    if (innerControls[i] != null && innerControls[i] == "label") {
      labelControl = innerControls[i];
    }
  }
  
  assert(labelControl != null, "select: label is null");
  
  XFormControl.call(this, element, bind, innerControls);
  
  this.selectMany  = selectMany;
  this.items       = items;
  this.incremental = incremental;
  this.appearance  = appearance;
};

XFormSelectControl.inherits(XFormControl);


XFormParser.prototype.parseSelectControl = function(element) {
  return new XFormSelectControl(
    element,
    element.tagName.replace(/^.*:/, "") == "select",
    
    this.getBindFor             (element, true),
    this.parseControls          (element),
    this.parseSelectControlItems(element), 
    this.parseIncremental       (element, true),
    this.parseAppearance        (element, "minimal")
  );
};

XFormParser.prototype.parseSelectControlItems = function(element) {
  var items = [];
  
  for (var childElement = element.firstChild; childElement != null; childElement = childElement.nextSibling) {
    if (childElement.nodeType == 1) {
      var value = null;
      for (var child = childElement.lastChild; child != null; child = child.previousSibling) {
        if (child.nodeType == 1) {
          if (child.nodeName.replace(/^.*:/, "") == "value" && child.namespaceURI == XmlNamespaces.XFORMS) {
            value = child;
            break;
          }
        }
      }
      
      switch (childElement.tagName.replace(/^.*:/, "")) {
        case "item":
          items.push({
            label: this.parseLabel(childElement),
            value: value.firstChild.nodeValue
          });
          
          break;
          
        case "itemset":
          items.push({
            bind:  this.getBindFor(childElement),
            label: this.parseLabel(childElement),
            value: this.getBindFor(value)
          });
          
          break;
      }
    }
  }
  
  return items;
};


XFormSelectControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormSelectControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormSelectControlInstance(control, binding, htmlNode, outerBinding) {
  assert(binding != null, "binding is null");
  
  var self = this;
      
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.items = this.getItems();
  var itemLen = this.items.length;
  
  if (this.control.appearance == "full") {
    this.buttons   = [];
    this.groupName = uniqueId();
    this.list      = document.createElement("div");
    
    for (var i = 0; i < itemLen; i++) {
      var listItem = document.createElement("div");
      var type     = control.selectMany ? "checkbox" : "radio";
  
      // Why doesn't "button.name = '...'" work?!
      if (userAgent.isInternetExplorer) {
        var button = document.createElement("<input type='" + type + "' name='" + this.groupName + "' />");
      }
      else {
        var button  = document.createElement("input");
        button.type = type;
      }
      
      button.name = this.groupName;
      button.id   = uniqueId();
      
      listItem .appendChild(button);
      listItem .appendChild(document.createTextNode(" "));
      listItem .appendChild(this.items[i].label.htmlNode);
      this.list.appendChild(listItem);
      
      this.items[i].label.labelElement.setAttribute("for", button.id);
      
      this.valueChangedOn     (button,                           ["click"]);
      this.valueChangedOn     (this.items[i].label.labelElement, ["click"]);
      
      this.watchForFocusChange(button);
      this.activateOnEnter    (button);
            
      this.buttons.push(button);
    }
  }
  else {
    this.selectBox = document.createElement("select");
    
    if (this.control.selectMany) {
      this.selectBox.multiple = true;
    }
    
    if (this.control.selectMany || this.control.appearance == "compact") {
      this.selectBox.size = Math.min(this.items.length, 5);
    }
    
    for (var i = 0; i < itemLen; i++) {
      var option = document.createElement("option");
      var label  = this.items[i].label;
      
      option.appendChild(document.createTextNode(label.getValue()));
      
      (function() {
        var thisOption = option;
        
        // When the label text changes, change the option text.
        label.addListener("text", function(control, property, value) {
          while (thisOption.hasChildNodes()) {
            thisOption.removeChild(thisOption.firstChild);
          }
          
          thisOption.appendChild(document.createTextNode(control.getValue()));
        });
      }) ();
  
      this.selectBox.appendChild(option);
    }
    
    this.selection(this.selectBox, ["keypress", "keydown", "click", "mousedown", "change"]);
    
    this.valueChangedOn(this.selectBox,
      this.control.incremental
        ? ["keypress", "keydown", "click", "mousedown", "change"]
        : ["change"]
    );
    
    this.watchForFocusChange(this.selectBox);
    this.activateOnEnter    (this.selectBox);
  }
};

XFormSelectControlInstance.inherits(XFormControlInstance);

XFormSelectControlInstance.prototype.selection = function(element, eventNames) {
  if (this.binding == null) {
    return;
  }
  
  var events = eventNames.length;
  for (var i = 0; i < events; i++) {
    new EventListener(
      element, eventNames[i], null,
      
      functionCall(setTimeout, methodCall(this, "itemSelected"), 1)
    );
  }
};

XFormSelectControlInstance.prototype.itemSelected = function() {
  XmlEvent.dispatch(this .htmlNode, "xforms-deselect");
  XmlEvent.dispatch(this .htmlNode, "xforms-select");
};

XFormSelectControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.control.label = label;
  
  if (this.control.appearance == "full") {
    this.htmlNode.appendChild(this.addLabel(this.list));
  }
  else {
    this.htmlNode.appendChild(this.addLabel(this.selectBox));
  }
};

XFormSelectControlInstance.prototype.getItems = function() {
  var items = [];
  var itemLen = this.control.items.length;
  
  for (var i = 0; i < itemLen; i++) {
    // <itemset/>
    if (this.control.items[i].bind) {
      var itemset        = this.control.items[i];
      var itemsetBinding = this.binding.innerBindings[0][itemset.bind.index];
      var boundNodesLen  = itemsetBinding.boundNodes.length;
      
      for (var j = 0; j < boundNodesLen; ++j) {
        var valueBinding = itemsetBinding.innerBindings[j][itemset.value.index];
        
        items.push({
          label: itemset.label.instantiate(this, itemsetBinding, j, document.createElement("span")),
          value: XPathFunction.stringValueOf(valueBinding.boundNodes[0])
        });
      }
    }
    // <item/>
    else {
      var item = this.control.items[i];
      
      items.push({
        label: item.label.instantiate(this, this.binding, 0, document.createElement("span")),
        value: item.value
      });
    }
  }
  
  return items;
};

XFormSelectControlInstance.prototype.getValue = function() {
  if (this.control.selectMany && this.control.appearance == "full") {
    var values = [];
    //var blen = this.buttons.length;
    
    for (var i = this.buttons.length - 1; i >= 0; --i) {
      if (this.buttons[i].checked) {
        values.push(this.items[i].value);
      }
    }

    return values.join(" ");
  }
  else if (this.control.selectMany) {
    var values = [];
    //var options = this.selectBox.options.length;
    
    for (var i = this.selectBox.options.length -1; i >= 0; --i) {
      if (this.selectBox.options[i].selected) {
        values.push(this.items[i].value);
      }
    }
    
    return values.join(" ");
  }
  else if (this.control.appearance == "full") {
    //var blen = this.buttons.length;
    for (var i = this.buttons.length - 1; i >= 0; --i) {
      if (this.buttons[i].checked) {
        return this.items[i].value;
      }
    }

    return "";
  }
  else {
    if (this.selectBox.selectedIndex >= 0) {
      return this.items[this.selectBox.selectedIndex].value;
    }

    return "";
  }
};

XFormSelectControlInstance.prototype.setValue = function(value) {
  if (this.control.selectMany && this.control.appearance == "full") {
    // Split the string into individual values, and then select each option whose
    // value is in the list.
    var values = value.split(/\s+/);
    var vals   = values.length;
    
    for (var i = this.items.length - 1; i >= 0; --i) {
      var item   = this.items  [i];
      var button = this.buttons[i];
      
      button.checked = false;
      
      for (var j = vals - 1; j >= 0; --j) {
        if (values[j] == item.value) {
          button.checked = true;
          break;
        }
      }
    }
  }
  else if (this.control.selectMany) {
    // Split the string into individual values, and then select each option whose
    // value is in the list.
    var values = value.split(/\s+/);
    var vals   = values.length;
    
    for (var i = this.items.length - 1; i >= 0; --i) {
      var item   = this.items[i];
      var option = this.selectBox.options[i];
      
      option.selected = false;
      
      for (var j = vals - 1; j >= 0; --j) {
        if (values[j] == item.value) {
          option.selected = true;
          break;
        }
      }
    }
  }
  else if (this.control.appearance == "full") {
    for (var i = this.items.length - 1; i >= 0; --i) {
      if (this.items[i].value == value) {
        this.buttons[i].checked = true;
        return;
      }
    }
  }
  else {
    for (var i = this.items.length - 1; i >= 0; --i) {
      if (this.items[i].value == value) {
        this.selectBox.selectedIndex = i;
        return;
      }
    }
  }
};

XFormSelectControlInstance.prototype.setReadOnly = function(isReadOnly) {
  if (this.control.appearance == "full") {
    var blen = this.buttons.length;
    for (var i = 0; i < blen; i++) {
      this.buttons[i].disabled = isReadOnly;
    }
  }
  else {
    this.selectBox.disabled = isReadOnly;
  }
};


XFormSelectControl        .prototype.toString =
XFormSelectControlInstance.prototype.toString = function() {
  return "select";
};

// --- xforms/controls/trigger.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new trigger control.
//
// Parameters:
//     element:    The element from which the trigger control was created.
//     bind:       The control's bind.
//     label:      The control's label.
//     appearance: The appearance of the control.
function XFormTriggerControl(element, bind, innerControls, appearance) {
  if (arguments.length == 0) {
    return;
  }

  assert(innerControls != null, "innerControls is null");
  
  var labelControl     = null;
  var numInnerControls = innerControls.length;
  
  for (var i = 0; i < numInnerControls; i++) {
    if (innerControls[i] != null && innerControls[i] == "label") {
      labelControl = innerControls[i];
    }
  }
  
  assert(labelControl != null, "trigger: label is null");
  
  XFormControl.call(this, element, bind, innerControls, appearance);
};

XFormTriggerControl.inherits(XFormControl);


XFormParser.prototype.parseTriggerControl = function(element) {
  return new XFormTriggerControl(
    element,
    
    this.getBindFor     (element),
    this.parseControls  (element),
    this.parseAppearance(element, "full")
  );
};



XFormTriggerControl.prototype.createInstance = function(binding, htmlNode, outerBinding, position) {
  return new XFormTriggerControlInstance(this, binding, htmlNode, outerBinding, position);
};

function XFormTriggerControlInstance(control, binding, htmlNode, outerBinding, position) {
  if (arguments.length == 0) {
    return;
  }

  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding, position);
  
  var self = this;
  
  switch (control.appearance) {
    case "full":
    case "compact":
      this.button       = document.createElement("input");
      this.button.type  = "button";
      this.button.value = "";
      
      this.htmlNode.appendChild(this.button);
      this.watchForFocusChange (this.button);
      
      // Fire "DOMActivate" events when the button is clicked.
      new EventListener(this.button, "click", null,
        functionCall(XmlEvent.dispatch, this.htmlNode, "DOMActivate"));
      
      break;
      
    case "minimal":
      this.anchor      = document.createElement("a");
      this.anchor.href = "javascript:void(0)";
      
      this.htmlNode.appendChild(this.anchor);
      this.watchForFocusChange (this.anchor);
      
      // Fire "DOMActivate" events when the link is clicked.
      new EventListener(this.anchor, "click", null,
        functionCall(XmlEvent.dispatch, this.htmlNode, "DOMActivate"));
      
      break;
      
    default:
      assert(false, "Unknown appearance value: " + this.appearance);
  }
};

XFormTriggerControlInstance.inherits(XFormControlInstance);

XFormTriggerControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.label = label;
  var self   = this;
  
  switch (this.control.appearance) {
    case "full":
    case "compact":
      this.button.value = label.getValue();
      
      // When the label text changes, change the button's label.
      this.label.addListener("text", function(control, property, value) {
        self.button.value = value;
      });
      
      break;
      
    case "minimal":
      this.anchor.appendChild(document.createTextNode(label.getValue()));
      
      // When the label text changes, change the anchor's text.
      this.label.addListener("text", function(control, property, value) {
        self.anchor.replaceChild(document.createTextNode(value), self.anchor.firstChild);
      });
      
      break;
  }
};

XFormTriggerControl        .prototype.toString =
XFormTriggerControlInstance.prototype.toString = function() {
  return "trigger";
};

// --- xforms/controls/group.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XForms group control.
//
// Parameters:
//     element:       The element from which the group control was created.
//     bind:          The control's bind.
//     label:         The control's label, or null if it has none.
//     innerControls: The controls immediately enclosed by the group.
function XFormGroupControl(element, bind, innerControls) {
  assert(innerControls != null, "innerControls is null");
  
  XFormControl.call(this, element, bind, innerControls);
};

XFormGroupControl.inherits(XFormControl);


XFormParser.prototype.parseGroupControl = function(element) {
  var bind          = this.getBindFor   (element);
  var innerControls = this.parseControls(element);
  
  return new XFormGroupControl(element, bind, innerControls);
};


XFormGroupControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormGroupControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormGroupControlInstance(control, binding, htmlNode, outerBinding) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.container = document.createElement("span");
  
  var labelElement = this.getLabelElement();
  
  if (labelElement == null) {
    this.htmlNode.appendChild(this.addLabel(this.container));
  }
};

XFormGroupControlInstance.inherits(XFormControlInstance);

XFormGroupControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.control.label = label;
  this.htmlNode.appendChild(this.addLabel(this.container));
};

XFormGroupControl        .prototype.toString =
XFormGroupControlInstance.prototype.toString = function() {
  return "group";
};

// --- xforms/controls/repeat.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new XForms repeat control.
//
// Parameters:
//     element:       The element from which the repeat control was created.
//     bind:          The repeat's bind.
//     label:         The repeat's label, or null if it has none.
//     innerControls: The controls immediately enclosed by the repeat.
//     number:        The initial number of repeat items to display, or null if
//                    unspecified.
function XFormRepeatControl(element, bind, innerControls, number) {
  assert(bind          != null, "repeat: bind is null");
  assert(innerControls != null, "repeat: innerControls is null");
  
  XFormControl.call(this, element, bind, innerControls);
  
  this.number = number;
  this.index  = 0;
};

XFormRepeatControl.inherits(XFormControl);


XFormParser.prototype.parseRepeatControl = function(element) {
  var bind          = this.getBindFor   (element);
  var innerControls = this.parseControls(element);
  
  return new XFormRepeatControl(element, bind, innerControls, null);
};


XFormRepeatControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormRepeatControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormRepeatControlInstance(control, binding, htmlNode, outerBinding) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.container = this.htmlNode;
};

XFormRepeatControlInstance.inherits(XFormControlInstance);

XFormRepeatControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.control.label = label;
};

XFormRepeatControlInstance.prototype.postInstantiate = function() {
  var childLength    = this.htmlNode.childNodes.length;
  var i              = 0;
  var nodesPerRepeat = childLength /
                       this.binding.boundNodes.length;
                       
  for (var child = this.htmlNode.firstChild; child != null; child = child.nextSibling) {
    new EventListener(child, "DOMFocusIn", "default",
      methodCall(this, "setIndex", Math.floor(i / nodesPerRepeat))
    );
    
    ++i;
  }
};

XFormRepeatControlInstance.prototype.setIndex = function(index) {
  if (index < 0)                               { index = 0; }
  if (index >= this.binding.boundNodes.length) { index = this.binding.boundNodes.length - 1; }
  
  if (this.control.activeInstance != null) {
    for (var node = this.control.activeInstance.htmlNode.firstChild; node != null; node = node.nextSibling) {
      
      if (typeof(node.className) != "undefined") {
        node.className = node.className.replace(/\bxforms-repeat-index\b/g, "");
      }
    }
  }

  var nodesPerRepeat = this.htmlNode.childNodes.length /
                       this.binding .boundNodes.length;
                       
  for (var i = 0; i < nodesPerRepeat; ++i) {
    var node = this.htmlNode.childNodes.item(index * nodesPerRepeat + i);
    
    if (typeof(node.className) != "undefined") {
      node.className += " xforms-repeat-index";
    }
  }

  this.control.index          = index;
  this.control.activeInstance = this;
  
  var innerInstances  = this.innerControlInstances;
  var instancesLength = innerInstances.length;
  for (var i = 0; i < instancesLength; i++) {
    var innerInnerInstances  = innerInstances[i];
    var innerInstancesLength = innerInnerInstances.length;
    for (var j = 0; j < innerInstancesLength; j++) {
      var innerControlInstance = innerInnerInstances[j];
      
      if (i == index) {
        innerControlInstance.activate();
      }
      else {
        innerControlInstance.deactivate();
      }
    }
  }
  
  status("Index changed to " + (index + 1) + ".");
};

XFormRepeatControlInstance.prototype.activate = function() {
  this.setIndex(this.control.index);
};

XFormRepeatControlInstance.prototype.setRelevant = function(isEnabled) {
};
  
XFormRepeatControl        .prototype.toString =
XFormRepeatControlInstance.prototype.toString = function() {
  return "repeat";
};

// --- xforms/controls/switch.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new switch control.
//
// Parameters:
//     element: The element from which the switch control was created.
//     bind:    The control's bind.
//     cases:   The switch's cases.
function XFormSwitchControl(element, bind, cases) {
  XFormControl.call(this, element, bind, cases);
  
  this.cases = cases;
  
  var clen = cases.length;
  for (var i = 0; i < clen; i++) {
    this.cases[i].switchControl = this;
  }
  
  for (var i = 0; i < clen; i++) {
    // If we found a selected case, make all the ones after it unselected.
    if (this.cases[i].selected) {
      for (var j = +i + 1; j < clen; ++j) {
        this.cases[j].selected = false;
      }
      
      return;
    }
  }
  
  // We didn't find any selected cases, so select the first one.
  this.cases[0].selected = true;
};

XFormSwitchControl.inherits(XFormControl);


XFormParser.prototype.parseSwitchControl = function(element) {
  return new XFormSwitchControl(
    element,

    this.getBindFor      (element),
    this.parseSwitchCases(element)
  );
};

XFormParser.prototype.parseSwitchCases = function(element) {
  var cases        = [];

  for (var child = element.firstChild; child != null; child = child.nextSibling) {
    if (child.nodeName.replace(/^.*:/, "") == "case" && child.namespaceURI == XmlNamespaces.XFORMS) {
      cases.push(this.parseCaseControl(child));
    }
  }
  
  return cases;
};


XFormSwitchControl.prototype.createInstance = function(binding, htmlNode, outerBinding, position) {
  return new XFormSwitchControlInstance(this, binding, htmlNode, outerBinding, position);
};

function XFormSwitchControlInstance(control, binding, htmlNode, outerBinding, position) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding, position);
  
  this.container = this.htmlNode;
};

XFormSwitchControlInstance.inherits(XFormControlInstance);

XFormSwitchControlInstance.prototype.addInstantiatedLabel = function(label) {
};

XFormSwitchControl        .prototype.toString =
XFormSwitchControlInstance.prototype.toString = function() {
  return "switch";
};

// --- xforms/controls/case.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new case control.
//
// Parameters:
//     element: The element from which the case control was created.
//     bind:    The control's bind.
function XFormCaseControl(element, bind, selected, innerControls) {
  XFormControl.call(this, element, bind, innerControls);
  
  this.selected = selected;
};

XFormCaseControl.inherits(XFormControl);

XFormCaseControl.prototype.toggle = function() {
  var cases  = this.switchControl.cases.length;
  
  for (var i = 0; i < cases; i++) {
    var caseControl = this.switchControl.cases[i];
    
    caseControl.instance.htmlNode.style.display = "none";
    XmlEvent.dispatch(caseControl.instance.htmlNode, "xforms-deselect");
  }
  
  this.instance.htmlNode.style.display = "";
  
  XmlEvent.dispatch(this.instance.htmlNode, "xforms-select");
};


XFormParser.prototype.parseCaseControl = function(element) {
  return new XFormCaseControl(
    element,

    this.getBindFor       (element),
    this.parseCaseSelected(element),
    this.parseControls    (element)
  );
};

XFormParser.prototype.parseCaseSelected = function(element) {
  return this.booleanValue(element, "selected", "false");
};


XFormCaseControl.prototype.createInstance = function(binding, htmlNode, outerBinding, position) {
  return this.instance = new XFormCaseControlInstance(this, binding, htmlNode, outerBinding, position);
};

function XFormCaseControlInstance(control, binding, htmlNode, outerBinding, position) {
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding, position);

  this.container  = document.createElement("span");
  this.isSelected = false;
  
  this.htmlNode.style.display = (this.control.selected) ? "" : "none";
  
  var labelElement = this.getLabelElement();
  
  if (labelElement == null) {
    this.htmlNode.appendChild(this.addLabel(this.container));
  }
};

XFormCaseControlInstance.inherits(XFormControlInstance);

XFormCaseControlInstance.prototype.addInstantiatedLabel = function(label) {
  this.control.label = label;
  this.htmlNode.appendChild(this.addLabel(this.container));
};

XFormCaseControl        .prototype.toString =
XFormCaseControlInstance.prototype.toString = function() {
  return "case";
};

// --- xforms/controls/submit.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new submit control.
//
// Parameters:
//     element:    The element from which the trigger control was created.
//     bind:       The control's bind.
//     label:      The control's label.
//     appearance: The control's appearance.
//     submission: The submission to trigger.
function XFormSubmitControl(element, bind, innerControls, appearance, submission) {
  assert(innerControls != null, "innerControls is null");
  XFormTriggerControl.call(this, element, bind, innerControls, appearance);
  
  this.submission = submission;
};

XFormSubmitControl.inherits(XFormTriggerControl);


XFormParser.prototype.parseSubmitControl = function(element) {
  return new XFormSubmitControl(
    element,
    
    this.getBindFor                  (element),
    this.parseControls               (element),
    this.parseAppearance             (element, "full"),
    this.parseSubmitControlSubmission(element)
  );
};

XFormParser.prototype.parseSubmitControlSubmission = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("submission"));
};


XFormSubmitControl.prototype.createInstance = function(binding, htmlNode, outerBinding, position) {
  return new XFormSubmitControlInstance(this, binding, htmlNode, outerBinding, position);
};

function XFormSubmitControlInstance(control, binding, htmlNode, outerBinding, position) {
  XFormTriggerControlInstance.call(this, control, binding, htmlNode, outerBinding, position);
  
  // When the control is activated fire xforms-submit on the submission.
  new EventListener(this.htmlNode, "DOMActivate", null,
    functionCall(XmlEvent.dispatch, this.control.submission.htmlNode, "xforms-submit"));
};

XFormSubmitControlInstance.inherits(XFormTriggerControlInstance);


XFormSubmitControl        .prototype.toString =
XFormSubmitControlInstance.prototype.toString = function() {
  return "submit";
};

// --- xforms/controls/extension.js ---
// Creates a new XForms extension control.
//
// Parameters:
//     element:       The element from which the extension control was created.
function XFormExtensionControl(element) {
  
  XFormControl.call(this, element, null, [], null);
  this.parentNode = element.parentNode;
};

XFormExtensionControl.inherits(XFormControl);


XFormParser.prototype.parseExtensionControl = function(element) {
  
  return new XFormExtensionControl(element);
};


XFormExtensionControl.prototype.createInstance = function(binding, htmlNode, outerBinding) {
  return new XFormExtensionControlInstance(this, binding, htmlNode, outerBinding);
};

function XFormExtensionControlInstance(control, binding, container, outerBinding) {
//debugger
  var htmlNode = document.createElement("span");
  XFormControlInstance.call(this, control, binding, htmlNode, outerBinding);
  
  this.container = this.htmlNode;
};

XFormExtensionControlInstance.inherits(XFormControlInstance);


XFormExtensionControlInstance.prototype.postInstantiate = function() {
//	var parentObject = xform.getObjectForHtmlNode(xform.getHtmlNode(this.xmlNode.parentNode));

  for (var child = this.xmlNode.firstChild; child != null; child = child.nextSibling) {
		      if (child.nodeType == 1) {
           var qqn = new QName(child.nodeName,child);
           if(XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn))
           	XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn)(this,child);
		      
				}  
	}
		
};
  
XFormExtensionControl        .prototype.toString =
XFormExtensionControlInstance.prototype.toString = function() {
  return "extension";
};

// --- xforms/actions/action.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Base class for all XForm actions.
//
// Parameters:
//     element: The element from which this action was created.
function XFormAction(element) {
  if (arguments.length == 0) {
    return;
  }

  assert(element != null, "element is null");

  XFormObject.call(this, element, true);
};

XFormAction.inherits(XFormObject);

XFormParser.prototype.parseActions = function(element) {
  var container     = null;
  var actions       = [];
  var self          = this;
  
  if (element.nodeName.replace(/^.*:/, "") == "action" && element.namespaceURI == XmlNamespaces.XFORMS) {
    container = element;
  }
  
  locateActions(element);
  
  function locateActions(element) {
    for (var child = element.firstChild; child != null; child = child.nextSibling) {
      if (child.nodeType != 1) {
        continue;
      }
      
      if (child.namespaceURI != XmlNamespaces.XFORMS) {
        locateActions(child);
        continue;
      }
      
      var parent = child.parentNode;
      
      if (parent.nodeName.replace(/^.*:/, "") == "action"
       && parent.namespaceURI == XmlNamespaces.XFORMS
       && parent != container)
      {
        continue;
      }
      
      switch (child.nodeName.replace(/^.*:/, "")) {
        case "action":      actions.push(self.parseActionAction     (child)); break;
        case "insert":      actions.push(self.parseInsertAction     (child)); break;
        case "delete":      actions.push(self.parseDeleteAction     (child)); break;
        case "setindex":    assert(false, "<setindex/> not supported.");
        case "dispatch":    actions.push(self.parseDispatchAction   (child)); break;
        case "load":        actions.push(self.parseLoadAction       (child)); break;
        case "setvalue":    actions.push(self.parseSetValueAction   (child)); break;
        case "setfocus":    assert(false, "<setfocus/> not supported.");
        case "rebuild":     actions.push(self.parseRebuildAction    (child)); break;
        case "recalculate": actions.push(self.parseRecalculateAction(child)); break;
        case "revalidate":  actions.push(self.parseRevalidateAction (child)); break;
        case "refresh":     actions.push(self.parseRefreshAction    (child)); break;
        case "reset":       assert(false, "<reset/> not supported.");
        case "send":        actions.push(self.parseSendAction       (child)); break;
        case "message":     actions.push(self.parseMessageAction    (child)); break;
        case "toggle":      actions.push(self.parseToggleAction     (child)); break;
        
        default:
          locateActions(child);
          break;
      }
    }
  }

  return actions;
};


XFormAction.prototype.execute = function() {
  var self = this;
  var models = xform.models.length;
  
//  // Perform the action after a short delay to allow the browser display to
//  // refresh. This lets buttons be released so the interface feels more
//  // responsive.
//  setTimeout(function() {
    // Reset the flags for all models to false.
    for (var i = 0; i < models; i++) {
      xform.models[i].doRebuild     = false;
      xform.models[i].doRecalculate = false;
      xform.models[i].doRevalidate  = false;
      xform.models[i].doRefresh     = false;
    }
    
    // Execute the action.
    self.activate();
    
    // Rebuild, recalculate, revalidate, and refresh any models with their flags
    // set.
    for (var i = 0; i < models; i++) {
      if (xform.models[i] != null && xform.models[i] != "undefined") {
        if (xform.models[i].doRebuild)     { xform.models[i].rebuild    (); }
        if (xform.models[i].doRecalculate) { xform.models[i].recalculate(); }
        if (xform.models[i].doRevalidate)  { xform.models[i].revalidate (); }
        if (xform.models[i].doRefresh)     { xform.models[i].refresh    (); }
      }
    }
//  }, 1);
};

XFormAction.prototype.activate = function() {
  assert(false, "activate() not implemented");
};

// --- xforms/actions/series.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new <action/> action, which encapsulates a series of actions.
//
// Parameters:
//     element: The element from which this action was created.
function XFormActionAction(element, actions) {
  assert(actions != null, "actions is null");
  
  XFormAction.call(this, element);
  
  this.actions = actions;
};

XFormActionAction.inherits(XFormAction);


XFormParser.prototype.parseActionAction = function(element) {
  return new XFormActionAction(
    element,
    this.parseActionActionActions(element)
  );
};

XFormParser.prototype.parseActionActionActions = function(element) {
  return this.parseActions(element);
};


XFormActionAction.prototype.activate = function() {
  for (var i = 0; i < this.actions.length; i++) {
    this.actions[i].activate();
  }
};

XFormActionAction.prototype.toString = function() {
  return "action";
};

// --- xforms/actions/load.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new load action.
//
// Parameters:
//     element:   The element from which this action was created.
//     resource:  The resource URI to load.
//     newWindow: Open the resource in a new window?
//     bind:      An optional bind.
function XFormLoadAction(element, resource, newWindow, bind) {
  XFormAction.call(this, element);

  this.resource  = resource;
  this.newWindow = newWindow;
  this.bind      = bind;
};

XFormLoadAction.inherits(XFormAction);


XFormParser.prototype.parseLoadAction = function(element) {
  return new XFormLoadAction(
    element,
  
    this.parseLoadActionResource(element),
    this.parseLoadActionShow    (element) == "new",
    this.getBindFor             (element)
  );
};

XFormParser.prototype.parseLoadActionResource = function(element) {
  return this.stringValue(element, "resource");
};

XFormParser.prototype.parseLoadActionShow = function(element) {
  return this.stringValue(element, "show", "replace");
};


XFormLoadAction.prototype.activate = function() {
  if (this.newWindow) {
    window.open(this.resource);
  }
  else {
    window.location = this.resource;
  }
};

XFormLoadAction.prototype.toString = function() {
  return "load";
};

// --- xforms/actions/message.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new message action.
//
// Parameters:
//     element: The element from which this action was created.
//     level:   The message level identifier, one of "ephemeral", "modeless", or
//              "modal".
//     text:    The text of the message.
function XFormMessageAction(element, level, text) {
  assert(level == "ephemeral" || level == "modeless" || level == "modal", "bad level: " + level);
  assert(text  != null, "text is null");
  
  XFormAction.call(this, element);

  this.level = level;
  this.text  = text.toString().replace(/^\s+|\s+$/, "");
};

XFormMessageAction.inherits(XFormAction);


XFormParser.prototype.parseMessageAction = function(element) {
  return new XFormMessageAction(
    element,
    
    this.parseMessageActionLevel(element),
    this.parseMessageActionText (element)
  );
};

XFormParser.prototype.parseMessageActionLevel = function(element) {
  return this.stringValue(element, "level");
};

XFormParser.prototype.parseMessageActionText = function(element) {
  return XPathFunction.stringValueOf(element);
};


XFormMessageAction.prototype.activate = function() {
  switch (this.level) {
    case "modal":
      alert(this.text);
  }
};

XFormMessageAction.prototype.toString = function() {
  return "message";
};

// --- xforms/actions/setvalue.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new setvalue action.
//
// Parameters:
//     element: The element from which this action was created.
//     bind:    The action's bind.
//     value:   The XPath expression or string literal value.
function XFormSetValueAction(element, bind, value) {
  assert(bind  != null, "setvalue: bind is null");
  assert(value != null, "setvalue: value is null");
  
  XFormAction.call(this, element);

  this.bind  = bind;
  this.value = value;
};

XFormSetValueAction.inherits(XFormAction);


XFormParser.prototype.parseSetValueAction = function(element) {
  return new XFormSetValueAction(
    element,
    
    this.getBindFor              (element),
    this.parseSetValueActionValue(element)
  );
};

XFormParser.prototype.parseSetValueActionValue = function(element) {
  var value = this.xpathValue(element, "value", null);
  
  if (value == null) {
    value = XPathFunction.stringValueOf(element);
  }
  
  return value;
};


XFormSetValueAction.prototype.activate = function() {
  var boundNodes = this.bind.defaultBinding.getBoundNodes();
  
  // If the <setvalue/> action is bound to an empty node-set, return immediately.
  if (boundNodes.length == 0) {
    return;
  }
  
  var node   = boundNodes[0];
  var vertex = this.bind.model.graph.addVertex(node, "text");
  var value  = this.value;
  
  if (instanceOf(value, XPath)) {
    value = XPath.STRING_FUNCTION.evaluate(value.evaluate(new XPathContext(node, 1, boundNodes.length)));
  }
  
  vertex.setValue(value);
  
  this.bind.model.doRecalculate = true;
  this.bind.model.doRevalidate  = true;
  this.bind.model.doRefresh     = true;
};

XFormSetValueAction.prototype.toString = function() {
  return xmlSerialize(this.xmlNode);
};

// --- xforms/actions/insert.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new insert action.
//
// Parameters:
//     element:  The element from which this action was created.
//     bind:     The action's bind.
//     at:       An XPath expression evaluated to determine the insert location.
//     position: The position at which to insert the new element, either
//               "before" or "after".
function XFormInsertAction(element, bind, at, position) {
  assert(bind     != null, "insert: bind is null");
  assert(at       != null, "insert: at is null");
  assert(position == "before" || position == "after", "bad position: " + position);
 
  XFormAction.call(this, element);

  this.bind     = bind;
  this.at       = at;
  this.position = position;
};

XFormInsertAction.inherits(XFormAction);


XFormParser.prototype.parseInsertAction = function(element) {
  return new XFormInsertAction(
    element,
    
    this.getBindFor               (element),
    this.parseInsertActionAt      (element),
    this.parseInsertActionPosition(element)
  );
};

XFormParser.prototype.parseInsertActionAt = function(element) {
  return this.xpathValue(element, "at");
};

XFormParser.prototype.parseInsertActionPosition = function(element) {
  return this.stringValue(element, "position");
};


XFormInsertAction.prototype.activate = function() {
  var nodeSet = this.bind.defaultBinding.getBoundNodes();
  
  if (nodeSet.length == 0) {
    return;
  }
  
  var node     = nodeSet[nodeSet.length - 1];
  var clone    = node.cloneNode(true);
  var at       = XPath.ROUND_FUNCTION.evaluate(this.at.evaluate(new XPathContext(nodeSet[0], 1, nodeSet.length)));
  var position = this.position;
  
  if (at < 1)              { at = 1;                                  }
  if (at > nodeSet.length) { at = nodeSet.length;                     }
  if (isNaN(at))           { at = nodeSet.length; position = "after"; }
  
  switch (position) {
    case "before": node.parentNode.insertBefore(clone, nodeSet[at - 1]);             break;
    case "after":  node.parentNode.insertBefore(clone, nodeSet[at - 1].nextSibling); break;
  }
  
  // Dispatch an xforms-insert event.
  var instances = this.bind.model.instances.length;
  for (var i = 0; i < instances; i++) {
    var instance = this.bind.model.instances[i];
    
    if (instance.document == clone.ownerDocument) {
      XmlEvent.dispatch(instance.htmlNode, "xforms-insert");
      break;
    }
  }
  
  this.bind.model.doRebuild     = true;
  this.bind.model.doRecalculate = true;
  this.bind.model.doRevalidate  = true;
  this.bind.model.doRefresh     = true;
};

XFormInsertAction.prototype.toString = function() {
  return "insert";
};

// Define the xforms-insert event.
XmlEvent.define("xforms-insert", "Events", true, false);

// --- xforms/actions/delete.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new delete action.
//
// Parameters:
//     element: The element from which this action was created.
//     bind:    The action's bind.
//     at:      An XPath expression evaluated to determine location of the
//              element to delete.
function XFormDeleteAction(element, bind, at) {
  assert(bind != null, "delete: bind is null");
  assert(at   != null, "delete: at is null");
 
  XFormAction.call(this, element);

  this.bind = bind;
  this.at   = at;
};

XFormDeleteAction.inherits(XFormAction);


XFormParser.prototype.parseDeleteAction = function(element) {
  return new XFormDeleteAction(
    element,
    
    this.getBindFor         (element),
    this.parseDeleteActionAt(element)
  );
};

XFormParser.prototype.parseDeleteActionAt = function(element) {
  return this.xpathValue(element, "at");
};


XFormDeleteAction.prototype.activate = function() {
  var nodeSet = this.bind.defaultBinding.getBoundNodes();;
  
  if (nodeSet.length == 0) {
    return;
  }
  
  var at = XPath.ROUND_FUNCTION.evaluate(this.at.evaluate(new XPathContext(nodeSet[0], 1, nodeSet.length)));
  
  if (at < 1 || at > nodeSet.length || isNaN(at)) {
    return;
  }
  
  nodeSet[0].parentNode.removeChild(nodeSet[at - 1]);
  
  // Dispatch an xforms-delete event.
  var instances = this.bind.model.instances.length;
  for (var i = 0; i < instances; i++) {
    var instance = this.bind.model.instances[i];
    
    if (instance.document == nodeSet[0].ownerDocument) {
      XmlEvent.dispatch(instance.htmlNode, "xforms-delete");
      break;
    }
  }
  
  this.bind.model.doRebuild     = true;
  this.bind.model.doRecalculate = true;
  this.bind.model.doRevalidate  = true;
  this.bind.model.doRefresh     = true;
};

XFormDeleteAction.prototype.toString = function() {
  return "delete";
};

// Define the xforms-deleteevent.
XmlEvent.define("xforms-delete", "Events", true, false);

// --- xforms/actions/toggle.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new toggle action.
//
// Parameters:
//     element:    The element from which this action was created.
//     switchCase: The case to switch to.
function XFormToggleAction(element, switchCase) {
  XFormAction.call(this, element);

  this.switchCase = switchCase;
};

XFormToggleAction.inherits(XFormAction);


XFormParser.prototype.parseToggleAction = function(element) {
  return new XFormToggleAction(
    element,
    this.parseToggleActionCase(element)
  );
};

XFormParser.prototype.parseToggleActionCase = function(element) {
  var switchCase = xform.getObjectById(element.attributes.getNamedItem("case"));
  
  if (!instanceOf(switchCase, XFormCaseControl)) {
    throw new XFormException(element, '"' + element.getAttribute("case") + '" is not the ID of a <case/> element.');
  }
  
  return switchCase;
};


XFormToggleAction.prototype.activate = function() {
  this.switchCase.toggle();
};

XFormToggleAction.prototype.toString = function() {
  return "toggle";
};

// --- xforms/actions/dispatch.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new dispatch action.
//
// Parameters:
//     element:    The element from which this action was created.
//     name:       The name of the event to dispatch.
//     target:     The ID of the event target element.
//     bubbles:    Boolean indicating if this event bubbles. For predefined
//                 events, this value has no effect.
//     cancelable: Boolean indicating if this event is cancelable. For
//                 predefined events, this value has no effect.
function XFormDispatchAction(element, name, target, bubbles, cancelable) {
  XFormAction.call(this, element);

  this.name       = name;
  this.target     = target;
  this.bubbles    = bubbles;
  this.cancelable = cancelable;
};

XFormDispatchAction.inherits(XFormAction);


XFormParser.prototype.parseDispatchAction = function(element) {
  return new XFormDispatchAction(
    element,

    this.parseDispatchActionName      (element),
    this.parseDispatchActionTarget    (element),
    this.parseDispatchActionBubbles   (element),
    this.parseDispatchActionCancelable(element)
  );
};

XFormParser.prototype.parseDispatchActionName = function(element) {
  return this.stringValue(element, "name");
};

XFormParser.prototype.parseDispatchActionTarget = function(element) {
  return this.stringValue(element, "target");
};

XFormParser.prototype.parseDispatchActionBubbles = function(element) {
  return this.booleanValue(element, "bubbles", "true");
};

XFormParser.prototype.parseDispatchActionCancelable = function(element) {
  return this.booleanValue(element, "cancelable", "true");
};


XFormDispatchAction.prototype.activate = function() {
  var target = new XPath("//*[@id = '" + this.target + "']").selectNode(this.xmlElement);
  var target = xform.getHtmlNode(target);
  
  XmlEvent.dispatch(target, this.name, "Events", this.bubbles, this.cancelable);
};

XFormDispatchAction.prototype.toString = function() {
  return "dispatch";
};

// --- xforms/actions/rebuild.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new rebuild action.
//
// Parameters:
//     element: The element from which this action was created.
//     model:   The model to rebuild.
function XFormRebuildAction(element, model) {
  assert(model != null, "model is null");
  
  XFormAction.call(this, element);

  this.model = model;
};

XFormRebuildAction.inherits(XFormAction);


XFormParser.prototype.parseRebuildAction = function(element) {
  return new XFormRebuildAction(
    element,
    
    this.parseRebuildActionModel(element)
  );
};

XFormParser.prototype.parseRebuildActionModel = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("model"));
};


XFormRebuildAction.prototype.activate = function() {
  this.model.rebuild();
  
  this.model.doRebuild     = false;
  this.model.doRecalculate = true;
  this.model.doRevalidate  = true;
  this.model.doRefresh     = true;
  
  xform.rebuilt            = true;
};

XFormRebuildAction.prototype.toString = function() {
  return "rebuild";
};

// --- xforms/actions/recalculate.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new recalculate action.
//
// Parameters:
//     element: The element from which this action was created.
//     model:   The model to recalculate.
function XFormRecalculateAction(element, model) {
  assert(model != null, "model is null");
  
  XFormAction.call(this, element);

  this.model = model;
};

XFormRecalculateAction.inherits(XFormAction);


XFormParser.prototype.parseRecalculateAction = function(element) {
  return new XFormRecalculateAction(
    element,
    
    this.parseRecalculateActionModel(element)
  );
};

XFormParser.prototype.parseRecalculateActionModel = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("model"));
};


XFormRecalculateAction.prototype.activate = function() {
  this.model.recalculate();
  
  this.model.doRecalculate = false;
  this.model.doRevalidate  = true;
  this.model.doRefresh     = true;
};

XFormRecalculateAction.prototype.toString = function() {
  return "recalculate";
};

// --- xforms/actions/revalidate.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new revalidate action.
//
// Parameters:
//     element: The element from which this action was created.
//     model:   The model to revalidate.
function XFormRevalidateAction(element, model) {
  assert(model != null, "model is null");
  
  XFormAction.call(this, element);

  this.model = model;
};

XFormRevalidateAction.inherits(XFormAction);


XFormParser.prototype.parseRevalidateAction = function(element) {
  return new XFormRevalidateAction(
    element,
    
    this.parseRevalidateActionModel(element)
  );
};

XFormParser.prototype.parseRevalidateActionModel = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("model"));
};


XFormRevalidateAction.prototype.activate = function() {
  this.model.revalidate();
  
  this.model.doRevalidate = false;
  this.model.doRefresh    = true;
};

XFormRevalidateAction.prototype.toString = function() {
  return "revalidate";
};

// --- xforms/actions/refresh.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new refresh action.
//
// Parameters:
//     element: The element from which this action was created.
//     model:   The model to refresh.
function XFormRefreshAction(element, model) {
  assert(model != null, "model is null");
  
  XFormAction.call(this, element);

  this.model = model;
};

XFormRefreshAction.inherits(XFormAction);


XFormParser.prototype.parseRefreshAction = function(element) {
  return new XFormRefreshAction(
    element,
    
    this.parseRefreshActionModel(element)
  );
};

XFormParser.prototype.parseRefreshActionModel = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("model"));
};


XFormRefreshAction.prototype.activate = function() {
  this.model.refresh();
  this.model.doRefresh = false;
};

XFormRefreshAction.prototype.toString = function() {
   return "refresh";
};

// --- xforms/actions/send.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.


// Creates a new send action.
//
// Parameters:
//     element:    The element from which this action was created.
//     submission: The submission object to submit.
function XFormSendAction(element, submission) {
  assert(submission != null, "submission is null");
  
  XFormAction.call(this, element);

  this.submission = submission;
};

XFormSendAction.inherits(XFormAction);


XFormParser.prototype.parseSendAction = function(element) {
  return new XFormSendAction(
    element,
    
    this.parseSendActionSubmission(element)
  );
};

XFormParser.prototype.parseSendActionSubmission = function(element) {
  return xform.getObjectById(element.attributes.getNamedItem("submission"), XFormSubmission);
};


XFormSendAction.prototype.activate = function() {
  XmlEvent.dispatch(this.submission.htmlNode, "xforms-submit");
};

XFormSendAction.prototype.toString = function() {
  return "send";
};

// --- xforms/loaded.js ---
// Copyright (c) 2000-2005 Progeny Systems Corporation.
//
// Consult license.html in the documentation directory for licensing
// information.

if (typeof(formfacesLoaded) != "undefined") {
  formfacesLoaded();
}