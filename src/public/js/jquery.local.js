var $j = jQuery.noConflict();  
/* avoid conflict with prototype/scriptaculous while ETD has both  */

(function($j){
    /**
    jQuery plugin to conditionally display or hide dom content based on radio button
    selected value.

    Example usage:
   
        <input type="radio" name="foo" value="no" class="required-field"> No
        <input type="radio" name="foo" value="yes" class="required-field"> Yes

        <div id="foo_no">Display only when NO is selected</div>
        <div id="foo_yes">Display only when YES is selected</div>

        $('input[name=foo]').conditionalDisplay({
            conditions: { yes: '#foo_yes', no: '#foo_no' }
        });  

        Recommended: leave conditional divs displayed by default and use javascript to 
        hide them by triggering the `change` event:

           $("input:radio").change();

    */

    var methods = {
     init : function( options ) {
        var settings = $j.extend({
            conditions: {},
            }, options);

       return this.each(function(){
         $j(this).data('conditionalDisplay', settings);
         $j(this).bind('change', methods.update_display);
       });

     },
    update_display : function() { 
       var conditions = $j(this).data('conditionalDisplay').conditions;
        // hide everything by default
        for (var cond in conditions) {
            $j(conditions[cond]).hide();
        }
        // show configured item matching current checked value, if any
        var val = $j(this).filter(':checked').first().val();
        if (conditions[val]) {
            $j(conditions[val]).show();
        }
     }
  };

   $j.fn.conditionalDisplay = function( method ) {
    
    if ( methods[method] ) {
      return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
      return methods.init.apply( this, arguments );
    } else {
      $.error( 'Method ' +  method + ' does not exist on jQuery.conditionalDisplay' );
    }    
}

})(jQuery);

$j(document).ready(function() {

    $j('a.add-repeating-input').click(function(){
        // copy last preceding input, clear value, and append
        var last_input = $j(this).prevAll('input').filter(':first');
        var new_input = last_input.clone();
        new_input.val('');
        new_input.insertAfter(last_input);
        $j('<a class="rm-input">x</a>').insertBefore(new_input);
        return false;
    });

    /* remove button for each repeating input */
    $j('<a class="rm-input">x</a>').insertBefore($j('input.repeating').not(':first'));
    $j('a.rm-input').click(function(){
        $j(this).next('input').remove();
        $j(this).remove();
    });
});                                       
