// Copyright (c) 2005 Facile Technology Ltd..
//
//
function XFormDispatchifAction(element, condition, bubbles, cancelable,action,target) {
  XFormAction.call(this, element);
  this.element    = element;
  this.condition  = condition;
  this.bubbles    = bubbles;
  this.cancelable = cancelable;
  this.action     = action;
  this.target     = target;
  switch(action){
  case 'dispatch':
  assert(target != null);
  break;
  case 'toggle':
  break;
  case 'submission':
  break;
  case 'script':
  break;
  default:
  alert('invalid action for dispatchif action='+action);
  assert(false);
  break;
  }
};

XFormDispatchifAction.inherits(XFormAction);


XFormParser.prototype.parseDispatchifAction = function(element) {
  return new XFormDispatchifAction(
    element,

    this.parseDispatchifActionCondition      (element),
    this.parseDispatchifActionBubbles   (element),
    this.parseDispatchifActionCancelable(element),
    this.parseDispatchifActionAction(element),
    this.parseDispatchifActionTarget    (element)

  );
};

XFormParser.prototype.parseDispatchifActionName = function(element) {
  return this.stringValue(element, "name");
};

XFormParser.prototype.parseDispatchifActionTarget = function(element) {
  return this.stringValue(element, "target",null);
};
XFormParser.prototype.parseDispatchifActionCondition = function(element) {
  return this.xpathValue(element, "condition",null);
};

XFormParser.prototype.parseDispatchifActionAction = function(element) {
  return this.stringValue(element, "action",null);
};

XFormParser.prototype.parseDispatchifActionBubbles = function(element) {
  return this.booleanValue(element, "bubbles", "true");
};

XFormParser.prototype.parseDispatchifActionCancelable = function(element) {
  return this.booleanValue(element, "cancelable", "true");
};


XFormDispatchifAction.prototype.activate = function() {
//  var target = xform.getHtmlNode(target);

  
//  XmlEvent.dispatch(target, this.name, "Events", this.bubbles, this.cancelable);
var condition = this.condition.evaluate(xform.xmlDocument);

if(condition == 'none' || condition == 'null') return;
switch(this.action)
{
	case 'toggle':
	var switchCase = xform.objectsById[condition];
	if (!instanceOf(switchCase, XFormCaseControl)) {
    throw new XFormException(this.element, '"' + condition + '" is not the ID of a <case/> element.');
  }
    switchCase.toggle();
	break;
	case 'script':
	try
	{
		eval(condition+'()');
	}
	catch(e){alert('no function '+condition+ ' in scope ');}
	break;
	case 'dispatch':
	try
	{
	var target = new XPath("//*[@id = '" + this.target + "']").selectNode(this.element);
	target = xform.getHtmlNode(target);
	XmlEvent.dispatch(target, condition, "Events", this.bubbles, this.cancelable);
	}
	catch(e){alert('error during dispatchif condition='+condition);}
	break;
	case 'submission':
    var submission = xform.objectsById[condition];
try
{
	XmlEvent.dispatch(submission.htmlNode, "xforms-submit");
	xform.rebuilt = false;
	submission.complete = false;
	submission.submit();
	var model = submission.model;
	if(xform.rebuilt)
	{
			XmlEvent.dispatch(model.htmlNode, "xforms-rebuild");
            XmlEvent.dispatch(model.htmlNode, "xforms-recalculate");
            XmlEvent.dispatch(model.htmlNode, "xforms-revalidate");
            XmlEvent.dispatch(model.htmlNode, "xforms-refresh");
			XmlEvent.dispatch(model.htmlNode, "xforms-model-construct-done");
            XmlEvent.dispatch(submission.htmlNode, 'xforms-submit-done');
	}
}
catch(e){}
break;
case 'script':
//var script = new XPath('*').evaluate(condition);
//alert(XPathFunction.stringValueOf(condition));
try
{
	eval(XPathFunction.stringValueOf(condition[0]));
}
catch(e){}
break;
	default:
	break;
}
};

XFormDispatchifAction.prototype.toString = function() {
  return "dispatchif";
};
