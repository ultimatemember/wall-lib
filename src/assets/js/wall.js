// TODO Make structure like `UM.common.{method/param}`. Probably `UM.wall.{method/param}` will be enough.
/**
 * if ( typeof ( window.UM ) !== 'object' ) {
 * 		window.UM = {};
 * }
 *
 * if ( typeof ( UM.wall ) !== 'object' ) {
 * 		UM.wall = {};
 * }
 *
 * UM.wall = {
 * 		prop1: val1,
 * 		form: {
 * 			checkTextareaLength: function() {},
 * 			enableSubmit: function() {},
 * 			disableSubmit: function() {},
 * 			...,
 * 			etc.,
 * 		}
 * }
 */

/**
 * Check the length of the text in a textarea and enable or disable the form submit button accordingly.
 *
 * @param {jQuery} editor - The textarea editor element to check the text length for.
 * @return {void}
 */
function um_check_textarea_length( editor ) {
	let form = editor.parents('form');
	let text;
	if ( editor.is('textarea' ) ) {
		text = editor.val().trim();
	} else {
		text = editor.text().trim();
	}

	if ( text.length > 0 ) {
		um_enable_post_submit(form);
	} else {
		if (form.find('.um-uploader-file.um-upload-completed').length === 0) {
			um_disable_post_submit(form);
		}
	}
}

function um_enable_post_submit( form ) {
	form.find( '.um-wall-post' ).prop( 'disabled', false );
}

function um_disable_post_submit( form ) {
	form.find( '.um-wall-post' ).prop( 'disabled', true );
}

function um_enable_comment_submit( form ) {
	form.find( '.um-wall-comment-post' ).prop( 'disabled', false );
}

function um_disable_comment_submit( form ) {
	form.find( '.um-wall-comment-post' ).prop( 'disabled', true );
}

function um_check_comment_length( textarea ) {
	let form = textarea.parents( '.um-wall-comment-form' );
	if ( textarea.val().trim().length > 0 ) {
		um_enable_comment_submit( form );
	} else {
		um_disable_comment_submit( form );
	}
}

let Loadwall_ajax = false;

jQuery( document ).ready(function () {
	/* Textarea autosize */
	UM.frontend.autosize.init( '.um-wall-textarea-elem', 2 );
	UM.frontend.autosize.init( 'textarea.um-wall-comment-textarea', 2 );
	// AJAX wall request
	if ( jQuery('.um-wall:not([data-single_post="1"])').not('[data-loading="sync"]').length ) {
		um_wall_ajax_request();
	}

	/* Detect change in editor content */
	jQuery( document.body ).on( 'input keyup paste', '.um-wall-textarea-elem', function() {
		um_check_textarea_length( jQuery( this ) );
	});

	/* Detect change in textarea content */
	jQuery( document.body ).on( 'input properychange', '.um-wall-comment-textarea', function() {
		um_check_comment_length( jQuery( this ) );
	});

	/* Post a status */
	jQuery( document.body ).on( 'click', '.um-wall-post', function() {
		jQuery(this).parents( '.um-wall-publish' ).trigger('submit');
	});

	/* Post publish */
	jQuery( document.body ).on( 'submit', '.um-wall-publish', function(e) {
		e.preventDefault();
		let form = jQuery(this);
		let post_content = form.find('.um-wall-textarea-elem').html();
		form.find('.um-wall-textarea').val(post_content);

		let formdata = UM.common.form.vanillaSerialize( form );
		if ( 'undefined' === typeof( formdata ) ) {
			form.find('textarea').trigger('focus');
			return;
		}

		if ( ! formdata.hasOwnProperty('_post_content') ) {
			form.find('textarea').trigger('focus');
			return;
		}

		let photoField = wp.hooks.applyFilters(
			'um.wall.postPhotoField',
			'',
			{ form: form, formdata: formdata }
		);
		let hasPhotos = 1 === parseInt( form.find('#_post_imgs').val() );

		if ( '' === formdata['_post_content'] && !hasPhotos ) {
			form.find('textarea').trigger('focus');
			return;
		}

		formdata = wp.hooks.applyFilters(
			'um_wall_publish_formdata',
			formdata,
			{
				form: form,
				action: formdata.action
			}
		);

		let $loader = form.find('.um-wall-right .um-ajax-spinner-svg');
		let $uploadToggle = form.find('.um-wall-toggle-uploader');

		um_disable_post_submit( form );
		$uploadToggle.prop('disabled',true);
		$loader.umShow();

		let $uploader = form.find('.um-uploader');
		let uploaderObj = UM.frontend.uploaders[ $uploader.data('plupload') ];

		wp.ajax.send({
			data: formdata,
			success: function (data)  {
				let handled = wp.hooks.applyFilters(
					'um_wall_publish_success_handled',
					false,
					{
						data: data,
						formdata: formdata,
						form: form,
						loader: $loader,
						uploadToggle: $uploadToggle,
						uploaderObj: uploaderObj
					}
				);

				if ( handled ) {
					return;
				}

				if ( form.find('input[name="_post_id"]').val() === '0' ) {
					let wall = form.parents('.um').find('.um-wall');

					/* for shortcode [ultimatemember_activity_form] */
					if ( wall.length < 1 ) {
						wall = jQuery( document.body ).find('.um-wall');
					}

					wall.prepend(data);

					form.find('textarea').val('').height('auto');
					um_clean_photo_fields( form );
					um_post_placeholder( form.find( 'textarea' ) );
				} else {
					let post_id = form.find('input[name="_post_id"]').val()
					jQuery('#postid-' + post_id ).replaceWith(data);
				}


				form.find('.um-uploader-filelist').html('').umHide();
				form.find('.um-wall-insert .um-badge').remove();
				jQuery('#_post_imgs').val('')

				UM.frontend.autosize.init( 'textarea.um-wall-comment-textarea', 2 );

				////// NEW
				form.find('.um-wall-right .um-ajax-spinner-svg').hide();
				form.find('.um-wall-toggle-uploader').prop('disabled',false);

				form.find('.um-wall-textarea-elem').html('');
				jQuery('.um-wall-empty').remove();
				let posts_count = jQuery('.count-posts span').html();
				jQuery('.count-posts span').html( parseInt(posts_count) + 1 )

				let params = new URLSearchParams( window.location.search );
				if ( params.has( 'action' ) ) {
					UM.frontend.url.deleteURLSearchParam('action');
				}
				if ( params.has( '_wpnonce' ) ) {
					UM.frontend.url.deleteURLSearchParam('_wpnonce');
				}

				UM.frontend.dropdown.init();
				UM.frontend.image.lazyload.init();
			},
			error: function(data) {
				form.find('.um-wall-right .um-ajax-spinner-svg').hide();
				form.find('.um-wall-toggle-uploader').prop('disabled',false);
				let error;
				if ( data.message ) {
					error = data.message;
				} else {
					error = form.attr('data-error');
				}
				jQuery(this).um_notice({
					message: error,
					type: 'error'
				});
			}
		});
	});

	/* Trash post */
	jQuery( document.body ).on('click', '.um-wall-trash', function(e) {
		let btn = jQuery(this);
		let post_id = btn.attr('data-post_id');
		let nonce = btn.attr('data-nonce');
		let msg = btn.attr('data-msg');
		let title = btn.attr('data-title');
		let post = jQuery('#postid-' + post_id);
		post.css('opacity', '0.5');

		let action = wp.hooks.applyFilters(
			'um_wall_remove_action',
			'um_wall_remove_post'
		);

		jQuery.um_confirm(
			{
				title   : title,
				message : msg,
				onYes: function() {
					wp.ajax.send( action, {
						data: {
							post_id: post_id,
							nonce: nonce
						},
						success: function( response ) {
							post.remove();
						},
						error: function( data ) {
							post.css('opacity', '1');
							let error = jQuery(this).attr('data-error');
							if ( data.message ) {
								error = data.message;
							}
							jQuery(this).um_notice({
								message: error,
								type: 'error'
							});
							console.log( data );
						}
					});
				},
				onNo: function() {
					post.css('opacity', '1');
				},
				object: this
			}
		);
	});

	/* Cancel Edit Post */
	jQuery( document.body ).on( 'click', '.um-wall-cancel-edit', function() {
		let post_id = jQuery(this).attr('data-post_id');
		let widget = jQuery('#postid-' + post_id )
		widget.find('.um-wall-post-form-wrapper').remove();
		widget.find('.um-wall-body').umShow();
		widget.find('.um-wall-comments').umShow();
		widget.find('.um-wall-comment-form').umShow();
		widget.find('.um-post-actions-toggle').umShow();
	});

	/* Edit Post */
	jQuery( document.body ).on( 'click', '.um-wall-manage', function() {
		let widget = jQuery(this).parents('.um-wall-widget');
		let post_id = jQuery(this).attr('data-post_id');
		let nonce = jQuery(this).attr('data-nonce');
		let loader = widget.find('.um-wall-post-loader');

		if ( jQuery(this).parents('.um-wall-dialog').length ) {
			jQuery(this).parents('.um-wall-dialog').hide();
		}

		if ( widget.hasClass( 'editing' ) ) {
			return;
		}

		widget.find('.um-post-actions-toggle').umHide();
		loader.umShow();

		let action = wp.hooks.applyFilters(
			'um_get_wall_action',
			'um_get_wall_post'
		);

		let data = { post_id: post_id, nonce: nonce };
		data = wp.hooks.applyFilters(
			'um_wall_edit_post_data',
			data,
			{
				action: action,
				post_id: post_id,
				nonce: nonce,
				widget: widget
			}
		);

		wp.ajax.send(
			action,
			{
				data: data,
				success: function( data ) {
					loader.umHide();
					widget.find('.um-wall-body').umHide();
					widget.find('.um-wall-comments').umHide();
					widget.find('.um-wall-comment-form').umHide();
					widget.find('.um-wall-body').before(data);
					UM.frontend.uploader.init();
					UM.frontend.autosize.init( '.um-wall-textarea-elem', 2 );
				},
				error: function(data) {
					console.log(data);
					loader.umHide();
					let error = jQuery(this).attr('data-error');
					if ( data.message ) {
						error = data.message;
					}
					widget.find('.um-post-actions-toggle').umShow();
					jQuery(this).um_notice({
						message: error,
						type: 'error'
					});
				}
			}
		);
	});

	/* Delete photo from post */
	jQuery( document ).on( 'click', '.um-wall-delete-photo', function ( e ) {
		e.preventDefault();

		let confirm_text = jQuery(this).data( 'confirmation' );

		if ( ! confirm( confirm_text ) ) {
			return false;
		}

		let count = jQuery(this).parents('.um-uploader-filelist').find('.um-uploader-file').length;
		jQuery(this).parents('.um-wall-post-form-wrapper').find('.um-wall-uploaded-count').text( parseInt( count ) - 1);

		let id = jQuery(this).data( 'id' );
		if ( id ) {
			let $input = jQuery('#um_wall_deleted_attachments');
			let currentVal = $input.val();
			let ids = currentVal ? currentVal.split(',') : [];
			if ( !ids.includes(String(id)) ) {
				ids.push(id);
				$input.val(ids.join(','));
			}
		}

		let $uploader = jQuery(this).parents( '.um-uploader' );

		let uploaderObj = UM.frontend.uploaders[ $uploader.data('plupload') ];

		let button = uploaderObj.getOption( 'browse_button' )[0];

		button.removeAttribute('disabled');
		let dropZone = uploaderObj.getOption( 'drop_element' )[0];
		if ( dropZone ) {
			dropZone.classList.remove('um-dropzone-disabled');
			let uploadLink = dropZone.querySelector( '.um-upload-link' );
			if ( uploadLink ) {
				uploadLink.classList.remove('um-link-disabled');
			}
		}

		$uploader.find('.um-wall-photos-error').umHide();
		jQuery(this).parents('.um-wall-widget').find('.um-wall-post').prop( 'disabled', false );
		let $fileRow = jQuery( '#wall-photo-' + jQuery(this).data( 'id' ) );
		$fileRow.remove();
	});

	/* Report post */
	jQuery( document.body ).on('click', '.um-wall-report:not(.flagged)', function() {
		let el = jQuery(this);
		let post_id = el.attr('data-post_id');
		let nonce = el.attr('data-report_nonce');
		let widget = jQuery(this).parents('.um-wall-widget');
		let loader = jQuery('#postid-' + post_id).find('.um-wall-post-loader');

		widget.find('.um-post-actions-toggle').umHide();
		loader.umShow();

		let action = wp.hooks.applyFilters(
			'um_wall_report_post_action',
			'um_wall_report_post'
		);

		let data = {
			post_id: post_id,
			nonce: nonce
		};
		data = wp.hooks.applyFilters(
			'um_wall_report_data',
			data,
			{
				post_id: post_id,
				nonce: nonce,
				el: el
			}
		);

		wp.ajax.send(
			action,
			{
				data: data,
				success: function( data ) {
					widget.find('.um-post-actions-toggle').umShow();
					loader.umHide();
					el.addClass('flagged').html(el.attr('data-cancel_report'));
				},
				error: function(data) {
					widget.find('.um-post-actions-toggle').umShow();
					loader.umHide();
					if ( data.message ) {
						let error = data.message;
						jQuery(this).um_notice({
							message: error,
							type: 'error'
						});
					}
				}
			}
		);
	});

	/* Cancel report post */
	jQuery( document.body ).on('click', '.um-wall-report.flagged', function() {
		let el = jQuery(this);
		let post_id = el.attr('data-post_id');
		let nonce = el.attr('data-unrepost_nonce');
		let widget = jQuery(this).parents('.um-wall-widget');
		let loader = jQuery('#postid-' + post_id).find('.um-wall-post-loader');

		widget.find('.um-post-actions-toggle').umHide();
		loader.umShow();

		let action = wp.hooks.applyFilters(
			'um_wall_unreport_post_action',
			'um_wall_unreport_post'
		);

		let data = {
			post_id: post_id,
			nonce: nonce
		};
		data = wp.hooks.applyFilters(
			'um_wall_report_data',
			data,
			{
				post_id: post_id,
				nonce: nonce,
				el: el
			}
		);

		wp.ajax.send(
			action,
			{
				data: data,
				success: function( data ) {
					widget.find('.um-post-actions-toggle').umShow();
					loader.umHide();
					el.removeClass('flagged').html(el.attr('data-report'));
				},
				error: function(data) {
					console.log(data);
					widget.find('.um-post-actions-toggle').umShow();
					loader.umHide();
					if ( data.message ) {
						let error = data.message;
						jQuery(this).um_notice({
							message: error,
							type: 'error'
						});
					}
				}
			}
		);
	});

	/* Like a post */
	jQuery( document.body ).on('click', '.um-wall-post-like:not(.active)', function(e) {
		let btn = jQuery(this);
		let wrap = jQuery(this).parents('.um-wall-like-wrap');
		let postid = jQuery(this).attr('data-id');
		let nonce = jQuery(this).attr('data-likenonce');

		if ( ! jQuery(this).parents('.um-wall-widget').hasClass('unready') ) {
			let action = wp.hooks.applyFilters(
				'um_wall_like',
				'um_wall_like_post'
			);
			wp.ajax.send( action, {
				data: {
					post_id: postid,
					nonce: nonce
				},
				success: function (answer) {
					btn.addClass('active');
					wrap.find('.um-badge').html(answer.likes);
					wrap.find('.um-wall-post-likes-avatars').html(answer.content).umShow();
					wrap.find('.um-wall-show-likes').attr('disabled', false);
				},
				error: function (data) {
					let error;
					if ( data.message ) {
						error = data.message;
					}
					jQuery(this).um_notice({
						message: error,
						type: 'error'
					});
					console.log(data);
				}
			});
		}
	});

	/* Unlike a post */
	jQuery( document.body ).on('click', '.um-wall-post-like.active', function(e) {
		let btn = jQuery(this);
		let wrap = jQuery(this).parents('.um-wall-like-wrap');
		let postid = jQuery(this).attr('data-id');
		let nonce = jQuery(this).attr('data-unlikenonce');

		let action = wp.hooks.applyFilters(
			'um_wall_unlike',
			'um_wall_unlike_post'
		);

		wp.ajax.send( action, {
			data: {
				post_id: postid,
				nonce: nonce
			},
			success: function( answer ) {
				btn.removeClass('active');
				wrap.find('.um-badge').html(answer.likes);
				wrap.find('.um-wall-post-likes-avatars').html(answer.content);
				let count = wrap.find('.um-wall-show-likes .um-badge').text();
				if ( parseInt( count ) === 0 ) {
					wrap.find('.um-wall-post-likes-avatars').umHide();
					wrap.find('.um-wall-show-likes').attr('disabled', true);
				}
			},
			error: function( data ) {
				console.log( data );
				let error;
				if ( data.message ) {
					error = data.message;
				}
				jQuery(this).um_notice({
					message: error,
					type: 'error'
				});
			}
		});
	});

	/* Show post likes in modal */
	jQuery( document.body ).on('click', '.um-wall-show-likes', function(e) {
		let btn = jQuery(this);
		let post_id = btn.attr('data-id');
		let nonce = btn.attr('data-wpnonce');
		let header = btn.attr('data-header');

		let action = wp.hooks.applyFilters(
			'um_wall_get_post_likes_action',
			'um_wall_get_post_likes'
		);

		if ( parseInt( btn.find('.um-wall-post-likes-count .um-badge').html() ) > 0 ) {
			wp.ajax.send( action, {
				data: {
					post_id: post_id,
					nonce: nonce
				},
				success: function( response ) {
					let settings = {
						classes:  'um-wall-likes-modal',
						duration: 400,
						footer:   '',
						header:   header,
						size:     'normal',
						content:  response.content
					};

					UM.modal.addModal( settings, null );
				},
				error: function( data ) {
					console.log( data );
					let error;
					if ( data.message ) {
						error = data.message;
					}
					jQuery(this).um_notice({
						message: error,
						type: 'error'
					});
				}
			});
		}
	});

	/* Reply to comment */
	jQuery( document ).on( 'click', '.um-wall-edit-comment, .um-wall-edit-comment-cancel', function(e) {
		let wrap = jQuery(this).parents('.um-wall-commentl');
		wrap.find('.um-wall-comment-text').umToggle();
		wrap.find('.um-wall-comment-edit').umToggle();
		wrap.find('.um-wall-editc .um-wall-edit-comment').umToggle();
		wrap.find('.um-wall-editc .um-wall-edit-comment-cancel').umToggle();
	});

	/* Show comment likes in modal */
	jQuery( document.body ).on('click', '.um-wall-comment-likes', function(e) {
		let btn = jQuery(this);
		let comment_id = btn.attr('data-commentid');
		let nonce = btn.attr('data-wpnonce');
		let header = btn.attr('data-header');

		if ( parseInt( btn.find('.um-wall-comment-likes-count .um-badge').html() ) > 0 ) {
			wp.ajax.send('um_wall_get_comment_likes', {
				data: {
					comment_id: comment_id,
					nonce: nonce
				},
				success: function (response) {
					let settings = {
						classes: 'um-wall-likes-modal',
						duration: 400,
						footer: '',
						header: header,
						size: 'normal',
						content: response.content
					};

					UM.modal.addModal(settings, null);
				},
				error: function (data) {
					console.log(data);
					jQuery(this).um_notice({
						message: data,
						type: 'error'
					});
				}
			});
		}
	});

	/* Like of a comment */
	jQuery( document.body ).on('click', '.um-wall-comment-like:not(.active)', function(e) {
		if (!jQuery(this).parents('.um-wall-commentl').hasClass('unready')) {
			let btn = jQuery(this);
			let commentid = btn.attr('data-commentid');
			let nonce = btn.attr('data-likenonce');
			let wrap = jQuery(this).parents('.um-wall-comment-meta');

			let action = wp.hooks.applyFilters(
				'um_wall_like_comment_action',
				'um_wall_like_comment'
			);

			wp.ajax.send( action, {
				data: {
					comment_id: commentid,
					nonce: nonce
				},
				success: function( response ) {
					btn.addClass('active');
					btn.attr('title', btn.attr('data-unlike_text'));
					wrap.find('.um-badge').text(response.likes);
					wrap.find('.um-wall-comment-likes-avatars').html(response.content);
					wrap.find('.um-wall-comment-likes').attr('disabled', false);
					if ( parseInt( response.likes) >0 ) {
						wrap.find('.um-wall-comment-likes-avatars').umShow();
					}
				},
				error: function( data ) {
					console.log( data );
				}
			});
		}
	});

	/* Unlike of a comment */
	jQuery( document.body ).on('click', '.um-wall-comment-like.active', function(e) {
		let btn = jQuery(this);
		let commentid = btn.attr('data-commentid');
		let nonce = btn.attr('data-unlikenonce');
		let wrap = jQuery(this).parents('.um-wall-comment-meta');

		let action = wp.hooks.applyFilters(
			'um_wall_unlike_comment_action',
			'um_wall_unlike_comment'
		);

		wp.ajax.send( action, {
			data: {
				comment_id: commentid,
				nonce: nonce
			},
			success: function( response ) {
				btn.removeClass('active');
				btn.attr('title', btn.attr('data-like_text'));
				wrap.find('.um-badge').text(response.likes);
				wrap.find('.um-wall-comment-likes-avatars').html(response.content);
				if ( parseInt( response.likes) >0 ) {
					wrap.find('.um-wall-comment-likes-avatars').umShow();
				}
			},
			error: function( data ) {
				console.log( data );
			}
		});
	});

	/* posting a comment */
	jQuery( document.body ).on( 'click', '.um-wall-comment-post', function(e) {
		let btn = jQuery(this);
		let btm_wrap = btn.parents('.um-wall-comment-form')
		let wrap;
		let nonce = btn.attr('data-wpnonce');
		let postid = btn.attr('data-post_id');
		let reply_to = btn.attr('data-reply_to');
		let comment_id = btn.attr('data-comment_id');

		let textarea = btm_wrap.find('.um-wall-comment-textarea');
		let comment = textarea.val();

		if ( 'undefined' === typeof( reply_to ) ) {
			wrap = btm_wrap.parent().find('.um-wall-comments');
		} else {
			wrap = btm_wrap.parents('.um-wall-commentwrap').find('.um-wall-comment-child-loop');
		}

		let loader = btm_wrap.find('.um-wall-loader');
		loader.umShow();
		btm_wrap.find('.um-alert').remove();

		let action = wp.hooks.applyFilters(
			'um_wall_post_comment_action',
			'um_wall_post_comment'
		);

		wp.ajax.send( action, {
			data: {
				post_id: postid,
				comment: comment,
				reply_to: reply_to,
				commentid: comment_id,
				nonce: nonce
			},
			success: function( response ) {
				loader.umHide();
				textarea.val('');
				btn.attr('disabled', true);

				if ( 'undefined' === typeof( reply_to ) ) {
					if ( wrap.find('.um-wall-comments-loop > .um-wall-commentload').length ) {
						more_count = parseInt( wrap.find('.um-wall-comments-loop > .um-wall-commentload .um-wall-more-count').html() );
						wrap.find('.um-wall-comments-loop > .um-wall-commentload .um-wall-more-count').html(more_count + 1);
					} else {
						wrap.find('.um-wall-comments-loop').append(response.content);
					}
				} else {
					if ( wrap.next().find('button').length ) {
						more_count = parseInt( wrap.next().find('.um-wall-more-count').html() );
						wrap.next().find('.um-wall-more-count').html(more_count + 1);
					} else {
						wrap.append(response.content);
					}
					btn.parents('.um-wall-commentwrap').find('.um-wall-comment-reply').umShow();
				}

				let count = wrap.parent().find('.um-wall-comments-toggle .um-badge');
				count.html( parseInt( count.html() ) + 1 );
				wrap.parent().find('.um-wall-comments-toggle').umShow().attr('disabled', false);

				if ( 'undefined' === typeof( reply_to ) ) {
					setTimeout( function() {
						btm_wrap.find('.um-alert').remove();
					}, 2000 );
				} else {
					btn.parents('.um-wall-reply-form').remove();
				}
			},
			error: function( data ) {
				console.log( data );
				loader.umHide();
				jQuery(this).um_notice({
					message: data,
					type: 'error'
				});
			}
		});
	});

	/* editing a comment */
	jQuery( document.body ).on( 'click', '.um-wall-edit-comment-save', function(e) {
		let btn = jQuery(this);
		let btm_wrap = btn.parents('.um-wall-comment-edit')
		let nonce = btn.attr('data-wpnonce');
		let commentid = btn.attr('data-commentid');

		let textarea = btm_wrap.find('textarea');
		let comment = textarea.val();

		let loader = btm_wrap.find('.um-wall-loader');
		loader.umShow();
		btm_wrap.find('.um-alert').remove();

		let action = wp.hooks.applyFilters(
			'um_wall_edit_comment_action',
			'um_wall_edit_comment'
		);

		wp.ajax.send( action, {
			data: {
				comment_id: commentid,
				comment: comment,
				nonce: nonce
			},
			success: function( response ) {
				loader.umHide();
				btn.parents('.um-wall-comment-edit').umToggle();

				btn.parents('.um-wall-comment-data').find('.um-wall-comment-text').html(response.content).umToggle();

				btn.parents('.um-wall-commentl').find('.um-wall-editc .um-wall-edit-comment-cancel').umToggle();
				btn.parents('.um-wall-commentl').find('.um-wall-editc .um-wall-edit-comment').umToggle();
			},
			error: function( data ) {
				console.log( data );
				loader.umHide();
				jQuery(this).um_notice({
					message: data,
					type: 'error'
				});
			}
		});
	});

	/* add reply form */
	jQuery( document.body ).on( 'click', '.um-wall-comment-reply', function(e) {
		let btn = jQuery(this);
		let wrap = btn.parents('.um-wall-commentwrap');
		if ( wrap.find('.um-wall-original-comment-info .um-wall-reply-form').length === 0 ) {
			let comment_id = wrap.attr('data-comment_id');
			let reply_form = btn.parents('.um-wall-comments').find('.um-wall-reply-form-wrap .um-wall-reply-form').clone();
			// let reply_to = wrap.find('.um-avatar').attr('data-user_id');
			let reply_to = wrap.attr('data-comment_id');

			reply_form.find('.um-wall-comment-post').attr('data-comment_id', comment_id).attr('data-reply_to', reply_to);
			wrap.find('.um-wall-comment-child-loop').append(reply_form);
		}
		btn.umHide();
	});

	/* remove reply form */
	jQuery( document.body ).on( 'click', '.um-wall-reply-cancel', function(e) {
		let btn = jQuery(this);
		btn.parents('.um-wall-comment-info').find('.um-wall-comment-reply').umShow();
		btn.parents('.um-wall-reply-form').remove();
	});

	/* load more comments */
	jQuery( document.body ).on('click', '.um-wall-commentload', function(e) {
		let btn = jQuery(this);
		let wrap = btn.parents('.um-wall-comments-loop');

		btn.umHide();

		let offset = btn.attr('data-loaded');
		let post_id = btn.attr('data-post_id');
		let nonce = btn.attr('data-wpnonce');
		let loader = wrap.find('>.um-wall-loader');
		loader.umShow();

		let action = wp.hooks.applyFilters(
			'um_wall_load_more_comments_action',
			'um_wall_load_more_comments'
		);

		wp.ajax.send( 'um_wall_load_more_comments', {
			data: {
				post_id: post_id,
				offset: offset,
				nonce: nonce
			},
			success: function( response ) {
				loader.umHide();
				wrap.find('.um-wall-commentwrap:last').after(response.content);
				if ( response.loadmore ) {
					btn.umShow()
					btn.attr('data-loaded', response.offset);
					btn.find('.um-wall-more-count').html(response.count);
				} else {
					btn.remove();
				}
			},
			error: function( data ) {
				console.log( data );
				loader.umHide();
			}
		});
	});

	/* load more replies */
	jQuery( document.body ).on('click', '.um-wall-replyload', function(e) {
		let btn = jQuery(this);
		let wrap = btn.parents('.um-wall-commentwrap');

		btn.umHide();

		let offset = btn.attr('data-loaded');
		let comment_id = btn.attr('data-comment_id');
		let nonce = btn.attr('data-wpnonce');
		let loader = wrap.find('> .um-wall-comment-child > .um-wall-comment-loadmore > .um-wall-loader');
		loader.umShow();

		let action = wp.hooks.applyFilters(
			'um_wall_load_more_replies_action',
			'um_wall_load_more_replies'
		);

		wp.ajax.send( action, {
			data: {
				comment_id: comment_id,
				offset: offset,
				nonce: nonce
			},
			success: function( response ) {
				loader.umHide();
				wrap.find('.um-wall-comment-child-loop').append(response.content);
				if ( response.loadmore ) {
					btn.umShow()
					btn.attr('data-loaded', response.offset);
					btn.find('.um-wall-more-count').html(response.count);
				} else {
					btn.remove();
				}
			},
			error: function( data ) {
				console.log( data );
				loader.umHide();
			}
		});
	});

	/* Trash comment popup */
	jQuery( document.body ).on('click', '.um-wall-delete-comment', function(e) {
		let btn = jQuery(this);
		let comment_id = btn.attr('data-comment_id');
		let nonce = btn.attr('data-wpnonce');
		let msg = btn.attr('data-msg');
		let title = btn.attr('data-title');
		let wrap = btn.parents('.um-wall-widget');
		let is_reply = btn.attr('data-is_reply');

		let action = wp.hooks.applyFilters(
			'um_wall_remove_comment_action',
			'um_wall_remove_comment'
		);

		if ( 1 === parseInt( is_reply ) ) {
			btn.parents('.um-wall-commentl.is-child').addClass('um-group-delete-loading');
			btn.parents('.um-wall-editc').find('button').attr('disabled', true);
		}

		jQuery.um_confirm(
			{
				title   : title,
				message : msg,
				onYes: function() {
					wp.ajax.send( action, {
						data: {
							comment_id: comment_id,
							nonce: nonce
						},
						success: function( response ) {
							if ( btn.parents('.um-wall-comment-child').length ) {
								btn.parents('.um-wall-commentl').remove();
							} else {
								btn.parents('.um-wall-commentwrap').remove();
							}
							if ( 1 !== parseInt( is_reply ) ) {
								let count = wrap.find('.um-wall-comments-toggle .um-badge');
								count.html( parseInt( count.text() ) - 1 );
								if ( parseInt( count.text() ) === 0 ) {
									wrap.find('.um-wall-comments-toggle').umHide();
								}
							}
						},
						error: function( data ) {
							console.log( data );
							btn.parents('.um-wall-commentl.is-child').removeClass('um-group-delete-loading');
							btn.parents('.um-wall-editc').find('button').attr('disabled', false);
							jQuery(this).um_notice({
								message: data,
								type: 'error'
							});
						}
					});
				},
				object: this
			}
		);
	});

	/* Image popup */
	jQuery( document.body ).on('click', '.um-wall-bodyinner-photo img', function(e) {
		e.preventDefault();
		let el = jQuery(this);
		let wrap = el.parents('.um-wall-bodyinner-photo');
		let el_id = el.parent().attr('id');

		let image = '<img src="' + el.attr('src') + '" alt="' + el.attr('alt') + '" />' +
			'<div class="um-wall-photo-view-modal-wrap">' +
			'<button type="button" class="um-button um-button-link-gray um-button-size-s has-background has-text-color um-button-has-icon um-button-icon-content um-wall-modal-prev" data-id="' + el_id + '" title=""><svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M15 6l-6 6l6 6"></path></svg></button>' +
			'<button type="button" class="um-button um-button-link-gray um-button-size-s has-background has-text-color um-button-has-icon um-button-icon-content um-wall-modal-next" data-id="' + el_id + '" title=""><svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 6l6 6l-6 6"></path></svg></button>' +
			'</div>';

		let count = wrap.find('.um-image-lazyload-wrapper').length;

		let settings = {
			classes:  'um-wall-photo-view-modal',
			duration: 400,
			header:   '',
			footer:   '',
			size:     'large',
			content:  image,
			template: '<div class="um-modal"><span class="um-modal-close um-modal-close-fixed">&times;</span><div class="um-modal-body"></div></div>'
		};

		UM.modal.addModal( settings, null );

		if ( 2 > parseInt( count ) ) {
			jQuery( '.um-wall-modal-prev, .um-wall-modal-next' ).remove();
		}
	});

	jQuery(document).on( 'click', '.um-wall-modal-prev, .um-wall-modal-next', function() {
		let el = jQuery(this);
		let current_id = el.attr('data-id');
		let current = jQuery('#' + current_id);
		let img_src, el_id;
		if ( el.hasClass('um-wall-modal-next') ) {
			if ( current.next().length ) {
				img_src = current.next().find('img').attr('src');
				el_id = current.next().attr('id');
			} else {
				let first = current.parent().find('.um-image-lazyload-wrapper').first();
				img_src = first.find('img').attr('src');
				el_id = first.attr('id');
			}
		} else {
			if ( current.prev().length ) {
				img_src = current.prev().find('img').attr('src');
				el_id = current.prev().attr('id');
			} else {
				let last = current.parent().find('.um-image-lazyload-wrapper').last();
				img_src = last.find('img').attr('src');
				el_id = last.attr('id');
			}
		}

		jQuery('.um-modal-body img').replaceWith( '<img src="' + img_src + '" alt="' + el.attr('alt') + '" />' );
		jQuery('.um-wall-photo-view-modal-wrap .um-wall-modal-prev').replaceWith( '<button type="button" class="um-button um-button-link-gray um-button-size-s has-background has-text-color um-button-has-icon um-button-icon-content um-wall-modal-prev" data-id="' + el_id + '" title=""><svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M15 6l-6 6l6 6"></path></svg></button>' );
		jQuery('.um-wall-photo-view-modal-wrap .um-wall-modal-next').replaceWith( '<button type="button" class="um-button um-button-link-gray um-button-size-s has-background has-text-color um-button-has-icon um-button-icon-content um-wall-modal-next" data-id="' + el_id + '" title=""><svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 6l6 6l-6 6"></path></svg></button>' );
		UM.modal.responsive();
	});

	/* Show hidden post content */
	jQuery( document.body ).on('click', '.um-wall-seemore', function(e) {
		let post_id = jQuery(this).attr('data-post_id');
		let container = jQuery(this).parents('.um-wall-bodyinner-txt');
		let nonce = jQuery(this).attr('data-nonce');
		container.addClass('loading');

		let action = wp.hooks.applyFilters(
			'um_wall_full_post_action',
			'um_wall_get_full_post'
		);

		wp.ajax.send(
			action,
			{
				data: {
					post_id: post_id,
					nonce: nonce
				},
				success: function( data ) {
					container.removeClass('loading');
					container.html( data.content );
				},
				error: function(e) {
					container.removeClass('loading');
					console.log( 'UM Wall Error', e );
				}
			}
		);
	});

	// Text editor with the contenteditable attribute
	let activeEditor = null;
	let rafPending = false;

	function scheduleSync() {
		if (rafPending) return;
		rafPending = true;
		requestAnimationFrame(() => {
			rafPending = false;
			syncToolbarFromSelection();
		});
	}

	function getSelectionNode() {
		const sel = window.getSelection && window.getSelection();
		if (!sel || sel.rangeCount === 0) return null;
		return sel.focusNode || sel.anchorNode || null;
	}

	function getEditorFromNode(node) {
		if (!node) return null;
		const el = (node.nodeType === 3) ? node.parentElement : node; // text node -> element
		if (!el || !el.closest) return null;
		return el.closest('.um-wall-textarea-elem');
	}

	function getToolbarForEditor(editor) {
		// find toolbar within the same editor wrapper, otherwise fallback to the first toolbar on the page
		const $wrap = jQuery(editor).closest('form');
		const $tb = $wrap.find('.um-wall-editor-toolbar').first();
		return $tb.length ? $tb : jQuery('.um-wall-editor-toolbar').first();
	}

	function setBtn($toolbar, key, isActive) {
		$toolbar
			.find(`button[data-cmd="${key}"], button[data-action="${key}"]`)
			.toggleClass('active', !!isActive);
	}

	function isInsideTag(node, editor, selector) {
		const el = (node && node.nodeType === 3) ? node.parentElement : node;
		if (!el) return false;
		const found = el.closest(selector);
		return !!(found && editor.contains(found));
	}

	function syncToolbarFromSelection() {
		const sel = window.getSelection && window.getSelection();
		if (!sel || sel.rangeCount === 0) return;

		const range = sel.getRangeAt(0);

		const node = sel.focusNode || sel.anchorNode || range.commonAncestorContainer;
		const editor = getEditorFromNode(node);
		if (!editor) return;

		activeEditor = editor;
		const $toolbar = getToolbarForEditor(editor);
		// console.log('Syncing toolbar for editor', editor, 'toolbar:', $toolbar);
		if (!sel.isCollapsed) {
			setBtn($toolbar, 'bold', !!document.queryCommandState('bold'));
			setBtn($toolbar, 'italic', !!document.queryCommandState('italic'));
			setBtn($toolbar, 'underline', !!document.queryCommandState('underline'));

			const common = range.commonAncestorContainer.nodeType === 3
				? range.commonAncestorContainer.parentElement
				: range.commonAncestorContainer;

			const inUl = common && common.closest && !!common.closest('ul');
			const inOl = common && common.closest && !!common.closest('ol');

			setBtn($toolbar, 'ul', inUl);
			setBtn($toolbar, 'ol', inOl);

			return;
		}

		const bold = isInsideTag(node, editor, 'b, strong, [style*="font-weight"]');
		const italic = isInsideTag(node, editor, 'i, em, [style*="font-style: italic"]');
		const underline = isInsideTag(node, editor, 'u, [style*="text-decoration"], [style*="underline"]');

		const ul = isInsideTag(node, editor, 'ul li, ul');
		const ol = isInsideTag(node, editor, 'ol li, ol');

		setBtn($toolbar, 'bold', bold);
		setBtn($toolbar, 'italic', italic);
		setBtn($toolbar, 'underline', underline);
		setBtn($toolbar, 'ul', ul);
		setBtn($toolbar, 'ol', ol);
	}

	// sync toolbar when focusing or typing in the editor
	jQuery(document).on('focus mousedown mouseup keyup input', '.um-wall-textarea-elem', function () {
		activeEditor = this;
		scheduleSync();
	});

	// prevent losing focus on toolbar click
	jQuery(document).on('mousedown', '.um-wall-editor-toolbar button', function (e) {
		e.preventDefault();
	});

	// handle toolbar actions
	jQuery(document).on('click', '.um-wall-editor-toolbar button', function (e) {
		e.preventDefault();
		if (!activeEditor) return;

		activeEditor.focus();

		const cmd = jQuery(this).data('cmd');
		const action = jQuery(this).data('action');

		switch (cmd || action) {
			case 'bold': document.execCommand('bold'); break;
			case 'italic': document.execCommand('italic'); break;
			case 'underline': document.execCommand('underline'); break;
			case 'ul': document.execCommand('insertUnorderedList'); break;
			case 'ol': document.execCommand('insertOrderedList'); break;
			case 'clear': document.execCommand('removeFormat'); break;
		}

		scheduleSync();
	});

	// sync toolbar when selection changes (e.g. with mouse)
	document.addEventListener('selectionchange', scheduleSync);
});

// AJAX wall request on scroll
jQuery( window ).on( 'scroll', function() {
	let wall = jQuery('.um-wall:not([data-single_post="1"])');
	if ( wall.length > 0 && jQuery(window).scrollTop() + jQuery(window).height() >= wall.offset().top + wall.height() ) {
		um_wall_ajax_request();
	}
});

function um_wall_ajax_request() {
	let wall = wp.hooks.applyFilters(
		'um_wall_selector',
		''
	);

	wall = jQuery(wall);
	if ( wall.length > 0 && Loadwall_ajax === false ) {
		let $activity_end = wall.find( '.um-wall-end' );
		if ( $activity_end.length ) {
			return;
		}

		Loadwall_ajax = true;

		let offset = wall.attr('data-offset');
		let nonce = wall.attr('data-nonce');

		let loader = wp.hooks.applyFilters(
			'um_wall_loader',
			'',
			{ wall: wall }
		);

		let action = wp.hooks.applyFilters(
			'um_wall_action',
			'um_wall_load_posts'
		);
		loader.umShow();

		let ajaxData =  {
			offset: offset,
			user_id:  wall.data('user_id'),
			user_wall: wall.data( 'user_wall' ),
			hashtag: wall.data('hashtag'),
			core_page: wall.data('core_page'),
			post_id: wall.data('post_id'),
			show_pending: wall.data('show_pending'),
			nonce: nonce
		};

		wp.ajax.send( action, {
			data: ajaxData,
			success: function( data ) {
				loader.umHide();
				let loadedPosts = jQuery(data).filter('.um-wall-widget');

				if ( loadedPosts.length === 0 ) {
					Loadwall_ajax = true;
				} else {
					// End of wall.
					if ( jQuery(data).filter('.um-wall-end').length ) {
						wall.append( data );
						loader.remove();
						Loadwall_ajax = false;
					} else {
						wall.attr('data-offset', parseInt( offset ) + loadedPosts.length);
						wall.append( data );
						if ( wall.attr( 'data-wall_post' ) > 0 ) {
							Loadwall_ajax = true;
						} else {
							Loadwall_ajax = false;
						}
					}
				}
				UM.frontend.dropdown.init();

				jQuery( document ).trigger('activity_loaded');

				wp.hooks.doAction( 'um_activity_wall_loaded', wall );
				UM.frontend.image.lazyload.init();
			},
			error: function (e) {
				wall.html(e.message);
				console.log('UM  Error', e);
			}
		});
	}
}


function um_clean_photo_fields( form ) {
	form.find('.um-wall-preview').hide();
	form.find('.um-wall-preview img').attr('src', '');
	form.find( 'input[type="hidden"][name="_post_img"]' ).val('');
	form.find( 'input[type="hidden"][name="_post_img_url"]' ).val('');
}

function um_post_placeholder( obj ) {
	obj.attr( 'placeholder', obj.attr( 'data-ph' ) );
}

jQuery(document).ready(function () {
	// Clear pasted text
	const ALLOWED_TAGS = new Set([ 'B', 'I', 'U', 'OL', 'UL', 'LI', 'BR' ]);

	/**
	 * Sanitize pasted HTML:
	 * - keeps only allowed tags
	 * - removes all attributes
	 * - unwraps disallowed elements but keeps their children/text
	 *
	 * @param {string} html
	 * @return {string}
	 */
	function sanitizePastedHtml(html) {
		const parser = new DOMParser();
		const doc = parser.parseFromString('<div>' + html + '</div>', 'text/html');
		const container = doc.body.firstChild;

		function sanitizeNode(node) {
			// Text node.
			if (node.nodeType === Node.TEXT_NODE) {
				return doc.createTextNode(node.nodeValue);
			}

			// Ignore comments and other node types.
			if (node.nodeType !== Node.ELEMENT_NODE) {
				return null;
			}

			const tagName = node.tagName.toUpperCase();
			const fragment = doc.createDocumentFragment();

			// If tag is not allowed, unwrap it but keep sanitized children.
			if (!ALLOWED_TAGS.has(tagName)) {
				Array.from(node.childNodes).forEach(function (child) {
					const cleanChild = sanitizeNode(child);
					if (cleanChild) {
						fragment.appendChild(cleanChild);
					}
				});

				return fragment;
			}

			// Create clean allowed element with no attributes.
			const cleanElement = doc.createElement(tagName.toLowerCase());

			// Special case: <br> has no children.
			if (tagName === 'BR') {
				return cleanElement;
			}

			Array.from(node.childNodes).forEach(function (child) {
				const cleanChild = sanitizeNode(child);
				if (cleanChild) {
					cleanElement.appendChild(cleanChild);
				}
			});

			return cleanElement;
		}

		const output = doc.createElement('div');

		Array.from(container.childNodes).forEach(function (child) {
			const cleanChild = sanitizeNode(child);
			if (cleanChild) {
				output.appendChild(cleanChild);
			}
		});

		return output.innerHTML;
	}

	/**
	 * Insert HTML at current caret position inside contenteditable.
	 *
	 * @param {string} html
	 */
	function insertHtmlAtCaret(html) {
		const sel = window.getSelection();

		if (!sel || !sel.rangeCount) {
			return;
		}

		const range = sel.getRangeAt(0);
		range.deleteContents();

		const temp = document.createElement('div');
		temp.innerHTML = html;

		const frag = document.createDocumentFragment();
		let node;
		let lastNode = null;

		while ((node = temp.firstChild)) {
			lastNode = frag.appendChild(node);
		}

		range.insertNode(frag);

		// Move caret to the end of inserted content.
		if (lastNode) {
			const newRange = document.createRange();
			newRange.setStartAfter(lastNode);
			newRange.collapse(true);

			sel.removeAllRanges();
			sel.addRange(newRange);
		}
	}
	jQuery(document).on('paste', 'div.um-wall-textarea-elem', function (e) {
		e.preventDefault();

		const clipboardData = e.originalEvent.clipboardData || window.clipboardData;
		const html = clipboardData.getData('text/html');
		const text = clipboardData.getData('text/plain');

		let cleanContent = '';

		if (html) {
			cleanContent = sanitizePastedHtml(html);
		} else if (text) {
			// Plain text: preserve line breaks.
			cleanContent = text
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/\r\n|\r|\n/g, '<br>');
		}

		insertHtmlAtCaret(cleanContent);
	});

	// mentions
	let activeEditor = null;
	let mentionTimer = null;
	let savedRange = null;

	// selectors for both editors
	const editorSelector = '.um-wall-textarea-elem, .um-wall-comment-textarea';

	//--------------------------------------------------------------------
	// UNIVERSAL HELPERS
	//--------------------------------------------------------------------
	function isTextarea(el) {
		return el.tagName === 'TEXTAREA';
	}

	function getEditorText(el) {
		return isTextarea(el) ? el.value : el.innerText;
	}

	// caret for textarea
	function getCaretOffsetTextarea(el) {
		return el.selectionStart;
	}

	// caret for contenteditable
	function getCaretOffsetDiv(el) {
		let sel = window.getSelection();
		if (!sel.rangeCount) return 0;

		let range = sel.getRangeAt(0);
		let preRange = range.cloneRange();
		preRange.selectNodeContents(el);
		preRange.setEnd(range.endContainer, range.endOffset);

		return preRange.toString().length;
	}

	function getCaretOffset(el) {
		return isTextarea(el)
			? getCaretOffsetTextarea(el)
			: getCaretOffsetDiv(el);
	}

	function getCurrentWord(editor) {
		let text = getEditorText(editor);
		let caret = getCaretOffset(editor);

		let before = text.slice(0, caret);
		return before.split(/\s/).pop();
	}

	//--------------------------------------------------------------------
	// REPLACE WORD — textarea
	//--------------------------------------------------------------------
	function replaceWordInTextarea(el, word, replacement) {
		const caret = el.selectionStart;
		const start = caret - word.length;
		const end   = caret;

		el.value =
			el.value.substring(0, start) +
			replacement +
			el.value.substring(end);

		const newPos = start + replacement.length;
		el.selectionStart = el.selectionEnd = newPos;
	}

	//--------------------------------------------------------------------
	// REPLACE WORD — contenteditable
	//--------------------------------------------------------------------
	function getTextNodeAtPosition(root, index) {
		let treeWalker = document.createTreeWalker(
			root,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: node => (
					node.nodeType === Node.TEXT_NODE
						? NodeFilter.FILTER_ACCEPT
						: NodeFilter.FILTER_REJECT
				)
			}
		);

		while (treeWalker.nextNode()) {
			let node = treeWalker.currentNode;
			if (index <= node.length) {
				return { node: node, offset: index };
			}
			index -= node.length;
		}

		return {
			node: root,
			offset: root.childNodes.length
		};
	}

	function replaceWordInDiv(el, word, replacement) {
		let caretOffset = getCaretOffsetDiv(el);
		let fullText   = el.innerText;
		let startOffset = caretOffset - word.length;

		let sel = window.getSelection();
		let range = document.createRange();

		let startInfo = getTextNodeAtPosition(el, startOffset);
		let endInfo   = getTextNodeAtPosition(el, caretOffset);

		range.setStart(startInfo.node, startInfo.offset);
		range.setEnd(endInfo.node, endInfo.offset);
		range.deleteContents();

		document.execCommand('insertText', false, replacement);
	}

	//--------------------------------------------------------------------
	// UNIVERSAL insertMention()
	//--------------------------------------------------------------------
	function insertMention(editor, mention) {
		let word = getCurrentWord(editor);

		// ensure space before if needed
		let text = getEditorText(editor);
		let caret = getCaretOffset(editor);
		let beforeChar = text.charAt(caret - 1);

		if (beforeChar && beforeChar !== ' ' && beforeChar !== "\n") {
			mention = ' ' + mention;
		}

		if (isTextarea(editor)) {
			replaceWordInTextarea(editor, word, mention);
		} else {
			replaceWordInDiv(editor, word, mention);
		}
	}

	//--------------------------------------------------------------------
	// detect active editor
	//--------------------------------------------------------------------
	jQuery(document).on('focus mousedown', editorSelector, function () {
		activeEditor = this;
	});

	//--------------------------------------------------------------------
	// detect "@" typing
	//--------------------------------------------------------------------
	jQuery(document).on('keyup', editorSelector, function (e) {
		activeEditor = this;

		// save range for contenteditable
		if (!isTextarea(this)) {
			let sel = window.getSelection();
			if (sel.rangeCount) {
				savedRange = sel.getRangeAt(0).cloneRange();
			}
		} else {
			savedRange = {
				textareaPos: this.selectionStart
			};
		}

		let word = getCurrentWord(this);
		let nonce = jQuery(this).attr('data-nonce');

		if (word && word.startsWith('@') && word.length > 1) {
			let term = word.substring(1);
			startMentionSearch(term, nonce, this);
		} else {
			hideMentionBox();
		}
	});

	//--------------------------------------------------------------------
	// AJAX search
	//--------------------------------------------------------------------
	function startMentionSearch(term, nonce, editor) {
		clearTimeout(mentionTimer);
		let action = wp.hooks.applyFilters(
			'um_wall_user_suggestions_action',
			'um_get_user_suggestions'
		);
		let data = { term: term, nonce: nonce };
		data = wp.hooks.applyFilters(
			'um_wall_user_suggestions_data',
			data,
			{ term, nonce, editor }
		);
		mentionTimer = setTimeout(function(){
			wp.ajax.send(action, {
				data: data,
				success: function(response){
					if (response) showMentionBox(response, editor);
					else hideMentionBox();
				},
				error: function(e){
					jQuery(this).um_notice({
						message: e,
						type: 'error'
					});
					hideMentionBox();
				}
			});
		}, 220);
	}

	//--------------------------------------------------------------------
	// Show suggestion box
	//--------------------------------------------------------------------
	function showMentionBox(users, editor){
		let box = jQuery('#um-mention-autocomplete');
		box.empty();

		if (!users.length) {
			hideMentionBox();
			return;
		}

		users.forEach(function(u){
			let item = jQuery('<div class="um-mention-item"></div>');
			item.html(
				u.photo +
				'<span class="name">' + u.name + '</span>' +
				'<span class="username">@' + u.username + '</span>'
			);
			item.data('user', u);
			box.append(item);
		});

		let pos = getCaretWordPosition(editor);

		box.css({
			left: pos.left,
			top:  pos.top
		}).show();
	}

	function hideMentionBox(){
		jQuery('#um-mention-autocomplete').hide();
	}

	//--------------------------------------------------------------------
	// caret box position
	//--------------------------------------------------------------------
	function getCaretWordPosition(editor){

		if (isTextarea(editor)) {
			let offset = jQuery(editor).offset();
			let height = jQuery(editor).outerHeight();
			return {
				left: offset.left + 20,
				top: offset.top + 25
			};
		}

		// contenteditable
		let sel = window.getSelection();
		let range = sel.getRangeAt(0).cloneRange();
		range.collapse(true);

		let rect = range.getClientRects()[0];

		if (!rect) {
			let offset = jQuery(editor).offset();
			return {
				left: offset.left,
				top: offset.top
			};
		}

		return {
			left: rect.left + window.scrollX,
			top: rect.bottom + window.scrollY + 3
		};
	}

	//--------------------------------------------------------------------
	// click on mention item
	//--------------------------------------------------------------------
	jQuery(document).on('click', '.um-mention-item', function(e){
		e.preventDefault();
		e.stopPropagation();

		let user = jQuery(this).data('user');

		activeEditor.focus();

		if (!isTextarea(activeEditor)) {
			if (savedRange) {
				let sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(savedRange);
			}
		} else {
			activeEditor.selectionStart = activeEditor.selectionEnd = savedRange.textareaPos;
		}

		insertMention(activeEditor, '@' + user.username + ' ');

		hideMentionBox();
	});

	//--------------------------------------------------------------------
	// Close box on outside click
	//--------------------------------------------------------------------
	jQuery(document).on('mousedown', function(e){
		if (!jQuery(e.target).closest('#um-mention-autocomplete').length &&
			!jQuery(e.target).closest('.um-mention-item').length)
		{
			hideMentionBox();
		}
	});
});

// Update files count badge and submit button state
function um_wall_calculate_files( $uploader, up, action ) {
	let count = $uploader.parents('.um-wall-post-form-wrapper').find('.um-wall-uploaded-count').text();
	let form = $uploader.parents( 'form' );
	if ( jQuery.isNumeric(count) && count > 0 ) {
		let filesCountBadge;
		// if action is add increase count by 1, if action is remove decrease by 1
		if ( action === 'add' ) {
			filesCountBadge = parseInt(count) + 1;
		} else {
			filesCountBadge = parseInt(count) - 1;
		}
		if ( filesCountBadge === 0 ) {
			let textarea = form.find( '.um-wall-textarea-elem' );
			let textarea_val;
			// check if textarea or contenteditable div and get value
			if ( textarea.is('textarea' ) ) {
				textarea_val = textarea.val();
			} else {
				textarea_val = textarea.text();
			}
			// if there is text in the textarea enable submit button, otherwise disable
			if ( textarea_val.trim().length > 0 ) {
				um_enable_post_submit( form );
			} else {
				um_disable_post_submit( form );
			}
			$uploader.parents('.um-wall-post-form-wrapper').find('.um-wall-uploaded-count').addClass('um-display-none').text('');
		} else {
			if ( filesCountBadge > 10 ) {
				filesCountBadge = '10+';
			}
			um_enable_post_submit( form );
			$uploader.parents('.um-wall-post-form-wrapper').find('.um-wall-uploaded-count').removeClass('um-display-none').text( filesCountBadge );
		}
	} else {
		$uploader.parents('.um-wall-post-form-wrapper').find('.um-wall-uploaded-count').removeClass('um-display-none').text( 1 );
		um_enable_post_submit( form );
	}
}
