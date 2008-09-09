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
           	XForms.THIRD_PARTY_FUNCTIONS.lookupFunction(qqn)(this, child);
		      
				}  
	}
		
};


  
XFormExtensionControl        .prototype.toString =
XFormExtensionControlInstance.prototype.toString = function() {
  return "extension";
};