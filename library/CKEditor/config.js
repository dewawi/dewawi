/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	config.toolbar = [
		<!--['Source'],-->
		['Undo','Redo'],
		['Cut','Copy','Paste','PasteText'],
		['Find','Replace','-','Scayt'],
		['Image','Table',/*'SpecialChar',*/ '-','Link','Unlink'],
		['Bold','Italic','Underline','Strike','Subscript','Superscript'],
		['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
		['NumberedList','BulletedList','-','RemoveFormat'],
		['Format','FontSize'],
		['TextColor','BGColor'],
		['Source', '-','Maximize', '-','About' ]
	];
};
