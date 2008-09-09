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