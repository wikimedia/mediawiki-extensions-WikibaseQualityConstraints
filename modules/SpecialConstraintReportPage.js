$(document).ready(function(){
    $('.wbq-expandable-content-indicator').on('click', function(){
        $(this).closest('td').find('.wbq-expandable-content').slideToggle('fast');
    });
});