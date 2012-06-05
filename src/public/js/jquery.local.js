var $j = jQuery.noConflict();  
/* avoid conflict with prototype/scriptaculous while ETD has both*/

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
