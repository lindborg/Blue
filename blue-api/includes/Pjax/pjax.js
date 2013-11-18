(function($) {
	/* PJAX FUNCTIONS */
	if (typeof history.pushState != 'function') return;
	
	var aWhiteList = 'a';
	var aNotList = '';
	
	
	/* TRANSITION END EVENT */
	var transitionEnd;
    var t;
    var el = document.createElement('fakeelement');
    var transitions = {
      'transition':'transitionend',
      'OTransition':'oTransitionEnd',
      'MozTransition':'transitionend',
      'WebkitTransition':'webkitTransitionEnd'
    }

    for(t in transitions){
        if( el.style[t] !== undefined ){
            transitionEnd = transitions[t];
        }
    }
    
    console.log(transitionEnd);
	
	function pjaxJson(url) {
	  return $.ajax({
		type: "GET",
		url: url + 'json',
		dataType: "json"
		});
	}
	
	function pjaxTemplate(url) {
	  return $.ajax({
		type: "GET",
		url: url + 'html',
		dataType: "text"
		});
	}
	
	var currentStateId = 1;
	var stateCounter = 1;
	
	var currentUrl = document.location.href.replace('http://', '').replace(document.location.hostname, '');
	
	var pjax = function(nextUrl, push, stateId) {
		if (nextUrl != currentUrl) {
			if (!stateId || stateId == -1) {
				stateId = ++stateCounter;
			}
			
			//remove old elements
			$('.out:not(.static)').remove();
			
			//set active state on menu
			$(aWhiteList).removeClass('active').filter('a[href="'+document.location.protocol+'//' + document.location.hostname + nextUrl + '"],a[href="'+nextUrl+ '"]').addClass('active');
			
			//load json + mustache template
			$.when(pjaxJson(nextUrl), pjaxTemplate(nextUrl)).done(function(j, t) {
				var json = j[0],
					template = t[0];
				
				//push onto history object
				if (push) {
					if (typeof history.pushState == 'function') {
						history.pushState({stateId: stateId}, null, nextUrl);
					}
				}
				
				var historyClass = (stateId > currentStateId) ? 'p-forward' : 'p-back';
				
				//compile + render template with hogan.js
				var compiledTemplate = Hogan.compile(template);
				var output = $('<div><div id="pjaxtmp">' + compiledTemplate.render(json) + '</div></div>');
				
				//simulate wordpress body_class
				$('body').attr('class', $('<div ' + json.meta.body_class+'></div>').attr('class'));
				
				//animate none static old elements
				$('body>div:not(.static,script),.animate').removeClass('in').addClass('out');
				
				//if static element are present - keep them!
				$('.static').each(function() {
					output.find('#' + $(this).attr('id')).remove();
				});
				
				//animate new elements
				output.find('#pjaxtmp>*:not(.static,script),.animate').addClass('in no-transition '+historyClass);
				
				
				$('body').append(output.find('#pjaxtmp').html());
				$('title').html(json.meta.title);
				
				currentStateId = stateId;
				currentUrl = nextUrl;
				
				$(window).trigger('pready');
				
				animateContent();
			})
			.fail(function() {
				console.log( 'Pjax load failed' );
			});
		}
	}
	
	var animateContent = function() {
		setTimeout(function() {
			$('.in').removeClass('in no-transition').bind(transitionEnd, function() {
				//todo..
				console.log('transition done...');
			});
		}, 50);
	}
	
	
	/* PJAX EVENTS */
	$(function() {
		$(document).on('click', aWhiteList, function(e) {
			if ($(this).attr('href').indexOf('history.') != -1 || $(this).parents('no-pjax').size() > 0) {
				return;
			}
			
			if ($(this).attr('target') != '_blank' && $(this).attr('href').indexOf('wp-content') == -1 && ($(this).attr('href').indexOf('://') == -1 || $(this).attr('href').substring(0, $(this).attr('href').indexOf('/', 7)).indexOf(document.location.hostname) != -1)) {
				e.preventDefault();
				pjax($(this).attr('href').replace('http://', '').replace(document.location.hostname, ''), true);
			}
		});
		
		window.onpopstate = function(event) {
			var stateId = -1;
			if (event.state) {
				stateId = event.state.stateId;
			}
			
			pjax(window.location.href.replace('http://', '').replace(document.location.hostname, ''), false, stateId); 
		}
	});
})(jQuery);