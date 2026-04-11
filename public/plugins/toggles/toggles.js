/**
@license jQuery Toggles v4.0.0
Copyright 2012 - 2015 Simon Tabor - MIT License
https://github.com/simontabor/jquery-toggles / http://simontabor.com/labs/toggles
*/
(function(root) {

  var factory = function($) {

    var Toggles = root['Toggles'] = function(el, opts) {
      var self = this;

      if (typeof opts === 'boolean' && el.data('toggles')) {
        el.data('toggles').toggle(opts);
        return;
      }

      var dataAttr = [
        'on',
        'drag',
        'click',
        'width',
        'height',
        'animate',
        'easing',
        'type',
        'checkbox'
      ];
      var dataOpts = {};
      for (var i = 0; i < dataAttr.length; i++) {
        var opt = el.data('toggle-' + dataAttr[i]);
        if (typeof opt !== 'undefined') dataOpts[dataAttr[i]] = opt;
      }

      // extend default opts with the users options
      opts = $.extend({
        // can the toggle be dragged
        'drag': true,
        // can it be clicked to toggle
        'click': true,
        'text': {
          // text for the ON/OFF position
          'on': 'ON',
          'off': 'OFF'
        },
        // is the toggle ON on init
        'on': false,
        // animation time (ms)
        'animate': 250,
        // animation transition easing function,
        'easing': 'swing',
        // the checkbox to toggle (for use in forms)
        'checkbox': null,
        // element that can be clicked on to toggle. removes binding from the toggle itself (use nesting)
        'clicker': null,
        // width (falls back to 50px)
        'width': 0,
        // height (falls back to 20px)
        'height': 0,
        // defaults to a compact toggle, other option is 'select' where both options are shown at once
        'type': 'compact',
        // the event name to fire when we toggle
        'event': 'toggle'
      }, opts || {}, dataOpts);

      el.data('toggles', self);

      // set active to the opposite of what we want, so toggle will run properly
      var active = !opts['on'];

      var selectType = opts['type'] === 'select';

      // make checkbox a jquery element
      var checkbox = $(opts['checkbox']);

      var clicker = opts['clicker'] && $(opts['clicker']);

      var height = opts['height'] || el.height() || 20;
      var width = opts['width'] || el.width() || 50;

      el.height(height);
      el.width(width);

      var div = function(name) {
        return $('<div class="toggle-' + name + '">');
      };

      // wrapper inside toggle
      var elSlide = div('slide');
      // inside slide, this bit moves
      var elInner = div('inner');
      // the on/off divs
      var elOn = div('on');
      var elOff = div('off');
      // the grip to drag the toggle
      var elBlob = div('blob');

      var halfHeight = height / 2;
      var onOffWidth = width - halfHeight;

      var text = opts['text'];

      // set up the CSS for the individual elements
      elOn
        .css({
          height: height,
          width: onOffWidth,
          textIndent: selectType ? '' : -height / 3,
          lineHeight: height + 'px'
        })
        .html(text['on']);

      elOff
        .css({
          height: height,
          width: onOffWidth,
          marginLeft: selectType ? '' : -halfHeight,
          textIndent: selectType ? '' : height / 3,
          lineHeight: height + 'px'
        })
        .html(text['off']);

      elBlob.css({
        height: height,
        width: height,
        marginLeft: -halfHeight
      });

      elInner.css({
        width: width * 2 - height,
        marginLeft: selectType ? 0 : -width + height
      });

      if (selectType) {
        elSlide.addClass('toggle-select');
        el.css('width', onOffWidth * 2);
        elBlob.hide();
      }

      // construct the toggle
      elInner.append(elOn, elBlob, elOff);
      elSlide.html(elInner);
      el.html(elSlide);

      var doToggle = self.toggle = function(state, noAnimate, noEvent) {
        // check we arent already in the desired state
        if (active === state) return;

        active = self['active'] = !active;

        el.data('toggle-active', active);

        elOff.toggleClass('active', !active);
        elOn.toggleClass('active', active);
        checkbox.prop('checked', active);

        if (!noEvent) el.trigger(opts['event'], active);

        if (selectType) return;

        var margin = active ? 0 : -width + height;

        // move the toggle!
        elInner.stop().animate({
          'marginLeft': margin
        }, noAnimate ? 0 : opts['animate'], opts['easing']);
      };


      // evt handler for click events
      var clickHandler = function(e) {
        // if the target isn't the blob or dragging is disabled, toggle!
        if (!el.hasClass('disabled') && (e['target'] !== elBlob[0] || !opts['drag'])) {
          doToggle();
        }
      };

      // if click is enabled and toggle isn't within the clicker element (stops double binding)
      if (opts['click'] && (!clicker || !clicker.has(el).length)) {
        el.on('click', clickHandler);
      }

      // setup the clicker element
      if (clicker) {
        clicker.on('click', clickHandler);
      }

      // bind up dragging stuff
      if (opts['drag'] && !selectType) {
        // time to begin the dragging parts/blob clicks
        var diff;
        var slideLimit = (width - height) / 4;

        // fired on mouseup and mouseleave events
        var upLeave = function(e) {
          el.off('mousemove');
          elSlide.off('mouseleave');
          elBlob.off('mouseup');

          if (!diff && opts['click'] && e.type !== 'mouseleave') {
            doToggle();
            return;
          }

          var overBound = active ? diff < -slideLimit : diff > slideLimit;
          if (overBound) {
            // dragged far enough, toggle
            doToggle();
          } else {
            // reset to previous state
            elInner.stop().animate({
              marginLeft: active ? 0 : -width + height
            }, opts['animate'] / 2, opts['easing']);
          }
        };

        var wh = -width + height;

        elBlob.on('mousedown', function(e) {

          if (el.hasClass('disabled')) return;

          // reset diff
          diff = 0;

          elBlob.off('mouseup');
          elSlide.off('mouseleave');
          var cursor = e.pageX;

          el.on('mousemove', elBlob, function(e) {
            diff = e.pageX - cursor;
            var marginLeft;

            if (active) {
              marginLeft = diff;

              // keep it within the limits
              if (diff > 0) marginLeft = 0;
              if (diff < wh) marginLeft = wh;
            } else {
              marginLeft = diff + wh;

              if (diff < 0) marginLeft = wh;
              if (diff > -wh) marginLeft = 0;
            }

            elInner.css('margin-left', marginLeft);
          });

          elBlob.on('mouseup', upLeave);
          elSlide.on('mouseleave', upLeave);
        });
      }

      // toggle the toggle to the correct state with no animation and no event
      doToggle(opts['on'], true, true);
    };

    $.fn['toggles'] = function(opts) {
      return this.each(function() {
        new Toggles($(this), opts);
      });
    };
  };

  if (typeof define === 'function' && define['amd']) {
    define(['jquery'], factory);
  } else {
    factory(root['jQuery'] || root['Zepto'] || root['ender'] || root['$'] || $);
  }

})(this);
