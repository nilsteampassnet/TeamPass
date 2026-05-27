/**
@license jQuery Toggles v4.0.0
Copyright 2012 - 2015 Simon Tabor - MIT License
https://github.com/simontabor/jquery-toggles / http://simontabor.com/labs/toggles
*/
(function(root) {

  var factory = function($) {

    <<Toggles>>

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
