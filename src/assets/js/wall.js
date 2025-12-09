function um_check_textarea_length( editor ) {
	let form = editor.parents('form');
	let text = editor.text().trim();
	if (text.length > 0) {
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
	autosize( jQuery('.um-wall-post-form-wrapper .um-wall-textarea-elem') );
	autosize( jQuery('.um-wall-widget .um-wall-comment-textarea') );
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
		console.log(jQuery(this))
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

		if ( formdata.hasOwnProperty('activity_post_photo[]') ) {
			if ( '' === formdata['_post_content'] && ! formdata['activity_post_photo[]'].length ) {
				form.find('textarea').trigger('focus');
				return;
			}
		} else {
			if ( '' === formdata['_post_content'] ) {
				form.find('textarea').trigger('focus');
				return;
			}
		}

		let $wall;
		let wallID = parseInt( formdata['_wall_id'] )
		formdata.wall_exists = 1;
		if ( 1 === parseInt( formdata['_only_form'] ) ) {
			// Add new post
			$wall = jQuery('.um-wall[data-user_wall="' + wallID + '"]');

			if ( ! $wall.length ) {
				/* for shortcode [ultimatemember_activity_form] */
				formdata.wall_exists = 0;
			}
		}

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
				if ( ! formdata.wall_exists ) {
					/* for shortcode [ultimatemember_activity_form] */
					$loader.umHide();
					$uploadToggle.prop('disabled',false);

					UM.common.form.messageTimeout( form.find('.um-wall-posting-result'), data, 5000 );

					form.find('.um-wall-textarea-elem').val('');
					form.find('.um-wall-toggle-uploader.um-toggle-button-active').trigger('click');
					uploaderObj.splice();
					return;
				}

				if ( form.find('input[name="_post_id"]').val() === '0' ) {
					let wall = form.parents('.um').find('.um-wall');

					/* for shortcode [ultimatemember_activity_form] */
					if ( wall.length < 1 ) {
						wall = jQuery( document.body ).find('.um-wall');
					}

					// if ( wall.length ) {
					// 	if ( jQuery('.um-wall-bigtext').length ) {
					// 		var content = data.content;
					// 		var hashtag = jQuery('.um-wall-bigtext').text();
					// 		if ( content.indexOf( '>' + hashtag + '<' ) >= 0 ) {
					// 			wall.prepend( widget_template( template_data ) );
					// 		}
					// 	} else {
					// 		wall.prepend( widget_template( template_data ) );
					// 	}
					//
					//
					// 	wall.find( '.unready' ).removeClass( 'unready um-activity-clone' ).fadeIn();
					// }

					wall.prepend(data);

					form.find('textarea').val('').height('auto');
					um_clean_photo_fields( form );
					um_post_placeholder( form.find( 'textarea' ) );

					// UM_wall_autocomplete_start();
				} else {
					let post_id = form.find('input[name="_post_id"]').val()
					jQuery('#postid-' + post_id ).replaceWith(data);
				}


				form.find('.um-uploader-filelist').html('').umHide();
				form.find('.um-wall-insert .um-badge').remove();

				autosize( jQuery('.um-wall-widget .um-wall-comment-textarea') );

				////// NEW
				form.find('.um-wall-right .um-ajax-spinner-svg').hide();
				form.find('.um-wall-toggle-uploader').prop('disabled',false);

				if ( jQuery('.um-wall-uploader-section.um-toggle-block .um-toggle-block-inner').hasClass('um-visible') && jQuery('.um-wall-uploader-section.um-toggle-block').hasClass('um-toggle-block-collapsed') ) {
					jQuery('.um-wall-uploader-section.um-toggle-block .um-toggle-block-inner').toggleClass('um-visible');
				}

				form.find('.um-wall-textarea-elem').html('');
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
				form.find('.um-wall-posting-result').umShow();
				UM.common.form.messageTimeout(
					form.find('.um-wall-posting-result'),
					error,
					3000,
					( wrapper ) => {
						wrapper.addClass( 'um-error-text' );
					}
				);
				console.log( data );
			}
		});

		// jQuery.ajax({
		// 	url: wp.ajax.settings.url,
		// 	type: 'post',
		// 	dataType: 'json',
		// 	data: formdata,
		// 	success: function( data ) {
		//
		// 		var widget_template;
		// 		var template_data;
		//
		// 		if ( form.find('input[name="_post_id"]').val() === '0' ) {
		// 			var wall = form.parents('.um').find('.um-wall-wall');
		//
		// 			/* for shortcode [ultimatemember_activity_form] */
		// 			if ( wall.length < 1 ) {
		// 				wall = jQuery( document.body ).find('.um-wall-wall');
		// 			}
		//
		// 			widget_template = wp.template( 'um-activity-widget' );
		// 			template_data = {
		// 				'content'       : data.content,
		// 				'img_src'       : ( 'undefined' !== typeof data.photo_orig_base ) ? data.photo_orig_base : '',
		// 				'img_src_url'   : ( 'undefined' !== typeof data.photo_orig_url ) ? data.photo_orig_url : '',
		// 				'modal'         : ( 'undefined' !== typeof data.photo ) ? data.photo : '',
		// 				/*'img_src'       : form.find('input[name="_post_img"]').val(),
		// 				'img_src_url'   : form.find('input[name="_post_img_url"]').val(),*/
		// 				'wall_id'       : form.find('input[name="_wall_id"]').val() || 0,
		// 				'user_id'       : data.user_id,
		// 				'post_id'       : data.postid,
		// 				'post_url'      : data.permalink,
		// 				'photo'         : ( form.find('input[name="_post_img"]').val().trim().length > 0 ),
		// 				'video'         : data.video || data.has_text_video,
		// 				'video_content' : data.video,
		// 				'oembed'        : data.has_oembed,
		// 				'link'          : data.link
		// 			};
		//
		// 			if ( jQuery('.um-wall-bigtext').length ) {
		// 				var content = data.content;
		// 				var hashtag = jQuery('.um-wall-bigtext').text();
		// 				if ( content.indexOf( '>' + hashtag + '<' ) >= 0 ) {
		// 					wall.prepend( widget_template( template_data ) );
		// 				}
		// 			} else {
		// 				wall.prepend( widget_template( template_data ) );
		// 			}
		//
		//
		// 			wall.find( '.unready' ).removeClass( 'unready um-activity-clone' ).fadeIn();
		//
		// 			form.find('textarea').val('').height('auto');
		// 			um_clean_photo_fields( form );
		// 			um_post_placeholder( form.find( 'textarea' ) );
		//
		// 			UM_wall_autocomplete_start();
		// 		} else {
		// 			form.parents('.um-wall-widget').removeClass( 'editing' );
		//
		// 			widget_template = wp.template( 'um-activity-post' );
		// 			template_data = {
		// 				'content'       : data.content,
		// 				'img_src'       : data.photo_orig_base,
		// 				'img_src_url'   : data.photo_orig_url,
		// 				'modal'         : data.photo,
		// 				/*'img_src'       : form.find('input[name="_post_img"]').val(),
		// 				'img_src_url'   : form.find('input[name="_post_img_url"]').val(),*/
		// 				'wall_id'       : form.find('input[name="_wall_id"]').val() || 0,
		// 				'user_id'       : data.user_id,
		// 				'post_id'       : data.postid,
		// 				'post_url'      : data.permalink,
		// 				'photo'         : ( form.find('input[name="_post_img"]').val().trim().length > 0 ),
		// 				'video'         : data.video || data.has_text_video,
		// 				'video_content' : data.video,
		// 				'oembed'        : data.has_oembed,
		// 				'link'          : data.link
		// 			};
		//
		// 			form.parents('.um-wall-body').html( widget_template( template_data ) );
		// 		}
		// 	}
		// });
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

		jQuery.um_confirm(
			{
				title   : title,
				message : msg,
				onYes: function() {
					wp.ajax.send( 'um_wall_remove_post', {
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
		let wall_id = jQuery('.um-wall-post-form-wrapper input[name="_wall_id"]').val() || 0;
		let loader = widget.find('.um-wall-post-loader');

		if ( jQuery(this).parents('.um-wall-dialog').length ) {
			jQuery(this).parents('.um-wall-dialog').hide();
		}

		if ( widget.hasClass( 'editing' ) ) {
			return;
		}

		loader.umShow();

		wp.ajax.send(
			'um_get_wall_post',
			{
				data: {
					post_id: post_id,
					wall_id: wall_id,
					nonce: nonce
				},
				success: function( data ) {
					console.log(data)
					loader.umHide();
					widget.find('.um-wall-body').umHide();
					widget.find('.um-wall-comments').umHide();
					widget.find('.um-wall-comment-form').umHide();
					widget.find('.um-wall-body').before(data);
					UM.frontend.uploader.init()
					widget.find('.um-post-actions-toggle').umHide();
				},
				error: function(data) {
					console.log(data);
					loader.umHide();
					let error = jQuery(this).attr('data-error');
					if ( data.message ) {
						error = data.message;
					}
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

	/* Like a post */
	jQuery( document.body ).on('click', '.um-wall-post-like:not(.active)', function(e) {
		let btn = jQuery(this);
		let wrap = jQuery(this).parents('.um-wall-like-wrap');
		let postid = jQuery(this).attr('data-id');
		let nonce = jQuery(this).attr('data-likenonce');

		if ( ! jQuery(this).parents('.um-wall-widget').hasClass('unready') ) {
			wp.ajax.send('um_wall_like_post', {
				data: {
					post_id: postid,
					nonce: nonce
				},
				success: function (answer) {
					btn.addClass('active');
					wrap.find('.um-badge').html(answer.likes);
					wrap.find('.um-wall-post-likes-avatars').html(answer.content);
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

		wp.ajax.send( 'um_wall_unlike_post', {
			data: {
				post_id: postid,
				nonce: nonce
			},
			success: function( answer ) {
				btn.removeClass('active');
				wrap.find('.um-badge').html(answer.likes);
				wrap.find('.um-wall-post-likes-avatars').html(answer.content);
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

		if ( parseInt( btn.find('.um-wall-post-likes-count .um-badge').html() ) > 0 ) {
			wp.ajax.send( 'um_wall_get_post_likes', {
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

			wp.ajax.send( 'um_wall_like_comment', {
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

		wp.ajax.send( 'um_wall_unlike_comment', {
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

		wp.ajax.send( 'um_wall_post_comment', {
			data: {
				post_id: postid,
				comment: comment,
				reply_to: reply_to,
				commentid: comment_id,
				nonce: nonce
			},
			success: function( response ) {
				console.log(response)
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
				}

				let count = wrap.parent().find('.um-wall-comments-toggle .um-badge');
				count.html( parseInt( count.html() ) + 1 );

				if ( 'undefined' === typeof( reply_to ) ) {
					btn.before( response.status );
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
				btn.before( data );
			}
		});
	});

	/* editing a comment */
	jQuery( document.body ).on( 'click', '.um-wall-edit-comment-save', function(e) {
		let btn = jQuery(this);
		let btm_wrap = btn.parents('.um-wall-comment-edit')
		let nonce = btn.attr('data-wpnonce');
		let postid = btn.attr('data-post_id');
		let commentid = btn.attr('data-commentid');

		let textarea = btm_wrap.find('textarea');
		let comment = textarea.val();

		let loader = btm_wrap.find('.um-wall-loader');
		loader.umShow();
		btm_wrap.find('.um-alert').remove();

		wp.ajax.send( 'um_wall_edit_comment', {
			data: {
				post_id: postid,
				comment_id: commentid,
				comment: comment,
				nonce: nonce
			},
			success: function( response ) {
				console.log(response)
				loader.umHide();
				btn.parents('.um-wall-comment-edit').umToggle();

				btn.parents('.um-wall-comment-data').find('.um-wall-comment-text').html(response.content).umToggle();

				btn.parents('.um-wall-commentl').find('.um-wall-editc .um-wall-edit-comment-cancel').umToggle();
				btn.parents('.um-wall-commentl').find('.um-wall-editc .um-wall-edit-comment').umToggle();
			},
			error: function( data ) {
				console.log( data );
				loader.umHide();
				btn.before( data );
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
			wrap.find('.um-wall-original-comment-info').append(reply_form);
		}
	});

	/* remove reply form */
	jQuery( document.body ).on( 'click', '.um-wall-reply-cancel', function(e) {
		let btn = jQuery(this);
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
		let post_id = btn.attr('data-post_id');
		let comment_id = btn.attr('data-comment_id');
		let nonce = btn.attr('data-wpnonce');
		let loader = wrap.find('> .um-wall-comment-child > .um-wall-comment-loadmore > .um-wall-loader');
		loader.umShow();

		wp.ajax.send( 'um_wall_load_more_replies', {
			data: {
				post_id: post_id,
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

		jQuery.um_confirm(
			{
				title   : title,
				message : msg,
				onYes: function() {
					wp.ajax.send( 'um_wall_remove_comment', {
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

							let count = wrap.parent().find('.um-wall-comments-toggle .um-badge');
							count.html( parseInt( count.html() ) - 1 );
						},
						error: function( data ) {
							console.log( data );
						}
					});
				},
				object: this
			}
		);
	});

	/* Trash comment popup */
	jQuery( document.body ).on('click', '.um-wall-bodyinner-photo img', function(e) {
		e.preventDefault();
		let image = '<div class="um-wall-photo-view-modal-wrap"><img src="' + jQuery(this).attr('src') + '" alt="" /></div>';
		let header = jQuery(this).attr('title');
		let settings = {
			classes:  'um-wall-photo-view-modal',
			duration: 400,
			header:   header,
			footer:   '',
			size:     'large',
			content:  image
		};

		UM.modal.addModal( settings, null );
	});

	/* Show hidden post content */
	jQuery( document.body ).on('click', '.um-wall-seemore', function(e) {
		let post_id = jQuery(this).attr('data-post_id');
		let container = jQuery(this).parents('.um-wall-bodyinner-txt');
		let nonce = jQuery(this).attr('data-nonce');
		container.addClass('loading');

		wp.ajax.send(
			'um_wall_get_full_post',
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

	jQuery(document).on('focus', '.um-wall-textarea-elem', function () {
		activeEditor = this;
	});
	jQuery(document).on('mousedown', '.um-wall-textarea-elem', function () {
		activeEditor = this;
	});
	jQuery(document).on('mousedown', '.um-wall-editor-toolbar button', function (e) {
		e.preventDefault();
	});

	jQuery(document).on('click', '.um-wall-editor-toolbar button', function (e) {
		e.preventDefault();

		jQuery(this).toggleClass('active');

		if (!activeEditor) return;

		activeEditor.focus();

		let cmd = jQuery(this).data('cmd');
		let action = jQuery(this).data('action');

		switch (cmd || action) {

			case 'bold':
				document.execCommand('bold');
				break;

			case 'italic':
				document.execCommand('italic');
				break;

			case 'underline':
				document.execCommand('underline');
				break;

			case 'ul':
				document.execCommand('insertUnorderedList');
				break;

			case 'ol':
				document.execCommand('insertOrderedList');
				break;

			case 'clear':
				document.execCommand('removeFormat');
				break;
		}
	});
});

// AJAX wall request on scroll
jQuery( window ).on( 'scroll', function() {
	let wall = jQuery('.um-wall:not([data-single_post="1"])');
	if ( wall.length > 0 && jQuery(window).scrollTop() + jQuery(window).height() >= wall.offset().top + wall.height() ) {
		um_wall_ajax_request();
	}
});

function um_wall_ajax_request() {
	let wall = jQuery('.um-wall');
	if ( wall.length > 0 && Loadwall_ajax === false ) {

		let $activity_end = wall.find( '.um-wall-end' );
		if ( $activity_end.length ) {
			return;
		}

		Loadwall_ajax = true;

		let offset = wall.attr('data-offset');
		let nonce = wall.attr('data-nonce');
		let loader = wall.parents('.um-activity').find('.um-wall-posts-loader');
		let action = wall.attr('data-action') + '_wall_load_posts';
		loader.umShow();

		wp.ajax.send( 'um_wall_load_posts', {
			data: {
				offset: offset,
				user_id:  wall.data('user_id'),
				user_wall: wall.data( 'user_wall' ),
				hashtag: wall.data('hashtag'),
				core_page: wall.data('core_page'),
				post_id: wall.data('post_id'),
				show_pending: wall.data('show_pending'),
				nonce: nonce
			},
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
			},
			error: function (e) {
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

// mentions
jQuery(document).ready(function () {
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
		mentionTimer = setTimeout(function(){
			wp.ajax.send('um_activity_get_user_suggestions', {
				data: { term: term, nonce: nonce },
				success: function(response){
					if (response) showMentionBox(response, editor);
					else hideMentionBox();
				},
				error: function(){
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
