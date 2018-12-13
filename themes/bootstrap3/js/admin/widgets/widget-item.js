jQuery( document ).ready( function( $ ) {
    $.datepicker.regional['cs'] = {
        closeText: 'Cerrar',
        prevText: 'Předchozí',
        nextText: 'Další',
        currentText: 'Hoy',
        monthNames: [
            'Leden','Únor','Březen','Duben','Květen','Červen',
            'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'
        ],
        monthNamesShort: ['Le','Ún','Bř','Du','Kv','Čn', 'Čc','Sr','Zá','Ří','Li','Pr'],
        dayNames: ['Neděle','Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota'],
        dayNamesShort: ['Ne','Po','Út','St','Čt','Pá','So',],
        dayNamesMin: ['Ne','Po','Út','St','Čt','Pá','So'],
        weekHeader: 'Sm',
        dateFormat: 'dd.mm.yy',
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: ''
    };

    $.datepicker.setDefaults($.datepicker.regional['cs']);

    $( '#dateFrom, #dateTo' ).datepicker( {
        changeMonth: true,
        changeYear: true,
        dateFormat: 'yy-mm-dd'
    });

    CKEDITOR.replace('text_cs');
    CKEDITOR.replace('text_en');
});