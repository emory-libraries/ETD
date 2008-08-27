// FIXME: universal key doesn't exist anymore?

FCKConfig.Plugins.Add('universalkey');

FCKConfig.ToolbarSets["etd-title"] = [
      ['Preview','-', 'Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
       '-', 'SpecialChar', 'UniversalKey', '-', 'SpellCheck', 'About']
];

FCKConfig.ToolbarSets["etd"] = [
	['Preview','-', 'Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
	   '-', 'SpecialChar', 'UniversalKey', '-', 'SpellCheck', 'About'],
	  '/',
	  ['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote']
  ];
/* same as etd + view source */
FCKConfig.ToolbarSets["etd-admin"] = [
	['Preview','-', 'Bold','Italic','Underline','-','Subscript','Superscript','-','Link','Unlink',
	   '-', 'SpecialChar', 'UniversalKey', '-', 'SpellCheck', 'About', '-', 'Source'],
	  '/',
	  ['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote']
  ];
