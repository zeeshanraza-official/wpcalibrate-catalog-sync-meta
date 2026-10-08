/**
 * WPCalibrate Catalog Sync for Meta - Admin JavaScript
 */
( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// Copy-to-clipboard buttons.
		$( '.wpcalibrate-copy-btn' ).on( 'click', function( e ) {
			e.preventDefault();
			var $btn    = $( this );
			var target  = $btn.data( 'target' );
			var $target = $( target );

			if ( ! $target.length ) {
				return;
			}

			var textToCopy = $target.is( 'input, textarea' ) ? $target.val() : $target.text();

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( textToCopy ).then( function() {
					showCopiedFeedback( $btn );
				} ).catch( function() {
					fallbackCopy( $target, $btn );
				} );
			} else {
				fallbackCopy( $target, $btn );
			}
		} );

		function fallbackCopy( $target, $btn ) {
			try {
				if ( $target.is( 'input, textarea' ) ) {
					$target.trigger( 'select' );
					document.execCommand( 'copy' );
					showCopiedFeedback( $btn );
				}
			} catch ( err ) {
				window.alert( ( window.wpcalibrateCsmL10n && window.wpcalibrateCsmL10n.copyFail ) || 'Could not copy to clipboard.' );
			}
		}

		function showCopiedFeedback( $btn ) {
			var originalText = $btn.text();
			var copiedText   = ( window.wpcalibrateCsmL10n && window.wpcalibrateCsmL10n.copied ) || 'Copied!';
			$btn.text( copiedText ).prop( 'disabled', true );

			setTimeout( function() {
				$btn.text( originalText ).prop( 'disabled', false );
			}, 2000 );
		}

		// Confirmation on Full Catalog Reconciliation.
		$( '.wpcalibrate-confirm-reconcile' ).on( 'click', function( e ) {
			var confirmMsg = ( window.wpcalibrateCsmL10n && window.wpcalibrateCsmL10n.confirmReconcile ) || 'Start full catalog reconciliation?';
			if ( ! window.confirm( confirmMsg ) ) {
				e.preventDefault();
			}
		} );

		// Confirmation on Meta Disconnect.
		$( '.wpcalibrate-confirm-disconnect' ).on( 'click', function( e ) {
			var confirmMsg = ( window.wpcalibrateCsmL10n && window.wpcalibrateCsmL10n.confirmDisconnect ) || 'Disconnect Meta connection?';
			if ( ! window.confirm( confirmMsg ) ) {
				e.preventDefault();
			}
		} );
	} );
} )( jQuery );
