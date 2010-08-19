/*
 * File Name: dialogue.js
 * 	Scripts for the fck_universalkey.html page.
 * 
 * File Authors:
 * 		Michel Staelens (michel.staelens@wanadoo.fr)
 * 		Bernadette Cierzniak
 * 		Abdul-Aziz Al-Oraij (top7up@hotmail.com)
 * 		Frederico Caldeira Knabben (fredck@fckeditor.net)
 */

function afficher(txt)
{
	document.getElementById( 'uni_area' ).value = txt ;
}

function rechercher()
{
	return document.getElementById( 'uni_area' ).value ;
}