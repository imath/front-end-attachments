/* globals bp, BP_Uploader, _, Backbone */

window.bp = window.bp || {};

( function( exports, $ ) {

	// Bail if not set
	if ( typeof BP_Uploader === 'undefined' ) {
		return;
	}

	bp.Models      = bp.Models || {};
	bp.Collections = bp.Collections || {};
	bp.Views       = bp.Views || {};

	bp.feAttachments = {
		start: function() {
			// Init some vars
			this.views    = new Backbone.Collection();
			this.warning = null;

			// Set up View
			this.uploaderView();

			// Front End Attachments are uploaded files
			this.feAttachments = bp.Uploader.filesUploaded;
		},

		uploaderView: function() {
			// Listen to the Queued uploads
			bp.Uploader.filesQueue.on( 'add', this.uploadProgress, this );

			// Create the BuddyPress Uploader
			var uploader = new bp.Views.Uploader();

			// Add it to views
			this.views.add( { id: 'upload', view: uploader } );

			// Display it
			uploader.inject( '.fe-attachments-uploader' );
		},

		uploadProgress: function() {
			// Create the Uploader status view
			var fileStatus = new bp.Views.uploadFeattachmentsStatus( { collection: bp.Uploader.filesQueue } );

			if ( ! _.isUndefined( this.views.get( 'status' ) ) ) {
				this.views.set( { id: 'status', view: fileStatus } );
			} else {
				this.views.add( { id: 'status', view: fileStatus } );
			}

			// Display it
	 		fileStatus.inject( '.fe-attachments-uploader-status' );
		},
	}

	// Custom Uploader Files view
	bp.Views.uploadFeattachmentsStatus = bp.Views.uploaderStatus.extend( {
		className: 'files',

		events: {
			'click .fe-attachments-insert' : 'sendtoEditor'
		},

		initialize: function() {
			bp.Views.uploaderStatus.prototype.initialize.apply( this, arguments );

			this.collection.on( 'change:url', this.updateEntry, this );
		},

		updateEntry: function( model ) {
			var insertText = BP_Uploader.strings.fe_attachments.insert;

			if ( ! _.isUndefined( model.get( 'icon' ) ) && ! $( '#' + model.get( 'id' ) + ' .filename img' ).length ) {
				$( '#' + model.get( 'id' ) + ' .filename' ).prepend( '<img src="' + model.get( 'icon' ) + '"> ' );
				$( '#' + model.get( 'id' ) + ' .bp-progress' ).addClass( 'fe-attachments-success' );
				$( '#' + model.get( 'id' ) + ' .bp-progress' ).html( '<a href="#" class="fe-attachments-insert button" data-fileurl="' + model.get( 'url' ) + '">' + insertText + '</a>' );
			}
		},

		sendtoEditor: function( event ) {
			var editor, insert = null,
				hasTinymce = typeof tinymce !== 'undefined',
				hasQuicktags = typeof QTags !== 'undefined';

			event.preventDefault();

			insert = $( event.target ).data( 'fileurl' );

			if ( _.isUndefined( window.wpActiveEditor ) ) {
				if ( hasTinymce && tinymce.activeEditor ) {
					editor = tinymce.activeEditor;
					wpActiveEditor = editor.id;
				} else if ( ! hasQuicktags ) {
					return false;
				}
			} else if ( hasTinymce ) {
				editor = tinymce.get( window.wpActiveEditor );
			}

			if ( editor && ! editor.isHidden() ) {
				editor.execCommand( 'mceInsertContent', false, '<img src="' + insert + '"/>' );
			} else if ( hasQuicktags ) {
				QTags.insertContent( insert );
			} else if ( window.wpActiveEditor ) {
				$( window.wpActiveEditor ).value += insert;
			}

			// If the old thickbox remove function exists, call it
			if ( window.tb_remove ) {
				try { window.tb_remove(); } catch( e ) {}
			}
		}
	} );

	bp.feAttachments.start();

} )( bp, jQuery );
