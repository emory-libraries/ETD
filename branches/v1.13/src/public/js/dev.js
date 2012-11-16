/** javascript for ETD site used only in development/staging modes */

var $j = jQuery.noConflict();  
/* avoid conflict with prototype/scriptaculous while ETD has both*/
$j(document).ready(function() {

    $j('form#set_role > select').change(function(){
        var form = $j('form#set_role');
        $j.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: {role: $j(this).val()},
            complete: function(xhr, status){
                if (status == 'success') {
                    var cls = 'success';
                    var msg = 'success';
                } else {
                    var cls = 'error';
                    var msg = 'failure : ' + status;
                }
                $j('#setrole_status').addClass(cls).text(msg).show().fadeOut(5000);
            }
        });
    });

});