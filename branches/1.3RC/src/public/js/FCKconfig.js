// NOTE: using etd.css (loaded via view helper so css url can be generated dynamically)
FCKConfig.BodyClass = 'fullrecord' ;
FCKConfig.IndentClasses = [ 'Indent1', 'Indent2', 'Indent3' ] ;
FCKConfig.JustifyClasses = [ 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyFull' ] ;


// load universal key tool for alternate language input
FCKConfig.Plugins.Add('universalkey');
// better universal keyboard with more languages
FCKConfig.Plugins.Add('Jsvk');


FCKConfig.ToolbarSets["etd-title"] = [
      ['Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
       '-', 'SpecialChar', 'UniversalKey', //'Jsvk',
       '-', 'Paste', 'PasteText', 'PasteWord',
       '-', 'SpellCheck', 'About']
];

FCKConfig.ToolbarSets["etd"] = [
	['Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
	 '-', 'SpecialChar', 'UniversalKey', //'Jsvk',
	 '-', 'Paste', 'PasteText', 'PasteWord',
	 '-', 'SpellCheck', 'About'],
	  '/',
	  ['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote']
  ];
/* same as etd + view source */
FCKConfig.ToolbarSets["etd-admin"] = [
	['Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
	 '-', 'SpecialChar', 'UniversalKey','Jsvk',
	 '-', 'Paste', 'PasteText', 'PasteWord',
	 '-', 'SpellCheck', 'About', '-', 'Source'],
	  '/',
	  ['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote']
  ];
