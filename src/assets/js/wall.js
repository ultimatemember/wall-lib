
function um_check_textarea_length( textarea ) {
	let form = textarea.parents( 'form' );
	if ( textarea.val().trim().length > 0 ) {
		um_enable_post_submit( form );
	} else {
		if ( form.find( '.um-uploader-file.um-upload-completed' ).length === 0 ) {
			um_disable_post_submit( form );
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

jQuery( document ).ready(function () {
	autosize( jQuery('.um-wall-post-form-wrapper .um-wall-textarea-elem') );
	autosize( jQuery('.um-wall-widget .um-wall-comment-textarea') );

	/* Detect change in textarea content */
	jQuery( document.body ).on( 'input onpropertychange', '.um-wall-textarea-elem', function() {
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
		if ( 0 === parseInt( formdata['_post_id'] ) ) {
			// Add new post
			$wall = jQuery('.um-wall-wall[data-user_wall="' + wallID + '"]');

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

				////// NEW END

				var widget_template;
				var template_data;

				if ( form.find('input[name="_post_id"]').val() === '0' ) {
					var wall = form.parents('.um').find('.um-wall-wall');

					/* for shortcode [ultimatemember_activity_form] */
					if ( wall.length < 1 ) {
						wall = jQuery( document.body ).find('.um-wall-wall');
					}

					if ( wall.length ) {
						widget_template = wp.template( 'um-activity-widget' );
						template_data = {
							'content'       : data.content,
							'img_src'       : ( 'undefined' !== typeof data.photo_orig_base ) ? data.photo_orig_base : '',
							'img_src_url'   : ( 'undefined' !== typeof data.photo_orig_url ) ? data.photo_orig_url : '',
							'modal'         : ( 'undefined' !== typeof data.photo ) ? data.photo : '',
							/*'img_src'       : form.find('input[name="_post_img"]').val(),
							'img_src_url'   : form.find('input[name="_post_img_url"]').val(),*/
							'wall_id'       : form.find('input[name="_wall_id"]').val() || 0,
							'user_id'       : data.user_id,
							'post_id'       : data.postid,
							'post_url'      : data.permalink,
							'photo'         : ( form.find('input[name="_post_img"]').val().trim().length > 0 ),
							'video'         : data.video || data.has_text_video,
							'video_content' : data.video,
							'oembed'        : data.has_oembed,
							'link'          : data.link
						};

						if ( jQuery('.um-wall-bigtext').length ) {
							var content = data.content;
							var hashtag = jQuery('.um-wall-bigtext').text();
							if ( content.indexOf( '>' + hashtag + '<' ) >= 0 ) {
								wall.prepend( widget_template( template_data ) );
							}
						} else {
							wall.prepend( widget_template( template_data ) );
						}


						wall.find( '.unready' ).removeClass( 'unready um-activity-clone' ).fadeIn();
					}

					form.find('textarea').val('').height('auto');
					um_clean_photo_fields( form );
					um_post_placeholder( form.find( 'textarea' ) );

					UM_wall_autocomplete_start();
				} else {
					form.parents('.um-wall-widget').removeClass( 'editing' );

					widget_template = wp.template( 'um-activity-post' );
					template_data = {
						'content'       : data.content,
						'img_src'       : data.photo_orig_base,
						'img_src_url'   : data.photo_orig_url,
						'modal'         : data.photo,
						/*'img_src'       : form.find('input[name="_post_img"]').val(),
						'img_src_url'   : form.find('input[name="_post_img_url"]').val(),*/
						'wall_id'       : form.find('input[name="_wall_id"]').val() || 0,
						'user_id'       : data.user_id,
						'post_id'       : data.postid,
						'post_url'      : data.permalink,
						'photo'         : ( form.find('input[name="_post_img"]').val().trim().length > 0 ),
						'video'         : data.video || data.has_text_video,
						'video_content' : data.video,
						'oembed'        : data.has_oembed,
						'link'          : data.link
					};

					form.parents('.um-wall-body').html( widget_template( template_data ) );
				}

				autosize( jQuery('.um-wall-widget .um-wall-comment-textarea') );

				////// NEW
				form.find('.um-wall-right .um-ajax-spinner-svg').hide();
				form.find('.um-wall-toggle-uploader').prop('disabled',false);
			},
			error: function(data) {
				////// NEW
				form.find('.um-wall-right .um-ajax-spinner-svg').hide();
				form.find('.um-wall-toggle-uploader').prop('disabled',false);
				// form.find('.um-wall-posting-result').html( data ).removeClass( 'um-display-none' ).addClass( 'um-error-text' );
				UM.common.form.messageTimeout(
					form.find('.um-wall-posting-result'),
					data,
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

	/* Trash post popup */
	jQuery( document.body ).on('click', '.um-wall-trash', function(e) {
		let btn = jQuery(this);
		var post_id = btn.attr('data-post_id');
		var nonce = btn.attr('data-wpnonce');
		var msg = btn.attr('data-msg');
		var title = btn.attr('data-title');

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
							// todo: remove post from wall or redirect
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

	/* Like a post */
	jQuery( document.body ).on('click', '.um-wall-post-like:not(.active)', function(e) {
		let btn = jQuery(this);
		let wrap = jQuery(this).parents('.um-wall-like-wrap');
		let postid = jQuery(this).attr('data-id');
		let nonce = jQuery(this).attr('data-likenonce');

		if ( ! jQuery(this).parents('.um-wall-widget').hasClass('unready') ) {
			wp.ajax.send('um_wall_like_post', {
				data: {
					postid: postid,
					nonce: nonce
				},
				success: function (answer) {
					btn.addClass('active');
					wrap.find('.um-badge').html(answer.likes);
					wrap.find('.um-wall-post-likes-avatars').html(answer.content);
				},
				error: function (data) {
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
				postid: postid,
				nonce: nonce
			},
			success: function( answer ) {
				btn.removeClass('active');
				wrap.find('.um-badge').html(answer.likes);
				wrap.find('.um-wall-post-likes-avatars').html(answer.content);
			},
			error: function( data ) {
				console.log( data );
			}
		});
	});

	/* Show post likes in modal */
	jQuery( document.body ).on('click', '.um-wall-show-likes', function(e) {
		let btn = jQuery(this);
		let post_id = btn.attr('data-id');
		let nonce = btn.attr('data-wpnonce');

		if ( parseInt( btn.find('.um-wall-post-likes-count .um-badge').html() ) > 0 ) {
			wp.ajax.send( 'um_activity_wall_get_post_likes', {
				data: {
					post_id: post_id,
					nonce: nonce
				},
				success: function( response ) {
					let settings = {
						classes:  'um-wall-likes-modal',
						duration: 400,
						footer:   '',
						header:   wp.i18n.__( 'Post likes', 'um-activity' ),
						size:     'normal',
						content:  response.content
					};

					UM.modal.addModal( settings, null );
				},
				error: function( data ) {
					console.log( data );
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

		if ( parseInt( btn.find('.um-wall-comment-likes-count .um-badge').html() ) > 0 ) {
			wp.ajax.send('um_wall_get_comment_likes', {
				data: {
					comment_id: comment_id,
					nonce: nonce
				},
				success: function (response) {
					let settings = {
						classes: 'um-activity-likes-modal',
						duration: 400,
						footer: '',
						header: wp.i18n.__('Post likes', 'um-activity'),
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
					commentid: commentid,
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
				commentid: commentid,
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
				postid: postid,
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
				postid: postid,
				commentid: commentid,
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
			let reply_to = wrap.find('.um-avatar').attr('data-user_id');

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
		var comment_id = btn.attr('data-comment_id');
		var nonce = btn.attr('data-wpnonce');
		var msg = btn.attr('data-msg');
		var title = btn.attr('data-title');

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
});