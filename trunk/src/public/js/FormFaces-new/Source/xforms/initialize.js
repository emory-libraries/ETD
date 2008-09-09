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