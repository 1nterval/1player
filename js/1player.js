jQuery(function($){

    // pour ajouter une version
    $('.add_version_button').click(function(e){
        var $parent = $(e.currentTarget).parent();
        
        var type = undefined;
        $parent.find('[name="type"]').each(function(i, elt){
            console.log({checked:elt.checked,type:type});
            if(elt.checked) {
                if(type == undefined) type = elt.value;
                else type = '';
            }
        });
        if(type == undefined) type = '';
        
        var qualite;
        $parent.find('[name="qualite"]').each(function(i, elt){
            if(elt.checked) qualite = elt.value;
        });
        
        var format = $parent.find('[name="format"]').val();
        
        var index = $('#versions div').length;
        
        $('#versions').append('<div>'
            + labels[qualite] + ' - ' + labels[format] + (type == '' ? '' : ' - '+labels[type])
            + '<input type="hidden" name="player[versions]['+index+'][0]" value="'+qualite+'">'
            + '<input type="hidden" name="player[versions]['+index+'][1]" value="'+format+'">'
            + (type == '' ? '' : '<input type="hidden" name="player[versions]['+index+'][2]" value="'+type+'">') 
            +'</div>');
        return false;
    });
    
    // pour supprimer une variante
    $('.suppr_version_button').live('click', function(e){
        $(e.currentTarget).parent().remove();
    });

});
