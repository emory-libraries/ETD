function XFormAction(element) {
  if (arguments.length == 0) {
    return;
  }
  assert(element != null, "element is null");
  var parser = new XFormParser();
  this.doif = parser.xpathValue(element,'if',null);
  this.dowhile = parser.xpathValue(element,'while',null);
  this.element = element;
  parser = null; // garbage it!

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
        case "dispatchif":  actions.push(self.parseDispatchAction   (child)); break;        
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

XFormAction.prototype.validate = function() {
    
//debugger
    if(this.doif && this.dowhile)
    {
        
        var doif = XPath.BOOLEAN_FUNCTION.evaluate(this.doif.evaluate(xform.xmlDocument));
        var dowhile = XPath.BOOLEAN_FUNCTION.evaluate(this.dowhile.evaluate(xform.xmlDocument));
        if(doif)
        {
            
            while(dowhile)
            {
                
                
                    this.activate();
                    
                
                dowhile = XPath.BOOLEAN_FUNCTION.evaluate(this.dowhile.evaluate(xform.xmlDocument));
                
            }
            
            
            
        }
        
        
    }
    
    else if(this.doif)
    {
        
        var doif = XPath.BOOLEAN_FUNCTION.evaluate(this.doif.evaluate(xform.xmlDocument));
        if(doif)
        {
            
                
                this.activate();
                
            
            
        }
        
        
    }
    
    else if(this.dowhile)
    {
        
        var dowhile = XPath.BOOLEAN_FUNCTION.evaluate(this.dowhile.evaluate(xform.xmlDocument));
        while(dowhile)
        {
            
                
                this.activate();
                
            
            dowhile = XPath.BOOLEAN_FUNCTION.evaluate(this.dowhile.evaluate(xform.xmlDocument));
            
        }
        
        
        
    }
    
    
    else
    {
        
            
            this.activate();
            
               
        
    }
        
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
//debugger    
    if(instanceOf(this,XFormActionAction))
    	self.activate();
    else
    	self.validate();
    
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

