jQuery(function($){
  var mediaFrame;
  function updatePreview($row, attachment) {
    $row.find('.fallback-image-id').val(attachment.id);
    $row.find('.fallback-image-preview img').attr('src',attachment.url).show();
    $row.find('.remove-fallback-image').show();
  }
  $(document).on('click','.select-fallback-image',function(e){
    e.preventDefault();
    var $row=$(this).closest('tr');
    if(mediaFrame) mediaFrame.close();
    mediaFrame=wp.media({title:'Escolher imagem de fallback',library:{type:'image'},button:{text:'Usar essa imagem'},multiple:false});
    mediaFrame.on('select',function(){
      updatePreview($row,mediaFrame.state().get('selection').first().toJSON());
    });
    mediaFrame.open();
  });
  $(document).on('click','.remove-fallback-image',function(e){
    e.preventDefault();
    var $row=$(this).closest('tr');
    $row.find('.fallback-image-id').val('');
    $row.find('.fallback-image-preview img').hide().attr('src','');
    $(this).hide();
  });
  $('#add-rule').on('click',function(e){
    e.preventDefault();
    var idx=$('#rules tbody tr').length;
    var $tpl=$('#rules tbody tr').first().clone();
    $tpl.find('input,select').each(function(){
      var name=$(this).attr('name');
      if(name){
        name=name.replace(/ebvcf_rules\[\d+\]/,'ebvcf_rules['+idx+']');
        $(this).attr('name',name);
        if($(this).is('[type=checkbox]')) $(this).prop('checked',false);
        else $(this).val('');
      }
    });
    $tpl.find('.fallback-image-preview img').hide().attr('src','');
    $tpl.find('.remove-fallback-image').hide();
    $('#rules tbody').append($tpl);
  });
  $(document).on('click','.remove-rule',function(e){
    e.preventDefault();
    if(!confirm('Remover essa regra?')) return;
    var $row=$(this).closest('tr');
    $row.remove();
    $('#rules tbody tr').each(function(i){
      $(this).find('input,select').each(function(){
        var name=$(this).attr('name');
        if(name) $(this).attr('name',name.replace(/ebvcf_rules\[\d+\]/,'ebvcf_rules['+i+']'));
      });
    });
    var data=$('#ebvcf-form').serialize()+'&action=ebvcf_remove_rule';
    $.post(ajaxurl,data);
  });
});