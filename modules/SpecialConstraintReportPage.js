$(document).ready(function(){
    $('.wbqc-expandable-content-indicator').on('click', function(){
        $(this).closest('td').find('.wbqc-expandable-content').slideToggle('fast');
    });
});