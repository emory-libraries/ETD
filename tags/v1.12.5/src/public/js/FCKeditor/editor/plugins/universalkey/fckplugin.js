/*
 * File Name: fckplugin.js
 * Plugin to launch the Unicode Keyboard dialog in FCKeditor
 */

// Register the related command.
FCKCommands.RegisterCommand( 'UniversalKey', new FCKDialogCommand( 'UniversalKey', FCKLang.UniversalKeyboard, FCKPlugins.Items['universalkey'].Path + 'fck_universalkey.html', 415, 300 ) ) ;

// Create the "UniversalKey" toolbar button.
var oUniversalKeyItem = new FCKToolbarButton( 'UniversalKey', FCKLang.UniversalKeyboard, FCKLang.UniversalKeyboard, null, null, false, true) ;
oUniversalKeyItem.IconPath = FCKPlugins.Items['universalkey'].Path + 'universalkey.gif' ;

FCKToolbarItems.RegisterItem( 'UniversalKey', oUniversalKeyItem ) ;

