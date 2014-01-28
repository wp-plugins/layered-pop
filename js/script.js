var lpl_active_window_id = false;
var lpl_subscribing = false;
var lpl_timeout;
function lpl_open() {
	clearTimeout(lpl_timeout);
	jQuery("#lpl").each(function() {
		lpl_active_window_id = true;
		jQuery("#lpl-overlay").fadeIn(300);
		if (jQuery(this).attr("data-close") == "on") {
			jQuery("#lpl-overlay").bind("click", function($) {
				lpl_close();
			});
		}
		var viewport = {
			width: Math.max(320, jQuery(window).width()),
			height: Math.max(320, jQuery(window).height())
		};
		var width = parseInt(jQuery(this).attr("data-width"), 10);
		var height = parseInt(jQuery(this).attr("data-height"), 10);
		
		var scale = Math.min((viewport.width-20)/width, viewport.height/height);
		if (scale > 1) scale = 1;
		width = parseInt(width*scale, 10);
		height = parseInt(height*scale, 10);
		jQuery(this).css({
			"width": width+"px",
			"height": height+"px",
			"margin-left": "-"+parseInt(width/2, 10)+"px",
			"margin-top": "-"+parseInt(height/2, 10)+"px"
		});
		var content = jQuery(this).find(".lpl-content");
		jQuery(content).css({
			"width": width+"px",
			"height": height+"px",
		});
		jQuery(content).find(".lpl-layer").each(function() {
			var layer = this;
			var layer_content_encoded = jQuery(layer).attr("data-base64");
			if (layer_content_encoded) {
				jQuery(layer).html(lpl_decode64(jQuery(layer).html()));
			}
			var layer_left = jQuery(layer).attr("data-left");
			var layer_top = jQuery(layer).attr("data-top");
			var layer_width = jQuery(layer).attr("data-width");
			var layer_height = jQuery(layer).attr("data-height");
			var layer_font_size = jQuery(layer).attr("data-font-size");
			var layer_appearance = jQuery(layer).attr("data-appearance");
			var layer_appearance_delay = parseInt(jQuery(layer).attr("data-appearance-delay"), 10);
			var layer_appearance_speed = parseInt(jQuery(layer).attr("data-appearance-speed"), 10);
			if (layer_width) jQuery(layer).css("width", parseInt(layer_width*scale, 10)+"px");
			if (layer_height) {
				jQuery(layer).css("height", parseInt(layer_height*scale, 10)+"px");
				var layer_scrollbar = jQuery(layer).attr("data-scrollbar");
				if (layer_scrollbar && layer_scrollbar == "on") {
					jQuery(layer).css("overflow", "hidden");
					jQuery(layer).scrollTop(0);
					jQuery(layer).perfectScrollbar({suppressScrollX: true});
				}
			}
			if (layer_font_size) jQuery(layer).css("font-size", Math.max(4, parseInt(layer_font_size*scale, 10))+"px");
			switch (layer_appearance) {
				case "slide-down":
					jQuery(layer).css({
						"left": parseInt(layer_left*scale, 10)+"px",
						"top": "-"+parseInt(2*viewport.height)+"px"
					});
					setTimeout(function() {
						jQuery(layer).animate({
							"top": parseInt(layer_top*scale, 10)+"px"
						}, layer_appearance_speed);
					}, layer_appearance_delay);
					break;
				case "slide-up":
					jQuery(layer).css({
						"left": parseInt(layer_left*scale, 10)+"px",
						"top": parseInt(2*viewport.height)+"px"
					});
					setTimeout(function() {
						jQuery(layer).animate({
							"top": parseInt(layer_top*scale, 10)+"px"
						}, layer_appearance_speed);
					}, layer_appearance_delay);
					break;
				case "slide-left":
					jQuery(layer).css({
						"left": parseInt(2*viewport.width)+"px",
						"top": parseInt(layer_top*scale, 10)+"px"
					});
					setTimeout(function() {
						jQuery(layer).animate({
							"left": parseInt(layer_left*scale, 10)+"px"
						}, layer_appearance_speed);
					}, layer_appearance_delay);
					break;
				case "slide-right":
					jQuery(layer).css({
						"left": "-"+parseInt(2*viewport.width)+"px",
						"top": parseInt(layer_top*scale, 10)+"px"
					});
					setTimeout(function() {
						jQuery(layer).animate({
							"left": parseInt(layer_left*scale, 10)+"px"
						}, layer_appearance_speed);
					}, layer_appearance_delay);
					break;
				case "fade-in":
					jQuery(layer).css({
						"left": parseInt(layer_left*scale, 10)+"px",
						"top": parseInt(layer_top*scale, 10)+"px",
						"display": "none"
					});
					setTimeout(function() {
						jQuery(layer).fadeIn(layer_appearance_speed);
					}, layer_appearance_delay);
					break;
				default:
					jQuery(layer).css({
						"left": parseInt(layer_left*scale, 10)+"px",
						"top": parseInt(layer_top*scale, 10)+"px"
					});
					break;
			}
		});
		jQuery(this).show();
	});
	return false;
}
function lpl_close() {
	jQuery("#lpl").each(function() {
		lpl_active_window_id = false;
		var layer_appearance_speed = 500;
		var content = jQuery(this).find(".lpl-content");
		var viewport = {
			width: Math.max(320, jQuery(window).width()),
			height: Math.max(320, jQuery(window).height())
		};
		jQuery("#lpl-overlay").unbind("click");
		jQuery(content).find(".lpl-layer").each(function() {
			var layer = this;
			var layer_appearance = jQuery(layer).attr("data-appearance");
			switch (layer_appearance) {
				case "slide-down":
					jQuery(layer).animate({
						"top": "-"+parseInt(2*viewport.height)+"px"
					}, layer_appearance_speed);
					break;
				case "slide-up":
					jQuery(layer).animate({
						"top": parseInt(2*viewport.height)+"px"
					}, layer_appearance_speed);
					break;
				case "slide-left":
					jQuery(layer).animate({
						"left": parseInt(2*viewport.width)+"px"
					}, layer_appearance_speed);
					break;
				case "slide-right":
					jQuery(layer).animate({
						"left": "-"+parseInt(2*viewport.width)+"px"
					}, layer_appearance_speed);
					break;
				case "fade-in":
					jQuery(layer).fadeOut(layer_appearance_speed);
					break;
				default:
					jQuery(layer).css({
						"display": "none"
					});
					break;
			}
			setTimeout(function() {
				var layer_content_encoded = jQuery(layer).attr("data-base64");
				if (layer_content_encoded) {
					jQuery(layer).html(lpl_encode64(jQuery(layer).html()));
				}
			}, layer_appearance_speed);		
		});
		setTimeout(function() {
			jQuery("#lpl").hide();
			jQuery("#lpl-overlay").fadeOut(300);
		}, layer_appearance_speed);		
	});
	return false;
}
function lpl_self_close() {
	lpl_close();
	return false;
}
function lpl_onload_open() {
	if (!lpl_active_window_id) {
		if (lpl_onload_mode == "once-session") lpl_write_cookie("lpl", lpl_cookie_value, 0);
		else if (lpl_onload_mode == "once-only") lpl_write_cookie("lpl", lpl_cookie_value, 180);
		lpl_open();
		if (lpl_onload_close_delay != 0) {
			lpl_timeout = setTimeout(function() {lpl_self_close();}, parseInt(lpl_onload_close_delay, 10)*1000);
		}
	}
}
function lpl_init() {
	if (jQuery("#lpl").length == 0) return;
	if (lpl_onload_mode == "none") return;
	lpl_cookie = lpl_read_cookie("lpl");
	if (lpl_cookie == lpl_cookie_value) return;
	if (parseInt(lpl_onload_delay, 10) <= 0) {
		lpl_onload_open();
	} else {
		setTimeout(function() {
			lpl_onload_open();
		}, parseInt(lpl_onload_delay, 10)*1000);
	}
}
function lpl_read_cookie(key) {
	var pairs = document.cookie.split("; ");
	for (var i = 0, pair; pair = pairs[i] && pairs[i].split("="); i++) {
		if (pair[0] === key) return pair[1] || "";
	}
	return null;
}
function lpl_write_cookie(key, value, days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	} else var expires = "";
	document.cookie = key+"="+value+expires+"; path=/";
}
jQuery(window).resize(function() {
	if (lpl_active_window_id) {
		var viewport = {
			width: Math.max(320, jQuery(window).width()),
			height: Math.max(320, jQuery(window).height())
		};
		var width = parseInt(jQuery("#lpl").attr("data-width"), 10);
		var height = parseInt(jQuery("#lpl").attr("data-height"), 10);
		var scale = Math.min((viewport.width-20)/width, viewport.height/height);
		if (scale > 1) scale = 1;
		width = parseInt(width*scale, 10);
		height = parseInt(height*scale, 10);
		jQuery("#lpl").css({
			"width": width+"px",
			"height": height+"px",
			"margin-left": "-"+parseInt(width/2, 10)+"px",
			"margin-top": "-"+parseInt(height/2, 10)+"px"
		});
		var content = jQuery("#lpl").find(".lpl-content");
		jQuery(content).css({
			"width": width+"px",
			"height": height+"px",
		});
		jQuery(content).find(".lpl-layer").each(function() {
			var layer = this;
			var layer_left = jQuery(layer).attr("data-left");
			var layer_top = jQuery(layer).attr("data-top");
			var layer_width = jQuery(layer).attr("data-width");
			var layer_height = jQuery(layer).attr("data-height");
			var layer_font_size = jQuery(layer).attr("data-font-size");
			if (layer_width) jQuery(layer).css("width", parseInt(layer_width*scale, 10)+"px");
			//if (layer_height) jQuery(layer).css("height", parseInt(layer_height*scale, 10)+"px");
			if (layer_height) {
				jQuery(layer).css("height", parseInt(layer_height*scale, 10)+"px");
				var layer_scrollbar = jQuery(layer).attr("data-scrollbar");
				if (layer_scrollbar && layer_scrollbar == "on") {
					jQuery(layer).css("overflow", "hidden");
					jQuery(layer).scrollTop(0);
					jQuery(layer).perfectScrollbar({suppressScrollX: true});
				}
			}
			
			if (layer_font_size) jQuery(layer).css("font-size", Math.max(4, parseInt(layer_font_size*scale, 10))+"px");
			jQuery(layer).css({
				"left": parseInt(layer_left*scale, 10)+"px",
				"top": parseInt(layer_top*scale, 10)+"px"
			});
		});
	}
});
jQuery(document).ready(function() {
	jQuery(".lpl-window").each(function() {
		var lpl_id = jQuery(this).attr("id");
		lpl_id = lpl_id.replace("lpl-", "");
		jQuery('[href="#'+lpl_id+'"]').click(function() {
			lpl_open(lpl_id);
			return false;
		});
	});
});
jQuery(document).keyup(function(e) {
	if (lpl_active_window_id) {
		if (jQuery("#lpl").attr("data-close") == "on") {
			if (e.keyCode == 27) lpl_self_close();
		}
	}
});
function lpl_encode64 (data) {
	var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
	ac = 0,
	enc = "",
	tmp_arr = [];
	if (!data) return data;
	do {
		o1 = data.charCodeAt(i++);
		o2 = data.charCodeAt(i++);
		o3 = data.charCodeAt(i++);

		bits = o1 << 16 | o2 << 8 | o3;

		h1 = bits >> 18 & 0x3f;
		h2 = bits >> 12 & 0x3f;
		h3 = bits >> 6 & 0x3f;
		h4 = bits & 0x3f;

		tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
	} while (i < data.length);
	enc = tmp_arr.join('');
	var r = data.length % 3;
	return (r ? enc.slice(0, r - 3) : enc) + '==='.slice(r || 3);
}
function lpl_decode64(input) {
	var output = "";
	var chr1, chr2, chr3 = "";
	var enc1, enc2, enc3, enc4 = "";
	var i = 0;
	var keyStr = "ABCDEFGHIJKLMNOP" +
		"QRSTUVWXYZabcdef" +
		"ghijklmnopqrstuv" +
		"wxyz0123456789+/" +
		"=";
	var base64test = /[^A-Za-z0-9\+\/\=]/g;
	if (base64test.exec(input)) return "";
	input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

	do {
		enc1 = keyStr.indexOf(input.charAt(i++));
		enc2 = keyStr.indexOf(input.charAt(i++));
		enc3 = keyStr.indexOf(input.charAt(i++));
		enc4 = keyStr.indexOf(input.charAt(i++));

		chr1 = (enc1 << 2) | (enc2 >> 4);
		chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
		chr3 = ((enc3 & 3) << 6) | enc4;

		output = output + String.fromCharCode(chr1);

		if (enc3 != 64) {
			output = output + String.fromCharCode(chr2);
		}
		if (enc4 != 64) {
			output = output + String.fromCharCode(chr3);
		}

		chr1 = chr2 = chr3 = "";
		enc1 = enc2 = enc3 = enc4 = "";

	} while (i < input.length);
	return unescape(output);
}